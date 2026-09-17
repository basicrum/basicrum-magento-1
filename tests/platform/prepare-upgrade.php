<?php
declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php prepare-upgrade.php <platform-root> <platform> <legacy|explicit>\n");
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$scenario = $argv[3];
if (!in_array($scenario, array('legacy', 'explicit'), true)) {
    fwrite(STDERR, "Unsupported upgrade scenario: {$scenario}\n");
    exit(2);
}

$resource = Mage::getSingleton('core/resource');
$connection = $resource->getConnection('core_write');
$resourceTable = $resource->getTableName('core/resource');
$configTable = $resource->getTableName('core/config_data');

// Recreate a pre-1.1.0 database state. The next PHP process must discover
// the absent setup-resource row and execute install-1.1.0.php through the
// platform's native setup runner.
$connection->delete(
    $resourceTable,
    array('code = ?' => 'basicrum_analytics_setup')
);
$connection->delete(
    $configTable,
    array('path LIKE ?' => 'basicrum_analytics/%')
);

if ($scenario === 'legacy') {
    Mage::getConfig()->saveConfig(
        'basicrum_analytics/general/enabled',
        '0',
        'default',
        0
    );
} else {
    Mage::getConfig()->saveConfig(
        'basicrum_analytics/privacy/opt_in_required',
        '1',
        'default',
        0
    );
}

Mage::app()->getCacheInstance()->cleanType('config');

echo "Prepared {$scenario} pre-1.1.0 database state for {$platform}.\n";
