import { join, resolve } from "node:path";
import type { Config } from "../config/schema.js";

export function getTargetDir(config: Config, configDir: string): string {
  return resolve(configDir, config.directory);
}

export function getBlockDir(
  config: Config,
  configDir: string,
  blockName: string,
): string {
  return join(getTargetDir(config, configDir), blockName);
}
