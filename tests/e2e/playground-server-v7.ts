import { createPlaygroundServer } from "../wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9411, // E2E tests use 9411 for v7
  pluginPath: join(__dirname, "../.."),
  pluginSlug: "blockstudio7",
  pluginMainFile: "blockstudio-v7.php", // v7 entry point
  testBlocksPath: "tests/blocks", // Same test blocks as unit tests
  testHelperPluginPath: "tests/test-helper.php",
  title: "Blockstudio E2E - Test Environment (v7)",
  excludeDirs: ["package/node_modules", "includes"], // Exclude v6 includes
  excludeFiles: ["blockstudio.php"], // Exclude v6 entry point
  landingPage: "/wp-admin/post-new.php", // Start in editor for E2E tests
});
