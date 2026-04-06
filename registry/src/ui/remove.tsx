import { useState, useEffect } from "react";
import { Box, Text, useApp } from "ink";
import { loadConfig } from "../config/loader.js";
import { exists, removeBlock } from "../utils/fs.js";
import { getBlockDir, getTargetDir } from "../utils/paths.js";
import { Confirm, Loading, Success, ErrorMessage } from "./components.js";

type Phase =
  | { type: "loading" }
  | { type: "confirm"; blockDir: string }
  | { type: "removing" }
  | { type: "done" }
  | { type: "cancelled" }
  | { type: "error"; message: string };

export function RemoveApp({
  blockName,
  cwd,
}: {
  blockName: string;
  cwd: string;
}) {
  const { exit } = useApp();
  const [phase, setPhase] = useState<Phase>({ type: "loading" });
  const [targetDir, setTargetDir] = useState("");

  useEffect(() => {
    check();
  }, []);

  async function check() {
    try {
      const { config, configDir } = await loadConfig(cwd);
      const td = getTargetDir(config, configDir);
      setTargetDir(td);
      const blockDir = getBlockDir(config, configDir, blockName);

      if (!(await exists(blockDir))) {
        throw new Error(`Block "${blockName}" is not installed.`);
      }

      setPhase({ type: "confirm", blockDir });
    } catch (e: any) {
      setPhase({ type: "error", message: e.message });
      setTimeout(() => exit(e), 100);
    }
  }

  async function doRemove() {
    try {
      setPhase({ type: "removing" });
      await removeBlock(blockName, targetDir);
      setPhase({ type: "done" });
      setTimeout(() => exit(), 100);
    } catch (e: any) {
      setPhase({ type: "error", message: e.message });
      setTimeout(() => exit(e), 100);
    }
  }

  return (
    <Box flexDirection="column" gap={0}>
      {phase.type === "loading" && <Loading message="Checking..." />}

      {phase.type === "confirm" && (
        <Confirm
          message={`Remove ${blockName} from ${phase.blockDir}?`}
          onConfirm={(yes) => {
            if (yes) {
              doRemove();
            } else {
              setPhase({ type: "cancelled" });
              setTimeout(() => exit(), 100);
            }
          }}
        />
      )}

      {phase.type === "removing" && <Loading message={`Removing ${blockName}...`} />}

      {phase.type === "done" && <Success message={`Removed ${blockName}`} />}

      {phase.type === "cancelled" && <Text dimColor>Aborted.</Text>}

      {phase.type === "error" && <ErrorMessage message={phase.message} />}
    </Box>
  );
}
