import { createPlaygroundServer } from "../wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9411,
  pluginPath: join(__dirname, "../.."),
  pluginSlug: "blockstudio7",
  pluginMainFile: "blockstudio.php",
  testBlocksPath: "tests/blocks",
  testHelperPluginPath: "tests/test-helper.php",
  testThemePath: "tests/e2e/theme",
  title: "Blockstudio E2E - Test Environment (v7)",
  excludeDirs: ["package/node_modules", "_reference"],
  landingPage: "/wp-admin/post-new.php",
});
