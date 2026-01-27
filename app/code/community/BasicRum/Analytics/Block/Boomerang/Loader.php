<?php
declare(strict_types=1);

/**
 * BasicRUM Analytics Boomerang Loader Block
 */
class BasicRum_Analytics_Block_Boomerang_Loader extends Mage_Core_Block_Abstract
{

    protected function _toHtml() : string
    {
        return $this->getBoomerangSnippet();
    }

    /**
     * Get the Boomerang JS snippet
     * @return string
     */
    public function getBoomerangSnippet(): string
    {
        /** @var BasicRum_Analytics_Helper_Data $helper */
        $helper = Mage::helper('basicrum_analytics');

        if (!$helper->isEnabled()) {
            return '';
        }

        // 1. Add anti-tampering technique.
        $beaconEndpoint = $helper->getBeaconEndpoint();
        if ($beaconEndpoint === null || trim($beaconEndpoint) === '') {
            return '';
        }

        $boomerangJsUrl = Mage::getBaseUrl("js") . "basicrum/boomerangs/boomerang-1.815.60.cutting-edge.min.js";

        $loaderSuffix = $helper->useUnminifiedLoaders() ? '.js' : '.min.js';

        if ($helper->isOptInRequired()) {
            $loaderScriptUrl = Mage::getBaseUrl("js") . "basicrum/loaders/consent-boomerang-loader-v1-15" . $loaderSuffix;
        } else {
            $loaderScriptUrl = Mage::getBaseUrl("js") . "basicrum/loaders/boomerang-loader-v15" . $loaderSuffix;
        }

        $pageType = $helper->getPageType();
        $waitAfterOnloadEnabled = $helper->isWaitAfterOnloadEnabled();
        $waitAfterOnloadMilliseconds = $helper->getWaitAfterOnloadMilliseconds();

        $boomerangVars = [
            ["addVar", "p_type", $pageType],
            ["addVar", "p_gen", "mage1"]
        ];
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        $boomerangVarsJs = json_encode($boomerangVars, $jsonFlags);

        $waitAfterOnloadScript = '';
        if ($waitAfterOnloadEnabled) {
            $waitAfterOnloadScript = <<<SCRIPT
    b.plugins = b.plugins || {};

    b.plugins.WaitAfterOnload = {
        complete: false,

        init: function() {
            b.subscribe("page_ready", function() {
                setTimeout(function() {
                    this.complete = true;
                    b.sendBeacon();
                }.bind(this), {$waitAfterOnloadMilliseconds});
            }, {}, this);
        },

        is_complete: function() {
            return this.complete;
        }
    };

SCRIPT;
        }

        return <<<SCRIPT
<script type="text/javascript">
(function(w) {

    if (!w) {
        return;
    }

    w.BOOMR_mq = window.BOOMR_mq || [];

    w.BOOMR_mq.push.apply(w.BOOMR_mq, {$boomerangVarsJs});

    w.BOOMR = (w.BOOMR !== undefined) ? w.BOOMR :  {};

    var b = w.BOOMR;
    
    b.url = "{$boomerangJsUrl}";

{$waitAfterOnloadScript}

    w.basicRumBoomerangConfig = {
        beacon_url: "{$beaconEndpoint}",
        instrument_xhr: false,
        Continuity: {
            enabled: true
        },
        ResourceTiming: {
            "enabled": true,
            "splitAtPath": true
        },
        secure_cookie: true,
        same_site_cookie: "Strict"
    }
})(window);
(function(d, s) {
  var js = d.createElement(s),
      sc = d.getElementsByTagName(s)[0];

  js.src="{$loaderScriptUrl}";
  js.async = true;

  sc.parentNode.insertBefore(js, sc);
}(document, "script"));
</script>

SCRIPT;
    }
}
