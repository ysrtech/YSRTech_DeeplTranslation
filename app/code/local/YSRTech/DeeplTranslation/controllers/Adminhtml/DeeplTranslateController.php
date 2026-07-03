<?php
/**
 * YSRTech_DeeplTranslation_Adminhtml_DeeplTranslateController
 *
 * Handles AJAX requests from the Initial Setup panel in System > Configuration.
 *
 * Actions:
 *   run  – accepts POST params: store_code, type (categories|products)
 *          Returns JSON { "output": "..." }
 */
class YSRTech_DeeplTranslation_Adminhtml_DeeplTranslateController extends Mage_Adminhtml_Controller_Action
{
    public function runAction()
    {
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json', true);

        // Validate form key manually (we need JSON back, not a redirect)
        $postFormKey    = $this->getRequest()->getPost('form_key');
        $sessionFormKey = Mage::getSingleton('core/session')->getFormKey();
        if (!$postFormKey || $postFormKey !== $sessionFormKey) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: invalid form key. Please reload the page.',
            )));
            return;
        }

        $storeCode = trim((string)$this->getRequest()->getPost('store_code'));
        $type      = trim((string)$this->getRequest()->getPost('type'));

        if (!$storeCode || !in_array($type, array('categories', 'products'))) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: invalid parameters.',
            )));
            return;
        }

        $helper = Mage::helper('ysrtech_deepltranslation');
        if (!$helper->getApiKey()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: no DeepL API key configured. Please save your API key first.',
            )));
            return;
        }

        // Verify the destination store view exists and is not the default
        try {
            $destStore    = Mage::app()->getStore($storeCode);
            $defaultStore = Mage::app()->getDefaultStoreView();
            if ($destStore->getId() == $defaultStore->getId()) {
                $response->setBody(Mage::helper('core')->jsonEncode(array(
                    'output' => 'Error: destination store cannot be the default store view.',
                )));
                return;
            }
        } catch (Mage_Core_Model_Store_Exception $e) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: store view "' . $storeCode . '" not found.',
            )));
            return;
        }

        set_time_limit(0);

        $sourceCode = Mage::app()->getDefaultStoreView()->getCode();

        ob_start();

        try {
            /** @var YSRTech_DeeplTranslation_Model_Translator $translator */
            $translator = Mage::getModel('ysrtech_deepltranslation/translator');
            $translator
                ->setStoreSource($sourceCode)
                ->setStoreDest($storeCode)
                ->setVerbose(true)
                ->setDebugMode(false)
                ->setDryRun(false);

            if ($type === 'categories') {
                // Translate ALL categories, ignore auto_translate flag
                $translator
                    ->setOnlyCategories(true)
                    ->setAllCategories(true);
            } else {
                // Products only, respects auto_translate flag
                $translator->setOnlyProducts(true);
            }

            $translator->run();

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            Mage::logException($e);
        }

        $output = ob_get_clean();

        $response->setBody(Mage::helper('core')->jsonEncode(array(
            'output' => $output ?: 'Completed with no output.',
        )));
    }

    /**
     * AJAX: translate a single category to the selected destination store view.
     *
     * POST params: form_key, category_id (int), dest_store_code (string)
     * Returns JSON { "output": "..." }
     */
    public function translateCategoryAction()
    {
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json', true);

        if (!$this->getRequest()->isPost()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: invalid request method.',
            )));
            return;
        }

        if (!$this->_validateFormKey()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: invalid form key. Please reload the page.',
            )));
            return;
        }

        $categoryId    = (int)$this->getRequest()->getPost('category_id');
        $destStoreCode = trim((string)$this->getRequest()->getPost('dest_store_code'));

        if (!$categoryId || !$destStoreCode) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: missing category_id or dest_store_code.',
            )));
            return;
        }

        $helper = Mage::helper('ysrtech_deepltranslation');
        if (!$helper->getApiKey()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: no DeepL API key configured.',
            )));
            return;
        }

        try {
            $destStore    = Mage::app()->getStore($destStoreCode);
            $defaultStore = Mage::app()->getDefaultStoreView();
            if ($destStore->getId() == $defaultStore->getId()) {
                $response->setBody(Mage::helper('core')->jsonEncode(array(
                    'output' => 'Error: destination store cannot be the default store view.',
                )));
                return;
            }
        } catch (Mage_Core_Model_Store_Exception $e) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: store view "' . $destStoreCode . '" not found.',
            )));
            return;
        }

        set_time_limit(60);
        ob_start();

        try {
            /** @var YSRTech_DeeplTranslation_Model_Translator $translator */
            $translator = Mage::getModel('ysrtech_deepltranslation/translator');
            $translator
                ->setStoreSource($defaultStore->getCode())
                ->setStoreDest($destStoreCode)
                ->setSpecificCategoryIds(array($categoryId))
                ->setOnlyCategories(true)
                ->setVerbose(true)
                ->setDebugMode(false)
                ->setDryRun(false)
                ->run();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            Mage::logException($e);
        }

        $output = ob_get_clean();

        $response->setBody(Mage::helper('core')->jsonEncode(array(
            'output' => $output ?: 'Completed with no output.',
        )));
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/config/ysrtech_deepltranslation');
    }

    /**
     * AJAX: translate a single product to the selected destination store view.
     *
     * POST params: form_key, product_id (int), dest_store_code (string)
     * Returns JSON { "output": "..." }
     */
    public function translateProductAction()
    {
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json', true);

        if (!$this->getRequest()->isPost()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: invalid request method.',
            )));
            return;
        }

        if (!$this->_validateFormKey()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: invalid form key. Please reload the page.',
            )));
            return;
        }

        $productId     = (int)$this->getRequest()->getPost('product_id');
        $destStoreCode = trim((string)$this->getRequest()->getPost('dest_store_code'));

        if (!$productId || !$destStoreCode) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: missing product_id or dest_store_code.',
            )));
            return;
        }

        $helper = Mage::helper('ysrtech_deepltranslation');
        if (!$helper->getApiKey()) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: no DeepL API key configured.',
            )));
            return;
        }

        try {
            $destStore    = Mage::app()->getStore($destStoreCode);
            $defaultStore = Mage::app()->getDefaultStoreView();
            if ($destStore->getId() == $defaultStore->getId()) {
                $response->setBody(Mage::helper('core')->jsonEncode(array(
                    'output' => 'Error: destination store cannot be the default store view.',
                )));
                return;
            }
        } catch (Mage_Core_Model_Store_Exception $e) {
            $response->setBody(Mage::helper('core')->jsonEncode(array(
                'output' => 'Error: store view "' . $destStoreCode . '" not found.',
            )));
            return;
        }

        set_time_limit(60);
        ob_start();

        try {
            /** @var YSRTech_DeeplTranslation_Model_Translator $translator */
            $translator = Mage::getModel('ysrtech_deepltranslation/translator');
            $translator
                ->setStoreSource($defaultStore->getCode())
                ->setStoreDest($destStoreCode)
                ->setSpecificProductIds(array($productId))
                ->setOnlyProducts(true)
                ->setVerbose(true)
                ->setDebugMode(false)
                ->setDryRun(false)
                ->run();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            Mage::logException($e);
        }

        $output = ob_get_clean();

        $response->setBody(Mage::helper('core')->jsonEncode(array(
            'output' => $output ?: 'Completed with no output.',
        )));
    }
}
