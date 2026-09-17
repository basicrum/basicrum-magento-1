<?php
declare(strict_types=1);

class Mage_Core_Exception extends Exception
{
}

class Mage_Core_Helper_Abstract
{
    public function __($message)
    {
        return $message;
    }
}

class Mage_Core_Block_Abstract
{
}

class Mage_Core_Model_Config_Data
{
    private $value;

    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    protected function _beforeSave()
    {
        return $this;
    }
}

class Basicrum_Test_Select
{
    public $table;
    public $columns;
    public $where = array();
    public $limit;

    public function from($table, $columns)
    {
        $this->table = $table;
        $this->columns = $columns;
        return $this;
    }

    public function where($condition, $value)
    {
        $this->where[] = array($condition, $value);
        return $this;
    }

    public function limit($count)
    {
        $this->limit = $count;
        return $this;
    }
}

class Basicrum_Test_Connection
{
    public $fetchResults;
    public $selects = array();

    public function __construct(array $fetchResults)
    {
        $this->fetchResults = $fetchResults;
    }

    public function select()
    {
        $select = new Basicrum_Test_Select();
        $this->selects[] = $select;
        return $select;
    }

    public function fetchOne($select)
    {
        if (!in_array($select, $this->selects, true)) {
            throw new RuntimeException('Installer queried an unknown select object');
        }

        if (!$this->fetchResults) {
            throw new RuntimeException('Installer made more queries than expected');
        }

        return array_shift($this->fetchResults);
    }
}

class Basicrum_Test_Config
{
    public $saved = array();

    public function saveConfig($path, $value, $scope, $scopeId)
    {
        $this->saved[] = array($path, $value, $scope, $scopeId);
        return $this;
    }
}

class Basicrum_Test_Setup
{
    public $connection;
    public $started = 0;
    public $ended = 0;
    public $requestedTables = array();

    public function __construct(array $fetchResults)
    {
        $this->connection = new Basicrum_Test_Connection($fetchResults);
    }

    public function startSetup()
    {
        $this->started += 1;
        return $this;
    }

    public function endSetup()
    {
        $this->ended += 1;
        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getTable($alias)
    {
        $this->requestedTables[] = $alias;
        return 'prefix_core_config_data';
    }

    public function runInstaller($path)
    {
        include $path;
    }
}

class Basicrum_Test_Request
{
    public $secure = false;

    public function isSecure()
    {
        return $this->secure;
    }
}

class Basicrum_Test_App
{
    public $request;

    public function __construct()
    {
        $this->request = new Basicrum_Test_Request();
    }

    public function getRequest()
    {
        return $this->request;
    }
}

class Basicrum_Test_PageTypeHelper
{
    public $pageType = 'Product';

    public function getPageType()
    {
        return $this->pageType;
    }
}

class Mage
{
    public static $storeConfig = array();
    public static $helpers = array();
    public static $baseUrls = array('js' => 'https://shop.example.test/js/');
    public static $app;
    public static $configObject;

    public static function getStoreConfig($path)
    {
        return array_key_exists($path, self::$storeConfig) ? self::$storeConfig[$path] : null;
    }

    public static function getStoreConfigFlag($path)
    {
        $value = self::getStoreConfig($path);
        return $value === true || $value === 1 || $value === '1';
    }

    public static function helper($alias)
    {
        if (!isset(self::$helpers[$alias])) {
            throw new RuntimeException('Missing test helper: ' . $alias);
        }

        return self::$helpers[$alias];
    }

    public static function getBaseUrl($type)
    {
        return self::$baseUrls[$type];
    }

    public static function app()
    {
        return self::$app;
    }

    public static function getConfig()
    {
        if (!self::$configObject) {
            throw new RuntimeException('Missing test configuration object');
        }

        return self::$configObject;
    }

    public static function throwException($message)
    {
        throw new Mage_Core_Exception($message);
    }
}

function basicrum_test_reset(array $overrides = array())
{
    Mage::$storeConfig = array_merge(array(
        'basicrum_analytics/general/enabled' => '1',
        'basicrum_analytics/general/beacon_endpoint' => 'https://collector.example.test/beacon',
        'basicrum_analytics/general/brum_site_id' => 'e926c1a2-7e33-4f54-90d0-e6e31f3ad43d',
        'basicrum_analytics/privacy/opt_in_required' => '0',
        'basicrum_analytics/wait_after_onload/enabled' => '0',
        'basicrum_analytics/wait_after_onload/wait_ms' => '0',
        'basicrum_analytics/developer/use_unminified_loaders' => '0',
    ), $overrides);
    Mage::$app = new Basicrum_Test_App();
    Mage::$configObject = new Basicrum_Test_Config();
    Mage::$baseUrls = array('js' => 'https://shop.example.test/js/');

    $helper = new BasicRum_Analytics_Helper_Data();
    $pageType = new Basicrum_Test_PageTypeHelper();
    Mage::$helpers = array(
        'basicrum_analytics' => $helper,
        'basicrum_analytics/pageTypeDetector' => $pageType,
    );

    return array($helper, $pageType);
}

function basicrum_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
        );
    }
}

function basicrum_assert_true($actual, $message)
{
    basicrum_assert_same(true, (bool) $actual, $message);
}

function basicrum_assert_contains($needle, $haystack, $message)
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . "\nMissing: " . $needle);
    }
}

function basicrum_assert_not_contains($needle, $haystack, $message)
{
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message . "\nUnexpected: " . $needle);
    }
}

function basicrum_assert_throws(callable $callback, $expectedClass, $message)
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException($message . ': received ' . get_class($exception));
    }

    throw new RuntimeException($message . ': no exception was thrown');
}
