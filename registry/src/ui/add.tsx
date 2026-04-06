import { useState, useEffect, useRef } from "react";
import { Box, Text, Static, useApp } from "ink";
import { loadConfig } from "../config/loader.js";
import type { Config, Registry } from "../config/schema.js";
import { resolveRegistryRef } from "../config/schema.js";
import { fetchRegistry, clearCache } from "../registry/fetcher.js";
import {
  resolveBlock,
  findBlockAcrossRegistries,
  type RegistryMatch,
  type ResolvedBlock,
} from "../registry/resolver.js";
import { downloadBlocks } from "../registry/downloader.js";
import { writeBlocks, exists } from "../utils/fs.js";
import { getTargetDir, getBlockDir } from "../utils/paths.js";
import { parseBlockArg } from "../commands/add.js";
import { Loading, Success, ErrorMessage, Select, Confirm } from "./components.js";

type CompletedStep = { key: string; message: string };

type Phase =
  | { type: "loading" }
  | { type: "conflict"; blockName: string; matches: RegistryMatch[] }
  | { type: "confirm-overwrite"; blockName: string; blockDir: string }
  | { type: "downloading"; blockName: string }
  | { type: "done" }
  | { type: "error"; message: string };

export function AddApp({
  blockArgs,
  cwd,
}: {
  blockArgs: string[];
  cwd: string;
}) {
  const { exit } = useApp();
  const [phase, setPhase] = useState<Phase>({ type: "loading" });
  const [steps, setSteps] = useState<CompletedStep[]>([]);

  const conflictResolve = useRef<(m: RegistryMatch) => void>();
  const overwriteResolve = useRef<(yes: boolean) => void>();

  const addStep = (message: string) => {
    setSteps((s) => [...s, { key: `${s.length}`, message }]);
  };

  useEffect(() => {
    execute();
  }, []);

  async function execute() {
    try {
      clearCache();
      const { config, configDir } = await loadConfig(cwd);
      const targetDir = getTargetDir(config, configDir);

      const registryNames = Object.keys(config.registries);
      if (registryNames.length === 0) {
        throw new Error("No registries configured in blocks.json.");
      }

      const registries = new Map<string, Registry>();
      const headersMap = new Map<string, Record<string, string>>();
      for (const name of registryNames) {
        const { url, headers } = resolveRegistryRef(config.registries[name]);
        registries.set(name, await fetchRegistry(url, headers));
        if (Object.keys(headers).length > 0) {
          headersMap.set(name, headers);
        }
      }

      addStep("Registries loaded");

      for (const arg of blockArgs) {
        const { namespace, name } = parseBlockArg(arg);
        let registryName: string;
        let registry: Registry;

        if (namespace) {
          registry = registries.get(namespace)!;
          if (!registry) {
            throw new Error(`Registry "${namespace}" not found.`);
          }
          registryName = namespace;

          if (!registry.blocks.find((b) => b.name === name)) {
            throw new Error(`Block "${name}" not found in "${namespace}".`);
          }
        } else {
          const matches = findBlockAcrossRegistries(name, registries, headersMap);

          if (matches.length === 0) {
            throw new Error(`Block "${name}" not found in any registry.`);
          }

          let match: RegistryMatch;
          if (matches.length === 1) {
            match = matches[0];
          } else {
            match = await new Promise<RegistryMatch>((resolve) => {
              conflictResolve.current = resolve;
              setPhase({ type: "conflict", blockName: name, matches });
            });
          }

          registryName = match.registryName;
          registry = registries.get(registryName)!;
        }

        const resolved = resolveBlock(name, registry, registryName, headersMap.get(registryName));

        for (const block of resolved) {
          const blockDir = getBlockDir(config, configDir, block.name);
          if (await exists(blockDir)) {
            const overwrite = await new Promise<boolean>((resolve) => {
              overwriteResolve.current = resolve;
              setPhase({
                type: "confirm-overwrite",
                blockName: block.name,
                blockDir,
              });
            });

            if (!overwrite) {
              addStep(`Skipped ${block.name}`);
              continue;
            }
          }
        }

        setPhase({ type: "downloading", blockName: name });
        const downloaded = await downloadBlocks(resolved);
        const written = await writeBlocks(downloaded, targetDir);

        for (const [blockName, files] of written) {
          addStep(`Installed ${blockName} (${files.length} files)`);
        }
      }

      setPhase({ type: "done" });
      setTimeout(() => exit(), 100);
    } catch (e: any) {
      setPhase({ type: "error", message: e.message });
      setTimeout(() => exit(e), 100);
    }
  }

  return (
    <Box flexDirection="column" gap={0}>
      <Static items={steps}>
        {(step) => <Success key={step.key} message={step.message} />}
      </Static>

      {phase.type === "loading" && <Loading message="Loading registries..." />}

      {phase.type === "conflict" && (
        <Select
          message={`"${phase.blockName}" found in multiple registries:`}
          items={phase.matches.map((m) => ({
            label: `${m.registryName}/${m.block.name}${m.block.title ? ` - ${m.block.title}` : ""}`,
            value: m.registryName,
          }))}
          onSelect={(registryName) => {
            const match = phase.matches.find(
              (m) => m.registryName === registryName,
            )!;
            conflictResolve.current?.(match);
          }}
        />
      )}

      {phase.type === "confirm-overwrite" && (
        <Confirm
          message={`${phase.blockName} already exists. Overwrite?`}
          onConfirm={(yes) => overwriteResolve.current?.(yes)}
        />
      )}

      {phase.type === "downloading" && (
        <Loading message={`Downloading ${phase.blockName}...`} />
      )}

      {phase.type === "done" && (
        <Text>
          {"\n"}
          <Text color="green" bold>
            Done!
          </Text>
        </Text>
      )}

      {phase.type === "error" && <ErrorMessage message={phase.message} />}
    </Box>
  );
}
