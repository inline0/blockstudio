import { Command } from "commander";
import { render } from "ink";
import { createElement } from "react";
import { runInit } from "./commands/init.js";
import { runAdd } from "./commands/add.js";
import { runList } from "./commands/list.js";
import { runSearch } from "./commands/search.js";
import { runRemove } from "./commands/remove.js";
import { InitApp } from "./ui/init.js";
import { AddApp } from "./ui/add.js";
import { RemoveApp } from "./ui/remove.js";

const isInteractive =
  process.env.FORCE_INTERACTIVE === "1" || process.stdin.isTTY === true;

async function renderInk(component: ReturnType<typeof createElement>) {
  const app = render(component);
  try {
    await app.waitUntilExit();
  } catch {
    process.exit(1);
  }
}

const program = new Command();

program
  .name("blockstudio")
  .description("Add WordPress blocks from the Blockstudio registry.")
  .version("0.0.1");

program
  .command("init")
  .description("Create a blocks.json config file")
  .option("-d, --directory <path>", "Block install directory", "blockstudio")
  .option("-y, --yes", "Skip prompts and use defaults")
  .action(async (opts) => {
    if (opts.yes || !isInteractive) {
      try {
        await runInit({ cwd: process.cwd(), directory: opts.directory, yes: true });
      } catch (e: any) {
        console.error(e.message);
        process.exit(1);
      }
    } else {
      await renderInk(createElement(InitApp, { cwd: process.cwd() }));
    }
  });

program
  .command("add")
  .description("Add a block to your project")
  .argument("<blocks...>", "Blocks to add (e.g. ui/tabs)")
  .option("-y, --yes", "Skip prompts")
  .option("--dry-run", "Preview without writing files")
  .action(async (blocks, opts) => {
    if (opts.yes || opts.dryRun || !isInteractive) {
      try {
        await runAdd(blocks, {
          cwd: process.cwd(),
          yes: opts.yes,
          dryRun: opts.dryRun,
        });
      } catch (e: any) {
        console.error(e.message);
        process.exit(1);
      }
    } else {
      await renderInk(createElement(AddApp, { blockArgs: blocks, cwd: process.cwd() }));
    }
  });

program
  .command("list")
  .description("List available blocks")
  .argument("[namespace]", "Registry namespace to list")
  .action(async (namespace) => {
    try {
      await runList({ cwd: process.cwd(), namespace });
    } catch (e: any) {
      console.error(e.message);
      process.exit(1);
    }
  });

program
  .command("search")
  .description("Search for blocks across registries")
  .argument("<query>", "Search query")
  .action(async (query) => {
    try {
      await runSearch(query, { cwd: process.cwd() });
    } catch (e: any) {
      console.error(e.message);
      process.exit(1);
    }
  });

program
  .command("remove")
  .description("Remove an installed block")
  .argument("<block>", "Block to remove")
  .option("-y, --yes", "Skip confirmation")
  .action(async (block, opts) => {
    if (opts.yes || !isInteractive) {
      try {
        await runRemove(block, { cwd: process.cwd(), yes: true });
      } catch (e: any) {
        console.error(e.message);
        process.exit(1);
      }
    } else {
      await renderInk(createElement(RemoveApp, { blockName: block, cwd: process.cwd() }));
    }
  });

program.parse();
