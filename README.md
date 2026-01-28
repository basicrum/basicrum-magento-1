# BasicRUM Analytics module for Magento 1

The BasicRUM Analytics module provides analytics integration with BasicRUM for Magento 1 stores.

## Installation

The module can be installed manually by copying files or by using Modman.

### Option 1: Modman (Recommended)

```bash
cd /path/to/magento
modman clone https://github.com/basicrum/basicrum-magento-1.git
```

### Option 2: Manual Installation

1. Copy the module files to your Magento installation:
   - Copy `app/code/community/BasicRum/Analytics` to your Magento installation's `app/code/community` directory
   - Copy `app/etc/modules/BasicRum_Analytics.xml` to your Magento installation's `app/etc/modules` directory
   - Copy `app/design/frontend/base/default/layout/basicrum_analytics.xml` to your Magento installation's `app/design/frontend/base/default/layout` directory
   - Copy `js/basicrum` to your Magento installation's `js` directory

### Post-Installation

1. Clear Magento cache:
   - Go to System > Cache Management in the admin panel
   - Click "Flush Magento Cache"

2. Verify the module is enabled:
   - Go to System > Configuration > Advanced > Advanced
   - Look for "BasicRum Analytics" in the list of modules

## Configuration

The module configuration can be found in:
- System > Configuration > BasicRum Analytics

## Page Type Detection

The module sends `p_type` to Boomerang based on Magento layout handles. Known handles are mapped
to friendly page types (e.g., `cms_index_index` → `home`, `catalog_product_view` → `product`).
If no known handle matches, the first non-generic handle is sent as `unmapped_{handle}`.

## Developer

### Minifying Loader Scripts

Use UglifyJS to minify the Boomerang loader scripts with IE compatibility:

```bash
# Standard loader
npx uglify-js js/basicrum/loaders/boomerang-loader-v15.js \
  --mangle \
  --compress sequences=false,ie=true \
  --output js/basicrum/loaders/boomerang-loader-v15.min.js

# Consent loader (GDPR opt-in)
npx uglify-js js/basicrum/loaders/consent-boomerang-loader-v1-15.js \
  --mangle \
  --compress sequences=false,ie=true \
  --output js/basicrum/loaders/consent-boomerang-loader-v1-15.min.js
```

## Version

1.0.0
