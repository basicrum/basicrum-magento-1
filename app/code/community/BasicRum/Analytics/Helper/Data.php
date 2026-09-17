<?php
declare(strict_types=1);

/**
 * Basicrum Analytics Helper
 */
class BasicRum_Analytics_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Basicrum backend identifiers are RFC 4122 UUID v4 values.
     */
    const BRUM_SITE_ID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';


    /**
     * Check if Basic RUM analytics is enabled
     * @return bool
     */
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/general/enabled');
    }

    /**
     * Check if opt-in consent is required (GDPR mode)
     * @return bool
     */
    public function isOptInRequired(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/privacy/opt_in_required');
    }

    /**
     * Check whether Boomerang should redact URL query strings.
     *
     * @return bool
     */
    public function shouldStripQueryString(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/privacy/strip_query_string');
    }

    /**
     * Get beacon endpoint URL
     * @return string|null
     */
    public function getBeaconEndpoint()
    {
        $url = trim((string) Mage::getStoreConfig('basicrum_analytics/general/beacon_endpoint'));

        if (!self::isValidBeaconEndpoint($url)) {
            return null;
        }

        // Enforce the production-safe policy even for values injected outside
        // the admin backend model.
        if (!$this->isDevelopmentMode()) {
            $url = preg_replace('/^http:\/\//i', 'https://', $url);
        }

        return $url;
    }

    /**
     * Get the Brum Site ID
     * @return string|null
     */
    public function getBrumSiteId()
    {
        $value = trim((string) Mage::getStoreConfig('basicrum_analytics/general/brum_site_id'));

        return self::isValidBrumSiteId($value) ? $value : null;
    }

    /**
     * Validate a Beacon endpoint without accepting executable URL schemes.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidBeaconEndpoint($value): bool
    {
        if (!is_string($value) || $value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    /**
     * Validate the Basicrum backend identifier contract (UUID v4).
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidBrumSiteId($value): bool
    {
        return is_string($value) && preg_match(self::BRUM_SITE_ID_PATTERN, $value) === 1;
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
        return min(30000, max(0, $value));
    }

    /**
     * Check if unminified loaders should be used (for debugging)
     * @return bool
     */
    public function useUnminifiedLoaders(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/developer/use_unminified_loaders');
    }

    /**
     * Check whether HTTP Beacon URLs are explicitly allowed for local testing.
     *
     * @return bool
     */
    public function isDevelopmentMode(): bool
    {
        return Mage::getStoreConfigFlag('basicrum_analytics/developer/development_mode');
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
