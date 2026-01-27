import { createPlaygroundServer } from "./wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9401, // Different port so both can run simultaneously
  pluginPath: join(__dirname, ".."),
  pluginSlug: "blockstudio7",
  pluginMainFile: "blockstudio-v7.php", // v7 entry point
  testBlocksPath: "tests/blocks",
  testHelperPluginPath: "tests/test-helper.php",
  title: "Blockstudio v7 - Test Environment",
  excludeDirs: ["package/node_modules", "includes"], // Exclude v6 includes
  excludeFiles: ["blockstudio.php"], // Exclude v6 entry point
  landingPage: "/wp-admin",
});
