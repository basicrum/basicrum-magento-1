# GitHub Copilot Instructions for BasicRum Analytics (Magento 1)

## Module Purpose
This module integrates **Boomerang.js** (Real User Monitoring) into Magento 1 stores to capture frontend performance analytics. It sends beacon data to a configurable endpoint for analysis via the BasicRUM platform.

### Key Features
- **RUM Data Collection**: Captures page load timing, resource timing, and continuity metrics.
- **GDPR/Privacy Compliance**: Supports opt-in mode for cookie consent requirements.
- **Configurable Beacon Endpoint**: Admin can specify where analytics data is sent.
- **Async Loading**: Boomerang JS loads asynchronously to minimize performance impact.

## Project Context
- **Framework**: Magento 1 (OpenMage LTS) / Modernized M1.
- **Module Name**: `BasicRum_Analytics`
- **Module Version**: `1.0.0`
- **Code Pool**: `community`
- **Deployment**: Uses `modman` for file mapping.

## Coding Standards & Environment
- **PHP Version**: **PHP 7+** (must remain compatible with PHP 7 while also working on the latest PHP 8.x). 
  - Use `declare(strict_types=1);`.
  - Use return type declarations compatible with PHP 7.
  - Avoid PHP 8-only features (attributes like `#[\Override]`, union types, `str_starts_with`, etc.).
- **Architecture**:
  - Follow Magento 1 class naming conventions (e.g., `BasicRum_Analytics_Block_Boomerang_Loader`).
  - **No Namespaces**: Do not use PHP namespaces; use underscores for class separation.
  - Use `Mage::` static factory methods (`Mage::getModel()`, `Mage::helper()`, `Mage::getSingleton()`).

## Module Architecture

### Directory Structure
```
app/code/community/BasicRum/Analytics/
├── Block/
│   └── Boomerang/
│       └── Loader.php              # Renders JS snippet in footer
├── Helper/
│   ├── Data.php                    # Config retrieval methods
│   └── PageTypeDetector.php        # Layout handle-based page type detection
└── etc/
    ├── config.xml                  # Module registration & defaults
    ├── system.xml                  # Admin configuration fields
    └── adminhtml.xml               # Admin ACL/menu

js/basicrum/
├── boomerangs/
│   └── boomerang-1.815.60.cutting-edge.min.js   # Core Boomerang library
└── loaders/
    ├── boomerang-loader-v15.js                  # Standard async loader (dev)
    ├── boomerang-loader-v15.min.js              # Standard async loader (prod)
    ├── consent-boomerang-loader-v1-15.js        # GDPR opt-in loader (dev)
    └── consent-boomerang-loader-v1-15.min.js    # GDPR opt-in loader (prod)
```

### Key Classes

| Class | Purpose |
|-------|---------|
| `BasicRum_Analytics_Block_Boomerang_Loader` | Generates the Boomerang JS inline script. Injected into `footer` reference. |
| `BasicRum_Analytics_Helper_Data` | Retrieves admin config values: `isEnabled()`, `isOptInRequired()`, `getBeaconEndpoint()`, `useUnminifiedLoaders()`. |
| `BasicRum_Analytics_Helper_PageTypeDetector` | Detects page type from layout handles (home, product, category, etc.). |

### Configuration Paths
Access via `Mage::getStoreConfig()` or `Mage::getStoreConfigFlag()`:

| Path | Type | Description |
|------|------|-------------|
| `basicrum_analytics/general/enabled` | bool | Enable/disable the module |
| `basicrum_analytics/general/opt_in_required` | bool | Use opt-in loader for GDPR compliance |
| `basicrum_analytics/general/beacon_endpoint` | string | URL where beacons are sent |
| `basicrum_analytics/wait_after_onload/enabled` | bool | Enable delayed beacon sending |
| `basicrum_analytics/wait_after_onload/wait_ms` | int | Milliseconds to wait before sending beacon |
| `basicrum_analytics/developer/use_unminified_loaders` | bool | Load non-minified JS for debugging |

### JavaScript Assets
Located in `js/basicrum/` and symlinked to Magento's `public/js/basicrum/`:

| File | Purpose |
|------|---------|
| `boomerangs/boomerang-1.815.60.cutting-edge.min.js` | Core Boomerang library |
| `loaders/boomerang-loader-v15.min.js` | Standard async loader (production) |
| `loaders/boomerang-loader-v15.js` | Standard async loader (development) |
| `loaders/consent-boomerang-loader-v1-15.min.js` | GDPR-compliant loader (production) |
| `loaders/consent-boomerang-loader-v1-15.js` | GDPR-compliant loader (development) |

### Layout Integration
The block is added to the `footer` reference in `basicrum_analytics.xml`:
```xml
<reference name="footer">
    <block type="basicrum_analytics/boomerang_loader" name="boomerang" />
</reference>
```

## Specific Instructions
1. **Modman**: When adding new files, always verify if the `modman` file needs updating to map the file from the source to the Magento root.
2. **Layouts**: Layout updates reside in `app/design/frontend/base/default/layout/`.
3. **JS/CSS**: Static assets are symlinked from `js/basicrum/` to the Magento root `public/js/` folder.
4. **Configuration**:
   - `config.xml`: Module version, models, blocks, helpers, events.
   - `system.xml`: Backend configuration fields (ACL, Scope).
   - `adminhtml.xml`: Admin menu items and ACL resources.

## Important Patterns
- **Helpers**: Always access helpers via `Mage::helper('basicrum_analytics')`.
- **Config Flags**: Use `Mage::getStoreConfigFlag()` for boolean values.
- **Config Values**: Use `Mage::getStoreConfig()` for string/text values.
- **Logs**: Use `Mage::log()` for debugging, ensuring the log file is specified if necessary.
- **Translations**: Wrap user-facing strings in `$this->__('String')`.
- **Inline Scripts**: Use HEREDOC syntax for multi-line JavaScript generation.

## Boomerang Configuration
The module configures Boomerang with these settings:
- `beacon_url`: From admin config.
- `instrument_xhr`: Enabled for XHR tracking.
- `Continuity.enabled`: Tracks user interaction metrics.
- `ResourceTiming.enabled`: Captures resource load times.
- `secure_cookie` & `same_site_cookie`: Set to `true` and `"Strict"` for security.

## File Headers
Ensure all new PHP files start with:
```php
<?php
declare(strict_types=1);
```

## Extending the Module
When adding new features:
1. **New Config Options**: Add fields to `system.xml`, defaults to `config.xml`, accessor methods to `Helper/Data.php`.
2. **New Blocks**: Create in `Block/` directory, register in `config.xml` under `<blocks>`.
3. **Page Type Detection**: Handled by `Helper/PageTypeDetector.php` using layout handles. Add new mappings there if needed.
