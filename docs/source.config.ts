import { defineDocs, defineCollections, frontmatterSchema } from "fumadocs-mdx/config";
import { z } from "zod";

export const docs = defineDocs({
  dir: "content/docs",
});

export const guides = defineCollections({
  type: "doc",
  dir: "content/guides",
  schema: frontmatterSchema.extend({
    date: z.date(),
    author: z.string(),
  }),
});

export const blog = defineCollections({
  type: "doc",
  dir: "content/blog",
  schema: frontmatterSchema.extend({
    date: z.date(),
    author: z.string(),
  }),
});
