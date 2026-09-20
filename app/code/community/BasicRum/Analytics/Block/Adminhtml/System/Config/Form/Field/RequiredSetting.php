<?php
declare(strict_types=1);

// Maho can disable its global Varien aliases. Keep the Magento 1 method
// signature compatible without requiring those aliases throughout the store.
if (defined('MAHO_ROOT_DIR')
    && !class_exists('Varien_Data_Form_Element_Abstract', false)
    && class_exists('Maho\\Data\\Form\\Element\\AbstractElement')
) {
    class_alias('Maho\\Data\\Form\\Element\\AbstractElement', 'Varien_Data_Form_Element_Abstract');
}

/**
 * Highlight required monitoring settings and explain inactive configurations.
 */
class BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_RequiredSetting
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    const ENABLED_FIELD_ID = 'basicrum_analytics_general_enabled';
    const BEACON_FIELD_ID = 'basicrum_analytics_general_beacon_endpoint';
    const SITE_ID_FIELD_ID = 'basicrum_analytics_general_brum_site_id';
    const CONSENT_FIELD_ID = 'basicrum_analytics_general_opt_in_required';

    /**
     * Render the native field row and monitoring status after the Site ID.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element): string
    {
        $html = parent::render($element);

        if (!$this->isSiteIdElement($element)) {
            return $html;
        }

        return $html . $this->getMonitoringStatusHtml($element);
    }

    /**
     * Add accessible, field-level feedback without replacing Magento's input.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element): string
    {
        $isEnabled = $this->isMonitoringEnabled($element);
        $message = $isEnabled ? $this->getValidationMessage($element) : null;

        if ($message !== null) {
            $element->addClass('validation-failed');
        }

        $html = parent::_getElementHtml($element);
        $errorId = $element->getHtmlId() . '_basicrum_error';
        $attributes = sprintf(
            ' aria-required="%s" aria-invalid="%s"',
            $isEnabled ? 'true' : 'false',
            $message !== null ? 'true' : 'false'
        );

        if ($message !== null) {
            $attributes .= ' aria-describedby="'
                . htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8')
                . '"';
        }

        $html = preg_replace('/<input\\b/', '<input' . $attributes, $html, 1);

        if ($message === null) {
            return (string) $html;
        }

        return (string) $html
            . '<div id="' . htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8')
            . '" class="validation-advice" role="status">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string|null
     */
    private function getValidationMessage(Varien_Data_Form_Element_Abstract $element)
    {
        $value = trim((string) $element->getValue());

        if ($this->isBeaconElement($element)) {
            if ($value === '') {
                return Mage::helper('basicrum_analytics')->__(
                    'Beacon URL is required while monitoring is enabled. Monitoring remains inactive.'
                );
            }

            if (!BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint($value)) {
                return Mage::helper('basicrum_analytics')->__(
                    'Enter a valid HTTP or HTTPS Beacon URL. Monitoring remains inactive.'
                );
            }
        }

        if ($this->isSiteIdElement($element)) {
            if ($value === '') {
                return Mage::helper('basicrum_analytics')->__(
                    'Brum Site ID is required while monitoring is enabled. Monitoring remains inactive.'
                );
            }

            if (!BasicRum_Analytics_Helper_Data::isValidBrumSiteId($value)) {
                return Mage::helper('basicrum_analytics')->__(
                    'Enter a valid UUID v4 Brum Site ID. Monitoring remains inactive.'
                );
            }
        }

        return null;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    private function isMonitoringEnabled(Varien_Data_Form_Element_Abstract $element): bool
    {
        return (string) $this->getFormValue(
            $element,
            self::ENABLED_FIELD_ID,
            'basicrum_analytics/general/enabled'
        ) === '1';
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    private function hasValidRequiredSettings(Varien_Data_Form_Element_Abstract $element): bool
    {
        $beacon = trim((string) $this->getFormValue(
            $element,
            self::BEACON_FIELD_ID,
            'basicrum_analytics/general/beacon_endpoint'
        ));
        $siteId = trim((string) $this->getFormValue(
            $element,
            self::SITE_ID_FIELD_ID,
            'basicrum_analytics/general/brum_site_id'
        ));

        return BasicRum_Analytics_Helper_Data::isValidBeaconEndpoint($beacon)
            && BasicRum_Analytics_Helper_Data::isValidBrumSiteId($siteId);
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    private function isConsentRequired(Varien_Data_Form_Element_Abstract $element): bool
    {
        return (string) $this->getFormValue(
            $element,
            self::CONSENT_FIELD_ID,
            'basicrum_analytics/privacy/opt_in_required'
        ) === '1';
    }

    /**
     * Read the resolved form value so Website and Store View inheritance works.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param string $fieldId
     * @param string $configPath
     * @return mixed
     */
    private function getFormValue(
        Varien_Data_Form_Element_Abstract $element,
        string $fieldId,
        string $configPath
    ) {
        $form = $element->getForm();
        $field = $form ? $form->getElement($fieldId) : null;

        return $field ? $field->getValue() : Mage::getStoreConfig($configPath);
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    private function isBeaconElement(Varien_Data_Form_Element_Abstract $element): bool
    {
        return $this->hasIdSuffix($element->getHtmlId(), '_beacon_endpoint');
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return bool
     */
    private function isSiteIdElement(Varien_Data_Form_Element_Abstract $element): bool
    {
        return $this->hasIdSuffix($element->getHtmlId(), '_brum_site_id');
    }

    /**
     * PHP 7.4-compatible suffix check.
     *
     * @param string $value
     * @param string $suffix
     * @return bool
     */
    private function hasIdSuffix(string $value, string $suffix): bool
    {
        return substr($value, -strlen($suffix)) === $suffix;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    private function getMonitoringStatusHtml(Varien_Data_Form_Element_Abstract $element): string
    {
        $helper = Mage::helper('basicrum_analytics');

        if (!$this->isMonitoringEnabled($element)) {
            $heading = $helper->__('Monitoring status: Disabled');
            $message = $helper->__('Basicrum is disabled. No monitoring scripts are emitted.');
            $background = '#f5f5f5';
            $border = '#777777';
        } elseif (!$this->hasValidRequiredSettings($element)) {
            $heading = $helper->__('Monitoring status: Blocked');
            $message = $helper->__(
                'Basicrum monitoring is enabled but inactive. Monitoring scripts are not emitted until both required fields contain valid values.'
            );
            $background = '#fff9e6';
            $border = '#eb5202';
        } elseif ($this->isConsentRequired($element)) {
            $heading = $helper->__('Monitoring status: Waiting for consent');
            $message = $helper->__(
                'Basicrum is configured. Boomerang loads only after the external consent tool explicitly allows monitoring on the current page.'
            );
            $background = '#eef5ff';
            $border = '#1976d2';
        } else {
            $heading = $helper->__('Monitoring status: Active');
            $message = $helper->__(
                'Basicrum monitoring starts immediately on storefront pages without waiting for consent.'
            );
            $background = '#edf7ed';
            $border = '#2e7d32';
        }

        return '<tr id="row_basicrum_analytics_general_configuration_status">'
            . '<td colspan="4" style="padding: 0 15px 10px;">'
            . '<div role="status" style="box-sizing: border-box; width: 100%; padding: 12px 15px; '
            . 'background: ' . $background . '; border-left: 4px solid ' . $border . ';">'
            . '<strong>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</strong>'
            . '<div style="margin-top: 4px;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></td></tr>';
    }
}
