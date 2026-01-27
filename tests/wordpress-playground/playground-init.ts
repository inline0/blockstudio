import express from "express";
import { readFileSync, readdirSync, statSync, existsSync } from "fs";
import { join, relative, isAbsolute } from "path";

export interface PlaygroundServerOptions {
  port?: number;
  pluginPath: string;
  pluginSlug: string;
  pluginMainFile: string;
  testBlocksPath?: string;
  testHelperPluginPath?: string;
  additionalPlugins?: string[];
  title?: string;
  excludeDirs?: string[];
  excludeFiles?: string[];
  landingPage?: string;
  phpVersion?: string;
  wpVersion?: string;
}

function getAllFiles(
  dirPath: string,
  excludeDirs: string[] = [],
  excludeFiles: string[] = [],
  arrayOfFiles: string[] = []
): string[] {
  const files = readdirSync(dirPath);

  files.forEach((file) => {
    const filePath = join(dirPath, file);
    if (statSync(filePath).isDirectory()) {
      const defaultExcludes = ["node_modules", ".git"];
      const allExcludes = [...defaultExcludes, ...excludeDirs];

      if (!allExcludes.some((exclude) => file === exclude || filePath.endsWith("/" + exclude))) {
        arrayOfFiles = getAllFiles(filePath, excludeDirs, excludeFiles, arrayOfFiles);
      }
    } else if (
      file.endsWith(".php") ||
      file.endsWith(".json") ||
      file.endsWith(".twig") ||
      file.endsWith(".scss") ||
      file.endsWith(".css") ||
      file.endsWith(".js")
    ) {
      // Skip excluded files
      if (!excludeFiles.some((exclude) => file === exclude)) {
        arrayOfFiles.push(filePath);
      }
    }
  });

  return arrayOfFiles;
}

export function createPlaygroundServer(options: PlaygroundServerOptions) {
  const {
    port = 9400,
    pluginPath,
    pluginSlug,
    pluginMainFile,
    testBlocksPath,
    testHelperPluginPath,
    additionalPlugins = [],
    title = "WordPress Playground",
    excludeDirs = [],
    excludeFiles = [],
    landingPage = "/wp-admin",
    phpVersion = "8.2",
    wpVersion = "latest",
  } = options;

  const app = express();

  app.use(express.static(pluginPath));

  app.get("/api/plugin-files", (_: express.Request, res: express.Response) => {
    try {
      const files = getAllFiles(pluginPath, excludeDirs, excludeFiles).map((filePath) => {
        const relativePath = relative(pluginPath, filePath);
        return {
          path: `/wordpress/wp-content/plugins/${pluginSlug}/${relativePath}`,
          content: readFileSync(filePath, "utf-8"),
        };
      });

      res.json(files);
    } catch (error) {
      console.error("Error reading plugin files:", error);
      res.status(500).json({ error: "Failed to read plugin files" });
    }
  });

  // Serve test blocks if configured
  app.get(
    "/api/test-blocks",
    (_: express.Request, res: express.Response) => {
      if (!testBlocksPath) {
        return res.status(404).json({ error: "Test blocks not configured" });
      }

      const fullPath = isAbsolute(testBlocksPath)
        ? testBlocksPath
        : join(pluginPath, testBlocksPath);

      if (!existsSync(fullPath)) {
        return res.status(404).json({ error: "Test blocks directory not found" });
      }

      try {
        const files = getAllFiles(fullPath, []).map((filePath) => {
          const relativePath = relative(fullPath, filePath);
          return {
            path: `/wordpress/wp-content/themes/twentytwentyfive/blockstudio/${relativePath}`,
            content: readFileSync(filePath, "utf-8"),
          };
        });
        res.json(files);
      } catch (error) {
        console.error("Error reading test blocks:", error);
        res.status(500).json({ error: "Failed to read test blocks" });
      }
    }
  );

  app.get(
    "/api/test-helper-plugin",
    (_: express.Request, res: express.Response) => {
      if (!testHelperPluginPath) {
        return res
          .status(404)
          .json({ error: "Test helper plugin not configured" });
      }

      const fullTestHelperPath = isAbsolute(testHelperPluginPath)
        ? testHelperPluginPath
        : join(pluginPath, testHelperPluginPath);
      if (!existsSync(fullTestHelperPath)) {
        return res
          .status(404)
          .json({ error: "Test helper plugin file not found" });
      }

      const testHelperPlugin = readFileSync(fullTestHelperPath, "utf-8");
      res.type("text/plain").send(testHelperPlugin);
    }
  );

  app.get("/", (_: express.Request, res: express.Response) => {
    res.setHeader(
      "Cache-Control",
      "no-store, no-cache, must-revalidate, private"
    );
    res.setHeader("Pragma", "no-cache");
    res.setHeader("Expires", "0");

    const testHelperFileName = `${pluginSlug}-test-helper.php`;
    const pluginsToActivate = [
      `${pluginSlug}/${pluginMainFile}`,
      ...(testHelperPluginPath ? [testHelperFileName] : []),
      ...additionalPlugins,
    ];

    const activateCode = pluginsToActivate
      .map((plugin) => `activate_plugin('${plugin}');`)
      .join("\n                ");

    const testHelperCode = testHelperPluginPath
      ? `
              await client.writeFile(
                '/wordpress/wp-content/plugins/${testHelperFileName}',
                await fetch('/api/test-helper-plugin').then((r) => r.text())
              );`
      : "";

    const testBlocksCode = testBlocksPath
      ? `
              const testBlockFiles = await fetch('/api/test-blocks').then((r) => r.json());
              for (const file of testBlockFiles) {
                const dir = file.path.substring(0, file.path.lastIndexOf('/'));
                try { await client.mkdir(dir); } catch (e) {}
                await client.writeFile(file.path, file.content);
              }`
      : "";

    res.send(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${title}</title>
        <meta charset="UTF-8">
        <style>
          * { margin: 0; padding: 0; box-sizing: border-box; }
          body { overflow: hidden; }
          iframe { border: none; width: 100vw; height: 100vh; background: white; }
          #loading {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            text-align: center; font-size: 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          }
          .spinner {
            border: 3px solid #e0e0e0; border-top: 3px solid #0073aa; border-radius: 50%;
            width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;
          }
          @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
      </head>
      <body>
        <div id="loading"><div class="spinner"></div><div>Loading WordPress...</div></div>
        <iframe id="playground" style="display:none;"></iframe>

        <script type="module">
          import { startPlaygroundWeb } from 'https://playground.wordpress.net/client/index.js';

          const iframe = document.getElementById('playground');
          const loadingEl = document.getElementById('loading');

          async function init() {
            try {
              const client = await startPlaygroundWeb({
                iframe,
                remoteUrl: 'https://playground.wordpress.net/remote.html?v=' + Date.now(),
                blueprint: {
                  landingPage: '${landingPage}',
                  preferredVersions: { php: '${phpVersion}', wp: '${wpVersion}' },
                  login: true,
                },
              });

              const pluginFiles = await fetch('/api/plugin-files').then((r) => r.json());

              for (const file of pluginFiles) {
                const dir = file.path.substring(0, file.path.lastIndexOf('/'));
                try { await client.mkdir(dir); } catch (e) {}
                await client.writeFile(file.path, file.content);
              }
              ${testHelperCode}
              ${testBlocksCode}

              await client.run({ code: \`<?php
                require_once '/wordpress/wp-load.php';
                ${activateCode}
              \` });

              loadingEl.style.display = 'none';
              iframe.style.display = 'block';
              await client.goTo('${landingPage}');
              window.playgroundClient = client;
              window.playgroundReady = true;
            } catch (error) {
              console.error('Error:', error);
              loadingEl.innerHTML = '<div style="color:#f44747;">Error: ' + error.message + '</div>';
            }
          }

          init();
        </script>
      </body>
    </html>
  `);
  });

  const server = app.listen(port, () => {
    console.log(
      "\n========================================================"
    );
    console.log(`  ${title}`);
    console.log(
      "========================================================\n"
    );
    console.log(`  Open: http://localhost:${port}`);
    console.log(`  User: admin`);
    console.log(`  Pass: password`);
    console.log("\n  Press Ctrl+C to stop");
    console.log(
      "========================================================\n"
    );
  });

  process.on("SIGINT", () => {
    console.log("\n\nShutting down...");
    server.close(() => {
      console.log("Server stopped\n");
      process.exit(0);
    });
  });

  process.on("SIGTERM", () => {
    console.log("\n\nShutting down...");
    server.close(() => {
      console.log("Server stopped\n");
      process.exit(0);
    });
  });

  return server;
}
