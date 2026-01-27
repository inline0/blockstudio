import { createPlaygroundServer } from "./wordpress-playground/playground-init";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

createPlaygroundServer({
  port: 9400,
  pluginPath: join(__dirname, ".."),
  pluginSlug: "blockstudio7",
  pluginMainFile: "blockstudio.php",
  testBlocksPath: "tests/blocks",
  testHelperPluginPath: "tests/test-helper.php",
  title: "Blockstudio - Test Environment",
  excludeDirs: ["package/node_modules"],
  landingPage: "/wp-admin",
});
