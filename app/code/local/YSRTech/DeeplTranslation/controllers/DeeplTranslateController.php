<?php
/**
 * YSRTech_DeeplTranslation_DeeplTranslateController
 *
 * Standalone admin controller handling AJAX requests from the
 * Initial Setup panel in System > Configuration.
 *
 * Route: ysrtech_deepl/deepl_translate/run
 * Actions:
 *   run  – POST params: store_code, type (categories|products)
 *          Returns JSON { "output": "..." }
 */
class YSRTech_DeeplTranslation_DeeplTranslateController extends Mage_Adminhtml_Controller_Action
{
    public function runAction()
    {
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json', true);

        // Validate form key
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

        if (!$storeCode || !in_array($type, array('categories_all', 'categories_flagged', 'products_all', 'products_flagged'))) {
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

            if ($type === 'categories_all') {
                // ALL categories, ignore auto_translate flag
                $translator
                    ->setOnlyCategories(true)
                    ->setAllCategories(true);
            } elseif ($type === 'categories_flagged') {
                // Only flagged categories
                $translator
                    ->setOnlyCategories(true)
                    ->setAllCategories(false);
            } elseif ($type === 'products_all') {
                // ALL products, ignore auto_translate flag
                $translator
                    ->setOnlyProducts(true)
                    ->setAllProducts(true);
            } else {
                // Only flagged products
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

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/config/ysrtech_deepltranslation');
    }
}
