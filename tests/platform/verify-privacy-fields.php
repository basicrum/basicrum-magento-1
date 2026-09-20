<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$originalStoreCode = Mage::app()->getStore()->getCode();
$store = Mage::app()->getStore('default');
$website = $store->getWebsite();
$scopes = array(
    'default' => array('id' => 0, 'website' => '', 'store' => ''),
    'websites' => array('id' => (int) $website->getId(), 'website' => $website->getCode(), 'store' => ''),
    'stores' => array('id' => (int) $store->getId(), 'website' => $website->getCode(), 'store' => $store->getCode()),
);
$fields = array('opt_in_required', 'strip_query_string');
$paths = array();
foreach ($fields as $field) {
    $paths[] = 'basicrum_analytics/privacy/' . $field;
    $paths[] = 'basicrum_analytics/general/' . $field;
}
$resource = Mage::getSingleton('core/resource');
$connection = $resource->getConnection('core_write');
$configTable = $resource->getTableName('core/config_data');
$originalRows = $connection->fetchAll(
    $connection->select()->from($configTable)->where('path IN (?)', $paths)
);

// Exercise explicit values, overwriting old rows, and removing child overrides.
// The two controls deliberately differ so swapped paths cannot pass unnoticed.
$cases = array(
    array('scope' => 'default', 'consent' => '1', 'strip' => '0', 'inherit' => false),
    array('scope' => 'default', 'consent' => '0', 'strip' => '1', 'inherit' => false),
    array('scope' => 'websites', 'consent' => '1', 'strip' => '0', 'inherit' => false),
    array('scope' => 'stores', 'consent' => '0', 'strip' => '1', 'inherit' => false),
    array('scope' => 'stores', 'consent' => '1', 'strip' => '0', 'inherit' => true),
    array('scope' => 'websites', 'consent' => '0', 'strip' => '1', 'inherit' => true),
);

try {
    foreach ($scopes as $scope => $scopeData) {
        $connection->delete($configTable, array(
            'path IN (?)' => $paths, 'scope = ?' => $scope, 'scope_id = ?' => $scopeData['id'],
        ));
    }
    foreach ($cases as $case) {
        $scopeData = $scopes[$case['scope']];
        $values = array('opt_in_required' => $case['consent'], 'strip_query_string' => $case['strip']);
        Mage::app()->getCacheInstance()->cleanType('config');
        basicrum_platform_reboot('admin');
        // Use the installation's test administrator and real ACL: config_path
        // saves must pass the platform's native section permission check.
        $user = Mage::getModel('admin/user')->loadByUsername('basicrum_admin');
        basicrum_platform_assert((bool) $user->getId(), 'test administrator is missing');
        Mage::getSingleton('admin/session')->setUser($user)
            ->setAcl(Mage::getResourceModel('admin/acl')->loadAcl());

        $postedFields = array();
        foreach ($values as $field => $value) {
            $postedFields[$field] = $case['inherit'] ? array('inherit' => '1') : array('value' => $value);
        }
        Mage::getModel('adminhtml/config_data')
            ->setSection('basicrum_analytics')->setWebsite($scopeData['website'])->setStore($scopeData['store'])
            ->setGroups(array('general' => array('fields' => $postedFields)))->save();

        Mage::app()->getCacheInstance()->cleanType('config');
        basicrum_platform_reboot('admin');
        Mage::app()->getRequest()->setParam('section', 'basicrum_analytics')
            ->setParam('website', $scopeData['website'])->setParam('store', $scopeData['store']);
        Mage::app()->getFrontController()->setAction(new Mage_Adminhtml_Controller_Action(
            Mage::app()->getRequest(), Mage::app()->getResponse()
        ));
        Mage::getSingleton('adminhtml/config_data')->setSection('basicrum_analytics')
            ->setWebsite($scopeData['website'])->setStore($scopeData['store']);
        $form = Mage::app()->getLayout()->createBlock('adminhtml/system_config_form')->initForm()->getForm();

        foreach ($values as $field => $value) {
            $message = $case['scope'] . '/' . $field . ($case['inherit'] ? ' inherited' : ' explicit');
            $select = $connection->select()->from($configTable, 'value')
                ->where('path = ?', 'basicrum_analytics/privacy/' . $field)
                ->where('scope = ?', $case['scope'])->where('scope_id = ?', $scopeData['id']);
            basicrum_platform_assert_same(
                $case['inherit'] ? false : $value, $connection->fetchOne($select),
                $message . ': save must use the existing privacy path, including inheritance deletion'
            );
            $element = $form->getElement('basicrum_analytics_general_' . $field);
            basicrum_platform_assert((bool) $element, $message . ': field is missing from General Settings');
            basicrum_platform_assert_same($value, (string) $element->getValue(), $message . ': wrong form value');
            basicrum_platform_assert_same(
                $case['inherit'], (bool) $element->getInherit(), $message . ': wrong inheritance state'
            );
        }
    }
    foreach ($fields as $field) {
        basicrum_platform_assert_same(
            '0', (string) $connection->fetchOne($connection->select()->from($configTable, 'COUNT(*)')
                ->where('path = ?', 'basicrum_analytics/general/' . $field)),
            $field . ': moving a field must not create a new storage path'
        );
    }
} finally {
    // Restore every touched scope, including absent overrides, for subsequent tests.
    foreach ($scopes as $scope => $scopeData) {
        $connection->delete($configTable, array(
            'path IN (?)' => $paths, 'scope = ?' => $scope, 'scope_id = ?' => $scopeData['id'],
        ));
    }
    foreach ($originalRows as $row) {
        if (isset($scopes[$row['scope']]) && (int) $row['scope_id'] === $scopes[$row['scope']]['id']) {
            $connection->insert($configTable, $row);
        }
    }
    Mage::app()->getCacheInstance()->cleanType('config');
    basicrum_platform_reboot($originalStoreCode);
}

echo 'Native ' . $platform . ' privacy field save/render/inheritance checks passed (' . count($cases) . " cases).\n";
