import { createPlaygroundServer } from "./wordpress-playground/playground-init";
import { join } from "path";

createPlaygroundServer({
  port: 9400,
  pluginPath: join(__dirname, ".."),
  pluginSlug: "blockstudio7",
  pluginMainFile: "blockstudio.php",
  testBlocksPath: "package/test-blocks",
  testHelperPluginPath: "tests/test-helper.php",
  title: "Blockstudio - Test Environment",
  excludeDirs: ["tests", "package/node_modules"],
  landingPage: "/wp-admin",
});
