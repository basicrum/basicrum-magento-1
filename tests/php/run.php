<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require __DIR__ . '/bootstrap.php';
require $root . '/app/code/community/BasicRum/Analytics/Helper/Data.php';
require $root . '/app/code/community/BasicRum/Analytics/Block/Boomerang/Loader.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Backend/SiteId.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Backend/BeaconEndpoint.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/Setup/HttpPolicyDefault.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/Setup/PrivacyDefault.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Source/ConsentMode.php';
require $root . '/app/code/community/BasicRum/Analytics/Model/System/Config/Source/HttpPolicy.php';
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

function basicrum_config_dependency_map(SimpleXMLElement $field, $defaultFieldset)
{
    $dependencies = array();

    if (!isset($field->depends)) {
        return $dependencies;
    }

    foreach ($field->depends->children() as $dependency) {
        $fieldset = isset($dependency->fieldset) ? (string) $dependency->fieldset : $defaultFieldset;
        $value = isset($dependency->value) ? (string) $dependency->value : (string) $dependency;
        $dependencies[$fieldset . '/' . $dependency->getName()] = $value;
    }

    return $dependencies;
}

$tests['general privacy controls preserve paths scopes and installation defaults'] = function () use ($root) {
    $xml = simplexml_load_file($root . '/app/code/community/BasicRum/Analytics/etc/system.xml');
    $groups = $xml->sections->basicrum_analytics->groups;
    basicrum_assert_true(!isset($groups->privacy), 'privacy must not have a separate admin accordion');
    foreach (array('strip_query_string', 'opt_in_required') as $name) {
        $field = $groups->general->fields->{$name};
        basicrum_assert_same(
            'basicrum_analytics/privacy/' . $name,
            (string) $field->config_path,
            'moving ' . $name . ' must preserve its stored configuration path'
        );
        foreach (array('default', 'website', 'store') as $scope) {
            basicrum_assert_same('1', (string) $field->{'show_in_' . $scope}, $name . ': missing scope ' . $scope);
        }
    }
    $defaults = simplexml_load_file($root . '/app/code/community/BasicRum/Analytics/etc/config.xml')
        ->default->basicrum_analytics;
    basicrum_assert_same('0', (string) $defaults->general->enabled, 'monitoring must be disabled by default');
    basicrum_assert_same('1', (string) $defaults->privacy->opt_in_required, 'new installs must require consent');
    basicrum_assert_same('0', (string) $defaults->privacy->strip_query_string, 'query-string default must not change');
};

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

$tests['admin HTTP policy choices explain production and development behavior'] = function () {
    basicrum_test_reset();

    $options = (new BasicRum_Analytics_Model_System_Config_Source_HttpPolicy())->toOptionArray();

    basicrum_assert_same('0', $options[0]['value'], 'HTTPS enforcement must retain its stored value');
    basicrum_assert_contains('Require HTTPS', $options[0]['label'], 'the safe policy must explain HTTPS enforcement');
    basicrum_assert_same('1', $options[1]['value'], 'development HTTP mode must retain its stored value');
    basicrum_assert_contains('local testing', $options[1]['label'], 'HTTP mode must be limited to local testing');
};

$tests['admin consent guidance is a full-width dependent row'] = function () use ($root) {
    $elementId = 'basicrum_analytics_general_consent_integration_info';
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
    basicrum_assert_contains(
        'overflow-wrap: anywhere',
        $html,
        'long callback names must wrap on narrow admin viewports'
    );
    basicrum_assert_contains(
        'max-width: 100%',
        $html,
        'callback snippets must stay within the available width'
    );
    basicrum_assert_not_contains(
        'white-space: nowrap',
        $html,
        'consent guidance must not force callback names beyond narrow viewports'
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
    $privacyFields = $xml->sections->basicrum_analytics->groups->general->fields;
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
    basicrum_assert_same(
        'adminhtml/system_config_source_yesno',
        (string) $privacyFields->strip_query_string->source_model,
        'query-string privacy must use a scoped Magento Yes/No control'
    );
    basicrum_assert_contains(
        '?qs-redacted',
        (string) $privacyFields->strip_query_string->comment,
        'query-string privacy guidance must name the redaction marker'
    );
    basicrum_assert_same(
        'basicrum_analytics/system_config_source_httpPolicy',
        (string) $xml->sections->basicrum_analytics->groups->developer->fields->development_mode->source_model,
        'HTTP policy must use plain-language choices'
    );
};

$tests['admin hides runtime controls while monitoring is disabled'] = function () use ($root) {
    $xml = simplexml_load_file($root . '/app/code/community/BasicRum/Analytics/etc/system.xml');
    basicrum_assert_true($xml !== false, 'system configuration XML must parse');

    $groups = $xml->sections->basicrum_analytics->groups;
    $generalFields = $groups->general->fields;
    $privacyFields = $generalFields;
    $waitFields = $groups->wait_after_onload->fields;
    $developerFields = $groups->developer->fields;

    basicrum_assert_same(
        'Basicrum',
        (string) $xml->tabs->basicrum_analytics->label,
        'admin tab must use canonical product casing'
    );
    basicrum_assert_same(
        'Basicrum Settings',
        (string) $xml->sections->basicrum_analytics->label,
        'configuration page must clearly identify Basicrum'
    );
    basicrum_assert_contains(
        'Basicrum — Real User Monitoring',
        (string) $groups->general->comment,
        'General Settings introduction must identify the product'
    );
    basicrum_assert_same('Enable Basicrum', (string) $generalFields->enabled->label, 'enable label must name Basicrum');
    basicrum_assert_same('Beacon URL', (string) $generalFields->beacon_endpoint->label, 'Beacon label must match WordPress');
    basicrum_assert_same('Brum Site ID', (string) $generalFields->brum_site_id->label, 'Site ID label must match WordPress');
    basicrum_assert_same(
        'Boomerang Version',
        (string) $generalFields->boomerang_version->label,
        'Boomerang label must match WordPress'
    );
    $adminAcl = simplexml_load_file($root . '/app/code/community/BasicRum/Analytics/etc/adminhtml.xml');
    basicrum_assert_same(
        'Basicrum Settings',
        (string) $adminAcl->acl->resources->admin->children->system->children->config
            ->children->basicrum_analytics->title,
        'ACL title must use the visible settings-page name'
    );

    basicrum_assert_same(
        array(),
        basicrum_config_dependency_map($generalFields->boomerang_version, 'general'),
        'Boomerang version must remain visible while monitoring is disabled'
    );
    basicrum_assert_same(
        array(),
        basicrum_config_dependency_map($generalFields->beacon_endpoint, 'general'),
        'Beacon URL must remain available for preconfiguration'
    );
    basicrum_assert_same(
        array(),
        basicrum_config_dependency_map($generalFields->brum_site_id, 'general'),
        'Site ID must remain available for preconfiguration'
    );

    $generalEnabledOnly = array('general/enabled' => '1');
    basicrum_assert_same(
        $generalEnabledOnly,
        basicrum_config_dependency_map($privacyFields->strip_query_string, 'general'),
        'query-string privacy must depend on monitoring being enabled'
    );
    basicrum_assert_same(
        $generalEnabledOnly,
        basicrum_config_dependency_map($privacyFields->opt_in_required, 'general'),
        'consent mode must depend on monitoring being enabled'
    );
    basicrum_assert_same(
        array(
            'general/enabled' => '1',
            'general/opt_in_required' => '1',
        ),
        basicrum_config_dependency_map($privacyFields->consent_integration_info, 'general'),
        'consent guidance must require both enabled monitoring and consent-controlled mode'
    );
    basicrum_assert_same(
        $generalEnabledOnly,
        basicrum_config_dependency_map($waitFields->wait_enabled, 'wait_after_onload'),
        'wait control must depend on monitoring being enabled'
    );
    basicrum_assert_same(
        'basicrum_analytics/wait_after_onload/enabled',
        (string) $waitFields->wait_enabled->config_path,
        'wait control must retain the established public configuration path'
    );
    basicrum_assert_same(
        array(
            'general/enabled' => '1',
            'wait_after_onload/wait_enabled' => '1',
        ),
        basicrum_config_dependency_map($waitFields->wait_ms, 'wait_after_onload'),
        'wait duration must require both enabled monitoring and enabled waiting'
    );
    basicrum_assert_same(
        $generalEnabledOnly,
        basicrum_config_dependency_map($developerFields->development_mode, 'developer'),
        'HTTP policy must depend on monitoring being enabled'
    );
    basicrum_assert_same(
        $generalEnabledOnly,
        basicrum_config_dependency_map($developerFields->use_unminified_loaders, 'developer'),
        'loader debugging must depend on monitoring being enabled'
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
        'Beacon URL is required while monitoring is enabled',
        $beaconHtml,
        'missing Beacon URL must have field-level guidance'
    );
    basicrum_assert_contains('aria-invalid="true"', $siteIdHtml, 'missing Site ID must be invalid');
    basicrum_assert_contains(
        'Brum Site ID is required while monitoring is enabled',
        $siteIdHtml,
        'missing Site ID must have field-level guidance'
    );
    basicrum_assert_contains(
        'Monitoring status: Blocked',
        $siteIdHtml,
        'incomplete enabled configuration must identify its blocked state'
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
        ->addElement('basicrum_analytics_general_brum_site_id', $validSiteId)
        ->addElement(
            'basicrum_analytics_general_opt_in_required',
            new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_opt_in_required', '0')
        );

    $validHtml = $renderer->render($validBeacon) . $renderer->render($validSiteId);
    basicrum_assert_contains('aria-invalid="false"', $validHtml, 'valid required fields must not be invalid');
    basicrum_assert_not_contains('validation-advice', $validHtml, 'valid fields must not display advice');
    basicrum_assert_not_contains(
        'Basicrum monitoring is enabled but inactive',
        $validHtml,
        'resolved valid scope values must suppress the inactive warning'
    );
    basicrum_assert_contains(
        'Monitoring status: Active',
        $validHtml,
        'valid immediate configuration must display active status'
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
    basicrum_assert_contains(
        'Monitoring status: Disabled',
        $disabledHtml,
        'disabled configuration must display disabled status'
    );

    $consentForm = new Basicrum_Test_Form();
    $consentEnabled = new Varien_Data_Form_Element_Abstract('basicrum_analytics_general_enabled', '1');
    $consentBeacon = new Varien_Data_Form_Element_Abstract(
        'basicrum_analytics_general_beacon_endpoint',
        'https://collector.example.test/beacon'
    );
    $consentSiteId = new Varien_Data_Form_Element_Abstract(
        'basicrum_analytics_general_brum_site_id',
        '550e8400-e29b-41d4-a716-446655440000'
    );
    $consentRequired = new Varien_Data_Form_Element_Abstract(
        'basicrum_analytics_general_opt_in_required',
        '1'
    );
    $consentForm->addElement('basicrum_analytics_general_enabled', $consentEnabled)
        ->addElement('basicrum_analytics_general_beacon_endpoint', $consentBeacon)
        ->addElement('basicrum_analytics_general_brum_site_id', $consentSiteId)
        ->addElement('basicrum_analytics_general_opt_in_required', $consentRequired);
    $consentHtml = $renderer->render($consentSiteId);
    basicrum_assert_contains(
        'Monitoring status: Waiting for consent',
        $consentHtml,
        'valid consent-controlled configuration must display waiting status'
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
        'Enter a valid HTTP or HTTPS Beacon URL',
        $invalidHtml,
        'invalid Beacon URL must have field-level guidance'
    );
    basicrum_assert_contains(
        'Enter a valid UUID v4 Brum Site ID',
        $invalidHtml,
        'invalid Site ID must have field-level guidance'
    );
    basicrum_assert_contains(
        'Monitoring status: Blocked',
        $invalidHtml,
        'invalid enabled configuration must display blocked status'
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

$tests['helper enforces the HTTP policy and normalizes wait milliseconds'] = function () {
    list($helper) = basicrum_test_reset(array(
        'basicrum_analytics/general/beacon_endpoint' => 'http://collector.example.test/beacon',
        'basicrum_analytics/wait_after_onload/wait_ms' => '90000',
    ));

    basicrum_assert_same(
        'https://collector.example.test/beacon',
        $helper->getBeaconEndpoint(),
        'strict mode must upgrade the Beacon URL even on an HTTP storefront'
    );
    basicrum_assert_same(30000, $helper->getWaitAfterOnloadMilliseconds(), 'wait value must be capped');
    basicrum_assert_same(false, $helper->shouldStripQueryString(), 'query stripping must remain disabled by default');

    list($privacyHelper) = basicrum_test_reset(array(
        'basicrum_analytics/privacy/strip_query_string' => '1',
    ));
    basicrum_assert_same(true, $privacyHelper->shouldStripQueryString(), 'query stripping must honor scoped config');

    list($developmentHelper) = basicrum_test_reset(array(
        'basicrum_analytics/general/beacon_endpoint' => 'http://127.0.0.1:8080/beacon?site=one',
        'basicrum_analytics/developer/development_mode' => '1',
    ));
    basicrum_assert_same(
        'http://127.0.0.1:8080/beacon?site=one',
        $developmentHelper->getBeaconEndpoint(),
        'development mode must retain an explicitly configured HTTP Beacon URL'
    );
};

$tests['HTTPS storefronts never emit an HTTP collector even when HTTP is allowed'] = function () {
    foreach (array('0', '1') as $allowHttp) {
        foreach (array(false, true) as $secure) {
            list($helper) = basicrum_test_reset(array(
                'basicrum_analytics/general/beacon_endpoint' => 'HTTP://collector.example.test/beacon?site=one',
                'basicrum_analytics/developer/development_mode' => $allowHttp,
            ));
            Mage::app()->getRequest()->secure = $secure;
            $expected = $allowHttp === '1' && !$secure
                ? 'HTTP://collector.example.test/beacon?site=one'
                : 'https://collector.example.test/beacon?site=one';

            basicrum_assert_same(
                $expected,
                $helper->getBeaconEndpoint(),
                'only an insecure storefront with HTTP explicitly allowed may use an HTTP collector'
            );
            $snippet = (new BasicRum_Analytics_Block_Boomerang_Loader())->getBoomerangSnippet();
            basicrum_assert_contains(
                json_encode($expected),
                $snippet,
                'the rendered loader configuration must use the resolved transport policy'
            );
        }
    }
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

    $strictBeacon = new Basicrum_Test_BeaconBackend();
    $strictBeacon->setValue('http://collector.example.test/beacon?site=one')->validate();
    basicrum_assert_same(
        'https://collector.example.test/beacon?site=one',
        $strictBeacon->getValue(),
        'Beacon backend must upgrade HTTP while strict mode is selected'
    );

    $developmentBeacon = new Basicrum_Test_BeaconBackend();
    $developmentBeacon->setGroups(array(
        'developer' => array(
            'fields' => array(
                'development_mode' => array('value' => '1'),
            ),
        ),
    ));
    $developmentBeacon->setValue('http://127.0.0.1:8080/beacon')->validate();
    basicrum_assert_same(
        'http://127.0.0.1:8080/beacon',
        $developmentBeacon->getValue(),
        'Beacon backend must honor development mode submitted on the same form'
    );

    basicrum_assert_throws(function () {
        $model = new Basicrum_Test_BeaconBackend();
        $model->setValue('data:text/javascript,alert(1)')->validate();
    }, Mage_Core_Exception::class, 'Beacon backend must reject non-HTTP schemes');
};

$tests['Beacon backend resolves omitted and inherited HTTP policy at the edited scope'] = function () {
    $path = 'basicrum_analytics/developer/development_mode';

    foreach (array('0', '1') as $allowHttp) {
        $opposite = $allowHttp === '1' ? '0' : '1';
        // Default, website, and store policy values; edited scope; submitted field.
        $cases = array(
            'default omitted' => array($allowHttp, null, null, 'default', null),
            'website omitted' => array($opposite, $allowHttp, null, 'website', null),
            'store omitted with own value' => array($opposite, $opposite, $allowHttp, 'store', null),
            'store omitted with inherited value' => array($opposite, $allowHttp, null, 'store', null),
            'website now inherits default' => array(
                $allowHttp, $opposite, null, 'website', array('inherit' => '1', 'value' => $opposite),
            ),
            'store now inherits website' => array(
                $opposite, $allowHttp, $opposite, 'store', array('inherit' => '1', 'value' => $opposite),
            ),
            'store now inherits through website' => array(
                $allowHttp, null, $opposite, 'store', array('inherit' => '1', 'value' => $opposite),
            ),
        );
        foreach (array('default', 'website', 'store') as $scope) {
            $cases[$scope . ' explicit submission'] = array(
                $opposite, $opposite, $opposite, $scope, array('value' => $allowHttp),
            );
        }

        foreach ($cases as $name => $case) {
            list($defaultPolicy, $websitePolicy, $storePolicy, $scope, $field) = $case;
            basicrum_test_reset(array($path => $defaultPolicy));
            $website = new Basicrum_Test_Website();
            $store = new Basicrum_Test_Store($website);
            if ($websitePolicy !== null) {
                $website->config[$path] = $websitePolicy;
            }
            if ($storePolicy !== null) {
                $store->config[$path] = $storePolicy;
            }
            Mage::app()->websites['selected_website'] = $website;
            Mage::app()->stores['selected_store'] = $store;

            $beacon = new Basicrum_Test_BeaconBackend();
            if ($scope !== 'default') {
                $beacon->setWebsiteCode('selected_website');
            }
            if ($scope === 'store') {
                $beacon->setStoreCode('selected_store');
            }
            if ($field !== null) {
                $beacon->setGroups(array('developer' => array('fields' => array('development_mode' => $field))));
            }
            $beacon->setValue('http://collector.example.test/beacon?site=one')->validate();
            $scheme = $allowHttp === '1' ? 'http' : 'https';
            basicrum_assert_same(
                $scheme . '://collector.example.test/beacon?site=one',
                $beacon->getValue(),
                $name . ' must resolve HTTP policy ' . $allowHttp . ' without using the admin store policy'
            );
        }
    }
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
    basicrum_assert_contains(
        '"strip_query_string":false',
        $immediate,
        'query strings must remain unchanged by default for compatibility'
    );

    basicrum_test_reset(array(
        'basicrum_analytics/privacy/strip_query_string' => '1',
    ));
    $redacted = $block->getBoomerangSnippet();
    basicrum_assert_contains(
        '"strip_query_string":true',
        $redacted,
        'enabled query-string privacy must reach Boomerang as a boolean'
    );

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

$tests['HTTP policy migration preserves each explicit Beacon URL scope'] = function () {
    $policies = BasicRum_Analytics_Model_Setup_HttpPolicyDefault::getValuesToPersist(
        array(
            array('scope' => 'default', 'scope_id' => '0', 'value' => 'http://collector.example.test/beacon'),
            array('scope' => 'websites', 'scope_id' => '2', 'value' => 'https://collector.example.test/beacon'),
            array('scope' => 'stores', 'scope_id' => '3', 'value' => ' HTTP://127.0.0.1:8080/beacon '),
            array('scope' => 'stores', 'scope_id' => '3', 'value' => 'http://duplicate.example.test'),
            array('scope' => 'invalid', 'scope_id' => '4', 'value' => 'http://ignored.example.test'),
            array('scope' => 'default', 'scope_id' => '9', 'value' => 'http://ignored.example.test'),
        ),
        array(
            array('scope' => 'default', 'scope_id' => '0'),
        )
    );

    basicrum_assert_same(
        array(
            array('scope' => 'websites', 'scope_id' => 2, 'value' => '0'),
            array('scope' => 'stores', 'scope_id' => 3, 'value' => '1'),
        ),
        $policies,
        'migration must preserve scoped HTTP and HTTPS behavior without overwriting explicit policy values'
    );
};

$tests['setup installer persists versioned defaults through Magento APIs'] = function () use ($root) {
    $installerPath = $root
        . '/app/code/community/BasicRum/Analytics/sql/basicrum_analytics_setup/install-1.1.0.php';
    $consentPath = 'basicrum_analytics/privacy/opt_in_required';
    $beaconPath = 'basicrum_analytics/general/beacon_endpoint';
    $httpPolicyPath = 'basicrum_analytics/developer/development_mode';

    $cases = array(
        'new installation' => array(
            'queryResults' => array(false, false),
            'fetchAllResults' => array(array(), array()),
            'expectedSaves' => array(array($consentPath, '1', 'default', 0)),
        ),
        'existing installation without an explicit decision' => array(
            'queryResults' => array(false, 'basicrum_analytics/general/enabled'),
            'fetchAllResults' => array(array(), array()),
            'expectedSaves' => array(array($consentPath, '0', 'default', 0)),
        ),
        'installation with an explicit default-scope decision' => array(
            'queryResults' => array(
                $consentPath,
                'basicrum_analytics/general/enabled',
            ),
            'fetchAllResults' => array(array(), array()),
            'expectedSaves' => array(),
        ),
        'existing scoped HTTP endpoints' => array(
            'queryResults' => array(false, $beaconPath),
            'fetchAllResults' => array(
                array(
                    array('scope' => 'default', 'scope_id' => '0', 'value' => 'http://collector.example.test'),
                    array('scope' => 'websites', 'scope_id' => '2', 'value' => 'https://collector.example.test'),
                    array('scope' => 'stores', 'scope_id' => '3', 'value' => 'http://127.0.0.1:8080/beacon'),
                ),
                array(
                    array('scope' => 'stores', 'scope_id' => '3'),
                ),
            ),
            'expectedSaves' => array(
                array($consentPath, '0', 'default', 0),
                array($httpPolicyPath, '1', 'default', 0),
                array($httpPolicyPath, '0', 'websites', 2),
            ),
        ),
    );

    foreach ($cases as $caseName => $case) {
        basicrum_test_reset();
        $setup = new Basicrum_Test_Setup($case['queryResults'], $case['fetchAllResults']);
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
        basicrum_assert_same(4, count($setup->connection->selects), $caseName . ': expected four detection queries');
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
        basicrum_assert_same(
            array(array('path = ?', $beaconPath)),
            $setup->connection->selects[2]->where,
            $caseName . ': HTTP preservation must inspect only Beacon URL rows'
        );
        basicrum_assert_same(
            array(array('path = ?', $httpPolicyPath)),
            $setup->connection->selects[3]->where,
            $caseName . ': HTTP preservation must respect explicit policy rows'
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
