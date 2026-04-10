import { z } from "zod";

export const blockEntrySchema = z.object({
  name: z.string().min(1),
  title: z.string().optional(),
  description: z.string().optional(),
  category: z.string().optional(),
  type: z.enum(["blockstudio", "create-block", "wordpress"]).optional(),
  dependencies: z.array(z.string()).optional(),
  files: z.array(z.string()).min(1),
});

export const registrySchema = z.object({
  $schema: z.string().optional(),
  name: z.string().min(1),
  description: z.string().optional(),
  baseUrl: z.string().url(),
  blocks: z.array(blockEntrySchema).min(1),
});

const registryRefStringSchema = z.string().url();

const registryRefObjectSchema = z.object({
  url: z.string().url(),
  headers: z.record(z.string()).optional(),
});

const registryRefSchema = z.union([registryRefStringSchema, registryRefObjectSchema]);

export const configSchema = z.object({
  $schema: z.string().optional(),
  directory: z.string().min(1),
  registries: z.record(z.string().min(1), registryRefSchema),
});

export type BlockEntry = z.infer<typeof blockEntrySchema>;
export type Registry = z.infer<typeof registrySchema>;
export type RegistryRef = z.infer<typeof registryRefSchema>;
export type Config = z.infer<typeof configSchema>;

export function resolveRegistryRef(ref: RegistryRef): {
  url: string;
  headers: Record<string, string>;
} {
  if (typeof ref === "string") {
    return { url: ref, headers: {} };
  }

  const headers: Record<string, string> = {};
  for (const [key, value] of Object.entries(ref.headers ?? {})) {
    headers[key] = value.replace(/\$\{([^}]+)\}/g, (_, envVar) => {
      const val = process.env[envVar];
      if (val === undefined) {
        throw new Error(
          `Environment variable "${envVar}" is not set (used in header "${key}").`,
        );
      }
      return val;
    });
  }

  return { url: ref.url, headers };
}
