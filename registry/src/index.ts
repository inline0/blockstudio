import { Command } from "commander";

const program = new Command();

program
  .name("blockstudio")
  .description("Add WordPress blocks from the Blockstudio registry.")
  .version("0.0.1");

program
  .command("add")
  .argument("<block>", "Block to add")
  .description("Add a block to your project")
  .action((block: string) => {
    console.log(`Adding block: ${block}`);
  });

program.parse();
