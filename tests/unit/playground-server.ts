import { createPlaygroundServer } from "../wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9706,
  pluginPath: join(__dirname, "../../_reference"),
  pluginSlug: "blockstudio",
  pluginMainFile: "blockstudio.php",
  testBlocksPath: "../tests/blocks",
  testHelperPluginPath: "../tests/plugins/test-helper/test-helper.php",
  title: "Blockstudio v6 Reference - Test Environment",
  excludeDirs: ["package/node_modules"],
  landingPage: "/wp-admin",
});
