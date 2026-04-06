import chalk from "chalk";
import { loadConfig } from "../config/loader.js";
import { exists, removeBlock } from "../utils/fs.js";
import { getBlockDir, getTargetDir } from "../utils/paths.js";

export type RemoveOptions = {
  cwd: string;
  yes?: boolean;
};

export async function runRemove(
  blockName: string,
  options: RemoveOptions,
): Promise<void> {
  const { config, configDir } = await loadConfig(options.cwd);
  const targetDir = getTargetDir(config, configDir);
  const blockDir = getBlockDir(config, configDir, blockName);

  if (!(await exists(blockDir))) {
    throw new Error(`Block "${blockName}" is not installed.`);
  }

  if (!options.yes) {
    throw new Error(
      `Remove "${blockName}" from ${blockDir}? Use --yes to confirm.`,
    );
  }

  await removeBlock(blockName, targetDir);
  console.log(chalk.green("Removed"), blockName);
}
