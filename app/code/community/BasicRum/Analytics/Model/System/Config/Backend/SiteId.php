<?php
declare(strict_types=1);

/**
 * Backend model to validate that brum_site_id is a valid UUID (v4) or empty.
 */
class BasicRum_Analytics_Model_System_Config_Backend_SiteId extends Mage_Core_Model_Config_Data
{
    const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Validate the value before saving
     *
     * @return BasicRum_Analytics_Model_System_Config_Backend_SiteId
     * @throws Mage_Core_Exception
     */
    protected function _beforeSave()
    {
        $value = (string) $this->getValue();

        if ($value !== '' && !preg_match(self::UUID_PATTERN, $value)) {
            Mage::throwException(
                Mage::helper('basicrum_analytics')->__('BasicRUM Site ID must be a valid UUID (e.g. e926c1a2-7e33-4f54-90d0-e6e31f3ad43d).')
            );
        }

        return parent::_beforeSave();
    }
}
