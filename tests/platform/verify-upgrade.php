<?php
declare(strict_types=1);

if ($argc !== 6) {
    fwrite(
        STDERR,
        "Usage: php verify-upgrade.php <platform-root> <platform> <scenario> <expected-consent> <expected-http-policy|absent>\n"
    );
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$scenario = $argv[3];
$expectedConsent = $argv[4];
$expectedHttpPolicy = $argv[5];

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

$httpPolicySelect = $connection->select()
    ->from($configTable, 'value')
    ->where('path = ?', 'basicrum_analytics/developer/development_mode')
    ->where('scope = ?', 'default')
    ->where('scope_id = ?', 0)
    ->limit(1);
$actualHttpPolicy = $connection->fetchOne($httpPolicySelect);

if ($expectedHttpPolicy === 'absent') {
    basicrum_platform_assert(
        $actualHttpPolicy === false,
        "{$scenario}: upgrade unexpectedly persisted an HTTP policy"
    );
} else {
    basicrum_platform_assert_same(
        $expectedHttpPolicy,
        (string) $actualHttpPolicy,
        "{$scenario}: upgrade did not preserve the legacy HTTP policy"
    );
}

echo "Native {$platform} {$scenario} upgrade check passed.\n";
