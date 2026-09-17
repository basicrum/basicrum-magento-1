<?php
declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$configTable = $installer->getTable('core/config_data');
$consentPath = 'basicrum_analytics/privacy/opt_in_required';

$explicitDefaultSelect = $connection->select()
    ->from($configTable, 'path')
    ->where('path = ?', $consentPath)
    ->where('scope = ?', 'default')
    ->where('scope_id = ?', 0)
    ->limit(1);

$existingConfigurationSelect = $connection->select()
    ->from($configTable, 'path')
    ->where('path LIKE ?', 'basicrum_analytics/%')
    ->limit(1);

$value = BasicRum_Analytics_Model_Setup_PrivacyDefault::getValueToPersist(
    $connection->fetchOne($explicitDefaultSelect) !== false,
    $connection->fetchOne($existingConfigurationSelect) !== false
);

if ($value !== null) {
    Mage::getConfig()->saveConfig($consentPath, $value, 'default', 0);
}

$installer->endSetup();
