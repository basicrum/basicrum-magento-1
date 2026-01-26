<?php
declare(strict_types=1);

/**
 * BasicRum Analytics Helper
 */
class BasicRum_Analytics_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Check if Basic RUM analytics is enabled
     * @return bool
     */
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/general/enabled');
    }

    /**
     * Check if module is enabled
     * @return bool
     */
    public function isOptInRequired(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/general/opt_in_required');
    }

    /**
     * Get beacon endpoint URL
     * @return string
     */
    public function getBeaconEndpoint(): null|string
    {
        return Mage::getStoreConfig('basicrum_analytics/general/beacon_endpoint');
    }

    /**
     * Check if Wait After Onload plugin is enabled
     * @return bool
     */
    public function isWaitAfterOnloadEnabled(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/wait_after_onload/enabled');
    }

    /**
     * Get wait after onload milliseconds
     * @return int
     */
    public function getWaitAfterOnloadMilliseconds(): int
    {
        $value = (int) Mage::getStoreConfig('basicrum_analytics/wait_after_onload/wait_ms');
        return max(0, $value);
    }

    /**
     * Get current page type based on layout handles
     *
     * @return string
     */
    public function getPageType(): string
    {
        /** @var BasicRum_Analytics_Helper_PageTypeDetector $detector */
        $detector = Mage::helper('basicrum_analytics/pageTypeDetector');

        return $detector->getPageType();
    }
}
