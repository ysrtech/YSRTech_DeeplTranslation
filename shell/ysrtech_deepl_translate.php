<?php
/**
 * YSRTech_DeeplTranslation - shell runner
 *
 * All translation logic lives in YSRTech_DeeplTranslation_Model_Translator.
 * This script is a thin bootstrap wrapper around that model.
 *
 * Usage:
 *   php -f shell/ysrtech_deepl_translate.php -- <source_store_code> <dest_store_code> [options]
 *
 * Type flags (default = both categories and products, flagged only):
 *   --allCategories      Translate all categories regardless of auto_translate flag
 *   --allProducts        Translate all products regardless of auto_translate flag
 *   --onlyCategories     Skip products entirely
 *   --onlyProducts       Skip categories entirely
 *
 * Batching:
 *   Products are processed in batches of PRODUCT_BATCH_SIZE. The script loops
 *   automatically until all products are done.
 */

require_once 'abstract.php';

class YSRTech_DeeplTranslation_Shell extends Mage_Shell_Abstract
{
    public function run()
    {
        $args       = array_keys($this->_args);
        $positional = array_values(array_filter($args, function ($k) {
            return strpos($k, '-') !== 0;
        }));

        $storeSource    = isset($positional[0]) ? $positional[0] : null;
        $storeDest      = isset($positional[1]) ? $positional[1] : null;
        $debugMode      = (bool)$this->getArg('debug');
        $dryRun         = (bool)$this->getArg('dry');
        $allCategories  = (bool)$this->getArg('allCategories');
        $allProducts    = (bool)$this->getArg('allProducts');
        $onlyCategories = (bool)$this->getArg('onlyCategories');
        $onlyProducts   = (bool)$this->getArg('onlyProducts');

        if (!$storeSource || !$storeDest) {
            die($this->usageHelp());
        }

        $apiKey = Mage::helper('ysrtech_deepltranslation')->getApiKey();
        if (!$apiKey) {
            die("Please set your DeepL API key in System > Configuration > Services > DeepL Translation.\n");
        }

        if (!function_exists('mb_strlen')) {
            die("Please install the mbstring PHP extension.\n");
        }

        $batchOffset = 0;
        $batchNum    = 1;

        do {
            if ($batchOffset > 0) {
                echo "\n--- Starting batch {$batchNum} (offset {$batchOffset}) ---\n";
            }

            /** @var YSRTech_DeeplTranslation_Model_Translator $translator */
            $translator = Mage::getModel('ysrtech_deepltranslation/translator');
            $translator
                ->setStoreSource($storeSource)
                ->setStoreDest($storeDest)
                ->setDebugMode($debugMode)
                ->setDryRun($dryRun)
                ->setAllCategories($allCategories)
                ->setAllProducts($allProducts)
                ->setOnlyCategories($onlyCategories)
                ->setOnlyProducts($onlyProducts)
                ->setBatchOffset($batchOffset)
                ->setVerbose(true)
                ->run();

            $batchOffset = $translator->getNextOffset();
            $batchNum++;
        } while ($batchOffset !== null);
    }

    public function usageHelp()
    {
        return <<<USAGE

Usage:
  php -f shell/ysrtech_deepl_translate.php -- <source_store_code> <dest_store_code> [options]

Arguments:
  source_store_code    Store view code to read from (e.g. "default")
  dest_store_code      Store view code to write to  (e.g. "nl_nl")

Options:
  --debug              Print every attribute value before and after translation
  --dry                Translate everything but do not save (implies --debug)
  --allCategories      Translate all categories, not only those with auto_translate=1
  --allProducts        Translate all products, not only those with auto_translate=1
  --onlyCategories     Skip products entirely
  --onlyProducts       Skip categories entirely

Examples:
  # Flagged items only (default)
  php -f shell/ysrtech_deepl_translate.php -- default nl_nl

  # All products only
  php -f shell/ysrtech_deepl_translate.php -- default nl_nl --onlyProducts --allProducts

  # All categories only
  php -f shell/ysrtech_deepl_translate.php -- default nl_nl --onlyCategories --allCategories

  # Everything, flagged
  php -f shell/ysrtech_deepl_translate.php -- default nl_nl

Configuration:
  System > Configuration > Services > DeepL Translation

USAGE;
    }
}

$shell = new YSRTech_DeeplTranslation_Shell();
$shell->run();
