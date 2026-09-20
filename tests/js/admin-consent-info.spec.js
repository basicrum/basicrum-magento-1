const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");

const scriptPath = path.resolve(__dirname, "../../js/basicrum/admin/consent-info.js");

for (const themeHeight of ["auto", "2em"]) {
  test(`callback textareas show five lines with theme height ${themeHeight}`, async ({ page }) => {
    const renderer = fs.readFileSync(path.resolve(__dirname,
      "../../app/code/community/BasicRum/Analytics/Block/Adminhtml/System/Config/Form/Field/ConsentInfo.php"), "utf8");
    const snippets = renderer.match(/<textarea\b[^>]*>[\s\S]*?<\/textarea>/g);
    expect(snippets).toHaveLength(2);
    await page.setContent(`<style>textarea { height: ${themeHeight}; }</style>${snippets.join("\n")}`);

    for (const textarea of await page.locator("textarea").all()) {
      await expect(textarea).toHaveAttribute("rows", "5");
      await expect(textarea).toHaveCSS("resize", "vertical");
      const visibleLines = await textarea.evaluate((element) => {
        const style = getComputedStyle(element);
        return (element.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom))
          / parseFloat(style.lineHeight);
      });
      expect(visibleLines).toBeGreaterThanOrEqual(5);
    }
  });
}

async function renderConsentExamples(page) {
  await page.setContent(`
    <textarea id="allow-snippet" readonly>window.OPT_IN_BASICRUM_LOADER_WRAPPER();</textarea>
    <p>
      <button type="button" class="basicrum-copy-consent-snippet" data-basicrum-copy-target="allow-snippet" data-copied-label="Copied" data-copy-fallback-label="Press Ctrl+C or Command+C to copy.">Copy allow snippet</button>
      <span class="basicrum-copy-status" aria-live="polite"></span>
    </p>
    <textarea id="deny-snippet" readonly>window.OPT_OUT_BASICRUM_LOADER_WRAPPER();</textarea>
    <p>
      <button type="button" class="basicrum-copy-consent-snippet" data-basicrum-copy-target="deny-snippet" data-copied-label="Copied" data-copy-fallback-label="Press Ctrl+C or Command+C to copy.">Copy deny snippet</button>
      <span class="basicrum-copy-status" aria-live="polite"></span>
    </p>
  `);
}

test("copies the selected manual consent snippet", async ({ page }) => {
  await renderConsentExamples(page);
  await page.evaluate(() => {
    Object.defineProperty(navigator, "clipboard", {
      configurable: true,
      value: {
        writeText(value) {
          window.__basicrumCopiedText = value;
          return Promise.resolve();
        }
      }
    });
  });
  await page.addScriptTag({ path: scriptPath });

  const allowButton = page.getByRole("button", { name: "Copy allow snippet" });
  await allowButton.click();

  await expect(allowButton.locator("xpath=following-sibling::*[contains(@class, 'basicrum-copy-status')]"))
    .toHaveText("Copied");
  await expect(allowButton).toHaveAttribute("data-basicrum-copy-ready", "true");
  expect(await page.evaluate(() => window.__basicrumCopiedText))
    .toBe("window.OPT_IN_BASICRUM_LOADER_WRAPPER();");
});

test("selects the snippet and explains manual copying when clipboard APIs fail", async ({ page }) => {
  await renderConsentExamples(page);
  await page.evaluate(() => {
    Object.defineProperty(navigator, "clipboard", {
      configurable: true,
      value: undefined
    });
    document.execCommand = function() {
      return false;
    };
  });
  await page.addScriptTag({ path: scriptPath });

  const denyButton = page.getByRole("button", { name: "Copy deny snippet" });
  await denyButton.click();

  await expect(denyButton.locator("xpath=following-sibling::*[contains(@class, 'basicrum-copy-status')]"))
    .toHaveText("Press Ctrl+C or Command+C to copy.");
  await expect(page.locator("#deny-snippet")).toBeFocused();
  expect(await page.locator("#deny-snippet").evaluate((textarea) => (
    textarea.selectionEnd - textarea.selectionStart
  ))).toBeGreaterThan(0);
});
