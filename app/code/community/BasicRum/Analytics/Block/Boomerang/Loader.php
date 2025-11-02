<?php
declare(strict_types=1);

/**
 * BasicRUM Analytics Boomerang Loader Block
 */
class BasicRum_Analytics_Block_Boomerang_Loader extends Mage_Core_Block_Abstract
{

    #[\Override]
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

        // 1. Add anti-tampering technique.
        $beaconEndpoint = $helper->getBeaconEndpoint();
        $boomerangJsUrl = Mage::getBaseUrl("js") . "basicrum/boomerang-1.815.60.cutting-edge.min.js";

        if ($helper->isOptInRequired()) {
            $loaderScriptUrl = Mage::getBaseUrl("js") . "basicrum/opt-in-boomerang-loader-v1-15.js";
        } else {
            $loaderScriptUrl = Mage::getBaseUrl("js") . "basicrum/boomerang-loader-v15.js";
        }


        $pageType = "test";

        return <<<SCRIPT
<script type="text/javascript">
(function(w) {
    var WAIT_AFTER_ONLOAD_MILLISECONDS = 5000;

    if (!w) {
        return;
    }

    w.BOOMR_mq = window.BOOMR_mq || [];

    w.BOOMR_mq.push(
        ["addVar", "p_type", "{$pageType}"],
        ["addVar", "p_gen", "mage1"]
    );

    w.BOOMR = (w.BOOMR !== undefined) ? w.BOOMR :  {};

    var b = w.BOOMR;
    
    b.url = "{$boomerangJsUrl}"; 
    
    b.plugins = b.plugins || {};

    b.plugins.WaitAfterOnload = {
        complete: false,

        init: function() {
            b.subscribe("page_ready", function() {
                setTimeout(function() {
                    this.complete = true;
                    b.sendBeacon();
                }.bind(this), WAIT_AFTER_ONLOAD_MILLISECONDS);
            }, {}, this);
        },

        is_complete: function() {
            return this.complete;
        }
    };

    w.basicRumBoomerangConfig = {
        beacon_url: "{$beaconEndpoint}",
        instrument_xhr: true,
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
