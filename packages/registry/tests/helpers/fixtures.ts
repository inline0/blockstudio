import { createServer, type Server } from "node:http";
import { readFile } from "node:fs/promises";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = fileURLToPath(new URL(".", import.meta.url));
const FIXTURES_DIR = join(__dirname, "..", "fixtures", "registries");

export async function startFixtureServer(): Promise<{
  url: string;
  stop: () => Promise<void>;
}> {
  const server = createServer(async (req, res) => {
    const urlPath = decodeURIComponent(req.url ?? "/");

    if (urlPath.endsWith("/registry.json")) {
      const registryName = urlPath.split("/").filter(Boolean)[0];
      const filePath = join(FIXTURES_DIR, registryName, "registry.json");

      try {
        let content = await readFile(filePath, "utf-8");
        const address = server.address();
        const port = typeof address === "object" ? address?.port : 0;
        content = content.replace(
          /\{\{BASE_URL\}\}/g,
          `http://localhost:${port}`,
        );
        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(content);
      } catch {
        res.writeHead(404);
        res.end("Not found");
      }
      return;
    }

    const filePath = join(FIXTURES_DIR, urlPath.slice(1));
    try {
      const content = await readFile(filePath, "utf-8");
      res.writeHead(200, { "Content-Type": "text/plain" });
      res.end(content);
    } catch {
      res.writeHead(404);
      res.end("Not found");
    }
  });

  return new Promise((resolve) => {
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      const port = typeof address === "object" ? address?.port : 0;
      resolve({
        url: `http://localhost:${port}`,
        stop: () =>
          new Promise<void>((res) => {
            server.close(() => res());
          }),
      });
    });
  });
}
