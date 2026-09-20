<?php
declare(strict_types=1);

/**
 * Versioned policy for the consent-mode default introduced in 1.1.0.
 */
class BasicRum_Analytics_Model_Setup_PrivacyDefault
{
    /**
     * Resolve the default-scope value that the first setup run should persist.
     *
     * A pre-existing explicit default is never overwritten. A store with any
     * earlier Basicrum configuration footprint keeps the historical immediate
     * mode. A genuinely new installation starts in consent-controlled mode.
     *
     * @param bool $hasExplicitDefault
     * @param bool $hasExistingConfiguration
     * @return string|null
     */
    public static function getValueToPersist(
        bool $hasExplicitDefault,
        bool $hasExistingConfiguration
    ) {
        if ($hasExplicitDefault) {
            return null;
        }

        return $hasExistingConfiguration ? '0' : '1';
    }
}
