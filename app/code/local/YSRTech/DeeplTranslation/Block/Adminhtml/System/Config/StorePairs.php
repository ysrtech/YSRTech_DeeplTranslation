<?php
/**
 * Dynamic rows field for defining source → destination store view translation pairs.
 *
 * Renders as an "Add Row" table in System > Configuration.
 * Each row: Source Store View Code | Destination Store View Code
 */
class YSRTech_DeeplTranslation_Block_Adminhtml_System_Config_StorePairs
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        $this->addColumn('source_store', array(
            'label' => Mage::helper('ysrtech_deepltranslation')->__('Source Store View Code'),
            'size'  => 20,
        ));
        $this->addColumn('dest_store', array(
            'label' => Mage::helper('ysrtech_deepltranslation')->__('Destination Store View Code'),
            'size'  => 20,
        ));

        $this->_addAfter       = false;
        $this->_addButtonLabel = Mage::helper('ysrtech_deepltranslation')->__('Add Pair');

        parent::__construct();
    }
}
