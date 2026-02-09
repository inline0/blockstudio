import type { Scorer } from "../types.ts";
import {
  extractByLanguage,
  extractByFilename,
  extractCodeBlocks,
} from "../utils/extract.ts";

function stripJsonComments(text: string): string {
  return text
    .split("\n")
    .filter((line) => !line.trimStart().startsWith("//"))
    .join("\n");
}

function findJsonObject(
  output: string,
  filenameHint: string
): Record<string, unknown> | null {
  let blocks = extractByFilename(output, filenameHint);
  if (blocks.length === 0) {
    blocks = extractByLanguage(output, "json");
  }

  for (const block of blocks) {
    try {
      const parsed = JSON.parse(stripJsonComments(block.content));
      if (typeof parsed === "object" && parsed !== null) {
        return parsed as Record<string, unknown>;
      }
    } catch {
      continue;
    }
  }
  return null;
}

function getNestedValue(obj: unknown, path: string): unknown {
  const parts = path.split(".");
  let current = obj;
  for (const part of parts) {
    if (current === null || current === undefined) return undefined;
    if (typeof current !== "object") return undefined;
    current = (current as Record<string, unknown>)[part];
  }
  return current;
}

function collectFieldTypes(attrs: unknown[]): string[] {
  const types: string[] = [];
  for (const attr of attrs) {
    if (typeof attr !== "object" || attr === null) continue;
    const a = attr as Record<string, unknown>;
    if (typeof a.type === "string") {
      types.push(a.type);
    }
    if (Array.isArray(a.attributes)) {
      types.push(...collectFieldTypes(a.attributes));
    }
    if (Array.isArray(a.tabs)) {
      for (const tab of a.tabs) {
        if (typeof tab === "object" && tab !== null) {
          const t = tab as Record<string, unknown>;
          if (Array.isArray(t.attributes)) {
            types.push(...collectFieldTypes(t.attributes));
          }
        }
      }
    }
  }
  return types;
}

function collectFieldIds(attrs: unknown[]): string[] {
  const ids: string[] = [];
  for (const attr of attrs) {
    if (typeof attr !== "object" || attr === null) continue;
    const a = attr as Record<string, unknown>;
    if (typeof a.id === "string") {
      ids.push(a.id);
    }
    if (Array.isArray(a.attributes)) {
      ids.push(...collectFieldIds(a.attributes));
    }
    if (Array.isArray(a.tabs)) {
      for (const tab of a.tabs) {
        if (typeof tab === "object" && tab !== null) {
          const t = tab as Record<string, unknown>;
          if (Array.isArray(t.attributes)) {
            ids.push(...collectFieldIds(t.attributes));
          }
        }
      }
    }
  }
  return ids;
}

export function hasFieldTypes(...expectedTypes: string[]): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, "block.json");
    if (!json) {
      return { name: "FieldTypes", score: 0, details: "No JSON found" };
    }

    const blockstudio = json.blockstudio as Record<string, unknown> | undefined;
    if (!blockstudio || !Array.isArray(blockstudio.attributes)) {
      return {
        name: "FieldTypes",
        score: 0,
        details: "No blockstudio.attributes",
      };
    }

    const types = collectFieldTypes(blockstudio.attributes);
    let found = 0;
    const missing: string[] = [];

    for (const expected of expectedTypes) {
      if (types.includes(expected)) {
        found++;
      } else {
        missing.push(expected);
      }
    }

    const score = expectedTypes.length > 0 ? found / expectedTypes.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${expectedTypes.length}. Missing: ${missing.join(", ")}`
        : `${found}/${expectedTypes.length} found`;

    return { name: "FieldTypes", score, details };
  };
}

export function hasFieldIds(...expectedIds: string[]): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, "block.json");
    if (!json) {
      return { name: "FieldIds", score: 0, details: "No JSON found" };
    }

    const blockstudio = json.blockstudio as Record<string, unknown> | undefined;
    if (!blockstudio || !Array.isArray(blockstudio.attributes)) {
      return {
        name: "FieldIds",
        score: 0,
        details: "No blockstudio.attributes",
      };
    }

    const ids = collectFieldIds(blockstudio.attributes);
    let found = 0;
    const missing: string[] = [];

    for (const expected of expectedIds) {
      if (ids.includes(expected)) {
        found++;
      } else {
        missing.push(expected);
      }
    }

    const score = expectedIds.length > 0 ? found / expectedIds.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${expectedIds.length}. Missing: ${missing.join(", ")}`
        : `${found}/${expectedIds.length} found`;

    return { name: "FieldIds", score, details };
  };
}

export function requiredFiles(...filenames: string[]): Scorer {
  return (output: string) => {
    const blocks = extractCodeBlocks(output);
    const allContent = output.toLowerCase();

    let found = 0;
    const missing: string[] = [];

    for (const filename of filenames) {
      const lower = filename.toLowerCase();
      const hasBlock = blocks.some(
        (b) =>
          b.filename.toLowerCase().includes(lower) ||
          b.language.toLowerCase() === lower.replace(".", "")
      );
      const mentioned = allContent.includes(lower);

      if (hasBlock || mentioned) {
        found++;
      } else {
        missing.push(filename);
      }
    }

    const score = filenames.length > 0 ? found / filenames.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${filenames.length}. Missing: ${missing.join(", ")}`
        : `${found}/${filenames.length} found`;

    return { name: "Files", score, details };
  };
}

export function hasProperties(
  filenameHint: string,
  ...checks: { path: string; value?: unknown }[]
): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, filenameHint);
    if (!json) {
      return { name: "Properties", score: 0, details: "No JSON found" };
    }

    let passed = 0;
    const failures: string[] = [];

    for (const check of checks) {
      const actual = getNestedValue(json, check.path);

      if (check.value === undefined) {
        if (actual !== undefined) {
          passed++;
        } else {
          failures.push(`${check.path} missing`);
        }
      } else {
        if (actual === check.value) {
          passed++;
        } else {
          failures.push(
            `${check.path}: expected ${JSON.stringify(check.value)}, got ${JSON.stringify(actual)}`
          );
        }
      }
    }

    const score = checks.length > 0 ? passed / checks.length : 0;
    const details =
      failures.length > 0
        ? `${passed}/${checks.length}. ${failures.join("; ")}`
        : `${passed}/${checks.length} passed`;

    return { name: "Properties", score, details };
  };
}

export function hasConditions(): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, "block.json") ||
      findJsonObject(output, "extend.json") ||
      findJsonObject(output, ".json");
    if (!json) {
      return { name: "Conditions", score: 0, details: "No JSON found" };
    }

    const content = JSON.stringify(json);
    const hasConditionsKey = content.includes('"conditions"');
    const hasOperator =
      content.includes('"operator"') || content.includes('"=="');

    if (hasConditionsKey && hasOperator) {
      return { name: "Conditions", score: 1, details: "Conditions found" };
    }
    if (hasConditionsKey) {
      return {
        name: "Conditions",
        score: 0.5,
        details: "conditions key found but no operator",
      };
    }
    return {
      name: "Conditions",
      score: 0,
      details: "No conditions found",
    };
  };
}

export function hasSwitch(): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, "block.json");
    if (!json) {
      return { name: "Switch", score: 0, details: "No JSON found" };
    }

    const content = JSON.stringify(json);
    const hasSwitchKey = content.includes('"switch"');

    return {
      name: "Switch",
      score: hasSwitchKey ? 1 : 0,
      details: hasSwitchKey ? "switch property found" : "No switch found",
    };
  };
}

export function hasStorage(): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, "block.json");
    if (!json) {
      return { name: "Storage", score: 0, details: "No JSON found" };
    }

    const content = JSON.stringify(json);
    const hasStorageKey = content.includes('"storage"');
    const hasPostMeta = content.includes('"postMeta"');
    const hasOption = content.includes('"option"');

    let score = 0;
    const found: string[] = [];
    if (hasStorageKey) {
      score += 0.5;
      found.push("storage key");
    }
    if (hasPostMeta || hasOption) {
      score += 0.5;
      found.push(hasPostMeta ? "postMeta" : "option");
    }

    return {
      name: "Storage",
      score,
      details: found.length > 0 ? found.join(", ") : "No storage found",
    };
  };
}

export function extensionStructure(
  targetBlock: string,
  minFields: number
): Scorer {
  return (output: string) => {
    const json = findJsonObject(output, "extend.json") ||
      findJsonObject(output, ".json");
    if (!json) {
      return { name: "Extension", score: 0, details: "No JSON found" };
    }

    let passed = 0;
    const total = 3;
    const checks: string[] = [];

    const blockstudio = json.blockstudio as Record<string, unknown> | undefined;
    if (blockstudio?.extend === true) {
      passed++;
      checks.push("extend=true");
    } else {
      checks.push("missing extend=true");
    }

    const name = json.name;
    const nameStr = Array.isArray(name) ? name.join(",") : String(name || "");
    if (nameStr.includes(targetBlock)) {
      passed++;
      checks.push(`targets ${targetBlock}`);
    } else {
      checks.push(`missing target ${targetBlock}`);
    }

    if (blockstudio && Array.isArray(blockstudio.attributes)) {
      const ids = collectFieldIds(blockstudio.attributes);
      if (ids.length >= minFields) {
        passed++;
        checks.push(`${ids.length} fields (need ${minFields})`);
      } else {
        checks.push(`only ${ids.length} fields (need ${minFields})`);
      }
    } else {
      checks.push("no attributes array");
    }

    return { name: "Extension", score: passed / total, details: checks.join("; ") };
  };
}

export function configStructure(...paths: string[]): Scorer {
  return (output: string) => {
    const json =
      findJsonObject(output, "blockstudio.json") ||
      findJsonObject(output, ".json");
    if (!json) {
      return { name: "Config", score: 0, details: "No JSON found" };
    }

    let found = 0;
    const missing: string[] = [];

    for (const path of paths) {
      const value = getNestedValue(json, path);
      if (value !== undefined) {
        found++;
      } else {
        missing.push(path);
      }
    }

    const score = paths.length > 0 ? found / paths.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${paths.length}. Missing: ${missing.join(", ")}`
        : `${found}/${paths.length} found`;

    return { name: "Config", score, details };
  };
}

export function patternStructure(...requiredKeys: string[]): Scorer {
  return (output: string) => {
    const json =
      findJsonObject(output, "pattern.json") ||
      findJsonObject(output, ".json");
    if (!json) {
      return { name: "Pattern", score: 0, details: "No JSON found" };
    }

    let found = 0;
    const missing: string[] = [];

    for (const key of requiredKeys) {
      const value = getNestedValue(json, key);
      if (value !== undefined) {
        found++;
      } else {
        missing.push(key);
      }
    }

    const score = requiredKeys.length > 0 ? found / requiredKeys.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${requiredKeys.length}. Missing: ${missing.join(", ")}`
        : `${found}/${requiredKeys.length} found`;

    return { name: "Pattern", score, details };
  };
}
