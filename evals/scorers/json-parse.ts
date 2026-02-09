import type { Scorer } from "../types.ts";
import { extractByLanguage } from "../utils/extract.ts";

function stripJsonComments(text: string): string {
  return text
    .split("\n")
    .filter((line) => !line.trimStart().startsWith("//"))
    .join("\n");
}

export function jsonParse(): Scorer {
  return (output: string) => {
    const blocks = extractByLanguage(output, "json").filter((b) => {
      const cleaned = stripJsonComments(b.content).trimStart();
      return cleaned.startsWith("{") || cleaned.startsWith("[");
    });

    if (blocks.length === 0) {
      return { name: "JsonParse", score: 0, details: "No JSON blocks found" };
    }

    let passed = 0;
    const errors: string[] = [];

    for (const block of blocks) {
      const cleaned = stripJsonComments(block.content);
      try {
        JSON.parse(cleaned);
        passed++;
      } catch (e) {
        const msg = e instanceof Error ? e.message : String(e);
        const preview = block.content.slice(0, 50);
        errors.push(`Parse error in "${preview}...": ${msg}`);
      }
    }

    const score = passed / blocks.length;
    const details =
      errors.length > 0
        ? `${passed}/${blocks.length} parsed. ${errors.join("; ")}`
        : `${passed}/${blocks.length} parsed`;

    return { name: "JsonParse", score, details };
  };
}
