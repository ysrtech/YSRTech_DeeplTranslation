<?php
/**
 * Retire Fballiano_FullCatalogTranslate: its "Translate automatically?" flags were copied to
 * auto_translate by upgrade-1.0.1-1.0.2.php, so drop the fb_translate attributes, the
 * module's configuration and its setup record. Does nothing when there is nothing to remove.
 *
 * @var Mage_Catalog_Model_Resource_Setup $installer
 */
$installer = $this;
$installer->startSetup();

foreach (array(Mage_Catalog_Model_Product::ENTITY, Mage_Catalog_Model_Category::ENTITY) as $entityType) {
    if ($installer->getAttributeId($entityType, 'fb_translate')) {
        $installer->removeAttribute($entityType, 'fb_translate');
    }
}

$connection = $installer->getConnection();
$connection->delete($installer->getTable('core/config_data'), array('path LIKE ?' => 'fballiano_full_catalog_translate/%'));
$connection->delete($installer->getTable('core/config_data'), array('path = ?' => 'advanced/modules_disable_output/Fballiano_FullCatalogTranslate'));
$connection->delete($installer->getTable('core/resource'), array('code = ?' => 'fballiano_fullcatalogtranslate_setup'));

$installer->endSetup();
