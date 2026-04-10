import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { configSchema, resolveRegistryRef } from "../../src/config/schema.js";

describe("configSchema with auth", () => {
  it("accepts string registry (backward compatible)", () => {
    const result = configSchema.parse({
      directory: "blockstudio",
      registries: { ui: "https://example.com/registry.json" },
    });
    expect(result.registries.ui).toBe("https://example.com/registry.json");
  });

  it("accepts object registry with url only", () => {
    const result = configSchema.parse({
      directory: "blockstudio",
      registries: {
        private: { url: "https://example.com/registry.json" },
      },
    });
    expect(result.registries.private).toEqual({
      url: "https://example.com/registry.json",
    });
  });

  it("accepts object registry with headers", () => {
    const result = configSchema.parse({
      directory: "blockstudio",
      registries: {
        private: {
          url: "https://example.com/registry.json",
          headers: { Authorization: "Bearer abc123" },
        },
      },
    });
    const ref = result.registries.private;
    expect(typeof ref).toBe("object");
    expect((ref as any).headers.Authorization).toBe("Bearer abc123");
  });

  it("accepts mixed string and object registries", () => {
    const result = configSchema.parse({
      directory: "blockstudio",
      registries: {
        public: "https://example.com/registry.json",
        private: {
          url: "https://private.example.com/registry.json",
          headers: { Authorization: "Bearer token" },
        },
      },
    });
    expect(typeof result.registries.public).toBe("string");
    expect(typeof result.registries.private).toBe("object");
  });

  it("rejects object registry without url", () => {
    const result = configSchema.safeParse({
      directory: "blockstudio",
      registries: {
        bad: { headers: { Authorization: "Bearer token" } },
      },
    });
    expect(result.success).toBe(false);
  });

  it("rejects object registry with invalid url", () => {
    const result = configSchema.safeParse({
      directory: "blockstudio",
      registries: {
        bad: { url: "not-a-url" },
      },
    });
    expect(result.success).toBe(false);
  });
});

describe("resolveRegistryRef", () => {
  it("resolves string ref to url with empty headers", () => {
    const result = resolveRegistryRef("https://example.com/registry.json");
    expect(result.url).toBe("https://example.com/registry.json");
    expect(result.headers).toEqual({});
  });

  it("resolves object ref with no headers", () => {
    const result = resolveRegistryRef({
      url: "https://example.com/registry.json",
    });
    expect(result.url).toBe("https://example.com/registry.json");
    expect(result.headers).toEqual({});
  });

  it("resolves object ref with static headers", () => {
    const result = resolveRegistryRef({
      url: "https://example.com/registry.json",
      headers: { Authorization: "Bearer abc123" },
    });
    expect(result.headers.Authorization).toBe("Bearer abc123");
  });

  it("resolves ${ENV_VAR} in header values", () => {
    process.env.TEST_REGISTRY_TOKEN = "my-secret-token";

    const result = resolveRegistryRef({
      url: "https://example.com/registry.json",
      headers: { Authorization: "Bearer ${TEST_REGISTRY_TOKEN}" },
    });

    expect(result.headers.Authorization).toBe("Bearer my-secret-token");
    delete process.env.TEST_REGISTRY_TOKEN;
  });

  it("resolves multiple env vars in one header", () => {
    process.env.TEST_USER = "admin";
    process.env.TEST_PASS = "secret";

    const result = resolveRegistryRef({
      url: "https://example.com/registry.json",
      headers: { Authorization: "Basic ${TEST_USER}:${TEST_PASS}" },
    });

    expect(result.headers.Authorization).toBe("Basic admin:secret");
    delete process.env.TEST_USER;
    delete process.env.TEST_PASS;
  });

  it("resolves env vars across multiple headers", () => {
    process.env.TEST_TOKEN = "tok123";
    process.env.TEST_ORG = "acme";

    const result = resolveRegistryRef({
      url: "https://example.com/registry.json",
      headers: {
        Authorization: "Bearer ${TEST_TOKEN}",
        "X-Org": "${TEST_ORG}",
      },
    });

    expect(result.headers.Authorization).toBe("Bearer tok123");
    expect(result.headers["X-Org"]).toBe("acme");
    delete process.env.TEST_TOKEN;
    delete process.env.TEST_ORG;
  });

  it("throws when env var is not set", () => {
    delete process.env.NONEXISTENT_VAR;

    expect(() =>
      resolveRegistryRef({
        url: "https://example.com/registry.json",
        headers: { Authorization: "Bearer ${NONEXISTENT_VAR}" },
      }),
    ).toThrow('Environment variable "NONEXISTENT_VAR" is not set');
  });

  it("throws with header name in error message", () => {
    delete process.env.MISSING;

    expect(() =>
      resolveRegistryRef({
        url: "https://example.com/registry.json",
        headers: { "X-Custom": "${MISSING}" },
      }),
    ).toThrow('header "X-Custom"');
  });

  it("passes through headers without env vars unchanged", () => {
    const result = resolveRegistryRef({
      url: "https://example.com/registry.json",
      headers: { "X-Static": "plain-value" },
    });
    expect(result.headers["X-Static"]).toBe("plain-value");
  });
});
