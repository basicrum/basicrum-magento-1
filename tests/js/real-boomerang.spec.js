const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");

const root = path.resolve(__dirname, "../..");
const shopUrl = "https://shop.example.test/";
const boomerangUrl = "https://assets.example.test/boomerang.js";
const beaconUrl = "https://collector.example.test/beacon";
const realBoomerang = fs.readFileSync(
  path.join(root, "js/basicrum/boomerangs/boomerang-1.815.60.cutting-edge.min.js"),
  "utf8"
);
const silenceMs = 1500;

function loaderPath(file) {
  return path.join(root, "js/basicrum/loaders", file);
}

async function prepareRealPage(page) {
  let releaseDownload;
  let markDownloadStarted;
  let beaconRequests = 0;
  const downloadGate = new Promise((resolve) => { releaseDownload = resolve; });
  const downloadStarted = new Promise((resolve) => { markDownloadStarted = resolve; });

  await page.route(shopUrl, (route) => route.fulfill({
    contentType: "text/html",
    body: "<!doctype html><html><head></head><body></body></html>"
  }));
  await page.route(`${beaconUrl}*`, (route) => {
    beaconRequests += 1;
    return route.fulfill({
      status: 204,
      headers: { "access-control-allow-origin": "*" },
      body: ""
    });
  });
  await page.route(boomerangUrl, async (route) => {
    markDownloadStarted();
    await downloadGate;
    await route.fulfill({
      status: 200,
      contentType: "application/javascript; charset=utf-8",
      body: realBoomerang
    });
  });

  await page.goto(shopUrl);
  await page.evaluate(({ bundleUrl, collectorUrl }) => {
    window.BOOMR = { url: bundleUrl };
    window.basicRumBoomerangConfig = {
      beacon_url: collectorUrl,
      instrument_xhr: false,
      Continuity: { enabled: true },
      secure_cookie: false,
      same_site_cookie: "Strict"
    };
  }, { bundleUrl: boomerangUrl, collectorUrl: beaconUrl });

  return {
    downloadStarted,
    releaseDownload,
    beaconRequests: () => beaconRequests
  };
}

async function waitForRealBoomerang(page) {
  await expect.poll(
    () => page.evaluate(() => window.BOOMR && window.BOOMR.version)
  ).toBe("1.815.60");
}

for (const consentLoader of [
  "consent-boomerang-loader-v1-15.js",
  "consent-boomerang-loader-v1-15.min.js"
]) {
  test.describe(`real Boomerang: ${consentLoader}`, () => {
    test("explicit opt-in initializes, sets RT, and sends a beacon", async ({ context, page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });

      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      gate.releaseDownload();
      await waitForRealBoomerang(page);

      await expect.poll(() => gate.beaconRequests(), { timeout: 10000 }).toBeGreaterThan(0);
      const cookies = await context.cookies(shopUrl);
      expect(cookies.some((cookie) => cookie.name === "RT")).toBe(true);
    });

    test("withdrawal during the real download leaves the arrived bundle inert", async ({ context, page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });

      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      gate.releaseDownload();
      await waitForRealBoomerang(page);
      await page.waitForTimeout(silenceMs);

      expect(gate.beaconRequests()).toBe(0);
      const cookies = await context.cookies(shopUrl);
      expect(cookies.some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
      expect(await page.evaluate(() => window.basicRumInitConfig || null)).toBe(null);
    });

    test("withdrawal after initialization cancels a pending Wait After Onload beacon", async ({ context, page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });
      await page.evaluate(() => {
        const boomerang = window.BOOMR;
        boomerang.plugins = boomerang.plugins || {};
        boomerang.plugins.WaitAfterOnload = {
          complete: false,
          timer: null,
          init() {
            boomerang.subscribe("page_ready", function() {
              this.timer = window.setTimeout(() => {
                this.complete = true;
                boomerang.sendBeacon();
              }, 500);
              window.__basicRumWaitScheduled = true;
            }, {}, this);
          },
          is_complete() {
            return this.complete;
          }
        };
      });

      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      gate.releaseDownload();
      await waitForRealBoomerang(page);
      await page.waitForFunction(() => window.__basicRumWaitScheduled === true);
      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      await page.waitForTimeout(1000);

      expect(gate.beaconRequests()).toBe(0);
      const cookies = await context.cookies(shopUrl);
      expect(cookies.some((cookie) => ["RT", "BA"].includes(cookie.name))).toBe(false);
    });

    test("a deny before loading does not block a later same-page allow", async ({ context, page }) => {
      const gate = await prepareRealPage(page);
      await page.addScriptTag({ path: loaderPath(consentLoader) });

      await page.evaluate(() => window.OPT_OUT_BASICRUM_LOADER_WRAPPER());
      await page.evaluate(() => window.OPT_IN_BASICRUM_LOADER_WRAPPER());
      await gate.downloadStarted;
      gate.releaseDownload();
      await waitForRealBoomerang(page);

      await expect.poll(() => gate.beaconRequests(), { timeout: 10000 }).toBeGreaterThan(0);
      const cookies = await context.cookies(shopUrl);
      expect(cookies.some((cookie) => cookie.name === "RT")).toBe(true);
    });
  });
}
