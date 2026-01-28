import { createPlaygroundServer } from "../wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9410, // E2E tests use 9410 for v6
  pluginPath: join(__dirname, "../.."),
  pluginSlug: "blockstudio7",
  pluginMainFile: "blockstudio.php",
  testBlocksPath: "tests/blocks", // Same test blocks as unit tests
  testHelperPluginPath: "tests/test-helper.php",
  testThemePath: "tests/e2e/theme", // Custom theme with Timber
  title: "Blockstudio E2E - Test Environment (v6)",
  excludeDirs: ["package/node_modules"],
  landingPage: "/wp-admin/post-new.php", // Start in editor for E2E tests
});
