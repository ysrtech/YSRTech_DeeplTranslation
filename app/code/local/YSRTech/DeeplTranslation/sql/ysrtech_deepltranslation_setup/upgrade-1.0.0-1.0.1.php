<?php
/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

// Fix category auto_translate: boolean input type does not render in category edit.
// Update to select with the boolean source model.
$installer->updateAttribute(Mage_Catalog_Model_Category::ENTITY, 'auto_translate', array(
    'frontend_input'  => 'select',
    'source_model'    => 'eav/entity_attribute_source_boolean',
));

$installer->endSetup();
