<?php
class YSRTech_DeeplTranslation_Model_Adminhtml_System_Config_Source_CategoryAttributes
{
    /**
     * Returns all text and textarea category attributes as an option array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options    = array();
        $collection = Mage::getResourceModel('catalog/category_attribute_collection')
            ->setOrder('attribute_code', 'ASC');

        foreach ($collection as $attribute) {
            if (!in_array($attribute->getFrontendInput(), array('text', 'textarea'))) {
                continue;
            }
            if ((int)$attribute->getIsGlobal() !== Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE) {
                continue;
            }
            if (!in_array($attribute->getBackendType(), array('varchar', 'text'))) {
                continue;
            }
            if ($attribute->getBackendModel()) {
                continue;
            }
            if (in_array($attribute->getAttributeCode(), array('url_path'))) {
                continue;
            }
            $label = $attribute->getFrontendLabel()
                ? $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')'
                : $attribute->getAttributeCode();

            $options[] = array(
                'value' => $attribute->getAttributeCode(),
                'label' => $label,
            );
        }

        return $options;
    }
}
