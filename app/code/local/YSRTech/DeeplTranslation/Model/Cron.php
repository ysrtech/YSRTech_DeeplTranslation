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

        if (!$helper->getApiKey()) {
            Mage::log(
                'YSRTech DeepL Translation cron: no API key configured, skipping.',
                Zend_Log::WARN,
                YSRTech_DeeplTranslation_Model_Translator::LOG_FILE
            );
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

        $sourceLanguage = substr((string) $defaultStore->getConfig('general/locale/code'), 0, 2);
        foreach ($destStores as $destCode) {
            // A store view in the source language (e.g. a second website's Dutch view) has
            // nothing to translate; DeepL would be asked for nl -> nl.
            $destLanguage = substr((string) Mage::app()->getStore($destCode)->getConfig('general/locale/code'), 0, 2);
            if ($destLanguage === $sourceLanguage) {
                continue;
            }
            try {
                // The translator handles PRODUCT_BATCH_SIZE products per run() and reports the
                // next offset; loop like the shell script does, or a cron run would only ever
                // translate the first batch of each store view's queue.
                $batchOffset = 0;
                do {
                    /** @var YSRTech_DeeplTranslation_Model_Translator $translator */
                    $translator = Mage::getModel('ysrtech_deepltranslation/translator');
                    $translator
                        ->setStoreSource($sourceCode)
                        ->setStoreDest($destCode)
                        ->setDebugMode(false)
                        ->setDryRun(false)
                        ->setVerbose(false)
                        ->setBatchOffset($batchOffset)
                        ->run();
                    $batchOffset = $translator->getNextOffset();
                } while ($batchOffset !== null);
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
