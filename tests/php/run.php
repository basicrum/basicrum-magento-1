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
require $root . '/app/code/community/BasicRum/Analytics/Block/Adminhtml/System/Config/Form/Field/RequiredSetting.php';

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
    basicrum_assert_contains(
        'Allow or grant callback',
        $html,
        'guidance must identify the allow integration point'
    );
    basicrum_assert_contains(
        'Deny, expiry, or withdrawal callback',
        $html,
        'guidance must identify every opt-out integration point'
    );
    basicrum_assert_contains(
        'Do not run the two snippets together',
        $html,
        'guidance must prevent the separated callbacks from being pasted as one sequence'
    );
    basicrum_assert_same(
        2,
        substr_count($html, 'class="scalable basicrum-copy-consent-snippet"'),
        'each focused callback example must have a copy action'
    );
    basicrum_assert_same(
        2,
        substr_count($html, 'readonly="readonly"'),
        'callback examples must be rendered in read-only fields'
    );
    basicrum_assert_contains(
        'https://shop.example.test/js/basicrum/admin/consent-info.js',
        $html,
        'guidance must load the copy-action behavior from the Magento JS base URL'
    );

    $allowStart = strpos($html, '<textarea id="' . $elementId . '_allow_snippet"');
    $denyStart = strpos($html, '<textarea id="' . $elementId . '_deny_snippet"');
    basicrum_assert_true($allowStart !== false && $denyStart !== false, 'both callback snippets must render');
    $allowSnippet = substr($html, $allowStart, strpos($html, '</textarea>', $allowStart) - $allowStart);
    $denySnippet = substr($html, $denyStart, strpos($html, '</textarea>', $denyStart) - $denyStart);
    basicrum_assert_contains(
        'OPT_IN_BASICRUM_LOADER_WRAPPER',
        $allowSnippet,
        'allow example must contain only the opt-in integration'
    );
    basicrum_assert_not_contains(
        'OPT_OUT_BASICRUM_LOADER_WRAPPER',
        $allowSnippet,
        'allow example must not immediately opt out'
    );
    basicrum_assert_contains(
        'OPT_OUT_BASICRUM_LOADER_WRAPPER',
        $denySnippet,
        'deny example must contain the opt-out integration'
    );
    basicrum_assert_not_contains(
        'OPT_IN_BASICRUM_LOADER_WRAPPER',
        $denySnippet,
        'deny example must not opt in'
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

$tests['enabled incomplete configuration is visibly inactive'] = function () use ($root) {
    basicrum_test_reset();

    $form = new Basicrum_Test_Form();
    $enabled = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_enabled', '1');
    $beacon = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_beacon_endpoint', '');
    $siteId = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_brum_site_id', '');
    $form->addElement('basicrum_analytics_general_enabled', $enabled)
        ->addElement('basicrum_analytics_general_beacon_endpoint', $beacon)
        ->addElement('basicrum_analytics_general_brum_site_id', $siteId);

    $renderer = new BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_RequiredSetting();
    $beaconHtml = $renderer->render($beacon);
    $siteIdHtml = $renderer->render($siteId);

    basicrum_assert_contains('aria-required="true"', $beaconHtml, 'enabled Beacon URL must be required');
    basicrum_assert_contains('aria-invalid="true"', $beaconHtml, 'missing Beacon URL must be invalid');
    basicrum_assert_contains('validation-failed', $beaconHtml, 'missing Beacon URL must be highlighted');
    basicrum_assert_contains(
        'Beacon Endpoint URL is required while monitoring is enabled',
        $beaconHtml,
        'missing Beacon URL must have field-level guidance'
    );
    basicrum_assert_contains('aria-invalid="true"', $siteIdHtml, 'missing Site ID must be invalid');
    basicrum_assert_contains(
        'BasicRUM Site ID is required while monitoring is enabled',
        $siteIdHtml,
        'missing Site ID must have field-level guidance'
    );
    basicrum_assert_contains(
        'Basicrum monitoring is enabled but inactive',
        $siteIdHtml,
        'incomplete enabled configuration must display a page-level warning'
    );
    basicrum_assert_contains(
        'Monitoring scripts are not emitted',
        $siteIdHtml,
        'the warning must explain the runtime consequence'
    );

    $xml = simplexml_load_file($root . '/app/code/community/BasicRum/Analytics/etc/system.xml');
    $generalFields = $xml->sections->basicrum_analytics->groups->general->fields;
    basicrum_assert_same(
        'basicrum_analytics/adminhtml_system_config_form_field_requiredSetting',
        (string) $generalFields->beacon_endpoint->frontend_model,
        'Beacon URL must use the required-setting renderer'
    );
    basicrum_assert_same(
        'basicrum_analytics/adminhtml_system_config_form_field_requiredSetting',
        (string) $generalFields->brum_site_id->frontend_model,
        'Site ID must use the required-setting renderer'
    );
};

$tests['admin required-setting feedback respects validity enabled state and resolved scope values'] = function () {
    basicrum_test_reset(array(
        'basicrum_analytics/general/beacon_endpoint' => 'javascript:alert(1)',
        'basicrum_analytics/general/brum_site_id' => 'invalid',
    ));

    $renderer = new BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_RequiredSetting();

    $validForm = new Basicrum_Test_Form();
    $validEnabled = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_enabled', '1');
    $validBeacon = new Varien_Data_Form_Element_Abstract(
        'basicrum_analytics_general_beacon_endpoint',
        'https://collector.example.test/beacon'
    );
    $validSiteId = new Varien_Data_Form_Element_Abstract(
        'basicrum_analytics_general_brum_site_id',
        '550e8400-e29b-41d4-a716-446655440000'
    );
    $validForm->addElement('basicrum_analytics_general_enabled', $validEnabled)
        ->addElement('basicrum_analytics_general_beacon_endpoint', $validBeacon)
        ->addElement('basicrum_analytics_general_brum_site_id', $validSiteId);

    $validHtml = $renderer->render($validBeacon) . $renderer->render($validSiteId);
    basicrum_assert_contains('aria-invalid="false"', $validHtml, 'valid required fields must not be invalid');
    basicrum_assert_not_contains('validation-advice', $validHtml, 'valid fields must not display advice');
    basicrum_assert_not_contains(
        'Basicrum monitoring is enabled but inactive',
        $validHtml,
        'resolved valid scope values must suppress the inactive warning'
    );

    $disabledForm = new Basicrum_Test_Form();
    $disabledEnabled = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_enabled', '0');
    $disabledBeacon = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_beacon_endpoint', '');
    $disabledSiteId = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_brum_site_id', '');
    $disabledForm->addElement('basicrum_analytics_general_enabled', $disabledEnabled)
        ->addElement('basicrum_analytics_general_beacon_endpoint', $disabledBeacon)
        ->addElement('basicrum_analytics_general_brum_site_id', $disabledSiteId);

    $disabledHtml = $renderer->render($disabledBeacon) . $renderer->render($disabledSiteId);
    basicrum_assert_contains('aria-required="false"', $disabledHtml, 'disabled monitoring must make fields optional');
    basicrum_assert_contains('aria-invalid="false"', $disabledHtml, 'disabled empty fields must not be invalid');
    basicrum_assert_not_contains('validation-advice', $disabledHtml, 'disabled empty fields must not display advice');
    basicrum_assert_not_contains(
        'Basicrum monitoring is enabled but inactive',
        $disabledHtml,
        'disabled monitoring must not display an inactive warning'
    );

    $invalidForm = new Basicrum_Test_Form();
    $invalidEnabled = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_enabled', '1');
    $invalidBeacon = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_beacon_endpoint', 'ftp://example.test');
    $invalidSiteId = new Varien_Data_Form_Element_Abstract(
        'basicrum_analytics_general_brum_site_id',
        '550e8400-e29b-11d4-a716-446655440000'
    );
    $invalidForm->addElement('basicrum_analytics_general_enabled', $invalidEnabled)
        ->addElement('basicrum_analytics_general_beacon_endpoint', $invalidBeacon)
        ->addElement('basicrum_analytics_general_brum_site_id', $invalidSiteId);

    $invalidHtml = $renderer->render($invalidBeacon) . $renderer->render($invalidSiteId);
    basicrum_assert_contains(
        'Enter a valid HTTP or HTTPS Beacon Endpoint URL',
        $invalidHtml,
        'invalid Beacon URL must have field-level guidance'
    );
    basicrum_assert_contains(
        'Enter a valid UUID v4 BasicRUM Site ID',
        $invalidHtml,
        'invalid Site ID must have field-level guidance'
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
