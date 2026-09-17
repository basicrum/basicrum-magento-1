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
 * Custom renderer for consent/opt-in information in admin config.
 */
class BasicRum_Analytics_Block_Adminhtml_System_Config_Form_Field_ConsentInfo
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Render consent guidance as a full-width configuration row.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element): string
    {
        $rowId = htmlspecialchars('row_' . $element->getHtmlId(), ENT_QUOTES, 'UTF-8');
        $allowSnippetId = htmlspecialchars($element->getHtmlId() . '_allow_snippet', ENT_QUOTES, 'UTF-8');
        $denySnippetId = htmlspecialchars($element->getHtmlId() . '_deny_snippet', ENT_QUOTES, 'UTF-8');
        $scriptUrl = htmlspecialchars(
            Mage::getBaseUrl('js') . 'basicrum/admin/consent-info.js',
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
<tr id="{$rowId}">
<td colspan="4" style="padding: 0 15px 10px;">
<div style="box-sizing: border-box; width: 100%; padding: 12px 15px; background: #f8f8f8; border-left: 4px solid #eb5202; border-radius: 3px;">
    <div style="font-weight: bold; margin-bottom: 8px; color: #333;">JavaScript API for Cookie Consent Integration</div>
    <p style="color: #555;">In consent-controlled mode, Basicrum stays inert until your external consent tool explicitly allows performance monitoring on the current page. Basicrum does not store or infer a consent decision. Call the API after this loader has registered the callbacks near the end of the page; calls made before registration are not replayed.</p>
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
    <p style="color: #555;"><strong>Connect both decisions:</strong> place the allow snippet only in your consent tool's allow or grant callback, and the deny snippet in its deny, expiry, or withdrawal callback. Do not run the two snippets together.</p>
    <div style="margin-top: 12px;">
        <label for="{$allowSnippetId}" style="display: block; margin-bottom: 4px;"><strong>Allow or grant callback</strong></label>
        <textarea id="{$allowSnippetId}" readonly="readonly" spellcheck="false" rows="3" style="box-sizing: border-box; width: 100%; padding: 8px; font-family: Monaco, Menlo, Consolas, monospace; font-size: 11px; line-height: 1.5; color: #f8f8f2; background: #2d2d2d; border: 0; border-radius: 4px; resize: vertical;">if (typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === 'function') {
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
}</textarea>
        <p style="margin: 5px 0 0;">
            <button type="button" class="scalable basicrum-copy-consent-snippet" data-basicrum-copy-target="{$allowSnippetId}" data-copied-label="Copied" data-copy-fallback-label="Press Ctrl+C or Command+C to copy."><span>Copy allow snippet</span></button>
            <span class="basicrum-copy-status" aria-live="polite" style="margin-left: 6px;"></span>
        </p>
    </div>
    <div style="margin-top: 14px;">
        <label for="{$denySnippetId}" style="display: block; margin-bottom: 4px;"><strong>Deny, expiry, or withdrawal callback</strong></label>
        <textarea id="{$denySnippetId}" readonly="readonly" spellcheck="false" rows="3" style="box-sizing: border-box; width: 100%; padding: 8px; font-family: Monaco, Menlo, Consolas, monospace; font-size: 11px; line-height: 1.5; color: #f8f8f2; background: #2d2d2d; border: 0; border-radius: 4px; resize: vertical;">if (typeof window.OPT_OUT_BASICRUM_LOADER_WRAPPER === 'function') {
    window.OPT_OUT_BASICRUM_LOADER_WRAPPER();
}</textarea>
        <p style="margin: 5px 0 0;">
            <button type="button" class="scalable basicrum-copy-consent-snippet" data-basicrum-copy-target="{$denySnippetId}" data-copied-label="Copied" data-copy-fallback-label="Press Ctrl+C or Command+C to copy."><span>Copy deny/withdrawal snippet</span></button>
            <span class="basicrum-copy-status" aria-live="polite" style="margin-left: 6px;"></span>
        </p>
    </div>
    <script type="text/javascript" src="{$scriptUrl}"></script>
</div>
</td>
</tr>
HTML;
    }
}
