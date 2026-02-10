import { readFileSync, writeFileSync, existsSync } from 'fs';
import { dirname, resolve, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const docsDir = resolve(root, 'docs/content/docs');
const outputPath = resolve(root, 'includes/llm/blockstudio-llm.txt');

interface MetaJson {
  title: string;
  pages: string[];
}

interface DocEntry {
  title: string;
  content: string;
  depth: number;
}

function readJson(path: string): unknown {
  return JSON.parse(readFileSync(path, 'utf-8'));
}

function stripFrontmatter(content: string): string {
  return content.replace(/^---[\s\S]*?---\n*/, '');
}

function stripGeneratedBlocks(content: string): string {
  return content.replace(/\{\/\* GENERATED_\w+_START \*\/\}[\s\S]*?\{\/\* GENERATED_\w+_END \*\/\}/g, '');
}

function compactCodeBlocks(content: string): string {
  // Strip title attributes from code fences, keep the fences and formatting
  content = content.replace(/^(```\w*)\s+title="[^"]+"/gm, '$1');
  return content;
}

function compactMarkdown(content: string): string {
  // Strip JSX component tags
  content = content.replace(/^\s*<\/?(Tabs|Tab|Steps|Step|Callout|Card|Cards|Accordions|Accordion)\b[^>]*>\s*$/gm, '');
  // Strip MDX comments
  content = content.replace(/\{\/\*[\s\S]*?\*\/\}/g, '');
  // Strip link URLs: [text](url) → text
  content = content.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
  // Strip bold/italic markers
  content = content.replace(/\*\*([^*]+)\*\*/g, '$1');
  content = content.replace(/\*([^*]+)\*/g, '$1');
  return content;
}

function cleanMdx(content: string): string {
  content = stripFrontmatter(content);
  content = stripGeneratedBlocks(content);
  // Remove first H1 (title is in section heading)
  content = content.replace(/^# .+\n*/, '');
  content = compactCodeBlocks(content);
  content = compactMarkdown(content);
  // Collapse 3+ newlines to 2
  content = content.replace(/\n{3,}/g, '\n\n');
  // Trim trailing whitespace per line
  content = content.replace(/[ \t]+$/gm, '');
  return content.trim();
}

function extractTitle(content: string): string {
  const frontmatterMatch = content.match(/^---[\s\S]*?title:\s*(.+?)[\s]*\n[\s\S]*?---/);
  if (frontmatterMatch) return frontmatterMatch[1].replace(/^["']|["']$/g, '');

  const headingMatch = content.match(/^#\s+(.+)$/m);
  if (headingMatch) return headingMatch[1];

  return '';
}

function slugToTitle(slug: string): string {
  return slug
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function collectDocs(dir: string, meta: MetaJson, depth: number): DocEntry[] {
  const entries: DocEntry[] = [];

  const skip = new Set(['migration', 'field-types', 'ai']);

  for (const page of meta.pages) {
    if (page.startsWith('---') && page.endsWith('---')) continue;
    if (skip.has(page)) continue;

    const mdxPath = join(dir, `${page}.mdx`);
    const subDir = join(dir, page);
    const subMeta = join(subDir, 'meta.json');
    const subIndex = join(subDir, 'index.mdx');

    if (existsSync(mdxPath)) {
      const raw = readFileSync(mdxPath, 'utf-8');
      const title = extractTitle(raw) || slugToTitle(page);
      const content = cleanMdx(raw);
      if (content) entries.push({ title, content, depth });
    }

    if (existsSync(subMeta)) {
      const childMeta = readJson(subMeta) as MetaJson;
      const sectionTitle = childMeta.title || slugToTitle(page);

      if (existsSync(subIndex)) {
        const raw = readFileSync(subIndex, 'utf-8');
        const title = extractTitle(raw) || sectionTitle;
        const content = cleanMdx(raw);
        if (content) entries.push({ title, content, depth });
      }

      entries.push(...collectDocs(subDir, childMeta, depth + 1));
    }
  }

  return entries;
}

function buildHeading(title: string, depth: number): string {
  const level = Math.min(depth + 2, 6);
  return '#'.repeat(level) + ' ' + title;
}

const pageSchema = {
  title: 'JSON schema for Blockstudio page definitions',
  $schema: 'http://json-schema.org/draft-04/schema#',
  type: 'object',
  required: ['name'],
  properties: {
    name: {
      type: 'string',
      description:
        'Unique identifier for the page. Used internally to track and reference the page definition.',
      example: 'about',
    },
    title: {
      type: 'string',
      description:
        'The title of the WordPress page/post. Defaults to a human-readable version of the name if not specified.',
      example: 'About Us',
    },
    slug: {
      type: 'string',
      description: 'The URL slug for the page. Defaults to the name if not specified.',
      example: 'about-us',
    },
    postType: {
      type: 'string',
      default: 'page',
      description: 'The WordPress post type to create. Can be any registered post type.',
      example: 'page',
    },
    postStatus: {
      type: 'string',
      default: 'draft',
      description:
        'The initial status for newly created posts. Does not affect existing posts.',
      enum: ['publish', 'draft', 'pending', 'private'],
      example: 'publish',
    },
    postId: {
      type: 'integer',
      description:
        'Pin the page to a specific WordPress post ID. Uses import_id during creation to request this ID. If the ID is already taken by an unrelated post, WordPress silently auto-assigns a new ID.',
      example: 42,
    },
    blockEditingMode: {
      type: 'string',
      enum: ['default', 'contentOnly', 'disabled'],
      description:
        "Controls how blocks can be edited. 'default' allows full editing, 'contentOnly' only allows text editing, 'disabled' prevents all editing.",
      example: 'disabled',
    },
    templateLock: {
      type: ['string', 'boolean'],
      default: 'all',
      description:
        "Controls how users can modify the block structure. 'all' prevents all modifications, 'insert' prevents adding/removing blocks, false allows full editing.",
      enum: ['all', 'insert', 'contentOnly', false],
      example: 'all',
    },
    templateFor: {
      type: ['string', 'null'],
      default: null,
      description:
        "When specified, this page's block structure becomes the default template for the specified post type. Any new posts of that type will start with this template.",
      example: 'product',
    },
    sync: {
      type: 'boolean',
      default: true,
      description:
        'Whether to automatically sync the page content when the template file changes. Set to false to create the page once and prevent future automatic updates.',
      example: true,
    },
  },
  additionalProperties: true,
};

// Build documentation
const rootMeta = readJson(join(docsDir, 'meta.json')) as MetaJson;
const docs = collectDocs(docsDir, rootMeta, 0);

(async () => {
  const schemasDir = resolve(root, 'docs/src/schemas');
  const { blockstudio: blockstudioSchema } = await import(resolve(schemasDir, 'blockstudio.ts'));
  const { schema: blockSchemaFn } = await import(resolve(schemasDir, 'schema.ts'));
  const { extend: extendSchemaFn } = await import(resolve(schemasDir, 'extend.ts'));

  const schemas: { name: string; filename: string; content: string }[] = [];

  // Block schema: only include the blockstudio property + shared definitions
  // Deduplicate: group.attributes is a full copy of all field types, replace with a note
  const full = await blockSchemaFn() as Record<string, any>;
  const bs = full.properties.blockstudio;

  const bsFieldTypes = bs.properties?.attributes?.items?.anyOf;
  const defFieldTypes = full.definitions?.Attribute?.anyOf;
  if (bsFieldTypes && defFieldTypes) {
    const defTypeNames = new Set(defFieldTypes.map((v: Record<string, unknown>) => (v.properties as Record<string, Record<string, string>>)?.type?.const));
    const extras = bsFieldTypes.filter((v: Record<string, unknown>) => {
      const t = (v.properties as Record<string, Record<string, string>>)?.type?.const;
      return !defTypeNames.has(t);
    });
    for (const variant of extras) {
      const props = variant.properties as Record<string, unknown>;
      if (props?.attributes && typeof props.attributes === 'object' && (props.attributes as Record<string, unknown>).items) {
        props.attributes = { type: 'array', description: 'Same field types as the top-level attributes.' };
      }
    }
    bs.properties.attributes.items = {
      _note: 'All field types from definitions.Attribute apply here, plus these additional types:',
      additionalTypes: extras,
    };
  }

  const trimmed = { definitions: full.definitions, blockstudio: bs };
  schemas.push({ name: 'Block Schema (blockstudio key from block.json)', filename: 'block.json', content: JSON.stringify(trimmed) });

  // Settings schema
  schemas.push({ name: 'Settings Schema (blockstudio.json)', filename: 'blockstudio.json', content: JSON.stringify(blockstudioSchema) });

  // Extensions schema: nearly identical to block schema, only include the unique "extend" property
  const ext = await extendSchemaFn() as Record<string, any>;
  const extendProp = ext.properties?.blockstudio?.properties?.extend;
  const trimmedExt = {
    _note: 'Extension schema is identical to the block schema above, plus this additional "extend" property on blockstudio.',
    extend: extendProp,
  };
  schemas.push({ name: 'Extension Schema (extensions.json)', filename: 'extensions.json', content: JSON.stringify(trimmedExt) });

  schemas.splice(2, 0, {
    name: 'Page Schema (page.json)',
    filename: 'page.json',
    content: JSON.stringify(pageSchema),
  });

  // Assemble output
  const parts: string[] = [];

  parts.push('# Blockstudio');
  parts.push('Context about the Blockstudio WordPress block framework for LLM coding assistants.');
  parts.push('');
  parts.push('## Documentation');
  parts.push('');

  for (const doc of docs) {
    parts.push(buildHeading(doc.title, doc.depth));
    parts.push('');
    parts.push(doc.content);
    parts.push('');
  }

  parts.push('## Schemas');
  parts.push('');

  for (const schema of schemas) {
    parts.push(`### ${schema.name}`);
    parts.push('');
    parts.push('```json');
    parts.push(schema.content);
    parts.push('```');
    parts.push('');
  }

  let output = parts.join('\n');
  output = output.replace(/\n{3,}/g, '\n\n');
  output = output.trimEnd() + '\n';

  writeFileSync(outputPath, output, 'utf-8');

  const docCount = docs.length;
  const schemaCount = schemas.length;
  const sizeKb = Math.round(Buffer.byteLength(output, 'utf-8') / 1024);
  console.log(
    `Built ${outputPath}\n  ${docCount} docs, ${schemaCount} schemas, ${sizeKb}KB`,
  );
})();
