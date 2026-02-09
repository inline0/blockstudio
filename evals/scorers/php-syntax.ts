import type { Scorer } from "../types.ts";
import { extractByLanguage, extractCodeBlocks } from "../utils/extract.ts";

export function phpSyntax(): Scorer {
  return (output: string) => {
    let blocks = extractByLanguage(output, "php");
    if (blocks.length === 0) {
      blocks = extractCodeBlocks(output).filter(
        (b) =>
          b.content.includes("<?php") ||
          b.content.includes("$a[") ||
          b.content.includes("<RichText") ||
          b.content.includes("<InnerBlocks")
      );
    }

    if (blocks.length === 0) {
      return { name: "PhpSyntax", score: 0, details: "No PHP blocks found" };
    }

    const checks: { passed: boolean; detail: string }[] = [];
    const content = blocks.map((b) => b.content).join("\n");

    const phpOpens = (content.match(/<\?php/g) || []).length;
    const phpCloses = (content.match(/\?>/g) || []).length;
    if (phpOpens > 0) {
      checks.push({
        passed: phpCloses <= phpOpens,
        detail: `PHP tags: ${phpOpens} opens, ${phpCloses} closes`,
      });
    }

    const foreachOpens = (content.match(/foreach\s*\(/g) || []).length;
    if (foreachOpens > 0) {
      checks.push({
        passed: true,
        detail: `foreach loops found: ${foreachOpens}`,
      });
    }

    const hasBlockVars =
      content.includes("$a[") ||
      content.includes("$a['") ||
      content.includes("$b[") ||
      content.includes("$b['") ||
      content.includes("$isEditor");
    if (
      hasBlockVars ||
      content.includes("<RichText") ||
      content.includes("<InnerBlocks")
    ) {
      checks.push({
        passed: true,
        detail: "Blockstudio variables present",
      });
    }

    const divOpens = (content.match(/<div[\s>]/g) || []).length;
    const divCloses = (content.match(/<\/div>/g) || []).length;
    if (divOpens > 0) {
      checks.push({
        passed: divOpens === divCloses,
        detail: `div tags: ${divOpens} opens, ${divCloses} closes`,
      });
    }

    const hasWrongPatterns =
      content.includes("$attributes[") ||
      content.includes("get_field(") ||
      content.includes("the_field(");
    checks.push({
      passed: !hasWrongPatterns,
      detail: hasWrongPatterns
        ? "Wrong patterns found (ACF/WP)"
        : "No wrong patterns",
    });

    if (checks.length === 0) {
      return {
        name: "PhpSyntax",
        score: 0.5,
        details: "No checks applicable",
      };
    }

    const passed = checks.filter((c) => c.passed).length;
    const score = passed / checks.length;
    const failed = checks.filter((c) => !c.passed).map((c) => c.detail);
    const details =
      failed.length > 0
        ? `${passed}/${checks.length} passed. Failed: ${failed.join("; ")}`
        : `${passed}/${checks.length} passed`;

    return { name: "PhpSyntax", score, details };
  };
}

export function templateVars(...expected: string[]): Scorer {
  return (output: string) => {
    const blocks = extractCodeBlocks(output);
    const content = blocks.map((b) => b.content).join("\n");

    let found = 0;
    const missing: string[] = [];

    for (const pattern of expected) {
      const altPattern = pattern.includes("'")
        ? pattern.replace(/'/g, '"')
        : pattern.replace(/"/g, "'");
      if (content.includes(pattern) || content.includes(altPattern)) {
        found++;
      } else {
        missing.push(pattern);
      }
    }

    const score = expected.length > 0 ? found / expected.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${expected.length}. Missing: ${missing.join(", ")}`
        : `${found}/${expected.length} found`;

    return { name: "TemplateVars", score, details };
  };
}

export function hookPresence(...patterns: string[]): Scorer {
  return (output: string) => {
    const blocks = extractCodeBlocks(output);
    const content = blocks.map((b) => b.content).join("\n");

    let found = 0;
    const missing: string[] = [];

    for (const pattern of patterns) {
      if (content.includes(pattern)) {
        found++;
      } else {
        missing.push(pattern);
      }
    }

    const score = patterns.length > 0 ? found / patterns.length : 0;
    const details =
      missing.length > 0
        ? `${found}/${patterns.length}. Missing: ${missing.join(", ")}`
        : `${found}/${patterns.length} found`;

    return { name: "HookPresence", score, details };
  };
}
