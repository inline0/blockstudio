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

export const configSchema = z.object({
  $schema: z.string().optional(),
  directory: z.string().min(1),
  registries: z.record(z.string().min(1), z.string().url()),
});

export type BlockEntry = z.infer<typeof blockEntrySchema>;
export type Registry = z.infer<typeof registrySchema>;
export type Config = z.infer<typeof configSchema>;
