import { createPlaygroundServer } from "../wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9410,
  pluginPath: join(__dirname, "../../_reference"),
  pluginSlug: "blockstudio",
  pluginMainFile: "blockstudio.php",
  testBlocksPath: "../../tests/blocks",
  testHelperPluginPath: "../../tests/test-helper.php",
  testThemePath: "../../tests/e2e/theme",
  title: "Blockstudio E2E - Test Environment (v6 Reference)",
  excludeDirs: ["package/node_modules"],
  landingPage: "/wp-admin/post-new.php",
});
