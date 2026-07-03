<?php
/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

// -----------------------------------------------------------------------
// Product attribute: auto_translate
// -----------------------------------------------------------------------
if (!$installer->getAttributeId(Mage_Catalog_Model_Product::ENTITY, 'auto_translate')) {
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'auto_translate', array(
        'group'                   => 'General',
        'type'                    => 'int',
        'backend'                 => '',
        'frontend'                => '',
        'label'                   => 'Auto Translate',
        'input'                   => 'boolean',
        'class'                   => '',
        'source'                  => 'eav/entity_attribute_source_boolean',
        'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'                 => true,
        'required'                => false,
        'user_defined'            => true,
        'default'                 => 0,
        'searchable'              => false,
        'filterable'              => false,
        'comparable'              => false,
        'visible_on_front'        => false,
        'unique'                  => false,
        'apply_to'                => '',
        'is_configurable'         => false,
        'used_in_product_listing' => false,
    ));
}

// -----------------------------------------------------------------------
// Category attribute: auto_translate
// -----------------------------------------------------------------------
if (!$installer->getAttributeId(Mage_Catalog_Model_Category::ENTITY, 'auto_translate')) {
    $installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'auto_translate', array(
        'group'          => 'General Information',
        'type'           => 'int',
        'backend'        => '',
        'frontend'       => '',
        'label'          => 'Auto Translate',
        'input'          => 'select',
        'class'          => '',
        'source'         => 'eav/entity_attribute_source_boolean',
        'global'         => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'        => true,
        'required'       => false,
        'user_defined'   => true,
        'default'        => 0,
        'unique'         => false,
    ));
}

$installer->endSetup();
