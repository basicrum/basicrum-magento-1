<?php
declare(strict_types=1);

/**
 * Backend validation for the Basicrum Beacon endpoint.
 */
class BasicRum_Analytics_Model_System_Config_Backend_BeaconEndpoint extends Mage_Core_Model_Config_Data
{
    /**
     * Validate and normalize the value before saving.
     *
     * @return BasicRum_Analytics_Model_System_Config_Backend_BeaconEndpoint
     * @throws Mage_Core_Exception
     */
    protected function _beforeSave()
    {
        $value = trim((string) $this->getValue());

        if ($value !== '' && !BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint($value)) {
            Mage::throwException(
                Mage::helper('basicrum_analytics')->__('Beacon Endpoint URL must be a valid HTTP or HTTPS URL.')
            );
        }

        $this->setValue($value);

        return parent::_beforeSave();
    }
}
