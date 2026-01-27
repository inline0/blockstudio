/**
 * Capture snapshot of compiled assets (_dist files).
 * Run with: npx tsx tests/capture-compiled-assets.ts
 *
 * Requires Playground to be running: npm run playground
 */

import { chromium } from "@playwright/test";
import { writeFileSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

async function captureCompiledAssets() {
  console.log("Starting browser...");
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  console.log("Opening WordPress Playground...");
  await page.goto("http://localhost:9400", { timeout: 60000 });

  console.log("Waiting for Playground to initialize...");
  await page.waitForFunction("window.playgroundReady === true", {
    timeout: 120000,
  });

  console.log("Playground ready! Fetching compiled assets...");

  // Get the WordPress iframe
  const playgroundFrame = page.frameLocator("iframe#playground");
  const wpFrame = playgroundFrame.frameLocator("iframe#wp");

  // Fetch the compiled assets from the REST API
  const compiledAssets = await wpFrame.locator("body").evaluate(async () => {
    const res = await fetch("/wp-json/blockstudio-test/v1/compiled-assets");
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${await res.text()}`);
    }
    return res.json();
  });

  console.log("\n=== COMPILED ASSETS ===\n");
  const keys = Object.keys(compiledAssets);
  console.log("Total compiled files:", keys.length);

  const cssFiles = keys.filter((k) => k.endsWith(".css"));
  const jsFiles = keys.filter((k) => k.endsWith(".js"));
  console.log("CSS files:", cssFiles.length);
  console.log("JS files:", jsFiles.length);

  console.log("\nFiles:");
  keys.forEach((k) => {
    const asset = compiledAssets[k];
    console.log(`  ${k} (${asset.size} bytes)`);
  });

  // Save to file
  const snapshotPath = join(__dirname, "snapshots", "compiled-assets.json");
  writeFileSync(snapshotPath, JSON.stringify(compiledAssets, null, 2));
  console.log(`\nSnapshot saved to: ${snapshotPath}`);

  await browser.close();
  console.log("\nDone!");
}

captureCompiledAssets().catch((err) => {
  console.error("Error:", err);
  process.exit(1);
});
