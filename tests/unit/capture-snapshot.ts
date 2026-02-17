/**
 * Capture snapshot of all Build class data.
 * Run with: npx tsx tests/capture-snapshot.ts
 */

import { chromium } from "@playwright/test";
import { writeFileSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const port = process.env.PLAYGROUND_PORT || "9701";

async function captureSnapshot() {
  console.log("Starting browser...");
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  console.log(`Opening WordPress Playground on port ${port}...`);
  await page.goto(`http://localhost:${port}`, { timeout: 60000 });

  console.log("Waiting for Playground to initialize...");
  await page.waitForFunction("window.playgroundReady === true", null, {
    timeout: 120000,
  });

  console.log("Playground ready! Fetching snapshot...");

  // Get the WordPress iframe
  const playgroundFrame = page.frameLocator("iframe#playground");
  const wpFrame = playgroundFrame.frameLocator("iframe#wp");

  // Fetch the snapshot from the REST API
  const snapshot = await wpFrame.locator("body").evaluate(async () => {
    const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${await res.text()}`);
    }
    return res.json();
  });

  console.log("\n=== SNAPSHOT DATA ===\n");
  console.log("Keys:", Object.keys(snapshot));
  console.log("\nblocks count:", Object.keys(snapshot.blocks || {}).length);
  console.log("data count:", Object.keys(snapshot.data || {}).length);
  console.log("extensions count:", Object.keys(snapshot.extensions || {}).length);
  console.log("files count:", Object.keys(snapshot.files || {}).length);
  console.log("paths count:", Array.isArray(snapshot.paths) ? snapshot.paths.length : Object.keys(snapshot.paths || {}).length);
  console.log("overrides count:", Object.keys(snapshot.overrides || {}).length);
  console.log("assets count:", Object.keys(snapshot.assets || {}).length);
  console.log("blade count:", Object.keys(snapshot.blade || {}).length);

  // Save to file
  const snapshotPath = join(__dirname, "snapshots", "build-snapshot.json");
  writeFileSync(snapshotPath, JSON.stringify(snapshot, null, 2));
  console.log(`\nSnapshot saved to: ${snapshotPath}`);

  await browser.close();
  console.log("\nDone!");
}

captureSnapshot().catch((err) => {
  console.error("Error:", err);
  process.exit(1);
});
