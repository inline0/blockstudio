import { defineDocs, defineCollections, frontmatterSchema } from "fumadocs-mdx/config";
import { z } from "zod";

export const docs = defineDocs({
  dir: "content/docs",
});

export const guides = defineDocs({
  dir: "content/guides",
});

export const registry = defineDocs({
  dir: "content/registry",
});

export const blog = defineCollections({
  type: "doc",
  dir: "content/blog",
  schema: frontmatterSchema.extend({
    date: z.date(),
    author: z.string(),
  }),
});
