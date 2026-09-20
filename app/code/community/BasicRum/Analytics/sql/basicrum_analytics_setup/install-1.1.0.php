<?php
declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$configTable = $installer->getTable('core/config_data');
$consentPath = 'basicrum_analytics/privacy/opt_in_required';
$beaconPath = 'basicrum_analytics/general/beacon_endpoint';
$httpPolicyPath = 'basicrum_analytics/developer/development_mode';

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

$beaconRowsSelect = $connection->select()
    ->from($configTable, array('scope', 'scope_id', 'value'))
    ->where('path = ?', $beaconPath);

$explicitHttpPolicySelect = $connection->select()
    ->from($configTable, array('scope', 'scope_id'))
    ->where('path = ?', $httpPolicyPath);

$httpPolicies = BasicRum_Analytics_Model_Setup_HttpPolicyDefault::getValuesToPersist(
    $connection->fetchAll($beaconRowsSelect),
    $connection->fetchAll($explicitHttpPolicySelect)
);

foreach ($httpPolicies as $policy) {
    Mage::getConfig()->saveConfig(
        $httpPolicyPath,
        $policy['value'],
        $policy['scope'],
        $policy['scope_id']
    );
}

$installer->endSetup();
