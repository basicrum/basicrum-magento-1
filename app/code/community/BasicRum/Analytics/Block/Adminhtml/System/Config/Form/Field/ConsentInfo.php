<?php
declare(strict_types=1);

/**
 * Custom renderer for consent/opt-in information in admin config.
 */
class BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_ConsentInfo
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Render the field with consent integration guidance.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element): string
    {
        $html = parent::_getElementHtml($element);

        $infoHtml = <<<HTML
<div style="margin-top: 10px; padding: 12px 15px; background: #f8f8f8; border-left: 4px solid #eb5202; border-radius: 3px;">
    <div style="font-weight: bold; margin-bottom: 8px; color: #333;">JavaScript API for Cookie Consent Integration</div>
    <p style="color: #555;">In consent-controlled mode, Basicrum stays inert until your external consent tool explicitly allows performance monitoring on the current page. Basicrum does not store or infer a consent decision.</p>
    <table style="margin: 10px 0; border-collapse: collapse;">
        <tr>
            <td style="padding: 4px 10px 4px 0; font-family: monospace; color: #0066cc; white-space: nowrap;">OPT_IN_BASICRUM_LOADER_WRAPPER()</td>
            <td style="padding: 4px 0; color: #555;">Call when the external tool reports that monitoring is allowed.</td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-family: monospace; color: #cc0000; white-space: nowrap;">OPT_OUT_BASICRUM_LOADER_WRAPPER()</td>
            <td style="padding: 4px 0; color: #555;">Call when monitoring is denied or withdrawn. This disables future collection and removes <code>RT</code>, <code>BA</code>, and legacy Basicrum consent cookies, but it cannot retract data already sent.</td>
        </tr>
    </table>
    <p style="color: #555;"><code>OPT_IN_BASIC_RUM()</code> and <code>OPT_OUT_BASIC_RUM()</code> remain available as backward-compatible Magento 1 aliases.</p>
    <p style="color: #555;">A deny before the first opt-in can be followed by an allow on the same page. After monitoring has started and consent is withdrawn, reload the page before re-granting; monitoring remains disabled for the rest of that page view.</p>
    <div style="margin-top: 12px; padding: 10px; background: #2d2d2d; border-radius: 4px;">
        <pre style="margin: 0; font-family: Monaco, Menlo, Consolas, monospace; font-size: 11px; line-height: 1.5; color: #f8f8f2; white-space: pre-wrap; word-wrap: break-word;">if (typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === 'function') {
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
}
if (typeof window.OPT_OUT_BASICRUM_LOADER_WRAPPER === 'function') {
    window.OPT_OUT_BASICRUM_LOADER_WRAPPER();
}</pre>
    </div>
</div>
HTML;

        return $html . $infoHtml;
    }
}
