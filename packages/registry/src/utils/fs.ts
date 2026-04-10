import { mkdir, writeFile, access, rm } from "node:fs/promises";
import { dirname, join } from "node:path";
import type { DownloadedBlock } from "../registry/downloader.js";

export async function exists(path: string): Promise<boolean> {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}

export async function writeBlock(
  block: DownloadedBlock,
  targetDir: string,
): Promise<string[]> {
  const blockDir = join(targetDir, block.block.name);
  const written: string[] = [];

  for (const file of block.files) {
    const filePath = join(blockDir, file.path);
    await mkdir(dirname(filePath), { recursive: true });
    await writeFile(filePath, file.content, "utf-8");
    written.push(filePath);
  }

  return written;
}

export async function writeBlocks(
  blocks: DownloadedBlock[],
  targetDir: string,
): Promise<Map<string, string[]>> {
  const result = new Map<string, string[]>();

  for (const block of blocks) {
    const files = await writeBlock(block, targetDir);
    result.set(block.block.name, files);
  }

  return result;
}

export async function removeBlock(
  blockName: string,
  targetDir: string,
): Promise<void> {
  const blockDir = join(targetDir, blockName);
  await rm(blockDir, { recursive: true, force: true });
}
