<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require __DIR__ . '/bootstrap.php';
require $root . '/app/code/community/BasicRum/Analytics/Helper/Data.php';
require $root . '/app/code/community/BasicRum/Analytics/Block/Boomerang/Loader.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Backend/SiteId.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Backend/BeaconEndpoint.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/Setup/PrivacyDefault.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Source/ConsentMode.php';
require $root . '/app/code/community/BasicRum/Analytics/Block/Adminhtml/System/Config/Form/Field/ConsentInfo.php';

class Basicrum_Test_SiteIdBackend extends BasicRum_Analytics_Model_System_Config_Backend_SiteId
{
    public function validate()
    {
        return $this->_beforeSave();
    }
}

class Basicrum_Test_BeaconBackend extends BasicRum_Analytics_Model_System_Config_Backend_BeaconEndpoint
{
    public function validate()
    {
        return $this->_beforeSave();
    }
}

$tests = array();

$tests['admin consent choices preserve values and explain behavior'] = function () {
    basicrum_test_reset();

    $options = (new BasicRum_Analytics_Model_System_Config_Source_ConsentMode())->toOptionArray();

    basicrum_assert_same('0', $options[0]['value'], 'immediate mode must retain its stored value');
    basicrum_assert_contains(
        'Monitor without consent',
        $options[0]['label'],
        'immediate mode must explain that consent is not required'
    );
    basicrum_assert_same('1', $options[1]['value'], 'consent-controlled mode must retain its stored value');
    basicrum_assert_contains(
        'Require consent before monitoring',
        $options[1]['label'],
        'consent-controlled mode must explain that monitoring waits for consent'
    );
};

$tests['admin consent guidance is a full-width dependent row'] = function () use ($root) {
    $elementId = 'basicrum_analytics_privacy_consent_integration_info';
    $renderer = new BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_ConsentInfo();
    $html = $renderer->render(new Varien_Data_Form_Element_Abstract($elementId));

    basicrum_assert_contains('id="row_' . $elementId . '"', $html, 'guidance row must use Magento field row ID');
    basicrum_assert_contains('colspan="4"', $html, 'guidance must span the configuration table');
    basicrum_assert_contains(
        'OPT_IN_BASICRUM_LOADER_WRAPPER()',
        $html,
        'guidance must retain the canonical opt-in callback'
    );
    basicrum_assert_contains(
        'OPT_IN_BASIC_RUM()',
        $html,
        'guidance must retain the legacy Magento callback alias'
    );

    $xml = simplexml_load_file($root . '/app/code/community/BasicRum/Analytics/etc/system.xml');
    basicrum_assert_true($xml !== false, 'system configuration XML must parse');
    $privacyFields = $xml->sections->basicrum_analytics->groups->privacy->fields;
    basicrum_assert_same(
        'basicrum_analytics/system_config_source_consentMode',
        (string) $privacyFields->opt_in_required->source_model,
        'consent control must use the plain-language source model'
    );
    basicrum_assert_same(
        '1',
        (string) $privacyFields->consent_integration_info->depends->opt_in_required,
        'guidance must depend on consent-controlled mode'
    );
};

$tests['runtime validation follows the backend contract'] = function () {
    basicrum_assert_true(
        BasicRum_Analytics_Helper_Data::isValidBrumSiteId('550e8400-e29b-41d4-a716-446655440000'),
        'UUID v4 must be accepted'
    );
    basicrum_assert_same(
        false,
        BasicRum_Analytics_Helper_Data::isValidBrumSiteId('550e8400-e29b-11d4-a716-446655440000'),
        'non-v4 UUID must be rejected'
    );
    basicrum_assert_true(
        BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint('https://collector.example.test/beacon?key=value'),
        'HTTPS Beacon URL must be accepted'
    );
    basicrum_assert_true(
        BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint('http://localhost:8080/beacon'),
        'HTTP Beacon URL must remain available for compatible development setups'
    );
    basicrum_assert_same(
        false,
        BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint('javascript:alert(1)'),
        'executable URL schemes must be rejected'
    );
    basicrum_assert_same(
        false,
        BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint('https:///missing-host'),
        'hostless URLs must be rejected'
    );
};

$tests['helper normalizes secure URLs and wait milliseconds'] = function () {
    list($helper) = basicrum_test_reset(array(
        'basicrum_analytics/general/beacon_endpoint' => 'http://collector.example.test/beacon',
        'basicrum_analytics/wait_after_onload/wait_ms' => '90000',
    ));
    Mage::$app->request->secure = true;

    basicrum_assert_same(
        'https://collector.example.test/beacon',
        $helper->getBeaconEndpoint(),
        'secure pages must upgrade the Beacon URL'
    );
    basicrum_assert_same(30000, $helper->getWaitAfterOnloadMilliseconds(), 'wait value must be capped');
};

$tests['backend models trim and validate configuration'] = function () {
    basicrum_test_reset();

    $siteId = new Basicrum_Test_SiteIdBackend();
    $siteId->setValue(' 550e8400-e29b-41d4-a716-446655440000 ')->validate();
    basicrum_assert_same(
        '550e8400-e29b-41d4-a716-446655440000',
        $siteId->getValue(),
        'Site ID backend must trim a valid value'
    );

    basicrum_assert_throws(function () {
        $model = new Basicrum_Test_SiteIdBackend();
        $model->setValue('550e8400-e29b-11d4-a716-446655440000')->validate();
    }, Mage_Core_Exception::class, 'Site ID backend must reject non-v4 UUIDs');

    $beacon = new Basicrum_Test_BeaconBackend();
    $beacon->setValue(' https://collector.example.test/beacon ')->validate();
    basicrum_assert_same(
        'https://collector.example.test/beacon',
        $beacon->getValue(),
        'Beacon backend must trim a valid URL'
    );

    basicrum_assert_throws(function () {
        $model = new Basicrum_Test_BeaconBackend();
        $model->setValue('data:text/javascript,alert(1)')->validate();
    }, Mage_Core_Exception::class, 'Beacon backend must reject non-HTTP schemes');
};

$tests['disabled and incomplete configurations render nothing'] = function () {
    $block = new BasicRum_Analytics_Block_Boomerang_Loader();

    basicrum_test_reset(array('basicrum_analytics/general/enabled' => '0'));
    basicrum_assert_same('', $block->getBoomerangSnippet(), 'disabled configuration must emit no scripts');

    basicrum_test_reset(array('basicrum_analytics/general/beacon_endpoint' => ''));
    basicrum_assert_same('', $block->getBoomerangSnippet(), 'missing Beacon URL must emit no scripts');

    basicrum_test_reset(array('basicrum_analytics/general/beacon_endpoint' => 'javascript:alert(1)'));
    basicrum_assert_same('', $block->getBoomerangSnippet(), 'invalid Beacon URL must emit no scripts');

    basicrum_test_reset(array('basicrum_analytics/general/brum_site_id' => ''));
    basicrum_assert_same('', $block->getBoomerangSnippet(), 'missing Site ID must emit no scripts');

    basicrum_test_reset(array(
        'basicrum_analytics/general/brum_site_id' => '550e8400-e29b-11d4-a716-446655440000',
    ));
    basicrum_assert_same('', $block->getBoomerangSnippet(), 'invalid Site ID must emit no scripts');
};

$tests['valid immediate and consent configurations select the expected loader'] = function () {
    $block = new BasicRum_Analytics_Block_Boomerang_Loader();

    basicrum_test_reset();
    $immediate = $block->getBoomerangSnippet();
    basicrum_assert_contains('boomerang-loader-v15.min.js', $immediate, 'immediate mode loader is required');
    basicrum_assert_not_contains('consent-boomerang-loader', $immediate, 'immediate mode must not use consent loader');
    basicrum_assert_contains('brum_site_id', $immediate, 'Site ID must be rendered');
    basicrum_assert_contains('beacon_url', $immediate, 'Beacon URL must be rendered');

    basicrum_test_reset(array(
        'basicrum_analytics/privacy/opt_in_required' => '1',
        'basicrum_analytics/developer/use_unminified_loaders' => '1',
    ));
    $consent = $block->getBoomerangSnippet();
    basicrum_assert_contains(
        'consent-boomerang-loader-v1-15.js',
        $consent,
        'consent-controlled mode must use the wrapper'
    );
};

$tests['wait-after-onload rendering is capped and cancellable on consent withdrawal'] = function () {
    basicrum_test_reset(array(
        'basicrum_analytics/privacy/opt_in_required' => '1',
        'basicrum_analytics/wait_after_onload/enabled' => '1',
        'basicrum_analytics/wait_after_onload/wait_ms' => '90000',
    ));

    $html = (new BasicRum_Analytics_Block_Boomerang_Loader())->getBoomerangSnippet();
    basicrum_assert_contains('timer: null', $html, 'wait plugin must expose its pending timer for opt-out');
    basicrum_assert_contains('this.timer = setTimeout', $html, 'wait plugin must retain its pending timer');
    basicrum_assert_contains(
        'if (w.basicRumConsentWithdrawn)',
        $html,
        'wait callback must remain inert after consent withdrawal'
    );
    basicrum_assert_contains('}.bind(this), 30000);', $html, 'rendered wait value must be capped at 30 seconds');
    basicrum_assert_not_contains('}.bind(this), 90000);', $html, 'uncapped wait value must not be rendered');
};

$tests['rendered values are JSON serialized for script safety'] = function () {
    list($helper, $pageType) = basicrum_test_reset(array(
        'basicrum_analytics/general/beacon_endpoint' => 'https://collector.example.test/beacon?first=1&second=2',
    ));
    $pageType->pageType = '</script><script>alert("x")</script>';

    $html = (new BasicRum_Analytics_Block_Boomerang_Loader())->getBoomerangSnippet();
    basicrum_assert_not_contains('</script><script>alert', $html, 'dynamic values must not terminate the script');
    basicrum_assert_contains('\\u003C\\/script\\u003E', $html, 'HTML-significant characters must be hex escaped');
    basicrum_assert_contains('\\u0026', $html, 'ampersands in Beacon URLs must be hex escaped');
};

$tests['privacy default migration distinguishes new installs and upgrades'] = function () {
    basicrum_assert_same(
        '1',
        BasicRum_Analytics_Model_Setup_PrivacyDefault::getValueToPersist(false, false),
        'new installs must start consent-controlled'
    );
    basicrum_assert_same(
        '0',
        BasicRum_Analytics_Model_Setup_PrivacyDefault::getValueToPersist(false, true),
        'upgrades without an explicit value must preserve immediate mode'
    );
    basicrum_assert_same(
        null,
        BasicRum_Analytics_Model_Setup_PrivacyDefault::getValueToPersist(true, true),
        'an explicit default-scope decision must never be overwritten'
    );
};

$tests['privacy default installer persists the versioned decision through Magento APIs'] = function () use ($root) {
    $installerPath = $root
        . '/app/code/community/BasicRum/Analytics/sql/basicrum_analytics_setup/install-1.1.0.php';
    $consentPath = 'basicrum_analytics/privacy/opt_in_required';

    $cases = array(
        'new installation' => array(
            'queryResults' => array(false, false),
            'expectedSaves' => array(array($consentPath, '1', 'default', 0)),
        ),
        'existing installation without an explicit decision' => array(
            'queryResults' => array(false, 'basicrum_analytics/general/enabled'),
            'expectedSaves' => array(array($consentPath, '0', 'default', 0)),
        ),
        'installation with an explicit default-scope decision' => array(
            'queryResults' => array(
                $consentPath,
                'basicrum_analytics/general/enabled',
            ),
            'expectedSaves' => array(),
        ),
    );

    foreach ($cases as $caseName => $case) {
        basicrum_test_reset();
        $setup = new Basicrum_Test_Setup($case['queryResults']);
        $setup->runInstaller($installerPath);

        basicrum_assert_same(1, $setup->started, $caseName . ': setup must start exactly once');
        basicrum_assert_same(1, $setup->ended, $caseName . ': setup must end exactly once');
        basicrum_assert_same(
            array('core/config_data'),
            $setup->requestedTables,
            $caseName . ': installer must resolve the scoped configuration table through Magento'
        );
        basicrum_assert_same(
            $case['expectedSaves'],
            Mage::$configObject->saved,
            $caseName . ': installer persisted the wrong privacy default'
        );
        basicrum_assert_same(2, count($setup->connection->selects), $caseName . ': expected two detection queries');
        basicrum_assert_same('path', $setup->connection->selects[0]->columns, $caseName . ': query must avoid legacy expression classes');
        basicrum_assert_same(1, $setup->connection->selects[0]->limit, $caseName . ': explicit query must stop after one row');
        basicrum_assert_same(1, $setup->connection->selects[1]->limit, $caseName . ': footprint query must stop after one row');
        basicrum_assert_same(
            array(
                array('path = ?', $consentPath),
                array('scope = ?', 'default'),
                array('scope_id = ?', 0),
            ),
            $setup->connection->selects[0]->where,
            $caseName . ': explicit default query must remain scope-aware'
        );
        basicrum_assert_same(
            array(array('path LIKE ?', 'basicrum_analytics/%')),
            $setup->connection->selects[1]->where,
            $caseName . ': upgrade detection must use the module configuration namespace'
        );
    }
};

$failures = array();
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS: {$name}\n";
    } catch (Throwable $exception) {
        $failures[] = $name . ': ' . $exception->getMessage();
        echo "FAIL: {$name}\n";
    }
}

if ($failures) {
    fwrite(STDERR, "\n" . implode("\n\n", $failures) . "\n");
    exit(1);
}

echo "\nAll PHP configuration/rendering tests passed.\n";
