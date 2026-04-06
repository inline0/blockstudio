import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { createServer } from "node:http";
import { readFile, access } from "node:fs/promises";
import { join } from "node:path";
import { run } from "../helpers/cli.js";
import { createTestProject } from "../helpers/project.js";
import { startFixtureServer } from "../helpers/fixtures.js";

let fixtureServer: Awaited<ReturnType<typeof startFixtureServer>>;

beforeAll(async () => {
  fixtureServer = await startFixtureServer();
});

afterAll(async () => {
  await fixtureServer.stop();
});

async function fileExists(path: string): Promise<boolean> {
  try {
    await access(path);
    return true;
  } catch {
    return false;
  }
}

function startAuthServer(expectedToken: string): Promise<{
  url: string;
  stop: () => Promise<void>;
  requests: Array<{ path: string; authorization: string | undefined }>;
}> {
  const requests: Array<{ path: string; authorization: string | undefined }> = [];

  return new Promise((resolve) => {
    const server = createServer(async (req, res) => {
      requests.push({
        path: req.url ?? "",
        authorization: req.headers.authorization,
      });

      if (req.headers.authorization !== `Bearer ${expectedToken}`) {
        res.writeHead(401);
        res.end("Unauthorized");
        return;
      }

      const proxyUrl = `${fixtureServer.url}${req.url}`;
      try {
        const upstream = await fetch(proxyUrl);
        const body = await upstream.text();
        res.writeHead(upstream.status, { "Content-Type": upstream.headers.get("Content-Type") ?? "text/plain" });
        res.end(body);
      } catch {
        res.writeHead(502);
        res.end("Bad Gateway");
      }
    });

    server.listen(0, "127.0.0.1", () => {
      const addr = server.address();
      const port = typeof addr === "object" ? addr?.port : 0;
      resolve({
        url: `http://localhost:${port}`,
        stop: () => new Promise<void>((r) => server.close(() => r())),
        requests,
      });
    });
  });
}

describe("auth: bearer token", () => {
  let authServer: Awaited<ReturnType<typeof startAuthServer>>;
  let project: Awaited<ReturnType<typeof createTestProject>>;

  beforeAll(async () => {
    authServer = await startAuthServer("test-secret-123");
  });

  afterAll(async () => {
    await authServer.stop();
  });

  afterEach(async () => {
    await project?.cleanup();
  });

  it("sends auth headers when fetching registry and downloading files", async () => {
    project = await createTestProject({
      registries: {
        private: {
          url: `${authServer.url}/ui/registry.json`,
          headers: { Authorization: "Bearer test-secret-123" },
        } as any,
      },
    });

    const result = await run(["add", "private/hero", "--yes"], {
      cwd: project.cwd,
    });

    expect(result.exitCode).toBe(0);
    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);

    const authedRequests = authServer.requests.filter((r) => r.authorization === "Bearer test-secret-123");
    expect(authedRequests.length).toBeGreaterThanOrEqual(1);

    const registryReq = authServer.requests.find((r) => r.path.includes("registry.json"));
    expect(registryReq?.authorization).toBe("Bearer test-secret-123");
  });

  it("fails with 401 when token is wrong", async () => {
    project = await createTestProject({
      registries: {
        private: {
          url: `${authServer.url}/ui/registry.json`,
          headers: { Authorization: "Bearer wrong-token" },
        } as any,
      },
    });

    const result = await run(["add", "private/hero", "--yes"], {
      cwd: project.cwd,
    });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("401");
  });

  it("resolves env vars in headers", async () => {
    project = await createTestProject({
      registries: {
        private: {
          url: `${authServer.url}/ui/registry.json`,
          headers: { Authorization: "Bearer ${TEST_AUTH_TOKEN}" },
        } as any,
      },
    });

    const result = await run(["add", "private/hero", "--yes"], {
      cwd: project.cwd,
      env: { TEST_AUTH_TOKEN: "test-secret-123" },
    });

    expect(result.exitCode).toBe(0);
    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);
  });

  it("fails when env var is missing", async () => {
    project = await createTestProject({
      registries: {
        private: {
          url: `${authServer.url}/ui/registry.json`,
          headers: { Authorization: "Bearer ${MISSING_TOKEN}" },
        } as any,
      },
    });

    const result = await run(["add", "private/hero", "--yes"], {
      cwd: project.cwd,
      env: {},
    });

    expect(result.exitCode).toBe(1);
    expect(result.stderr).toContain("MISSING_TOKEN");
  });

  it("works alongside public registries", async () => {
    project = await createTestProject({
      registries: {
        public: `${fixtureServer.url}/starter/registry.json`,
        private: {
          url: `${authServer.url}/ui/registry.json`,
          headers: { Authorization: "Bearer test-secret-123" },
        } as any,
      },
    });

    const r1 = await run(["add", "public/hero", "--yes"], { cwd: project.cwd });
    expect(r1.exitCode).toBe(0);

    const r2 = await run(["add", "private/tabs", "--yes"], { cwd: project.cwd });
    expect(r2.exitCode).toBe(0);

    expect(await fileExists(join(project.cwd, "blockstudio", "hero", "block.json"))).toBe(true);
    expect(await fileExists(join(project.cwd, "blockstudio", "tabs", "block.json"))).toBe(true);
  });
});
