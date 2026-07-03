<?php
class YSRTech_DeeplTranslation_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ENABLED            = 'ysrtech_deepltranslation/general/enabled';

    // Cron config paths
    const XML_PATH_CRON_ENABLED        = 'ysrtech_deepltranslation/cron/enabled';
    const XML_PATH_CRON_EXPR           = 'ysrtech_deepltranslation/cron/cron_expr';

    const XML_PATH_API_KEY            = 'ysrtech_deepltranslation/general/api_key';
    const XML_PATH_FORMALITY          = 'ysrtech_deepltranslation/general/formality';
    const XML_PATH_PRODUCT_ATTRIBUTES = 'ysrtech_deepltranslation/general/product_attributes';
    const XML_PATH_CATEGORY_ATTRIBUTES = 'ysrtech_deepltranslation/general/category_attributes';

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        $encrypted = Mage::getStoreConfig(self::XML_PATH_API_KEY);
        return $encrypted ? Mage::helper('core')->decrypt($encrypted) : '';
    }

    /**
     * @return string  e.g. "prefer_more"
     */
    public function getFormality()
    {
        $formality = Mage::getStoreConfig(self::XML_PATH_FORMALITY);
        return $formality ?: 'prefer_more';
    }

    /**
     * Returns product attribute codes configured for translation.
     *
     * @return array
     */
    public function getAttributesToTranslate()
    {
        $raw = Mage::getStoreConfig(self::XML_PATH_PRODUCT_ATTRIBUTES);
        return $this->_parseAttributeList($raw);
    }

    /**
     * Returns category attribute codes configured for translation.
     *
     * @return array
     */
    public function getCategoryAttributesToTranslate()
    {
        $raw = Mage::getStoreConfig(self::XML_PATH_CATEGORY_ATTRIBUTES);
        return $this->_parseAttributeList($raw);
    }

    // ------------------------------------------------------------------
    // Cron helpers
    // ------------------------------------------------------------------

    /**
     * @return bool
     */
    public function isCronEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CRON_ENABLED);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Split a newline-delimited textarea config value into a trimmed, non-empty array.
     * Also accepts commas as a delimiter for backwards compatibility.
     *
     * @param  string $raw
     * @return array
     */
    protected function _parseAttributeList($raw)
    {
        if (!$raw) {
            return array();
        }
        $parts = preg_split('/[\r\n,]+/', $raw);
        return array_values(array_filter(array_map('trim', $parts)));
    }
}
