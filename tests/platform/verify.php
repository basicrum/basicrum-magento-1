<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$moduleConfig = Mage::getConfig()->getModuleConfig('BasicRum_Analytics');
basicrum_platform_assert($moduleConfig !== false, 'BasicRum_Analytics is not present in merged configuration');
basicrum_platform_assert_same('1.1.0', (string) $moduleConfig->version, 'unexpected merged module version');

$resourceSetup = Mage::getResourceModel('core/resource');
basicrum_platform_assert_same(
    '1.1.0',
    (string) $resourceSetup->getDbVersion('basicrum_analytics_setup'),
    'module setup resource did not install version 1.1.0'
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
    '1',
    (string) $connection->fetchOne($select),
    'fresh installation did not persist the privacy-first default'
);

$helper = Mage::helper('basicrum_analytics');
basicrum_platform_assert(
    $helper instanceof BasicRum_Analytics_Helper_Data,
    'Magento did not resolve the Basicrum helper alias'
);

$block = Mage::app()->getLayout()->createBlock('basicrum_analytics/boomerang_loader');
basicrum_platform_assert(
    $block instanceof BasicRum_Analytics_Block_Boomerang_Loader,
    'Magento did not resolve the Basicrum block alias'
);
basicrum_platform_assert_same('', $block->getBoomerangSnippet(), 'incomplete configuration emitted monitoring code');

$consentInfo = Mage::app()->getLayout()
    ->createBlock('basicrum_analytics/adminhtml_system_config_form_field_consentInfo');
basicrum_platform_assert(
    $consentInfo instanceof BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_ConsentInfo,
    'Magento did not resolve the Basicrum admin consent renderer'
);

$requiredSetting = Mage::app()->getLayout()
    ->createBlock('basicrum_analytics/adminhtml_system_config_form_field_requiredSetting');
basicrum_platform_assert(
    $requiredSetting instanceof BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_RequiredSetting,
    'Magento did not resolve the Basicrum required-setting renderer'
);

$siteIdBackend = Mage::getModel('basicrum_analytics/system_config_backend_siteId');
basicrum_platform_assert(
    $siteIdBackend instanceof BasicRum_Analytics_Model_System_Config_Backend_SiteId,
    'Magento did not resolve the Basicrum Site ID backend model'
);

$beaconBackend = Mage::getModel('basicrum_analytics/system_config_backend_beaconEndpoint');
basicrum_platform_assert(
    $beaconBackend instanceof BasicRum_Analytics_Model_System_Config_Backend_BeaconEndpoint,
    'Magento did not resolve the Basicrum Beacon URL backend model'
);

$layoutFile = Mage::getConfig()->getNode('frontend/layout/updates/basicrumanalytics/file');
basicrum_platform_assert_same('basicrum_analytics.xml', (string) $layoutFile, 'frontend layout update is not registered');

basicrum_platform_save(array(
    'basicrum_analytics/general/enabled' => '1',
    'basicrum_analytics/general/beacon_endpoint' => 'https://collector.example.test/beacon',
    'basicrum_analytics/general/brum_site_id' => '550e8400-e29b-41d4-a716-446655440000',
    'basicrum_analytics/privacy/opt_in_required' => '0',
    'basicrum_analytics/wait_after_onload/enabled' => '1',
    'basicrum_analytics/wait_after_onload/wait_ms' => '90000',
    'basicrum_analytics/developer/use_unminified_loaders' => '0',
));
basicrum_platform_reboot();

$immediate = Mage::app()->getLayout()
    ->createBlock('basicrum_analytics/boomerang_loader')
    ->getBoomerangSnippet();
basicrum_platform_assert(
    strpos($immediate, 'boomerang-loader-v15.min.js') !== false,
    'immediate configuration did not render the standard loader'
);
basicrum_platform_assert(
    strpos($immediate, 'consent-boomerang-loader') === false,
    'immediate configuration rendered the consent loader'
);
basicrum_platform_assert(
    strpos($immediate, '}.bind(this), 30000);') !== false,
    'wait-after-onload value was not capped at 30 seconds'
);

basicrum_platform_save(array(
    'basicrum_analytics/general/beacon_endpoint' => 'javascript:alert(1)',
));
basicrum_platform_reboot();
$invalid = Mage::app()->getLayout()
    ->createBlock('basicrum_analytics/boomerang_loader')
    ->getBoomerangSnippet();
basicrum_platform_assert_same('', $invalid, 'unsafe Beacon URL emitted monitoring code');

$store = Mage::app()->getStore('default');
$storeId = (int) $store->getId();
$websiteId = (int) $store->getWebsiteId();

basicrum_platform_save(array(
    'basicrum_analytics/general/beacon_endpoint' => 'https://collector.example.test/beacon',
    'basicrum_analytics/privacy/opt_in_required' => '0',
));
basicrum_platform_save(array(
    'basicrum_analytics/privacy/opt_in_required' => '1',
), 'websites', $websiteId);
basicrum_platform_reboot();
basicrum_platform_assert(
    Mage::getStoreConfigFlag('basicrum_analytics/privacy/opt_in_required', $storeId),
    'website-scope consent setting was not inherited by the store'
);

basicrum_platform_save(array(
    'basicrum_analytics/privacy/opt_in_required' => '0',
), 'stores', $storeId);
basicrum_platform_reboot();
basicrum_platform_assert(
    !Mage::getStoreConfigFlag('basicrum_analytics/privacy/opt_in_required', $storeId),
    'store-scope consent setting did not override the website'
);

basicrum_platform_save(array(
    'basicrum_analytics/privacy/opt_in_required' => '1',
), 'stores', $storeId);
basicrum_platform_reboot();
$consent = Mage::app()->getLayout()
    ->createBlock('basicrum_analytics/boomerang_loader')
    ->getBoomerangSnippet();
basicrum_platform_assert(
    strpos($consent, 'consent-boomerang-loader-v1-15.min.js') !== false,
    'consent configuration did not render the consent loader'
);

$assetRoot = $platform === 'maho' ? $platformRoot . '/public' : $platformRoot;
basicrum_platform_assert(
    is_file($assetRoot . '/js/basicrum/loaders/consent-boomerang-loader-v1-15.min.js'),
    'consent loader is not deployed under the platform document root'
);
basicrum_platform_assert(
    is_file($assetRoot . '/js/basicrum/admin/consent-info.js'),
    'admin consent copy behavior is not deployed under the platform document root'
);

echo "Native {$platform} configuration and rendering checks passed.\n";
