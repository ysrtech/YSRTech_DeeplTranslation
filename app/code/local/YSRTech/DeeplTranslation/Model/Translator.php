<?php
/**
 * YSRTech_DeeplTranslation_Model_Translator
 *
 * Core translation service.  Shared by the cron runner and the shell script.
 * Extend Varien_Object so callers can use magic setters before calling run().
 *
 * Required setters before calling run():
 *   setStoreSource(string)   – source store view code
 *   setStoreDest(string)     – destination store view code
 *
 * Optional setters:
 *   setDebugMode(bool)       – echo attribute-level diff (default false)
 *   setDryRun(bool)          – translate but do not persist (default false, implies debug)
 *   setAllCategories(bool)   – translate all categories, not only auto_translate=1 (default false)
 *   setVerbose(bool)         – echo progress lines; always logs to ysrtech_deepltranslation.log
 *
 * All DeepL API calls are batched: strings for all items are collected first and
 * sent in chunks of DEEPL_BATCH_SIZE in as few HTTP requests as possible.
 * Products are saved via catalog/product_action::updateAttributes (no Magmi required).
 */
class YSRTech_DeeplTranslation_Model_Translator extends Varien_Object
{
    const LOG_FILE         = 'ysrtech_deepltranslation.log';

    /**
     * Maximum number of strings sent to DeepL in a single API request.
     * DeepL supports up to 50 texts per call.
     */
    const DEEPL_BATCH_SIZE = 50;

    /**
     * Number of products processed per HTTP request to avoid server timeouts.
     */
    const PRODUCT_BATCH_SIZE = 20;

    /** @var YSRTech_DeeplTranslation_Helper_Data */
    protected $_helper;

    /** @var string DeepL API key */
    protected $_apiKey;

    /** @var string 2-letter ISO source language */
    protected $_langSource;

    /** @var string 2-letter ISO dest language (or en-GB) */
    protected $_langDest;

    /** @var int */
    protected $_storeIdSource;

    /** @var int */
    protected $_storeIdDest;

    /**
     * Offset for the next product batch, or null if all products have been processed.
     * @var int|null
     */
    protected $_nextOffset = null;

    // ------------------------------------------------------------------
    // Public entry point
    // ------------------------------------------------------------------

    /**
     * Run the full translation: categories first, then flagged products.
     *
     * @return $this
     * @throws Exception
     */
    public function run()
    {
        if ($this->getDryRun()) {
            $this->setDebugMode(true);
        }

        $this->_helper  = Mage::helper('ysrtech_deepltranslation');

        if (!$this->_helper->isEnabled()) {
            $this->_output("DeepL Translation is disabled in configuration. Skipping.\n");
            return $this;
        }

        $this->_apiKey  = $this->_helper->getApiKey();

        $appEmulation = Mage::getSingleton('core/app_emulation');

        // Resolve dest store
        try {
            $envDest = $appEmulation->startEnvironmentEmulation($this->getStoreDest());
        } catch (Mage_Core_Model_Store_Exception $e) {
            Mage::throwException("DeepL: target store view \"{$this->getStoreDest()}\" does not exist.");
        }
        $this->_storeIdDest = Mage::app()->getStore()->getId();
        $this->_langDest    = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        $appEmulation->stopEnvironmentEmulation($envDest);

        // Collect product IDs only if products will be translated
        $productIds = array();
        if (!$this->getOnlyCategories()) {
            $specificIds = $this->getSpecificProductIds();
            if ($specificIds !== null) {
                // Single-product path: use provided IDs directly, skip batching
                $productIds = (array)$specificIds;
            } else {
                if ($this->getAllProducts()) {
                    $productIds = $this->_getAllProductIds();
                } else {
                    $productIds = $this->_getFlaggedProductIds($this->_storeIdDest);
                }

                // Apply batch slicing
                $offset = (int)$this->getBatchOffset();
                if ($offset > 0) {
                    $productIds = array_slice($productIds, $offset);
                }
                if (count($productIds) > self::PRODUCT_BATCH_SIZE) {
                    $this->_nextOffset = $offset + self::PRODUCT_BATCH_SIZE;
                    $productIds = array_slice($productIds, 0, self::PRODUCT_BATCH_SIZE);
                }
            }
        }

        // Resolve source store
        try {
            $envSource = $appEmulation->startEnvironmentEmulation($this->getStoreSource());
        } catch (Mage_Core_Model_Store_Exception $e) {
            Mage::throwException("DeepL: source store view \"{$this->getStoreSource()}\" does not exist.");
        }
        $this->_storeIdSource = Mage::app()->getStore()->getId();
        $this->_langSource    = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);

        // Translate categories and/or products depending on flags
        if (!$this->getOnlyProducts()) {
            $this->_translateCategories();
        }
        if (!$this->getOnlyCategories()) {
            $this->_translateProducts($productIds);
        }

        $appEmulation->stopEnvironmentEmulation($envSource);

        $this->_output("DeepL translation run completed.\n");

        return $this;
    }

    // ------------------------------------------------------------------
    // Products
    // ------------------------------------------------------------------

    protected function _getFlaggedProductIds($storeIdDest)
    {
        $attributeId = Mage::getModel('catalog/entity_attribute')
            ->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'auto_translate')
            ->getId();

        if (!$attributeId) {
            $this->_output("WARNING: product attribute 'auto_translate' not found – no products will be translated.\n");
            return array();
        }

        $table = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_int');
        return Mage::getSingleton('core/resource')
            ->getConnection('core_read')
            ->fetchCol(
                "SELECT entity_id FROM {$table} "
                . "WHERE attribute_id={$attributeId} AND store_id={$storeIdDest} AND value=1"
            );
    }

    protected function _getAllProductIds()
    {
        $table = Mage::getSingleton('core/resource')->getTableName('catalog_product_entity');
        return Mage::getSingleton('core/resource')
            ->getConnection('core_read')
            ->fetchCol("SELECT entity_id FROM {$table}");
    }

    protected function _translateProducts(array $productIds)
    {
        if (empty($productIds)) {
            $this->_output("No products flagged for translation.\n");
            return;
        }

        $productHelper   = Mage::helper('catalog/output');
        $productUrlModel = Mage::getSingleton('catalog/factory')->getProductUrlInstance();
        $coreHelper      = Mage::helper('core');
        $attributes      = $this->_helper->getAttributesToTranslate();
        $productAction   = Mage::getSingleton('catalog/product_action');

        // Load all flagged products in one collection query
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->setStoreId($this->_storeIdSource)
            ->addAttributeToSelect($attributes)
            ->addFieldToFilter('entity_id', array('in' => $productIds));

        // Build a flat job list and parallel source-text array for batched DeepL call
        // $jobs[i]  = array(productId, attribute)
        // $texts[i] = source string to translate
        $jobs       = array();
        $texts      = array();
        $productData = array(); // productId => ['sku' => ..., 'data' => [...]]

        foreach ($collection as $product) {
            $row = $product->getData();
            $productData[$product->getId()] = array(
                'sku'    => isset($row['sku']) ? $row['sku'] : (string)$product->getId(),
                'data'   => $row,
                'object' => $product,
            );

            foreach ($attributes as $attribute) {
                if (!isset($row[$attribute]) || !strlen($row[$attribute])) {
                    continue;
                }

                if ($attribute === 'description') {
                    $source = $productHelper->productAttribute(
                        $product, $product->getDescription(), 'description'
                    );
                } elseif ($attribute === 'short_description') {
                    $source = $productHelper->productAttribute(
                        $product, $product->getShortDescription(), 'short_description'
                    );
                } else {
                    $source = $row[$attribute];
                }

                $jobs[]  = array($product->getId(), $attribute);
                $texts[] = $source;
            }
        }

        if (empty($texts)) {
            $this->_output("No translatable content found in flagged products.\n");
            return;
        }

        $this->_output(
            sprintf(
                "Sending %d string(s) for %d product(s) to DeepL (%s → %s)...\n",
                count($texts), count($productData),
                $this->_langSource, $this->_langDest
            )
        );

        // One (or a few chunked) DeepL API calls for all product strings
        $translated = $this->_translateBatch($texts);

        // Map results back: productId => [attribute => translatedValue]
        $translatedByProduct = array();
        foreach ($jobs as $i => $job) {
            list($productId, $attribute) = $job;
            $translatedByProduct[$productId][$attribute] = $translated[$i];
        }

        // Save per product using native Magento product_action
        foreach ($translatedByProduct as $productId => $attrData) {
            $info = $productData[$productId];
            $row  = $info['data'];
            $sku  = $info['sku'];

            $this->_output("Saving product {$sku}...");

            if ($this->getDebugMode()) {
                $this->_output("\n");
                foreach ($attrData as $attr => $val) {
                    $orig = isset($row[$attr]) ? $row[$attr] : '';
                    $this->_output("\t[{$attr}] [{$orig}] -> [{$val}]\n");
                }
            }

            // Derive url_key from translated name
            if (isset($attrData['name'])) {
                $nameForUrl = ($this->_langDest === 'de')
                    ? $coreHelper->removeAccents($attrData['name'], true)
                    : $attrData['name'];
                $attrData['url_key'] = $productUrlModel->formatUrlKey($nameForUrl);
                // save_rewrites_history is not a real EAV attribute; omit it
            }

            // Clear the auto_translate flag on the dest store view
            $attrData['auto_translate'] = 0;

            if (!$this->getDryRun()) {
                $productAction->updateAttributes(
                    array($productId),
                    $attrData,
                    $this->_storeIdDest
                );
            }

            $this->_output(" OK\n");
        }
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    protected function _translateCategories()
    {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $helper        = Mage::helper('catalog/output');
        $categoryModel = Mage::getModel('catalog/category');
        $coreHelper    = Mage::helper('core');
        $attributes    = $this->_helper->getCategoryAttributesToTranslate();

        $specificIds = $this->getSpecificCategoryIds();
        if ($specificIds !== null) {
            $categoryIds = (array)$specificIds;
        } elseif ($this->getAllCategories()) {
            $tree = $categoryModel->getTreeModel();
            $tree->load();
            $categoryIds = $tree->getCollection()->getAllIds();
        } else {
            $categoryIds = $this->_getFlaggedCategoryIds($this->_storeIdDest);
        }

        if (empty($categoryIds)) {
            $this->_output("No categories flagged for translation.\n");
            return;
        }

        // Load all category source data and build flat job/text arrays
        // $jobs[i]  = array(categoryId, attribute)
        // $texts[i] = source string
        $jobs         = array();
        $texts        = array();
        $categoryData = array(); // categoryId => ['object' => ..., 'data' => [...]]

        foreach ($categoryIds as $categoryId) {
            $category = $categoryModel->setStoreId($this->_storeIdSource)->load($categoryId);
            $row      = $category->getData();

            $categoryData[$categoryId] = array(
                'object' => $category,
                'data'   => $row,
            );

            foreach ($attributes as $attribute) {
                if (!isset($row[$attribute]) || !strlen($row[$attribute])) {
                    continue;
                }

                if ($attribute === 'description') {
                    $source = $helper->categoryAttribute($category, $row[$attribute], 'description');
                } elseif ($attribute === 'short_desc') {
                    $source = $helper->categoryAttribute($category, $row[$attribute], 'short_desc');
                } else {
                    $source = $row[$attribute];
                }

                $jobs[]  = array($categoryId, $attribute);
                $texts[] = $source;
            }
        }

        if (empty($texts)) {
            $this->_output("No translatable content found in categories.\n");
            return;
        }

        $this->_output(
            sprintf(
                "Sending %d string(s) for %d categor%s to DeepL (%s → %s)...\n",
                count($texts), count($categoryData),
                count($categoryData) === 1 ? 'y' : 'ies',
                $this->_langSource, $this->_langDest
            )
        );

        // One (or a few chunked) DeepL API calls for all category strings
        $translated = $this->_translateBatch($texts);

        // Map results back: categoryId => [attribute => translatedValue]
        $translatedByCategory = array();
        foreach ($jobs as $i => $job) {
            list($categoryId, $attribute) = $job;
            $translatedByCategory[$categoryId][$attribute] = $translated[$i];
        }

        // Save per category
        foreach ($translatedByCategory as $categoryId => $translatedRow) {
            $info     = $categoryData[$categoryId];
            $row      = $info['data'];
            $category = $info['object'];
            $name     = isset($row['name']) ? $row['name'] : $categoryId;

            $this->_output("Saving category {$categoryId} \"{$name}\"...");

            if ($this->getDebugMode()) {
                $this->_output("\n");
                foreach ($translatedRow as $attr => $val) {
                    $orig = isset($row[$attr]) ? $row[$attr] : '';
                    $this->_output("\t[{$attr}] [{$orig}] -> [{$val}]\n");
                }
            }

            // Derive url_key from translated name
            if (isset($translatedRow['name'])) {
                $nameForUrl = ($this->_langDest === 'de')
                    ? $coreHelper->removeAccents($translatedRow['name'], true)
                    : $translatedRow['name'];
                $translatedRow['url_key'] = $category->formatUrlKey($nameForUrl);
                // save_rewrites_history is not a real EAV attribute; omit it
            }

            if (!$this->getDryRun()) {
                $categoryDest    = $categoryModel->setStoreId($this->_storeIdDest)->load($categoryId);
                $categoryResource = Mage::getResourceModel('catalog/category');

                // Attributes that are not real EAV columns and must be skipped
                $skipAttrs = array('save_rewrites_history');

                foreach ($translatedRow as $key => $value) {
                    if (in_array($key, $skipAttrs)) {
                        continue;
                    }
                    try {
                        $categoryDest->setData($key, $value);
                        $categoryResource->saveAttribute($categoryDest, $key);
                    } catch (Exception $e) {
                        $this->_output("Error saving attribute {$key} on category {$categoryId}: " . $e->getMessage() . "\n");
                        Mage::logException($e);
                    }
                }

                // Reset the auto_translate flag to No on the dest store view
                try {
                    $categoryDest->setData('auto_translate', 0);
                    $categoryResource->saveAttribute($categoryDest, 'auto_translate');
                } catch (Exception $e) {
                    $this->_output("Error resetting auto_translate on category {$categoryId}: " . $e->getMessage() . "\n");
                    Mage::logException($e);
                }
            }

            $this->_output(" OK\n");
        }
    }

    protected function _getFlaggedCategoryIds($storeIdDest)
    {
        $attributeId = Mage::getModel('catalog/entity_attribute')
            ->loadByCode(Mage_Catalog_Model_Category::ENTITY, 'auto_translate')
            ->getId();

        if (!$attributeId) {
            $this->_output("WARNING: category attribute 'auto_translate' not found – no categories will be translated.\n");
            return array();
        }

        $table = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity_int');
        return Mage::getSingleton('core/resource')
            ->getConnection('core_read')
            ->fetchCol(
                "SELECT entity_id FROM {$table} "
                . "WHERE attribute_id={$attributeId} AND store_id={$storeIdDest} AND value=1"
            );
    }

    // ------------------------------------------------------------------
    // DeepL – batched
    // ------------------------------------------------------------------

    /**
     * Translate an array of strings via the DeepL REST API using curl.
     * Strings are sent in chunks of DEEPL_BATCH_SIZE.
     *
     * @param  array $strings
     * @return array  Translated strings, same order as input.
     */
    protected function _translateBatch(array $strings)
    {
        if (empty($strings)) {
            return array();
        }

        $targetLang = ($this->_langDest === 'en') ? 'en-GB' : strtoupper($this->_langDest);
        $sourceLang = strtoupper($this->_langSource);
        $formality  = $this->_helper->getFormality();

        // Free-tier API keys end with ':fx'
        $apiBase = (substr($this->_apiKey, -3) === ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $results = array();

        foreach (array_chunk($strings, self::DEEPL_BATCH_SIZE) as $chunk) {
            // Build POST fields: DeepL accepts repeated 'text[]' params
            $fields = array(
                'source_lang'    => $sourceLang,
                'target_lang'    => $targetLang,
                'tag_handling'   => 'html',
                'split_sentences'=> '1',
                'formality'      => $formality,
            );
            // Append each text as text[]
            $postBody = http_build_query($fields);
            foreach ($chunk as $text) {
                $postBody .= '&text=' . urlencode($text);
            }

            $ch = curl_init($apiBase);
            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => array(
                    'Authorization: DeepL-Auth-Key ' . $this->_apiKey,
                    'Content-Type: application/x-www-form-urlencoded',
                ),
                CURLOPT_TIMEOUT        => 60,
            ));

            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Mage::throwException('DeepL API curl error: ' . $curlError);
            }
            if ($httpCode !== 200) {
                Mage::throwException('DeepL API error (HTTP ' . $httpCode . '): ' . $responseBody);
            }

            $json = json_decode($responseBody, true);
            if (!isset($json['translations'])) {
                Mage::throwException('DeepL API unexpected response: ' . $responseBody);
            }

            foreach ($json['translations'] as $translation) {
                $results[] = $translation['text'];
            }
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Batch helpers
    // ------------------------------------------------------------------

    /**
     * Returns the offset for the next batch, or null if processing is complete.
     *
     * @return int|null
     */
    public function getNextOffset()
    {
        return $this->_nextOffset;
    }

    // ------------------------------------------------------------------
    // Output helper
    // ------------------------------------------------------------------

    protected function _output($message)
    {
        Mage::log(rtrim($message), Zend_Log::INFO, self::LOG_FILE);
        if ($this->getVerbose()) {
            echo $message;
        }
    }
}
