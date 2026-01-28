<?php
declare(strict_types=1);

/**
 * Custom renderer for consent/opt-in information in admin config
 */
class BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_ConsentInfo
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Render the field with custom info box below
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element): string
    {
        $html = parent::_getElementHtml($element);

        $infoHtml = <<<HTML
<div style="margin-top: 10px; padding: 12px 15px; background: #f8f8f8; border-left: 4px solid #eb5202; border-radius: 3px;">
    <div style="font-weight: bold; margin-bottom: 8px; color: #333;">
        JavaScript API for Cookie Consent Integration
    </div>
    <div style="margin-bottom: 6px; color: #555;">
        When opt-in is enabled, Boomerang will not load until consent is given. Use these global functions to integrate with your cookie consent solution:
    </div>
    <table style="margin: 10px 0; border-collapse: collapse;">
        <tr>
            <td style="padding: 4px 10px 4px 0; font-family: monospace; color: #0066cc; white-space: nowrap;">
                OPT_IN_BASIC_RUM()
            </td>
            <td style="padding: 4px 0; color: #555;">
                Call when user <strong>accepts</strong> cookies/tracking. Loads Boomerang and sets consent cookie.
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-family: monospace; color: #cc0000; white-space: nowrap;">
                OPT_OUT_BASIC_RUM()
            </td>
            <td style="padding: 4px 0; color: #555;">
                Call when user <strong>rejects</strong> tracking. Disables Boomerang and clears all RUM cookies.
            </td>
        </tr>
    </table>
    <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #ddd;">
        <div style="font-weight: bold; margin-bottom: 6px; color: #333;">
            Cookies Created
        </div>
        <table style="margin: 6px 0; border-collapse: collapse; font-size: 12px;">
            <tr>
                <td style="padding: 3px 10px 3px 0; font-family: monospace; color: #666;">BOOMR_CONSENT</td>
                <td style="padding: 3px 0; color: #555;">Remembers user consent preference (expires after <strong>1 year</strong>)</td>
            </tr>
            <tr>
                <td style="padding: 3px 10px 3px 0; font-family: monospace; color: #666;">RT</td>
                <td style="padding: 3px 0; color: #555;">Round-trip timing cookie (created on opt-in, deleted on opt-out)</td>
            </tr>
            <tr>
                <td style="padding: 3px 10px 3px 0; font-family: monospace; color: #666;">BA</td>
                <td style="padding: 3px 0; color: #555;">Bandwidth/latency cookie (created on opt-in, deleted on opt-out)</td>
            </tr>
        </table>
    </div>
    <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #ddd;">
        <div style="font-weight: bold; margin-bottom: 6px; color: #333;">
                Integration Example:
        </div>
    </div>
    <div style="margin-top: 12px; padding: 10px; background: #2d2d2d; border-radius: 4px;">
        <pre style="margin: 0; font-family: 'Monaco', 'Menlo', 'Consolas', monospace; font-size: 11px; line-height: 1.5; color: #f8f8f2; white-space: pre-wrap; word-wrap: break-word;"><span style="color: #888;">// Accept button handler</span>
<span style="color: #66d9ef;">if</span> (<span style="color: #f92672;">typeof</span> window.OPT_IN_BASIC_RUM <span style="color: #f92672;">===</span> <span style="color: #e6db74;">'function'</span>) {
    window.<span style="color: #a6e22e;">OPT_IN_BASIC_RUM</span>();
}

<span style="color: #888;">// Reject button handler</span>
<span style="color: #66d9ef;">if</span> (<span style="color: #f92672;">typeof</span> window.OPT_OUT_BASIC_RUM <span style="color: #f92672;">===</span> <span style="color: #e6db74;">'function'</span>) {
    window.<span style="color: #a6e22e;">OPT_OUT_BASIC_RUM</span>();
}</pre>
    </div>
</div>
HTML;

        return $html . $infoHtml;
    }
}
