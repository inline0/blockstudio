import { useState, useEffect } from "react";
import { Box, Text, useApp } from "ink";
import { writeFile } from "node:fs/promises";
import { join } from "node:path";
import { exists } from "../utils/fs.js";
import { Prompt, Confirm, Success, ErrorMessage } from "./components.js";
import type { Config } from "../config/schema.js";

type Phase =
  | { type: "check-existing" }
  | { type: "confirm-overwrite" }
  | { type: "directory"; value: string }
  | { type: "add-registry-prompt" }
  | { type: "registry-name"; value: string }
  | { type: "registry-url"; value: string; name: string }
  | { type: "done" }
  | { type: "cancelled" }
  | { type: "error"; message: string };

export function InitApp({ cwd }: { cwd: string }) {
  const { exit } = useApp();
  const [phase, setPhase] = useState<Phase>({ type: "check-existing" });
  const [directory, setDirectory] = useState("blockstudio");
  const [registries, setRegistries] = useState<Record<string, string>>({});
  const [completed, setCompleted] = useState<string[]>([]);

  const configPath = join(cwd, "blocks.json");

  useEffect(() => {
    if (phase.type === "check-existing") {
      exists(configPath).then((found) => {
        if (found) {
          setPhase({ type: "confirm-overwrite" });
        } else {
          setPhase({ type: "directory", value: "" });
        }
      });
    }
  }, [phase.type]);

  useEffect(() => {
    if (phase.type === "done") {
      const config: Config = {
        $schema: "https://blockstudio.dev/schema/blocks.json",
        directory,
        registries,
      };
      writeFile(configPath, JSON.stringify(config, null, 2) + "\n", "utf-8")
        .then(() => setTimeout(() => exit(), 100))
        .catch((e) => setPhase({ type: "error", message: e.message }));
    }
    if (phase.type === "cancelled" || phase.type === "error") {
      setTimeout(() => exit(phase.type === "error" ? new Error(phase.type) : undefined), 100);
    }
  }, [phase.type]);

  return (
    <Box flexDirection="column" gap={0}>
      {completed.map((step, i) => (
        <Success key={i} message={step} />
      ))}

      {phase.type === "check-existing" && <Text dimColor>Checking...</Text>}

      {phase.type === "confirm-overwrite" && (
        <Confirm
          message="blocks.json already exists. Overwrite?"
          onConfirm={(yes) => {
            if (yes) {
              setPhase({ type: "directory", value: "" });
            } else {
              setCompleted((s) => [...s, "Cancelled"]);
              setPhase({ type: "cancelled" });
            }
          }}
        />
      )}

      {phase.type === "directory" && (
        <Prompt
          message="Block directory:"
          value={phase.value}
          onChange={(v) => setPhase({ type: "directory", value: v })}
          onSubmit={(v) => {
            const dir = v || "blockstudio";
            setDirectory(dir);
            setCompleted((s) => [...s, `Directory: ${dir}`]);
            setPhase({ type: "add-registry-prompt" });
          }}
          placeholder="blockstudio"
        />
      )}

      {phase.type === "add-registry-prompt" && (
        <Confirm
          message="Add a registry?"
          onConfirm={(yes) => {
            if (yes) {
              setPhase({ type: "registry-name", value: "" });
            } else {
              setPhase({ type: "done" });
            }
          }}
        />
      )}

      {phase.type === "registry-name" && (
        <Prompt
          message="Registry name:"
          value={phase.value}
          onChange={(v) => setPhase({ type: "registry-name", value: v })}
          onSubmit={(v) => {
            if (!v.trim()) return;
            setPhase({ type: "registry-url", value: "", name: v.trim() });
          }}
          placeholder="ui"
        />
      )}

      {phase.type === "registry-url" && (
        <Prompt
          message={`Registry URL for "${phase.name}":`}
          value={phase.value}
          onChange={(v) => setPhase({ type: "registry-url", value: v, name: phase.name })}
          onSubmit={(v) => {
            if (!v.trim()) return;
            setRegistries((r) => ({ ...r, [phase.name]: v.trim() }));
            setCompleted((s) => [...s, `Registry: ${phase.name} → ${v.trim()}`]);
            setPhase({ type: "add-registry-prompt" });
          }}
          placeholder="https://example.com/registry.json"
        />
      )}

      {phase.type === "done" && <Success message={`Created ${configPath}`} />}

      {phase.type === "cancelled" && <Text dimColor>Aborted.</Text>}

      {phase.type === "error" && <ErrorMessage message={phase.message} />}
    </Box>
  );
}
