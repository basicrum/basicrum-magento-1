<?php
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php bootstrap.php <platform-root> <platform>\n");
    exit(2);
}

$platformRoot = realpath($argv[1]);
$platform = $argv[2];

if ($platformRoot === false || !is_dir($platformRoot)) {
    fwrite(STDERR, "Invalid platform root: {$argv[1]}\n");
    exit(2);
}

if (!in_array($platform, array('magento-ce', 'openmage', 'maho'), true)) {
    fwrite(STDERR, "Unsupported platform: {$platform}\n");
    exit(2);
}

chdir($platformRoot);

if ($platform === 'maho') {
    if (!defined('MAHO_ROOT_DIR')) {
        define('MAHO_ROOT_DIR', $platformRoot);
    }
    if (!defined('MAHO_PUBLIC_DIR')) {
        define('MAHO_PUBLIC_DIR', $platformRoot . '/public');
    }
    require $platformRoot . '/vendor/autoload.php';
} else {
    require $platformRoot . '/app/Mage.php';
}

Mage::app('default');

function basicrum_platform_fail($message)
{
    throw new RuntimeException($message);
}

function basicrum_platform_assert($condition, $message)
{
    if (!$condition) {
        basicrum_platform_fail($message);
    }
}

function basicrum_platform_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        basicrum_platform_fail(
            $message . '\nExpected: ' . var_export($expected, true)
            . '\nActual: ' . var_export($actual, true)
        );
    }
}

function basicrum_platform_save(array $values, $scope = 'default', $scopeId = 0)
{
    foreach ($values as $path => $value) {
        Mage::getConfig()->saveConfig($path, $value, $scope, $scopeId);
    }

    Mage::app()->getCacheInstance()->cleanType('config');
}

function basicrum_platform_reboot($store = 'default')
{
    Mage::reset();
    Mage::app($store);
}
