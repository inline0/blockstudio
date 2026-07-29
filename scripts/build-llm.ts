import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'fs';
import { dirname, relative, resolve, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const contentDir = resolve(root, 'docs');
const docsDir = resolve(root, 'docs/docs');
const schemasDir = resolve(root, 'schemas');
const indexPath = resolve(root, 'includes/llm/blockstudio-llm.txt');
const fullPath = resolve(root, 'includes/llm/blockstudio-llm-full.txt');

const indexedCollections = ['docs', 'guides', 'registry'];
const publicSchemas = [
  { name: 'block', purpose: 'Block definitions, including every field type.' },
  { name: 'blockstudio', purpose: 'Settings in blockstudio.json.' },
  { name: 'extend', purpose: 'Extensions that add fields to existing blocks.' },
  { name: 'field', purpose: 'Reusable custom field definitions.' },
  { name: 'page', purpose: 'File-based page definitions.' },
];
const packageNames = new Set([
  'blockstudio/blockstudio',
  'blockstudio/phpstan',
  'blockstudio/registry',
]);

interface MetaJson {
  title: string;
  pages: string[];
}

interface DocEntry {
  title: string;
  content: string;
  depth: number;
}

interface IndexEntry {
  collection: string;
  section: string;
  subsection: string;
  title: string;
  route: string;
  file: string;
  purpose: string;
  order: number;
  settings: string[];
  hooks: string[];
  php: string[];
  cli: string[];
}

function readJson(path: string): unknown {
  return JSON.parse(readFileSync(path, 'utf-8'));
}

function stripFrontmatter(content: string): string {
  return content.replace(/^---[\s\S]*?---\n*/, '');
}

function stripGeneratedBlocks(content: string): string {
  return content.replace(
    /<!-- GENERATED_\w+_START -->[\s\S]*?<!-- GENERATED_\w+_END -->/g,
    '',
  );
}

function compactCodeBlocks(content: string): string {
  content = content.replace(/^(```\w*)\s+title="[^"]+"/gm, '$1');
  return content;
}

function compactMarkdown(content: string): string {
  content = content.replace(
    /^\s*<\/?(Tabs|Tab|Steps|Step|Callout|Card|Cards|Accordions|Accordion)\b[^>]*>\s*$/gm,
    '',
  );
  content = content.replace(/\{\/\*[\s\S]*?\*\/\}/g, '');
  content = content.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
  content = content.replace(/\*\*([^*]+)\*\*/g, '$1');
  content = content.replace(/\*([^*]+)\*/g, '$1');
  return content;
}

function cleanMarkdown(content: string): string {
  content = stripFrontmatter(content);
  content = stripGeneratedBlocks(content);
  content = content.replace(/^# .+\n*/, '');
  content = compactCodeBlocks(content);
  content = compactMarkdown(content);
  content = content.replace(/\n{3,}/g, '\n\n');
  content = content.replace(/[ \t]+$/gm, '');
  return content.trim();
}

function extractTitle(content: string): string {
  const frontmatterMatch = content.match(
    /^---[\s\S]*?title:\s*(.+?)[\s]*\n[\s\S]*?---/,
  );
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

    const markdownPath = join(dir, `${page}.md`);
    const subDir = join(dir, page);
    const subMeta = join(subDir, 'meta.json');
    const subIndex = join(subDir, 'index.md');

    if (existsSync(markdownPath)) {
      const raw = readFileSync(markdownPath, 'utf-8');
      const title = extractTitle(raw) || slugToTitle(page);
      const content = cleanMarkdown(raw);
      if (content) entries.push({ title, content, depth });
    }

    if (existsSync(subMeta)) {
      const childMeta = readJson(subMeta) as MetaJson;
      const sectionTitle = childMeta.title || slugToTitle(page);

      if (existsSync(subIndex)) {
        const raw = readFileSync(subIndex, 'utf-8');
        const title = extractTitle(raw) || sectionTitle;
        const content = cleanMarkdown(raw);
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

function markdownFiles(dir: string): string[] {
  const files: string[] = [];
  for (const name of readdirSync(dir).sort()) {
    const path = resolve(dir, name);
    if (statSync(path).isDirectory()) files.push(...markdownFiles(path));
    else if (name.endsWith('.md')) files.push(path);
  }
  return files;
}

function frontmatter(source: string): Record<string, string> {
  const match = source.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!match) return {};

  const values: Record<string, string> = {};
  for (const line of match[1].split(/\r?\n/)) {
    const field = line.match(/^([A-Za-z0-9_-]+):\s*(.*)$/);
    if (field) {
      values[field[1]] = field[2].trim().replace(/^(["'])([\s\S]*)\1$/, '$2');
    }
  }
  return values;
}

function schemaPaths(schema: Record<string, any>, prefix = ''): string[] {
  const paths: string[] = [];
  const properties = schema.properties as Record<string, any> | undefined;
  if (!properties) return paths;

  for (const [key, value] of Object.entries(properties)) {
    const path = prefix ? `${prefix}/${key}` : key;
    paths.push(path);
    paths.push(...schemaPaths(value, path));
  }
  return paths;
}

function stripLiteralBlocks(body: string): string {
  return body.replace(/^```(?:text|txt)\r?\n[\s\S]*?^```/gm, '');
}

function inlineCodeSpans(body: string): string[] {
  return [...body.matchAll(/`([^`\n]+)`/g)].map((match) => match[1]);
}

function collectHooks(body: string, spans: string[]): string[] {
  const found = new Set<string>();

  for (const match of body.matchAll(
    /(?:add_filter|add_action|apply_filters|do_action|addFilter|addAction|applyFilters|doAction)\(\s*['"]([^'"]+)['"]/g,
  )) {
    if (match[1].startsWith('blockstudio/')) found.add(match[1]);
  }
  for (const span of spans) {
    if (/^blockstudio\/[a-z0-9_/{}-]+$/.test(span) && !packageNames.has(span)) {
      found.add(span);
    }
  }

  return [...found].sort();
}

function collectPhp(body: string, spans: string[]): string[] {
  const found = new Set<string>();

  for (const match of body.matchAll(/\bbs_[a-z0-9_]+(?=\()/g)) {
    found.add(`${match[0]}()`);
  }
  for (const match of body.matchAll(
    /\b([A-Z][A-Za-z0-9_]*)::([a-z_][A-Za-z0-9_]*)(?=\()/g,
  )) {
    found.add(`${match[1]}::${match[2]}()`);
  }
  for (const match of body.matchAll(
    /\bBlockstudio\\{1,2}([A-Z][A-Za-z0-9_]*)/g,
  )) {
    found.add(`Blockstudio\\${match[1]}`);
  }
  for (const span of spans) {
    if (/^\$[a-z]$/.test(span)) found.add(span);
  }

  return [...found].sort();
}

function collectCli(body: string): string[] {
  const found = new Set<string>();

  for (const match of body.matchAll(/\bwp bs [a-z-]+(?: [a-z-]+)?/g)) {
    found.add(match[0]);
  }
  for (const match of body.matchAll(/\bvendor\/bin\/blockstudio-[a-z-]+/g)) {
    found.add(match[0]);
  }
  for (const match of body.matchAll(/\bnpx blockstudio [a-z-]+/g)) {
    found.add(match[0]);
  }

  return [...found].sort();
}

function collectSettings(body: string, spans: string[], paths: string[]): string[] {
  const found = new Set<string>();
  const prose = body
    .replace(/\]\([^)]*\)/g, ' ')
    .replace(/https?:\/\/\S+/g, ' ');

  for (const path of paths) {
    const dotted = path.replace(/\//g, '.');
    if (spans.includes(path) || spans.includes(dotted)) {
      found.add(path);
      continue;
    }
    if (path.includes('/') && (prose.includes(path) || prose.includes(dotted))) {
      found.add(path);
      continue;
    }
    if (
      !path.includes('/') &&
      new RegExp(`"${path}"\\s*:`).test(body)
    ) {
      found.add(path);
    }
  }

  const owned = [...found];
  return owned
    .filter((path) => !owned.some((other) => other.startsWith(`${path}/`)))
    .sort();
}

function collectIndex(paths: string[]): IndexEntry[] {
  const entries: IndexEntry[] = [];

  for (const collection of indexedCollections) {
    for (const path of markdownFiles(resolve(contentDir, collection))) {
      const source = readFileSync(path, 'utf-8');
      const meta = frontmatter(source);
      const body = stripLiteralBlocks(
        stripGeneratedBlocks(stripFrontmatter(source)),
      );
      const spans = inlineCodeSpans(body);
      const route = meta.path === '.' ? collection : `${collection}/${meta.path}`;

      entries.push({
        collection,
        section: meta.section || '',
        subsection: meta.subsection || '',
        title: meta.title || slugToTitle(path),
        route: `/${route}`,
        file: relative(root, path).replaceAll('\\', '/'),
        purpose: meta.description || meta.meta_description || '',
        order: Number(meta.order ?? 0),
        settings: collectSettings(body, spans, paths),
        hooks: collectHooks(body, spans),
        php: collectPhp(body, spans),
        cli: collectCli(body),
      });
    }
  }

  return entries.sort((a, b) => {
    if (a.collection !== b.collection) {
      return (
        indexedCollections.indexOf(a.collection) -
        indexedCollections.indexOf(b.collection)
      );
    }
    if (a.order !== b.order) return a.order - b.order;
    return a.route.localeCompare(b.route);
  });
}

function renderIdentifiers(label: string, values: string[]): string[] {
  if (!values.length) return [];
  return [`${label}: ${values.join(', ')}`];
}

function groupOf(entry: IndexEntry): string {
  const parts = [slugToTitle(entry.collection), entry.section, entry.subsection];
  return parts
    .filter((part, position) => part !== '' && part !== parts[position - 1])
    .join(' / ');
}

function buildIndex(entries: IndexEntry[]): string {
  const parts: string[] = [
    '# Blockstudio documentation index',
    '',
    'This is the primary context file for coding assistants. It is an index, not',
    'the documentation itself. Each entry names one document, what it is for, and',
    'the identifiers that document owns. Read this index, then open only the',
    'document you need.',
    '',
    'Every document is addressable two ways: as a URL on https://blockstudio.dev,',
    'and as a Markdown file in the Blockstudio repository. The full text of every',
    'document and schema is also available in one file at /blockstudio-llm-full.txt',
    'for tools that want everything at once.',
    '',
    'Entries are grouped by the section and subsection they belong to, in',
    'navigation order. Each entry has a route, a source file, a one-line purpose,',
    'and any of these identifier lines: settings (blockstudio.json paths), hooks',
    '(PHP and JavaScript filter and action names), php (functions, classes, and',
    'methods), and cli (commands).',
    '',
  ];

  let group = '';
  for (const entry of entries) {
    const current = groupOf(entry);
    if (current !== group) {
      group = current;
      parts.push(`## ${group}`);
      parts.push('');
    }

    parts.push(`### ${entry.title}`);
    parts.push(`route: ${entry.route}`);
    parts.push(`file: ${entry.file}`);
    if (entry.purpose) parts.push(`purpose: ${entry.purpose}`);
    parts.push(...renderIdentifiers('settings', entry.settings));
    parts.push(...renderIdentifiers('hooks', entry.hooks));
    parts.push(...renderIdentifiers('php', entry.php));
    parts.push(...renderIdentifiers('cli', entry.cli));
    parts.push('');
  }

  parts.push('## Schemas');
  parts.push('');
  for (const schema of publicSchemas) {
    parts.push(`### ${schema.name}.json`);
    parts.push(`route: /schema/${schema.name}`);
    parts.push(`file: schemas/${schema.name}.json`);
    parts.push(`purpose: ${schema.purpose}`);
    parts.push('');
  }

  return parts.join('\n').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';
}

const pageSchema = {
  title:
    'JSON schema for Blockstudio page definitions and collection page entries',
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
      description:
        'The URL slug for the page. Defaults to the name if not specified.',
      example: 'about-us',
    },
    path: {
      type: 'string',
      default: '.',
      description:
        'Logical path inside a collection. "." is the collection root, "guide/install" maps under the collection URL base.',
      example: 'guide/install',
    },
    order: {
      type: 'integer',
      default: 0,
      description: 'Menu order stored on the synced WordPress post.',
      example: 20,
    },
    postType: {
      type: 'string',
      default: 'page',
      description:
        'The WordPress post type to create. Collection pages inherit the top-level pages.json postType by default.',
      example: 'bs_docs',
    },
    postTypeArgs: {
      type: 'object',
      description:
        'register_post_type() arguments used when a collection pages.json registers a custom post type.',
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
        'Whether explicit API or CLI reconciliation may update the page from its source. Set to false to ignore the page during normal reconciliation; use force_sync() when intentionally creating or replacing it.',
      example: true,
    },
    contentType: {
      type: 'string',
      enum: ['php', 'blade', 'twig', 'markdown', 'html', 'generated'],
      description:
        'Detected or explicit source type. Markdown is converted to block content during sync.',
      example: 'markdown',
    },
    markdown: {
      type: 'string',
      description: 'Inline Markdown content for a page or loader entry.',
    },
    html: {
      type: 'string',
      description: 'Inline HTML content for a page or loader entry.',
    },
    content: {
      type: 'string',
      description:
        'Inline content. Use contentType to force markdown or html when needed.',
    },
    file: {
      type: 'string',
      description: 'Local file path for a loader-generated page source.',
    },
    template: {
      type: 'string',
      description: 'Alias for file in loader-generated page sources.',
    },
    generated: {
      type: 'boolean',
      default: true,
      description:
        'Marks loader and container pages as generated so missing output can be pruned.',
    },
    meta: {
      type: 'object',
      description:
        'Custom page metadata. Unknown page.json or frontmatter keys are also stored here.',
    },
    trusted: {
      type: 'boolean',
      description: 'Marks a local generated source as trusted.',
    },
  },
  additionalProperties: true,
};

const collectionSchema = {
  title: 'JSON schema for Blockstudio page collection manifests',
  $schema: 'http://json-schema.org/draft-04/schema#',
  type: 'object',
  properties: {
    collection: {
      type: 'string',
      description: 'Collection slug. This becomes the public URL base.',
      example: 'docs',
    },
    title: {
      type: 'string',
      description: 'Human-readable collection title.',
      example: 'Documentation',
    },
    postType: {
      type: 'string',
      default: 'page',
      description:
        'Post type inherited by collection pages. If missing, pages sync as normal WordPress pages.',
      example: 'bs_docs',
    },
    postTypeArgs: {
      type: 'object',
      description:
        'register_post_type() args for custom collection post types. show_in_rest defaults to true.',
    },
    defaults: {
      type: 'object',
      description: 'Default page properties applied to every collection page.',
    },
    source: {
      type: 'object',
      description: 'Source metadata for the collection.',
    },
    order: {
      type: 'integer',
      description: 'Optional collection ordering metadata.',
    },
    meta: {
      type: 'object',
      description:
        'Custom collection metadata. Unknown manifest keys are also stored here.',
    },
  },
  additionalProperties: true,
};
const rootMeta = readJson(join(docsDir, 'meta.json')) as MetaJson;
const docs = collectDocs(docsDir, rootMeta, 0);

(async () => {
  const blockstudioSchema = readJson(
    resolve(schemasDir, 'blockstudio.json'),
  ) as Record<string, any>;
  const full = readJson(resolve(schemasDir, 'block.json')) as Record<
    string,
    any
  >;
  const ext = readJson(resolve(schemasDir, 'extend.json')) as Record<
    string,
    any
  >;

  const index = collectIndex(schemaPaths(blockstudioSchema));
  const indexOutput = buildIndex(index);
  writeFileSync(indexPath, indexOutput, 'utf-8');

  const schemas: { name: string; filename: string; content: string }[] = [];
  const bs = full.properties.blockstudio;

  const bsFieldTypes = bs.properties?.attributes?.items?.anyOf;
  const defFieldTypes = full.definitions?.Attribute?.anyOf;
  if (bsFieldTypes && defFieldTypes) {
    const defTypeNames = new Set(
      defFieldTypes.map(
        (v: Record<string, unknown>) =>
          (v.properties as Record<string, Record<string, string>>)?.type?.const,
      ),
    );
    const extras = bsFieldTypes.filter((v: Record<string, unknown>) => {
      const t = (v.properties as Record<string, Record<string, string>>)?.type
        ?.const;
      return !defTypeNames.has(t);
    });
    for (const variant of extras) {
      const props = variant.properties as Record<string, unknown>;
      if (
        props?.attributes &&
        typeof props.attributes === 'object' &&
        (props.attributes as Record<string, unknown>).items
      ) {
        props.attributes = {
          type: 'array',
          description: 'Same field types as the top-level attributes.',
        };
      }
    }
    bs.properties.attributes.items = {
      _note:
        'All field types from definitions.Attribute apply here, plus these additional types:',
      additionalTypes: extras,
    };
  }

  const trimmed = { definitions: full.definitions, blockstudio: bs };
  schemas.push({
    name: 'Block Schema (blockstudio key from block.json)',
    filename: 'block.json',
    content: JSON.stringify(trimmed),
  });
  schemas.push({
    name: 'Settings Schema (blockstudio.json)',
    filename: 'blockstudio.json',
    content: JSON.stringify(blockstudioSchema),
  });
  const extendProp = ext.properties?.blockstudio?.properties?.extend;
  const trimmedExt = {
    _note:
      'Extension schema is identical to the block schema above, plus this additional "extend" property on blockstudio.',
    extend: extendProp,
  };
  schemas.push({
    name: 'Extension Schema (extensions.json)',
    filename: 'extensions.json',
    content: JSON.stringify(trimmedExt),
  });

  schemas.splice(2, 0, {
    name: 'Page Schema (page.json)',
    filename: 'page.json',
    content: JSON.stringify(pageSchema),
  });
  schemas.splice(3, 0, {
    name: 'Page Collection Schema (pages.json)',
    filename: 'pages.json',
    content: JSON.stringify(collectionSchema),
  });
  const parts: string[] = [];

  parts.push('# Blockstudio, full text');
  parts.push(
    'Every documentation page and schema in one file, for tools that want the complete corpus.',
  );
  parts.push(
    'The primary context file is the index at /blockstudio-llm.txt: it lists each document with its route, purpose, and the identifiers it owns. Start there and open only what you need.',
  );
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

  writeFileSync(fullPath, output, 'utf-8');

  const size = (value: string) =>
    Math.round(Buffer.byteLength(value, 'utf-8') / 1024);
  console.log(
    `Built ${indexPath}\n  ${index.length} documents, ${publicSchemas.length} schemas, ${size(indexOutput)}KB`,
  );
  console.log(
    `Built ${fullPath}\n  ${docs.length} docs, ${schemas.length} schemas, ${size(output)}KB`,
  );
})();
