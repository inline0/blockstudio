/**
 * Compiled Assets Snapshot Tests
 *
 * These tests verify that SCSS compilation and asset generation produce
 * EXACTLY the same output as captured in the snapshot. This ensures the
 * migration doesn't break the asset compilation pipeline.
 */

import { test, expect } from "../wordpress-playground/fixtures";
import { readFileSync, existsSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Load the snapshot
const snapshotPath = join(__dirname, "snapshots", "compiled-assets.json");

// Only run tests if snapshot exists
const snapshotExists = existsSync(snapshotPath);
const snapshot = snapshotExists
  ? JSON.parse(readFileSync(snapshotPath, "utf-8"))
  : {};

test.describe("Compiled Assets Snapshot Tests", () => {
  test.skip(!snapshotExists, "Snapshot not found - run capture-compiled-assets.ts first");

  test("compiled assets count matches snapshot", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/compiled-assets");
      return res.json();
    });

    const snapshotKeys = Object.keys(snapshot);
    const freshKeys = Object.keys(fresh);

    expect(freshKeys.length).toBe(snapshotKeys.length);
    expect(freshKeys.sort()).toEqual(snapshotKeys.sort());
  });

  test("all compiled CSS files match snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/compiled-assets");
      return res.json();
    });

    const cssKeys = Object.keys(snapshot).filter((k) => k.endsWith(".css"));

    for (const key of cssKeys) {
      expect(fresh[key], `CSS file ${key} missing`).toBeDefined();
      expect(fresh[key].content, `CSS content mismatch for ${key}`).toBe(
        snapshot[key].content
      );
    }
  });

  test("all compiled JS files match snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/compiled-assets");
      return res.json();
    });

    const jsKeys = Object.keys(snapshot).filter((k) => k.endsWith(".js"));

    for (const key of jsKeys) {
      expect(fresh[key], `JS file ${key} missing`).toBeDefined();
      expect(fresh[key].content, `JS content mismatch for ${key}`).toBe(
        snapshot[key].content
      );
    }
  });
});
