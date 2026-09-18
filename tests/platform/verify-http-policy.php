<?php
declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php verify-http-policy.php <platform-root> <platform>\n");
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$policyPath = 'basicrum_analytics/developer/development_mode';
$endpointPath = 'basicrum_analytics/general/beacon_endpoint';
$endpoint = 'http://collector.example.test/beacon?site=scope';
$originalStoreCode = Mage::app()->getStore()->getCode();
$store = Mage::app()->getStore('default');
$website = $store->getWebsite();
$scopes = array(
    'default' => array('id' => 0, 'website' => '', 'store' => ''),
    'websites' => array('id' => (int) $website->getId(), 'website' => $website->getCode(), 'store' => ''),
    'stores' => array('id' => (int) $store->getId(), 'website' => $website->getCode(), 'store' => $store->getCode()),
);

$resource = Mage::getSingleton('core/resource');
$connection = $resource->getConnection('core_write');
$configTable = $resource->getTableName('core/config_data');
$originalRows = array();
foreach ($scopes as $scope => $scopeData) {
    $select = $connection->select()
        ->from($configTable)
        ->where('path IN (?)', array($policyPath, $endpointPath))
        ->where('scope = ?', $scope)
        ->where('scope_id = ?', $scopeData['id']);
    $originalRows = array_merge($originalRows, $connection->fetchAll($select));
}

// Null fixture values mean no override at that scope. Run each direction so
// neither the admin store's policy nor a stale child override can pass by chance.
$cases = array();
foreach (array('0', '1') as $policy) {
    $opposite = $policy === '1' ? '0' : '1';
    $cases[] = array(
        'name' => "website omitted field uses its own policy {$policy}",
        'scope' => 'websites',
        'policies' => array('default' => $opposite, 'websites' => $policy, 'stores' => $opposite),
        'field' => null,
        'expected' => $policy,
    );
    $cases[] = array(
        'name' => "store omitted field inherits website policy {$policy}",
        'scope' => 'stores',
        'policies' => array('default' => $opposite, 'websites' => $policy, 'stores' => null),
        'field' => null,
        'expected' => $policy,
    );
    $cases[] = array(
        'name' => "store omitted field uses its own policy {$policy}",
        'scope' => 'stores',
        'policies' => array('default' => $opposite, 'websites' => $opposite, 'stores' => $policy),
        'field' => null,
        'expected' => $policy,
    );
    $cases[] = array(
        'name' => "store inherit replaces stale override with website policy {$policy}",
        'scope' => 'stores',
        'policies' => array('default' => $opposite, 'websites' => $policy, 'stores' => $opposite),
        'field' => array('inherit' => '1', 'value' => $opposite),
        'expected' => $policy,
    );
    $cases[] = array(
        'name' => "store inherit resolves website's inherited default policy {$policy}",
        'scope' => 'stores',
        'policies' => array('default' => $policy, 'websites' => null, 'stores' => $opposite),
        'field' => array('inherit' => '1'),
        'expected' => $policy,
    );
    $cases[] = array(
        'name' => "website inherit replaces stale override with default policy {$policy}",
        'scope' => 'websites',
        'policies' => array('default' => $policy, 'websites' => $opposite, 'stores' => $opposite),
        'field' => array('inherit' => '1', 'value' => $opposite),
        'expected' => $policy,
    );
    foreach (array_keys($scopes) as $scope) {
        $cases[] = array(
            'name' => "{$scope} explicit same-form policy {$policy} overrides saved policy",
            'scope' => $scope,
            'policies' => array('default' => $opposite, 'websites' => $opposite, 'stores' => $opposite),
            'field' => array('inherit' => '0', 'value' => $policy),
            'expected' => $policy,
        );
    }
}

try {
    foreach ($cases as $case) {
        foreach ($scopes as $scope => $scopeData) {
            $connection->delete($configTable, array(
                'path IN (?)' => array($policyPath, $endpointPath),
                'scope = ?' => $scope,
                'scope_id = ?' => $scopeData['id'],
            ));
            if ($case['policies'][$scope] !== null) {
                Mage::getConfig()->saveConfig($policyPath, $case['policies'][$scope], $scope, $scopeData['id']);
            }
        }
        Mage::app()->getCacheInstance()->cleanType('config');
        basicrum_platform_reboot('admin');
        basicrum_platform_assert_same(
            0,
            (int) Mage::app()->getStore()->getId(),
            $case['name'] . ': the active store must be admin while editing another scope'
        );

        $groups = array(
            'general' => array('fields' => array('beacon_endpoint' => array('value' => ' ' . $endpoint . ' '))),
        );
        if ($case['field'] !== null) {
            $groups['developer'] = array('fields' => array('development_mode' => $case['field']));
        }

        $scopeData = $scopes[$case['scope']];
        // Exercise native backend dispatch, _beforeSave(), transactions, and
        // inheritance deletion. saveConfig() above only prepares the fixtures.
        Mage::getModel('adminhtml/config_data')
            ->setSection('basicrum_analytics')
            ->setWebsite($scopeData['website'])
            ->setStore($scopeData['store'])
            ->setGroups($groups)
            ->save();

        $select = $connection->select()
            ->from($configTable, 'value')
            ->where('path = ?', $endpointPath)
            ->where('scope = ?', $case['scope'])
            ->where('scope_id = ?', $scopeData['id']);
        $expectedEndpoint = $case['expected'] === '1' ? $endpoint : 'https://' . substr($endpoint, 7);
        basicrum_platform_assert_same(
            $expectedEndpoint,
            $connection->fetchOne($select),
            $case['name'] . ': backend saved the wrong Beacon URL'
        );

        $select = $connection->select()
            ->from($configTable, 'value')
            ->where('path = ?', $policyPath)
            ->where('scope = ?', $case['scope'])
            ->where('scope_id = ?', $scopeData['id']);
        $expectedStoredPolicy = $case['policies'][$case['scope']];
        if ($case['field'] !== null) {
            $expectedStoredPolicy = !empty($case['field']['inherit']) ? null : $case['field']['value'];
        }
        basicrum_platform_assert_same(
            $expectedStoredPolicy === null ? false : $expectedStoredPolicy,
            $connection->fetchOne($select),
            $case['name'] . ': native save did not preserve the requested policy/inheritance state'
        );
    }
} finally {
    // Preserve the preceding rendering fixture, including absent overrides, so
    // live storefront and upgrade checks can run after this script unchanged.
    foreach ($scopes as $scope => $scopeData) {
        $connection->delete($configTable, array(
            'path IN (?)' => array($policyPath, $endpointPath),
            'scope = ?' => $scope,
            'scope_id = ?' => $scopeData['id'],
        ));
    }
    foreach ($originalRows as $row) {
        $connection->insert($configTable, $row);
    }
    Mage::app()->getCacheInstance()->cleanType('config');
    basicrum_platform_reboot($originalStoreCode);
}

echo 'Native ' . $platform . ' HTTP policy backend checks passed (' . count($cases) . " cases).\n";
