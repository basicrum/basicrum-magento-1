# Admin UI parity checklist

This checklist tracks Magento 1 admin UI parity with the Basicrum WordPress
settings UI. It focuses on equivalent administrator outcomes rather than making
Magento look or behave exactly like WordPress.

## Inspection baseline

- Inspected: 2026-09-17
- WordPress: WordPress 7.1 with the current `basicrum-wordpress` implementation
- Magento 1: OpenMage 20.18.0 with the current Magento 1 plugin branch
- Scope: settings presentation, discoverability, validation feedback, consent
  integration guidance, and platform-specific configuration behavior

Legend:

- `[x]` Parity is present or the platform-specific behavior is intentionally kept.
- `[ ]` Work remains.
- `Partial` means the core capability exists but the administrator experience is
  not yet equivalent.

## Core configuration

- [x] Provide an Enable Monitoring control.
- [x] Provide a required Beacon Endpoint field.
- [x] Provide a required Brum Site ID field with UUID v4 guidance.
- [x] Display the bundled Boomerang version.
  - Partial: WordPress presents this as read-only text; Magento uses a disabled
    select-style control.
- [x] Default genuinely new installations to consent-controlled monitoring.
- [x] Provide Wait After Onload enablement and a millisecond delay field.
- [x] Document and enforce the 0–30000 millisecond delay range.
- [x] Provide a Use Unminified Loaders developer setting.
- [x] Keep privacy controls and consent guidance within General Settings, with
  separate Wait After Onload and Developer sections. Existing privacy
  configuration paths and scope inheritance are preserved.

## Consent and privacy experience

- [x] Replace the Magento `Yes`/`No` consent choice with labels that explain the
  consequences, equivalent to WordPress's “Monitor without consent” and “Require
  consent before monitoring” choices.
- [x] Make the Magento consent integration guidance use the available content
  width.
  - Implemented as a separate full-width configuration row so the instructions
    remain readable in Magento's native table layout.
- [x] Hide the consent integration guidance when consent-controlled monitoring is
  disabled.
  - Implemented with Magento's native field dependency mechanism, including
    inherited Website and Store View configuration.
- [x] Keep manual callbacks as the intentional Magento 1 consent integration.
  - Magento 1/OpenMage does not provide a standardized consent-provider API
    comparable to WordPress. Native Cookie Restriction Mode is a basic persisted
    allow signal, while third-party consent tools expose provider-specific APIs.
  - Automatic native or third-party provider adapters are deferred to a future
    phase. Any adapter must use documented current-page allow and withdrawal
    signals rather than inferring consent from the presence of a banner or an
    arbitrary cookie.
- [x] Improve manual integration usability with focused examples and copy actions.
  - The allow and deny/expiry/withdrawal callbacks are presented separately so
    administrators do not accidentally run both decisions as one sequence.
  - Each read-only snippet has an accessible copy action with a select-and-copy
    fallback when the Clipboard API is unavailable.
- [x] Document the canonical WordPress-compatible opt-in and opt-out callback names.
- [x] Document the legacy Magento callback aliases as compatibility APIs.
- [x] Explain that Basicrum does not persist or infer consent.
- [x] Explain cookie removal and that data already sent cannot be retracted.
- [x] Explain safe re-grant behavior after withdrawal.
- [x] Add a Strip Query Strings privacy setting.
  - It uses Boomerang's native `strip_query_string` option, remains disabled by
    default for compatibility, and preserves query parameters in the Beacon Endpoint.

## Validation and state feedback

- [x] Show a visible incomplete-configuration state when Beacon Endpoint or Brum Site ID
  is missing, making clear that monitoring remains disabled.
- [x] Add field-level invalid-state feedback comparable to WordPress warnings and
  inline errors while retaining Magento's server-side validation.
  - Feedback is shown only when monitoring is enabled, uses the resolved field
    values at the current configuration scope, and does not replace the backend
    validation contract.
- [x] Disable or hide irrelevant dependent controls when the module is disabled.
  - Magento's native field dependencies hide and disable privacy, wait, and
    developer runtime controls. Stored scoped values remain intact.
  - Boomerang version, Beacon Endpoint, and Brum Site ID stay visible so
    administrators can inspect or prepare identity configuration before enabling.
- [x] Reveal Wait After Onload milliseconds only when Wait After Onload is enabled.
- [x] Hide consent-specific controls when the module itself is disabled.
  - Consent guidance additionally requires consent-controlled mode, including
    when the controlling values are inherited at Website or Store View scope.

## Remaining WordPress controls

- [x] Resolve Track Admin Users as an intentional platform difference.
  - WordPress can identify a logged-in frontend user with the `manage_options`
    capability. Magento authenticates backend users in the separate `adminhtml`
    application and does not expose a reliable admin identity on storefront
    requests. Basicrum never runs on admin pages, and initializing the admin
    session in the frontend solely for tracking exclusion would add coupling and
    session risk. No misleading Magento setting is added; collector-side staff
    exclusion or a future explicit frontend signal remains available for stores
    that require it.
- [x] Add an explicit development-only HTTP policy.
  - HTTPS is enforced by default. HTTP requires a scoped, clearly labeled local
    testing option, and legacy HTTP/HTTPS endpoints retain their behavior through
    a versioned scope-preserving upgrade policy.
- [x] Keep Script Position fixed at Magento's native `before_body_end` reference.
  - This matches WordPress's safe default footer behavior without pretending that
    Magento themes provide a portable `wp_head` equivalent. Moving consent mode
    earlier would also change callback-registration timing, so no selector is
    added without a separate behavioral requirement.

## Presentation and discoverability

- [x] Align product casing and field terminology with Basicrum.
  - User-facing Magento copy now consistently uses `Basicrum`, `Beacon Endpoint`, and
    `Brum Site ID`; internal `BasicRum_Analytics` class and module identifiers are
    retained for backward compatibility. `Beacon Endpoint` follows the Basicrum
    backoffice terminology.
- [x] Give the Magento configuration page a clearer Basicrum identity while
  retaining native Magento administration patterns.
  - The native tab and page are labeled Basicrum and Basicrum Settings, and the
    General Settings introduction identifies the product and configuration scope.
- [x] Improve complex help content for narrower admin viewports.
  - Long callback names wrap, code fields remain within the available width, and
    copy controls wrap without changing Magento's native configuration layout.
- [ ] Refresh `docs/media/admin-area.png` after moving privacy into General Settings
  and renaming the Beacon Endpoint field.
  - The previous capture predates these UI changes.

## Intentional Magento-specific behavior

- [x] Preserve Default, Website, and Store View configuration scopes and inheritance.
- [x] Retain Magento's System Configuration placement and accordion structure.
- [x] Retain native Magento controls where they communicate the choice clearly.
- [x] Retain legacy Magento callback aliases for existing integrations.
- [x] Keep the current script placement unless a separate behavioral requirement
  demonstrates that a configurable position is safe and useful.

## Suggested processing order

### P0 — consent clarity and configuration safety

- [x] Fix the consent guidance width and wrapping.
- [x] Conditionally display consent guidance.
- [x] Use plain-language consent choices.
- [x] Add incomplete and invalid configuration feedback.

### P1 — integration parity

- [x] Document manual consent integration as the supported Magento 1 strategy.
- [x] Improve manual integration examples and copy actions.
- [x] Resolve Strip Query Strings and HTTP-policy parity.

### Deferred — consent-provider adapters

- [ ] Investigate optional adapters for Magento Cookie Restriction Mode and named
  third-party consent tools when they expose reliable allow and withdrawal APIs.
- [ ] If adapters are added, provide explicit selection, active-provider status,
  diagnostics, and a clear next action instead of heuristic auto-detection.

### P2 — refinement

- [x] Resolve Track Admin Users applicability.
- [x] Align terminology and branding.
- [x] Review narrow-viewport presentation.
- [x] Update the admin screenshot after the UI stabilizes.

## Completion criteria

- [x] A new administrator can tell whether monitoring is active, disabled, waiting
  for consent, or blocked by incomplete configuration without reading source code.
- [x] Consent-controlled mode clearly explains what the external consent tool must
  do and shows only relevant instructions.
- [x] Immediate mode does not display consent-integration instructions.
- [x] Required configuration errors are visible at the affected fields.
- [x] Magento configuration scopes continue to work at all supported levels.
- [x] Platform-specific differences are documented and intentional.
- [x] Updated screenshots match the shipped admin UI.
