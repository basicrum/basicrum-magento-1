const path = require("node:path");
const { test, expect } = require("@playwright/test");

const root = path.resolve(__dirname, "../..");
const shopUrl = "https://shop.example.test/";
const boomerangUrl = "https://assets.example.test/boomerang.js";
const bundleStub = `
window.__bundleExecutions = (window.__bundleExecutions || 0) + 1;
window.BOOMR = window.BOOMR || {};
window.BOOMR.version = "test";
window.BOOMR.window = window;
window.BOOMR.init = function(config) {
  window.__initCalls = (window.__initCalls || 0) + 1;
  window.__lastConfig = config;
};
window.BOOMR.disable = function() {
  window.__disableCalls = (window.__disableCalls || 0) + 1;
};
window.BOOMR.utils = {
  removeCookie: function(name) {
    window.__utilityCookieRemovals = window.__utilityCookieRemovals || [];
    window.__utilityCookieRemovals.push(name);
  }
};
window.basicRumInitConfig = window.basicRumBoomerangConfig;
if (window.basicRumInitConfig) {
  window.BOOMR.init(window.basicRumInitConfig);
}
`;

function loaderPath(file) {
  return path.join(root, "js/basicrum/loaders", file);
}

async function preparePage(page, options = {}) {
  let releaseDownload;
  let markDownloadStarted;
  const downloadGate = options.holdDownload
    ? new Promise((resolve) => { releaseDownload = resolve; })
    : Promise.resolve();
  const downloadStarted = new Promise((resolve) => { markDownloadStarted = resolve; });

  await page.route(shopUrl, (route) => route.fulfill({
    contentType: "text/html",
    body: "<!doctype html><html><head></head><body></body></html>"
  }));
  await page.route(boomerangUrl, async (route) => {
    markDownloadStarted();
    await downloadGate;
    await route.fulfill({ contentType: "application/javascript", body: bundleStub });
  });

  if (options.cookies) {
    await page.context().addCookies(options.cookies.map((name) => ({
      name,
      value: "legacy",
      domain: "shop.example.test",
      path: "/"
    })));
  }

  await page.goto(shopUrl);
  await page.evaluate((url) => {
    window.BOOMR = { url };
    window.basicRumBoomerangConfig = { beacon_url: "https://collector.example.test/beacon" };
    window.__initCalls = 0;
    window.__bundleExecutions = 0;
    window.__disableCalls = 0;
    window.__utilityCookieRemovals = [];
  }, boomerangUrl);

  return {
    downloadStarted,
    releaseDownload: () => releaseDownload && releaseDownload()
  };
}

for (const standardLoader of ["boomerang-loader-v15.js", "boomerang-loader-v15.min.js"]) {
  test(`immediate loader executes Boomerang once: ${standardLoader}`, async ({ page }) => {
    await preparePage(page);
    await page.addScriptTag({ path: loaderPath(standardLoader) });
    await page.waitForFunction(() => window.__initCalls === 1);

    await page.addScriptTag({ path: loaderPath(standardLoader) });
    await page.waitForTimeout(100);

    await expect.poll(() => page.evaluate(() => ({
      initCalls: window.__initCalls,
      executions: window.__bundleExecutions
    }))).toEqual({ initCalls: 1, executions: 1 });
  });
}

for (const consentLoader of [
  "consent-boomerang-loader-v1-15.js",
  "consent-boomerang-loader-v1-15.min.js"
]) {
  test.describe(`consent wrapper: ${consentLoader}`, () => {
    test("stays inert despite a legacy allow cookie", async ({ page }) => {
      await preparePage(page, { cookies: ["BRUM_CONSENT"] });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.waitForTimeout(150);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        canonicalIn: typeof window.OPT_IN_BASICRUM_LOADER_WRAPPER,
        canonicalOut: typeof window.OPT_OUT_BASICRUM_LOADER_WRAPPER,
        legacyIn: typeof window.OPT_IN_BASIC_RUM,
        legacyOut: typeof window.OPT_OUT_BASIC_RUM
      }))).toEqual({
        initCalls: 0,
        executions: 0,
        canonicalIn: "function",
        canonicalOut: "function",
        legacyIn: "function",
        legacyOut: "function"
      });
    });

    test("repeated opt-in loads only once and persists no consent cookie", async ({ page }) => {
      await preparePage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => {
        window.OPT_IN_BASICRUM_LOADER_WRAPPER();
        window.OPT_IN_BASIC_RUM();
      });
      await page.waitForFunction(() => window.__initCalls === 1);
      await page.waitForTimeout(100);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        cookies: document.cookie
      }))).toEqual({ initCalls: 1, executions: 1, cookies: "" });
    });

    test("opt-out before loading clears legacy cookies and still permits a later allow", async ({ page }) => {
      const cookieNames = ["RT", "BA", "BRUM_CONSENT", "BOOMR_CONSENT"];
      await preparePage(page, { cookies: cookieNames });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_OUT_BASIC_RUM());

      expect(await page.evaluate(() => ({
        cookies: document.cookie,
        configPresent: Boolean(window.basicRumBoomerangConfig),
        executions: window.__bundleExecutions
      }))).toEqual({ cookies: "", configPresent: true, executions: 0 });

      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.waitForFunction(() => window.__initCalls === 1);
      expect(await page.evaluate(() => window.__bundleExecutions)).toBe(1);
    });

    test("opt-out removes host-only and parent-domain cookies", async ({ context, page }) => {
      const cookieNames = ["RT", "BA", "BRUM_CONSENT", "BOOMR_CONSENT"];
      await preparePage(page, { cookies: cookieNames });
      await context.addCookies(cookieNames.map((name) => ({
        name,
        value: "parent",
        domain: ".example.test",
        path: "/",
        secure: true
      })));
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());

      const remaining = (await context.cookies(shopUrl))
        .filter((cookie) => cookieNames.includes(cookie.name));
      expect(remaining).toEqual([]);
    });

    test("opt-out during download prevents initialization and requires reload to re-grant", async ({ page }) => {
      const gate = await preparePage(page, { holdDownload: true });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      gate.releaseDownload();
      await page.waitForFunction(() => window.__bundleExecutions === 1);

      await page.evaluate(() => window.OPT_IN_BASIC_RUM());
      await page.waitForTimeout(100);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        config: window.basicRumBoomerangConfig
      }))).toEqual({ initCalls: 0, executions: 1, config: null });
    });

    test("opt-out after initialization disables collection and blocks same-page re-grant", async ({ page }) => {
      const cookieNames = ["RT", "BA", "BRUM_CONSENT", "BOOMR_CONSENT"];
      await preparePage(page, { cookies: cookieNames });
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.waitForFunction(() => window.__initCalls === 1);

      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await page.waitForTimeout(100);

      expect(await page.evaluate(() => ({
        initCalls: window.__initCalls,
        executions: window.__bundleExecutions,
        disableCalls: window.__disableCalls,
        cookies: document.cookie,
        removals: window.__utilityCookieRemovals.sort()
      }))).toEqual({
        initCalls: 1,
        executions: 1,
        disableCalls: 1,
        cookies: "",
        removals: ["BA", "BOOMR_CONSENT", "BRUM_CONSENT", "RT"]
      });
    });
  });
}
