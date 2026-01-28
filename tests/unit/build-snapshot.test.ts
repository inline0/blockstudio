/**
 * Build Class Snapshot Tests
 *
 * These tests verify that the Build class returns EXACTLY the same data
 * as captured in the snapshot. This ensures the migration doesn't break anything.
 *
 * Note: Some dynamic values (URLs with scope IDs, mtimes, generated IDs) are
 * normalized before comparison since they change between Playground instances.
 */

import { test, expect } from "../wordpress-playground/fixtures";
import { readFileSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Load the snapshot
const snapshotPath = join(__dirname, "snapshots", "build-snapshot.json");
const snapshot = JSON.parse(readFileSync(snapshotPath, "utf-8"));

/**
 * Normalize dynamic values in the data so we can compare structures.
 * - Replace Playground scope IDs in URLs with a placeholder
 * - Remove/normalize mtimes
 * - Normalize generated IDs (nameAlt)
 * - Normalize timestamps in filenames
 * - Normalize generated hash IDs in keys
 */
function normalizeString(str: string): string {
  return str
    // Normalize Playground scope URLs: scope:0.1234567890 -> scope:NORMALIZED
    .replace(/scope:[0-9.]+/g, "scope:NORMALIZED")
    // Normalize timestamps in filenames: test-1769516162.js -> test-TIMESTAMP.js
    .replace(/(-\d{10,})\.(js|css|scss)/g, "-TIMESTAMP.$2")
    // Normalize content hashes in filenames: style-10af90f280e9944d28a32c07649e0628.css -> style-CONTENTHASH.css
    .replace(/-[a-f0-9]{32}\.(js|css|scss)/g, "-CONTENTHASH.$1")
    // Normalize generated 12-char hex IDs: blockstudio-49a9c898bab4 -> blockstudio-HASHID
    .replace(/blockstudio-[a-f0-9]{12}/g, "blockstudio-HASHID");
}

function normalizeData(obj: any): any {
  if (obj === null || obj === undefined) {
    return obj;
  }

  if (typeof obj === "string") {
    return normalizeString(obj);
  }

  if (typeof obj === "number") {
    return obj;
  }

  if (Array.isArray(obj)) {
    return obj.map(normalizeData).sort();
  }

  if (typeof obj === "object") {
    const normalized: any = {};
    for (const [key, value] of Object.entries(obj)) {
      // Normalize the key itself (may contain hash IDs)
      const normalizedKey = normalizeString(key);

      // Skip mtime fields entirely - they always change
      if (key === "mtime") {
        normalized[normalizedKey] = "NORMALIZED_MTIME";
        continue;
      }
      // Skip key fields that are timestamps (10-digit numbers)
      if (key === "key" && typeof value === "number" && value > 1000000000) {
        normalized[normalizedKey] = "NORMALIZED_KEY";
        continue;
      }
      // Normalize filename fields with timestamps (e.g., test-1769516162 -> test-TIMESTAMP)
      if (key === "filename" && typeof value === "string" && /-\d{10,}$/.test(value)) {
        normalized[normalizedKey] = value.replace(/-\d{10,}$/, "-TIMESTAMP");
        continue;
      }
      // Normalize filename fields with content hashes (e.g., style-10af90f280e9944d28a32c07649e0628 -> style-CONTENTHASH)
      if (key === "filename" && typeof value === "string" && /-[a-f0-9]{32}$/.test(value)) {
        normalized[normalizedKey] = value.replace(/-[a-f0-9]{32}$/, "-CONTENTHASH");
        continue;
      }
      // Normalize nameAlt (generated IDs like blockstudio-49a9c898bab4)
      if (key === "nameAlt" && typeof value === "string" && value.startsWith("blockstudio-")) {
        normalized[normalizedKey] = "blockstudio-NORMALIZED";
        continue;
      }
      // Normalize scopedClass (hash like bs-43849caa438e2447ef552c25a075ff08)
      if (key === "scopedClass" && typeof value === "string" && /^bs-[a-f0-9]{32}$/.test(value)) {
        normalized[normalizedKey] = "bs-NORMALIZED_HASH";
        continue;
      }
      normalized[normalizedKey] = normalizeData(value);
    }
    return normalized;
  }

  return obj;
}

test.describe("Build Class Snapshot Tests", () => {
  test("blocks() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.blocks);
    const normalizedSnapshot = normalizeData(snapshot.blocks);

    // Compare block count
    const snapshotBlockNames = Object.keys(normalizedSnapshot);
    const freshBlockNames = Object.keys(normalizedFresh);

    expect(freshBlockNames.length).toBe(snapshotBlockNames.length);
    expect(freshBlockNames.sort()).toEqual(snapshotBlockNames.sort());

    // Compare each block
    for (const blockName of snapshotBlockNames) {
      expect(normalizedFresh[blockName]).toEqual(normalizedSnapshot[blockName]);
    }
  });

  test("data() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.data);
    const normalizedSnapshot = normalizeData(snapshot.data);

    const snapshotKeys = Object.keys(normalizedSnapshot);
    const freshKeys = Object.keys(normalizedFresh);

    expect(freshKeys.length).toBe(snapshotKeys.length);
    expect(freshKeys.sort()).toEqual(snapshotKeys.sort());

    for (const key of snapshotKeys) {
      expect(normalizedFresh[key]).toEqual(normalizedSnapshot[key]);
    }
  });

  test("extensions() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.extensions);
    const normalizedSnapshot = normalizeData(snapshot.extensions);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("files() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.files);
    const normalizedSnapshot = normalizeData(snapshot.files);

    const snapshotKeys = Object.keys(normalizedSnapshot);
    const freshKeys = Object.keys(normalizedFresh);

    expect(freshKeys.length).toBe(snapshotKeys.length);
    expect(freshKeys.sort()).toEqual(snapshotKeys.sort());

    for (const key of snapshotKeys) {
      expect(normalizedFresh[key]).toEqual(normalizedSnapshot[key]);
    }
  });

  test("assetsAdmin() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.assetsAdmin);
    const normalizedSnapshot = normalizeData(snapshot.assetsAdmin);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("assetsBlockEditor() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.assetsBlockEditor);
    const normalizedSnapshot = normalizeData(snapshot.assetsBlockEditor);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("assetsGlobal() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.assetsGlobal);
    const normalizedSnapshot = normalizeData(snapshot.assetsGlobal);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("paths() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.paths);
    const normalizedSnapshot = normalizeData(snapshot.paths);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("overrides() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.overrides);
    const normalizedSnapshot = normalizeData(snapshot.overrides);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("assets() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.assets);
    const normalizedSnapshot = normalizeData(snapshot.assets);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });

  test("blade() matches snapshot exactly", async ({ wp }) => {
    const fresh = await wp.locator("body").evaluate(async () => {
      const res = await fetch("/wp-json/blockstudio-test/v1/snapshot");
      return res.json();
    });

    const normalizedFresh = normalizeData(fresh.blade);
    const normalizedSnapshot = normalizeData(snapshot.blade);

    expect(normalizedFresh).toEqual(normalizedSnapshot);
  });
});
