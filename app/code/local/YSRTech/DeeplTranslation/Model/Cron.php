<?php
/**
 * YSRTech_DeeplTranslation_Model_Cron
 *
 * Called by the OpenMage/Magento 1 cron scheduler.
 * Reads all settings from System > Configuration > Services > DeepL Translation > Scheduled Translation.
 */
class YSRTech_DeeplTranslation_Model_Cron
{
    /**
     * Entry point invoked by the cron scheduler.
     *
     * @return $this
     */
    public function run()
    {
        /** @var YSRTech_DeeplTranslation_Helper_Data $helper */
        $helper = Mage::helper('ysrtech_deepltranslation');

        if (!$helper->isEnabled()) {
            return $this;
        }

        if (!$helper->isCronEnabled()) {
            return $this;
        }

        // Source is always the default store view.
        // Destination is every other active store view.
        $defaultStore = Mage::app()->getDefaultStoreView();
        $sourceCode   = $defaultStore->getCode();

        $destStores = array();
        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            if ($store->getId() == $defaultStore->getId()) {
                continue;
            }
            $destStores[] = $store->getCode();
        }

        if (empty($destStores)) {
            Mage::log(
                'YSRTech DeepL Translation cron: no destination store views found.',
                Zend_Log::WARN,
                YSRTech_DeeplTranslation_Model_Translator::LOG_FILE
            );
            return $this;
        }

        foreach ($destStores as $destCode) {
            try {
                /** @var YSRTech_DeeplTranslation_Model_Translator $translator */
                $translator = Mage::getModel('ysrtech_deepltranslation/translator');
                $translator
                    ->setStoreSource($sourceCode)
                    ->setStoreDest($destCode)
                    ->setDebugMode(false)
                    ->setDryRun(false)
                    ->setVerbose(false)
                    ->run();
            } catch (Exception $e) {
                Mage::log(
                    'YSRTech DeepL Translation cron error ('
                        . $sourceCode . ' → ' . $destCode . '): '
                        . $e->getMessage(),
                    Zend_Log::ERR,
                    YSRTech_DeeplTranslation_Model_Translator::LOG_FILE
                );
                Mage::logException($e);
            }
        }

        return $this;
    }
}
