import { execFileSync } from "child_process";
import { readdirSync, mkdirSync, writeFileSync } from "fs";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";
import type { EvalSuite, CaseResult, ScoreResult } from "./types.ts";

const __dirname = dirname(fileURLToPath(import.meta.url));
const CONTEXT_PATH = resolve(__dirname, "../includes/llm/blockstudio-llm.txt");

const DEFAULT_MODEL = "sonnet";

let totalCost = 0;

function parseArgs() {
  const args = process.argv.slice(2);
  let evalFile: string | null = null;
  let model = DEFAULT_MODEL;
  let caseNum: number | null = null;

  for (const arg of args) {
    if (arg.startsWith("--model=")) {
      model = arg.replace("--model=", "");
    } else if (arg.startsWith("--case=")) {
      caseNum = parseInt(arg.replace("--case=", ""), 10);
    } else if (!arg.startsWith("--")) {
      evalFile = arg;
    }
  }

  return { evalFile, model, caseNum };
}

async function loadEvalSuite(name: string): Promise<EvalSuite> {
  const path = resolve(__dirname, `${name}.eval.ts`);
  const module = await import(path);
  return module.default as EvalSuite;
}

function discoverEvalFiles(): string[] {
  const files = readdirSync(__dirname);
  return files
    .filter((f) => f.endsWith(".eval.ts"))
    .map((f) => f.replace(".eval.ts", ""))
    .sort();
}

interface CliResult {
  text: string;
  cost: number;
  durationMs: number;
}

function callCli(model: string, prompt: string): CliResult {
  const fullPrompt = prompt + "\n\nRespond with the complete file contents in fenced code blocks (```json, ```php, etc). Include the filename above each block.";

  const raw = execFileSync("claude", [
    "-p",
    "--output-format",
    "json",
    "--system-prompt-file",
    CONTEXT_PATH,
    "--model",
    model,
    fullPrompt,
  ], { encoding: "utf-8", maxBuffer: 10 * 1024 * 1024, timeout: 120_000 });

  const json = JSON.parse(raw);
  return {
    text: json.result || "",
    cost: json.total_cost_usd || 0,
    durationMs: json.duration_ms || 0,
  };
}

function runScorers(
  output: string,
  scorers: Array<(output: string) => ScoreResult>
): ScoreResult[] {
  return scorers.map((scorer) => scorer(output));
}

function printTable(suite: EvalSuite, results: CaseResult[], model: string) {
  console.log();
  console.log(`${suite.name} (${model})`);
  console.log("\u2501".repeat(80));

  const scorerNames: string[] = [];
  for (const result of results) {
    for (const score of result.scores) {
      if (!scorerNames.includes(score.name)) {
        scorerNames.push(score.name);
      }
    }
  }

  const header = [
    " #".padEnd(4),
    "Case".padEnd(30),
    ...scorerNames.map((n) => n.padEnd(12)),
    "Avg".padEnd(6),
  ].join("");
  console.log(header);

  for (const result of results) {
    const scoreMap = new Map(result.scores.map((s) => [s.name, s.score]));
    const row = [
      ` ${result.caseIndex}`.padEnd(4),
      result.caseName.slice(0, 28).padEnd(30),
      ...scorerNames.map((n) => {
        const score = scoreMap.get(n);
        if (score === undefined) return "  -   ".padEnd(12);
        return formatScore(score).padEnd(12);
      }),
      formatScore(result.average).padEnd(6),
    ].join("");
    console.log(row);
  }

  console.log("\u2501".repeat(80));

  const overallAvg =
    results.length > 0
      ? results.reduce((sum, r) => sum + r.average, 0) / results.length
      : 0;
  console.log(`Average: ${formatScore(overallAvg)}`);
  console.log();
}

function formatScore(score: number): string {
  if (score === 1) return "\x1b[32m1.00\x1b[0m";
  if (score >= 0.8) return `\x1b[33m${score.toFixed(2)}\x1b[0m`;
  return `\x1b[31m${score.toFixed(2)}\x1b[0m`;
}

function plainScore(score: number): string {
  return score.toFixed(2);
}

function printFailureDetails(results: CaseResult[]) {
  const failures = results.filter((r) =>
    r.scores.some((s) => s.score < 1)
  );
  if (failures.length === 0) return;

  console.log("Failure Details:");
  console.log("-".repeat(60));
  for (const result of failures) {
    const failed = result.scores.filter((s) => s.score < 1);
    if (failed.length > 0) {
      console.log(`  Case ${result.caseIndex}: ${result.caseName}`);
      for (const score of failed) {
        console.log(
          `    ${score.name}: ${score.score.toFixed(2)} - ${score.details || ""}`
        );
      }
    }
  }
  console.log();
}

async function runSuite(
  model: string,
  suite: EvalSuite,
  caseNum: number | null
): Promise<CaseResult[]> {
  const cases =
    caseNum !== null ? [suite.cases[caseNum - 1]] : suite.cases;
  const startIndices =
    caseNum !== null
      ? [caseNum]
      : suite.cases.map((_, i) => i + 1);

  const results: CaseResult[] = [];

  for (let i = 0; i < cases.length; i++) {
    const evalCase = cases[i];
    const caseIndex = startIndices[i];

    process.stdout.write(
      `  Running case ${caseIndex}: ${evalCase.name}...`
    );

    try {
      const { text: output, cost, durationMs } = callCli(model, evalCase.prompt);
      totalCost += cost;
      const scores = runScorers(output, evalCase.scorers);
      const average =
        scores.length > 0
          ? scores.reduce((sum, s) => sum + s.score, 0) / scores.length
          : 0;

      results.push({
        caseIndex,
        caseName: evalCase.name,
        scores,
        average,
        output,
      });

      process.stdout.write(` ${formatScore(average)}\n`);
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      console.log(` ERROR: ${msg}`);
      results.push({
        caseIndex,
        caseName: evalCase.name,
        scores: [{ name: "Error", score: 0, details: msg }],
        average: 0,
        output: "",
      });
    }
  }

  return results;
}

interface SuiteLog {
  suite: EvalSuite;
  results: CaseResult[];
}

async function main() {
  const { evalFile, model, caseNum } = parseArgs();

  const evalNames = evalFile ? [evalFile] : discoverEvalFiles();

  if (evalNames.length === 0) {
    console.error("No eval files found");
    process.exit(1);
  }

  console.log(`Running evals with model: ${model}`);
  console.log(`Eval files: ${evalNames.join(", ")}`);
  console.log();

  totalCost = 0;
  const allResults: CaseResult[] = [];
  const allSuiteResults: SuiteLog[] = [];

  for (const name of evalNames) {
    try {
      const suite = await loadEvalSuite(name);
      const results = await runSuite(model, suite, caseNum);
      printTable(suite, results, model);
      printFailureDetails(results);
      allResults.push(...results);
      allSuiteResults.push({ suite, results });
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      console.error(`Failed to load eval "${name}": ${msg}`);
    }
  }

  if (allResults.length > 0) {
    const overallAvg =
      allResults.reduce((sum, r) => sum + r.average, 0) / allResults.length;
    if (evalNames.length > 1) {
      console.log("=".repeat(80));
      console.log(`Overall Average: ${formatScore(overallAvg)}`);
      console.log(`Total Cases: ${allResults.length}`);
    }
    console.log(`Total Cost: $${totalCost.toFixed(4)}`);
    console.log();

    writeLogFile(model, allResults, allSuiteResults, overallAvg);
  } else {
    console.log("No results to report.");
  }
}

function writeLogFile(
  model: string,
  allResults: CaseResult[],
  suites: SuiteLog[],
  overallAvg: number
) {
  const resultsDir = resolve(__dirname, "results");
  mkdirSync(resultsDir, { recursive: true });

  const ts = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
  const logPath = resolve(resultsDir, `${model}-${ts}.log`);

  const lines: string[] = [];
  lines.push(`Eval Run: ${new Date().toISOString()}`);
  lines.push(`Model: ${model}`);
  lines.push(`Total Cases: ${allResults.length}`);
  lines.push(`Overall Average: ${plainScore(overallAvg)}`);
  lines.push(`Total Cost: $${totalCost.toFixed(4)}`);
  lines.push("");

  for (const { suite, results } of suites) {
    lines.push("=".repeat(80));
    lines.push(`${suite.name} (${model})`);
    lines.push("-".repeat(80));

    const scorerNames: string[] = [];
    for (const result of results) {
      for (const score of result.scores) {
        if (!scorerNames.includes(score.name)) {
          scorerNames.push(score.name);
        }
      }
    }

    const header = [
      " #".padEnd(4),
      "Case".padEnd(30),
      ...scorerNames.map((n) => n.padEnd(12)),
      "Avg".padEnd(6),
    ].join("");
    lines.push(header);

    for (const result of results) {
      const scoreMap = new Map(result.scores.map((s) => [s.name, s.score]));
      const row = [
        ` ${result.caseIndex}`.padEnd(4),
        result.caseName.slice(0, 28).padEnd(30),
        ...scorerNames.map((n) => {
          const score = scoreMap.get(n);
          if (score === undefined) return "  -   ".padEnd(12);
          return plainScore(score).padEnd(12);
        }),
        plainScore(result.average).padEnd(6),
      ].join("");
      lines.push(row);
    }

    const suiteAvg =
      results.length > 0
        ? results.reduce((sum, r) => sum + r.average, 0) / results.length
        : 0;
    lines.push(`Average: ${plainScore(suiteAvg)}`);
    lines.push("");

    // Failure details
    const failures = results.filter((r) => r.scores.some((s) => s.score < 1));
    if (failures.length > 0) {
      lines.push("Failure Details:");
      for (const result of failures) {
        const failed = result.scores.filter((s) => s.score < 1);
        if (failed.length > 0) {
          lines.push(`  Case ${result.caseIndex}: ${result.caseName}`);
          for (const score of failed) {
            lines.push(
              `    ${score.name}: ${plainScore(score.score)} - ${score.details || ""}`
            );
          }
        }
      }
      lines.push("");
    }
  }

  // Raw outputs
  lines.push("=".repeat(80));
  lines.push("RAW LLM OUTPUTS");
  lines.push("=".repeat(80));
  for (const result of allResults) {
    lines.push("");
    lines.push(`--- Case ${result.caseIndex}: ${result.caseName} ---`);
    lines.push(result.output || "(no output)");
    lines.push("");
  }

  writeFileSync(logPath, lines.join("\n"), "utf-8");
  console.log(`Full results written to: ${logPath}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
