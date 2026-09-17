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
- [x] Provide a required Beacon URL field.
- [x] Provide a required Brum Site ID field with UUID v4 guidance.
- [x] Display the bundled Boomerang version.
  - Partial: WordPress presents this as read-only text; Magento uses a disabled
    select-style control.
- [x] Default genuinely new installations to consent-controlled monitoring.
- [x] Provide Wait After Onload enablement and a millisecond delay field.
- [x] Document and enforce the 0–30000 millisecond delay range.
- [x] Provide a Use Unminified Loaders developer setting.
- [x] Keep settings grouped into General, Privacy, Performance/Wait, and Developer
  sections.

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
- [ ] Add an explicit Automatic/Manual consent integration choice, or document an
  intentional decision that Magento remains manual-only.
- [ ] If automatic integration is added, show the active provider, detected
  providers, diagnostics, and a next action comparable to WordPress.
- [ ] Improve manual integration usability with focused examples and copy actions.
- [x] Document the canonical WordPress-compatible opt-in and opt-out callback names.
- [x] Document the legacy Magento callback aliases as compatibility APIs.
- [x] Explain that Basicrum does not persist or infer consent.
- [x] Explain cookie removal and that data already sent cannot be retracted.
- [x] Explain safe re-grant behavior after withdrawal.
- [ ] Add a Strip Query Strings privacy setting or record a deliberate reason not
  to expose it on Magento 1.

## Validation and state feedback

- [x] Show a visible incomplete-configuration state when Beacon URL or Brum Site ID
  is missing, making clear that monitoring remains disabled.
- [x] Add field-level invalid-state feedback comparable to WordPress warnings and
  inline errors while retaining Magento's server-side validation.
  - Feedback is shown only when monitoring is enabled, uses the resolved field
    values at the current configuration scope, and does not replace the backend
    validation contract.
- [ ] Disable or hide irrelevant dependent controls when the module is disabled.
- [x] Reveal Wait After Onload milliseconds only when Wait After Onload is enabled.
- [ ] Review whether consent-specific controls should be disabled when the module
  itself is disabled.

## Remaining WordPress controls

- [ ] Decide whether Track Admin Users has a meaningful Magento 1 equivalent and
  implement it if applicable.
- [ ] Add an explicit development-only HTTP policy or document why Magento's
  current HTTP/HTTPS behavior is intentionally different.
- [ ] Review Script Position as a behavioral requirement. Do not add a Header/Footer
  selector solely for visual parity: Magento's layout placement and runtime timing
  differ from WordPress.

## Presentation and discoverability

- [ ] Align product casing and field terminology across implementations:
  - `Basicrum` versus `BasicRUM`
  - `Beacon URL` versus `Beacon Endpoint URL`
  - `Brum Site ID` versus `BasicRUM Site ID`
- [ ] Give the Magento configuration page a clearer Basicrum identity while
  retaining native Magento administration patterns.
- [ ] Improve complex help content for narrower admin viewports.
- [ ] Replace `docs/media/admin-area.png` after the admin UI work is complete; the
  checked-in screenshot no longer represents the current settings UI.

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

- [ ] Decide and implement the Magento automatic-consent integration strategy.
- [ ] Add integration status and diagnostics if automatic integration is supported.
- [ ] Improve manual integration examples and copy actions.
- [ ] Resolve Strip Query Strings and HTTP-policy parity.

### P2 — refinement

- [ ] Resolve Track Admin Users applicability.
- [ ] Align terminology and branding.
- [ ] Review narrow-viewport presentation.
- [ ] Update the admin screenshot after the UI stabilizes.

## Completion criteria

- [ ] A new administrator can tell whether monitoring is active, inactive, or
  blocked by incomplete configuration without reading source code.
- [x] Consent-controlled mode clearly explains what the external consent tool must
  do and shows only relevant instructions.
- [x] Immediate mode does not display consent-integration instructions.
- [x] Required configuration errors are visible at the affected fields.
- [x] Magento configuration scopes continue to work at all supported levels.
- [ ] Platform-specific differences are documented and intentional.
- [ ] Updated screenshots match the shipped admin UI.
