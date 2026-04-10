import { mkdtemp, writeFile, rm } from "node:fs/promises";
import { join } from "node:path";
import { tmpdir } from "node:os";

export async function createTestProject(options?: {
  registries?: Record<string, string>;
  directory?: string;
}): Promise<{
  cwd: string;
  cleanup: () => Promise<void>;
}> {
  const cwd = await mkdtemp(join(tmpdir(), "blockstudio-test-"));

  const config = {
    $schema: "https://blockstudio.dev/schema/blocks.json",
    directory: options?.directory ?? "blockstudio",
    registries: options?.registries ?? {},
  };

  await writeFile(
    join(cwd, "blocks.json"),
    JSON.stringify(config, null, 2) + "\n",
    "utf-8",
  );

  return {
    cwd,
    cleanup: () => rm(cwd, { recursive: true, force: true }),
  };
}
