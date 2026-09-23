<?php
/**
 * Carry the Fballiano_FullCatalogTranslate "Translate automatically?" flags (fb_translate)
 * over to auto_translate, so items queued for translation under the old module are still
 * queued after switching. Same semantics: value 1 on the destination store view means
 * "translate me to this store view". Existing auto_translate rows are left alone.
 *
 * @var Mage_Catalog_Model_Resource_Setup $installer
 */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
foreach (array(
    array(Mage_Catalog_Model_Product::ENTITY, $installer->getTable('catalog/product') . '_int'),
    array(Mage_Catalog_Model_Category::ENTITY, $installer->getTable('catalog/category') . '_int'),
) as $entity) {
    list($entityType, $table) = $entity;
    $from = $installer->getAttributeId($entityType, 'fb_translate');
    $to   = $installer->getAttributeId($entityType, 'auto_translate');
    if (!$from || !$to) {
        continue;
    }
    $select = $connection->select()
        ->from(array('src' => $table), array('entity_type_id', new Zend_Db_Expr((int) $to), 'store_id', 'entity_id', 'value'))
        ->joinLeft(
            array('dst' => $table),
            'dst.entity_id = src.entity_id AND dst.store_id = src.store_id AND dst.attribute_id = ' . (int) $to,
            array()
        )
        ->where('src.attribute_id = ?', $from)
        ->where('src.value = 1')
        ->where('dst.value_id IS NULL');
    $connection->query($connection->insertFromSelect($select, $table, array('entity_type_id', 'attribute_id', 'store_id', 'entity_id', 'value')));
}

$installer->endSetup();
