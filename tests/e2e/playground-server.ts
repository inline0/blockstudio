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
  testBlocksPath: "tests/e2e/types", // E2E test blocks
  testHelperPluginPath: "tests/test-helper.php",
  title: "Blockstudio E2E - Test Environment (v6)",
  excludeDirs: ["package/node_modules"],
  landingPage: "/wp-admin/post-new.php", // Start in editor for E2E tests
});
