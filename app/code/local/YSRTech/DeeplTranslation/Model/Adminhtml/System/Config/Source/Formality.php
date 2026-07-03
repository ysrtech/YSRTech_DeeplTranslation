<?php
class YSRTech_DeeplTranslation_Model_Adminhtml_System_Config_Source_Formality
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'default',      'label' => Mage::helper('ysrtech_deepltranslation')->__('Default')),
            array('value' => 'prefer_more',  'label' => Mage::helper('ysrtech_deepltranslation')->__('Formal (prefer more)')),
            array('value' => 'prefer_less',  'label' => Mage::helper('ysrtech_deepltranslation')->__('Informal (prefer less)')),
            array('value' => 'more',         'label' => Mage::helper('ysrtech_deepltranslation')->__('Formal (strict)')),
            array('value' => 'less',         'label' => Mage::helper('ysrtech_deepltranslation')->__('Informal (strict)')),
        );
    }
}
