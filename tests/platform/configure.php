<?php
declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php configure.php <platform-root> <platform> <disabled|immediate|consent>\n");
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$mode = $argv[3];
if (!in_array($mode, array('disabled', 'immediate', 'consent'), true)) {
    fwrite(STDERR, "Unsupported monitoring mode: {$mode}\n");
    exit(2);
}

basicrum_platform_save(array(
    'basicrum_analytics/general/enabled' => $mode === 'disabled' ? '0' : '1',
    'basicrum_analytics/general/beacon_endpoint' => 'https://collector.example.test/beacon',
    'basicrum_analytics/general/brum_site_id' => '550e8400-e29b-41d4-a716-446655440000',
    'basicrum_analytics/privacy/strip_query_string' => '0',
    'basicrum_analytics/privacy/opt_in_required' => $mode === 'consent' ? '1' : '0',
    'basicrum_analytics/developer/development_mode' => '0',
    'basicrum_analytics/developer/use_unminified_loaders' => '0',
));

$store = Mage::app()->getStore('default');
basicrum_platform_save(array(
    'basicrum_analytics/privacy/opt_in_required' => $mode === 'consent' ? '1' : '0',
), 'websites', (int) $store->getWebsiteId());
basicrum_platform_save(array(
    'basicrum_analytics/privacy/opt_in_required' => $mode === 'consent' ? '1' : '0',
), 'stores', (int) $store->getId());

echo "Configured {$mode} monitoring mode.\n";
