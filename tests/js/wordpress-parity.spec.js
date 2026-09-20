const fs = require("node:fs");
const path = require("node:path");
const { createHash } = require("node:crypto");
const { test, expect } = require("@playwright/test");
const UglifyJS = require("uglify-js");

const root = path.resolve(__dirname, "../..");
// Review changes in WordPress first, then update this baseline deliberately.
// https://github.com/basicrum/basicrum-wordpress/tree/64f19d9e5a9fbe580c12c19796e86e3ad0dd17ff
const revision = "64f19d9e5a9fbe580c12c19796e86e3ad0dd17ff";
const hashes = {
  "loaders/boomerang-loader-v15.js": "e22055fc1919b89ab8ba6530a415636c6d31df328c10f096a500ae5a37e93d6d",
  "loaders/boomerang-loader-v15.min.js": "a9c283722d1d2eb97a7e1820d51ba3c317a5f2641f7358922931ae305aab0281",
  "loaders/consent-boomerang-loader-v1-15.js": "23163a2f6b51db6010ae415762427105b21ca27da5ff6346a5ca6b9a5f8f6f4c",
  "loaders/consent-boomerang-loader-v1-15.min.js": "2f8b929b49a7e72b9b7531385c3fd981395cc767bea0f3a21f0c3e2658ce8bb1",
  "boomr/boomerang-1.815.60.cutting-edge.min.js": "90e8a1c85949b10d43e441efc3f0545f95e4384e26ee3042344a8b2b4110589c"
};

function sha256(contents) {
  return createHash("sha256").update(contents).digest("hex");
}

function readMagentoAsset(asset) {
  return fs.readFileSync(path.join(root, "js/basicrum", asset.replace(/^boomr\//, "boomerangs/")), "utf8");
}

// Exact, reviewed additions only. Do not strip arbitrary marked blocks: that
// would let unrelated edits evade the upstream hash check. These additions
// retain Magento's existing compatibility and delayed-beacon protections.
const magentoAdditions = [
  {
    before: "      mainWin.basicRumBoomerangConfig = null;\n",
    addition: "      mainWin.basicRumConsentWithdrawn = true;\n",
    after: "    }\n"
  },
  {
    before: "    if (mainWin.BOOMR) {\n",
    addition: `      var waitPlugin = mainWin.BOOMR.plugins && mainWin.BOOMR.plugins.WaitAfterOnload;
      if (waitPlugin && waitPlugin.timer !== null) {
        mainWin.clearTimeout(waitPlugin.timer);
        waitPlugin.timer = null;
      }

`,
    after: '      if (typeof mainWin.BOOMR.disable === "function") {\n'
  },
  {
    before: '        mainWin.BOOMR.utils.removeCookie("BA");\n',
    addition: `        mainWin.BOOMR.utils.removeCookie("BRUM_CONSENT");
        mainWin.BOOMR.utils.removeCookie("BOOMR_CONSENT");
`,
    after: "      }\n"
  },
  {
    before: '    removeCookie("BA");\n',
    addition: `    removeCookie("BRUM_CONSENT");
    removeCookie("BOOMR_CONSENT");
`,
    after: "  };\n"
  },
  {
    before: "  };\n",
    addition: `
  // Backward-compatible aliases used by earlier Magento 1 integrations.
  mainWin.OPT_IN_BASIC_RUM = mainWin.OPT_IN_BASICRUM_LOADER_WRAPPER;
  mainWin.OPT_OUT_BASIC_RUM = mainWin.OPT_OUT_BASICRUM_LOADER_WRAPPER;
`,
    after: "})(window);\n"
  }
];

for (const asset of [
  "loaders/boomerang-loader-v15.js",
  "loaders/boomerang-loader-v15.min.js",
  "boomr/boomerang-1.815.60.cutting-edge.min.js"
]) {
  test(`WordPress baseline: ${asset}`, () => {
    expect(sha256(readMagentoAsset(asset)), `WordPress ${revision}: ${asset}`).toBe(hashes[asset]);
  });
}

test("consent wrapper embeds the unchanged WordPress standard loader", () => {
  const consent = readMagentoAsset("loaders/consent-boomerang-loader-v1-15.js");
  const wrapped = consent.match(
    /\/\* BEGIN BASICRUM STANDARD LOADER \*\/\n([\s\S]*?)\n    \/\* END BASICRUM STANDARD LOADER \*\//
  );
  expect(wrapped).not.toBeNull();
  expect(wrapped[1]).toBe(readMagentoAsset("loaders/boomerang-loader-v15.js"));
});

test("consent wrapper differs from WordPress only by reviewed Magento additions", () => {
  let consent = readMagentoAsset("loaders/consent-boomerang-loader-v1-15.js");
  for (const { before, addition, after } of magentoAdditions) {
    const parts = consent.split(before + addition + after);
    expect(parts.length, `Expected exactly one Magento addition: ${addition}`).toBe(2);
    consent = parts.join(before + after);
  }
  expect(sha256(consent)).toBe(hashes["loaders/consent-boomerang-loader-v1-15.js"]);

  const minified = UglifyJS.minify(consent, {
    compress: false,
    mangle: true,
    output: { comments: /^!/ }
  });
  expect(minified.error).toBeUndefined();
  expect(sha256(minified.code)).toBe(hashes["loaders/consent-boomerang-loader-v1-15.min.js"]);
});

// Optional local cross-check; ordinary CI needs neither a sibling repository
// nor network access. Point this at the reviewed WordPress checkout.
if (process.env.BASICRUM_WORDPRESS_ROOT) {
  test("pinned baseline matches the supplied WordPress checkout", () => {
    for (const [asset, hash] of Object.entries(hashes)) {
      const contents = fs.readFileSync(path.join(
        process.env.BASICRUM_WORDPRESS_ROOT, "plugins/basicrum/assets/js", asset
      ));
      expect(sha256(contents), `WordPress ${revision}: ${asset}`).toBe(hash);
    }
  });
}
