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
                Mage::helper('basicrum_analytics')->__('Beacon URL must be a valid HTTP or HTTPS URL.')
            );
        }

        if (!$this->isHttpAllowed() && stripos($value, 'http://') === 0) {
            $value = 'https://' . substr($value, 7);
        }

        $this->setValue($value);

        return parent::_beforeSave();
    }

    /**
     * Resolve the HTTP policy submitted on the same configuration form.
     * Fall back to the current scoped value when the field was not posted.
     *
     * @return bool
     */
    private function isHttpAllowed(): bool
    {
        $groups = $this->getGroups();

        if (is_array($groups)
            && isset($groups['developer']['fields']['development_mode'])
            && is_array($groups['developer']['fields']['development_mode'])
        ) {
            $field = $groups['developer']['fields']['development_mode'];
            if (empty($field['inherit']) && array_key_exists('value', $field)) {
                return (string) $field['value'] === '1';
            }
        }

        return Mage::getStoreConfigFlag('basicrum_analytics/developer/development_mode');
    }
}
