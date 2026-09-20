# Basicrum Analytics for Magento 1

Basicrum Analytics integrates Magento 1, OpenMage LTS, and Maho Commerce stores with a Basicrum collector using the bundled Boomerang Real User Monitoring library.

## Requirements

- Magento 1.x, OpenMage LTS, or Maho Commerce
- PHP 7.0 or newer
- A Beacon URL and matching Brum Site ID supplied by the Basicrum backend

## Installation

### Modman

```bash
cd /path/to/magento
modman clone https://github.com/basicrum/basicrum-magento-1.git
```

### Manual installation

Copy these paths into the matching locations under the Magento root:

- `app/code/community/BasicRum/Analytics`
- `app/etc/modules/BasicRum_Analytics.xml`
- `app/design/frontend/base/default/layout/basicrum_analytics.xml`
- `app/locale/en_US/BasicRum_Analytics.csv`
- `js/basicrum`

Clear Magento configuration and layout caches after installation or upgrade.
On Maho, place `js/basicrum` under `public/js/basicrum` and run `composer dump-autoload` after deploying the PHP files so Maho rebuilds its module class map.

## Configuration

Go to **System > Configuration > Basicrum > Basicrum Settings**. Configuration remains available at Magento's default, website, and store scopes.

**Enable Basicrum** defaults to **No (disabled)**. General Settings includes the collector identity, **Strip Query Strings**, **Require Consent Before Monitoring**, and consent integration guidance. The privacy controls retain their existing `basicrum_analytics/privacy/*` configuration paths and scoped values; only their placement in the admin changes.

Monitoring scripts are emitted only when all of these conditions are met:

- **Enable Basicrum** is set to Yes.
- **Beacon URL** is a valid HTTP or HTTPS URL.
- **Brum Site ID** is a valid RFC 4122 UUID v4.

Both identity values are mandatory. Runtime validation is performed again when rendering, so missing, malformed, or programmatically injected values fail closed even if they bypass the admin backend models. Dynamic JavaScript values are JSON encoded with HTML-significant characters escaped.

The status panel reports whether monitoring is Disabled, Blocked by invalid or incomplete identity configuration, Waiting for consent, or Active in immediate mode.

When **Enable Basicrum** is set to No, Magento keeps the bundled Boomerang version, Beacon URL, and Brum Site ID visible so an administrator can prepare or inspect the identity configuration before enabling monitoring. Privacy, wait, and developer runtime controls are hidden and disabled through Magento's native field dependencies. Their stored default, website, and store-view values are retained and reappear when monitoring is enabled; normal scope inheritance and **Use Default/Use Website** behavior are unchanged.

HTTPS Beacon URLs are enforced by default. The Developer setting **HTTP Strictness** can allow HTTP only for local testing; do not enable it on production stores. When strict mode is active, an HTTP URL saved through the admin is upgraded to HTTPS, and runtime rendering applies the same upgrade to values injected outside the admin path. HTTPS storefronts always upgrade HTTP Beacon URLs to HTTPS to prevent mixed-content blocking, even when HTTP is allowed by the selected policy.

### Query-string privacy

**Strip Query Strings** controls Boomerang's native URL redaction. It remains disabled by default to preserve the established Magento 1 behavior and match the WordPress default. When enabled, complete query strings in page, navigation, referrer, and resource URLs are replaced with `?qs-redacted` before beacons are sent; URL paths remain available for performance analysis.

This setting does not modify query parameters in the configured Beacon URL. Those parameters are part of the collector destination and continue to be safely serialized unchanged.

### Consent-controlled loading

**Require Consent Before Monitoring** is the privacy-first default for new installations. In this mode the loader remains inert until an external consent tool explicitly calls the opt-in callback on the current page:

```javascript
if (typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER === 'function') {
    window.OPT_IN_BASICRUM_LOADER_WRAPPER();
}
```

Call the opt-out callback whenever monitoring is denied or withdrawn:

```javascript
if (typeof window.OPT_OUT_BASICRUM_LOADER_WRAPPER === 'function') {
    window.OPT_OUT_BASICRUM_LOADER_WRAPPER();
}
```

Basicrum does not show a consent dialog, determine the site's legal basis, persist a consent choice, or trust a decision from an earlier page. The external consent tool remains the source of truth and must signal the current decision on each page.

For backward compatibility, existing Magento integrations may continue calling:

- `window.OPT_IN_BASIC_RUM()`
- `window.OPT_OUT_BASIC_RUM()`

They are aliases of the canonical callbacks above.

Repeated opt-in calls inject Boomerang only once. Opt-out before the first opt-in clears RUM and legacy consent cookies but retains the in-page configuration, so a later allow decision on that page can start monitoring. Opt-out during download neutralizes the configuration before the bundle can initialize. Opt-out after initialization disables Boomerang. Once loading has started and consent is withdrawn, re-granting does not restart monitoring on that page; reload the page and let the external tool report the new allow decision. Opt-out stops future browser collection but cannot retract beacon data already sent to the configured collector.

When monitoring runs, Boomerang sets a first-party `RT` cookie at path `/`. It contains a random session identifier that links monitored page views, uses a 30-minute session window, and has a rolling seven-day expiry. It uses `SameSite=Strict` and is marked `Secure` on HTTPS sites. `BA` is a legacy Boomerang cookie. Opt-out removes `RT`, `BA`, and the legacy `BRUM_CONSENT` and `BOOMR_CONSENT` cookies across applicable host-domain paths. The extension never creates either consent cookie.

### Upgrade behavior in 1.1.0

Version 1.1.0 introduces a versioned Magento setup resource for the privacy default:

- A genuinely new installation with no `basicrum_analytics/*` rows in `core_config_data` gets an explicit default-scope `opt_in_required=1`.
- An upgraded store with an existing Basicrum configuration footprint and no explicit default-scope consent value gets `opt_in_required=0`, preserving the historical immediate-monitoring behavior.
- An existing explicit default-scope consent value is never overwritten. Website and store overrides continue to inherit or override through normal Magento scope rules.
- Existing HTTP Beacon URLs keep their policy after upgrade: for every explicit Beacon URL, the installer records a matching policy at the same scope (`HTTP` remains allowed and `HTTPS` remains strict) unless that scope already contains an explicit policy decision. Descendant scopes continue to inherit normally, and new installations remain HTTPS-strict. As before, HTTPS storefronts upgrade HTTP Beacon URLs to HTTPS regardless of that policy.
- A previously installed but never configured and disabled module is treated like a new installation; this cannot start monitoring because **Enable**, Beacon URL, and Site ID are still required.

The migration policies live in `BasicRum_Analytics_Model_Setup_PrivacyDefault` and `BasicRum_Analytics_Model_Setup_HttpPolicyDefault` and are covered by the PHP and native-platform test harnesses.

Two 1.1.0 changes can intentionally stop monitoring until configuration or consent integration is corrected:

- Beacon URL and Brum Site ID are now both mandatory, and the Site ID must be a UUID v4. A store with an empty or previously accepted non-v4 Site ID emits no monitoring scripts until a valid backend identifier is saved.
- Basicrum no longer resumes from a `BRUM_CONSENT` cookie. In consent-controlled mode, the external consent tool must call the opt-in callback on every page after the Basicrum loader has registered it near the end of the document. Calls made before registration are not queued or replayed.

### Wait After Onload

Enable this option to delay the page-load beacon while collecting additional metrics. The canonical configuration path is:

```text
basicrum_analytics/wait_after_onload/wait_ms
```

Values are clamped to 0–30000 milliseconds. The older mismatched `ms` default key is no longer used.

### Magento-specific administrator and script behavior

WordPress can exclude logged-in users with the `manage_options` capability because its administrators and storefront visitors share the same user system. Magento admin users authenticate in the separate `adminhtml` application and do not have a reliable frontend identity. Basicrum is not emitted on Magento admin pages, and a backend user visiting the storefront is indistinguishable from any other storefront visitor without initializing an admin session in the frontend. For that reason Magento 1 does not expose a misleading **Track Admin Users** setting. Stores that need staff-traffic exclusion should use collector-side rules or a separately designed frontend signal.

Magento inserts the configuration and async loader in the native `before_body_end` layout reference. This is the documented, fixed equivalent of WordPress's default footer placement. A Header/Footer selector is intentionally not provided: moving the consent loader to the header would alter registration timing and could start immediate-mode downloads earlier, while Magento themes do not provide a single portable header insertion point equivalent to WordPress's `wp_head`.

## Page type compatibility

Magento 1 continues to emit its existing `p_type` values in this phase (for example, `Home`, `Product`, and `404 Not Found`). These values are not schema-compatible with the current WordPress and Magento 2 values. They are intentionally unchanged to avoid breaking existing Magento 1 reporting; normalization requires a later coordinated schema-migration phase.

## Automated verification

PHP configuration and rendering tests use a lightweight Magento compatibility harness and do not require a full Magento installation:

```bash
php tests/php/run.php
```

Browser tests use Playwright and exercise both source and minified loaders in Chromium, including immediate loading, current-page consent, repeated opt-in, and opt-out before loading, during download, and after initialization:

```bash
npm ci
npx playwright install chromium
npm test
```

The WordPress loaders are the source of truth. `tests/js/wordpress-parity.spec.js`
pins their source/minified SHA-256 hashes and the Boomerang bundle to WordPress
commit `64f19d9e5a9fbe580c12c19796e86e3ad0dd17ff`. It also requires the consent
wrapper to embed the standard loader byte-for-byte. These checks run in the
normal CI suite without a WordPress checkout or network access.

The only allowed consent-wrapper additions are Magento's legacy callback
aliases, legacy consent-cookie cleanup, and its existing Wait After Onload
timer cancellation/withdrawal guard. The parity test removes only these exact
additions before checking the WordPress hash; unexpected differences fail.
General loader behavior changes should be reviewed in WordPress first, then
ported here with the baseline updated explicitly. Do not remove Magento's
existing withdrawal protection just to match the current WordPress wrapper.

To also verify the baseline against a local WordPress checkout:

```bash
BASICRUM_WORDPRESS_ROOT=/path/to/basicrum-wordpress npm test
```

When updating the baseline, compare both WordPress loaders, retain only the
documented Magento additions, regenerate the minified files, update the pinned
revision/hashes and notices, and run the browser suite against the real bundle.
Script placement and callback registration timing remain Magento-specific;
this port does not add automatic consent-provider adapters or change the
underlying Boomerang shutdown behavior.

Run XML, Modman, Boomerang checksum, and temporary package-archive verification with:

```bash
bash tests/check-package.sh
```

Regenerate both minified loaders after changing either source file:

```bash
npm run build:loaders
```

GitHub Actions runs PHP syntax/tests on PHP 7.0, 7.4, and 8.3, the Playwright suite, XML validation, and packaging checks.

It also installs the extension into a real application and boots the storefront for this pinned compatibility matrix:

| Platform | Runtime | Coverage |
|----------|---------|----------|
| Magento CE 1.9.4.5 | PHP 7.4 | Native setup resource, configuration/rendering, scopes, and live storefront |
| OpenMage 20.18.0 | PHP 8.3 | Native setup resource, configuration/rendering, scopes, and live storefront |
| Maho 26.9.0 | PHP 8.3 | Native setup resource, admin rendering with global Varien aliases disabled, configuration/rendering, scopes, and live storefront |

The real-install jobs exercise a fresh privacy-first installation, storefront-triggered upgrades from a simulated pre-1.1.0 database with and without explicit consent or legacy HTTP behavior, incomplete and unsafe configuration, HTTPS enforcement and development HTTP mode, native admin saves with explicit/omitted/newly inherited HTTP policy at default/website/store scopes, immediate and consent-controlled rendering, query-string privacy, the 30-second wait cap, default/website/store inheritance, frontend and admin block resolution, callback-compatible loader delivery, and disabled-mode suppression. Platform versions are deliberately pinned so upstream releases cannot silently change the test baseline; updates should be made explicitly after local validation.

For a local run, provide a disposable platform checkout and an empty MariaDB database, then run—for example—`bash tests/platform/run.sh openmage /path/to/openmage`. The default database is `basicrum` at `127.0.0.1` with username and password `basicrum`; override it with `BASICRUM_TEST_DB_HOST`, `BASICRUM_TEST_DB_NAME`, `BASICRUM_TEST_DB_USER`, and `BASICRUM_TEST_DB_PASSWORD`. The runner deploys the extension into the checkout and installs the application, so neither target should contain data that must be preserved.

## Bundled Boomerang provenance

The bundled `js/basicrum/boomerangs/boomerang-1.815.60.cutting-edge.min.js` is byte-identical to the WordPress bundle and has SHA-256:

```text
90e8a1c85949b10d43e441efc3f0545f95e4384e26ee3042344a8b2b4110589c
```

It was built from commit `ead2783a33a2ce91205fe34f8fc992433faba9a2` in the [Basicrum Boomerang fork](https://github.com/basicrum/boomerang), based on [Akamai Boomerang](https://github.com/akamai/boomerang). The embedded banner identifies parent commit `564759ed70de7801bb64de5e2025fb6ac049ff5f` because the final source change was uncommitted when that artifact was generated. Reproducible-build and fork-change details are in [THIRD-PARTY-NOTICES.txt](THIRD-PARTY-NOTICES.txt).

## License

Basicrum-owned code is licensed under the GNU General Public License version 2 or later; see [LICENSE.md](LICENSE.md). Bundled Boomerang retains its BSD license in [js/basicrum/LICENSE.txt](js/basicrum/LICENSE.txt).

## Version

1.1.0
