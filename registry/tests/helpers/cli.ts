import { execa, type ResultPromise } from "execa";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = fileURLToPath(new URL(".", import.meta.url));
const CLI_PATH = join(__dirname, "..", "..", "dist", "index.js");

export async function run(
  args: string[],
  options?: {
    cwd?: string;
    env?: Record<string, string>;
  },
): Promise<{ stdout: string; stderr: string; exitCode: number }> {
  try {
    const result = await execa("node", [CLI_PATH, ...args], {
      cwd: options?.cwd,
      env: { ...process.env, ...options?.env, NO_COLOR: "1" },
      reject: false,
    });

    return {
      stdout: result.stdout,
      stderr: result.stderr,
      exitCode: result.exitCode,
    };
  } catch (error: any) {
    return {
      stdout: error.stdout ?? "",
      stderr: error.stderr ?? error.message,
      exitCode: error.exitCode ?? 1,
    };
  }
}
