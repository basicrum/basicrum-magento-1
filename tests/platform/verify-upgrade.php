<?php
declare(strict_types=1);

if ($argc !== 5) {
    fwrite(
        STDERR,
        "Usage: php verify-upgrade.php <platform-root> <platform> <scenario> <expected-consent>\n"
    );
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$scenario = $argv[3];
$expectedConsent = $argv[4];

$resourceSetup = Mage::getResourceModel('core/resource');
basicrum_platform_assert_same(
    '1.1.0',
    (string) $resourceSetup->getDbVersion('basicrum_analytics_setup'),
    "{$scenario}: native setup runner did not restore module version 1.1.0"
);

$resource = Mage::getSingleton('core/resource');
$connection = $resource->getConnection('core_read');
$configTable = $resource->getTableName('core/config_data');
$select = $connection->select()
    ->from($configTable, 'value')
    ->where('path = ?', 'basicrum_analytics/privacy/opt_in_required')
    ->where('scope = ?', 'default')
    ->where('scope_id = ?', 0)
    ->limit(1);

basicrum_platform_assert_same(
    $expectedConsent,
    (string) $connection->fetchOne($select),
    "{$scenario}: upgrade persisted the wrong consent mode"
);

echo "Native {$platform} {$scenario} upgrade check passed.\n";
