<?php
/**
 * Backend model for the cron_expr config field.
 *
 * Saves the schedule expression into crontab.xml so that the OpenMage
 * cron scheduler picks it up without requiring a manual XML deploy.
 * This mirrors the pattern used by Magento_GoogleShopping and others.
 */
class YSRTech_DeeplTranslation_Model_Adminhtml_System_Config_Backend_Cron
    extends Mage_Core_Model_Config_Data
{
    const CRON_MODEL_PATH = 'crontab/jobs/ysrtech_deepltranslation_translate/run/model';
    const CRON_EXPR_PATH  = 'crontab/jobs/ysrtech_deepltranslation_translate/schedule/cron_expr';

    protected function _afterSave()
    {
        $cronExpr = $this->getData('groups/cron/fields/cron_expr/value');
        $enabled  = $this->getData('groups/cron/fields/enabled/value');

        // If disabled or expression is empty, store a blank so the job does nothing
        $exprToSave = ($enabled && $cronExpr) ? $cronExpr : '';

        /** @var Mage_Core_Model_Config $config */
        $config = Mage::getModel('core/config');

        try {
            $config->saveConfig(self::CRON_EXPR_PATH, $exprToSave, 'default', 0);
            $config->saveConfig(
                self::CRON_MODEL_PATH,
                'ysrtech_deepltranslation/cron::run',
                'default',
                0
            );
            Mage::app()->getCacheInstance()->cleanType('config');
        } catch (Exception $e) {
            Mage::throwException(
                Mage::helper('ysrtech_deepltranslation')->__('Unable to save cron expression: %s', $e->getMessage())
            );
        }

        return parent::_afterSave();
    }
}
