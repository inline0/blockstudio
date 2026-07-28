#!/usr/bin/env node

import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, extname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const contentRoot = resolve(repositoryRoot, 'docs');
const problems = [];
const expectedDocuments = {
  docs: 72,
  guides: 9,
  registry: 14,
  blog: 8,
  ui: 2,
};
const draftCollections = new Set(['drafts']);
const expectedMetaFiles = 19;

function collectionOf(path) {
  return relative(contentRoot, path).split(/[\\/]/)[0];
}

function filesUnder(root) {
  const files = [];
  const walk = (directory) => {
    for (const name of readdirSync(directory).sort()) {
      const path = resolve(directory, name);
      if (statSync(path).isDirectory()) walk(path);
      else files.push(path);
    }
  };
  walk(root);
  return files;
}

function frontmatter(source) {
  const match = source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?/);
  if (!match) return null;
  const values = {};
  for (const line of match[1].split(/\r?\n/)) {
    const field = line.match(/^([A-Za-z0-9_-]+):\s*(.*)$/);
    if (field) values[field[1]] = field[2].trim().replace(/^(["'])(.*)\1$/, '$2');
  }
  return values;
}

function checkPortableMarkdown(path, source) {
  let fence = '';
  source.split(/\r?\n/).forEach((line, index) => {
    const match = line.match(/^\s*(`{3,}|~{3,})/);
    if (match) {
      const token = match[1][0];
      if (!fence) fence = token;
      else if (fence === token) fence = '';
      return;
    }
    if (fence) return;
    if (/^\s*<\/?(?:Tabs?|Callout|GuideBanner)\b/.test(line) || /\{\/\*/.test(line)) {
      problems.push(`${relative(contentRoot, path)}:${index + 1} contains MDX-only syntax`);
    }
    if (/^\s*(?:import|export)\s/.test(line)) {
      problems.push(`${relative(contentRoot, path)}:${index + 1} contains executable module syntax`);
    }
  });
  if (fence) problems.push(`${relative(contentRoot, path)} has an unclosed code fence`);
}

const files = filesUnder(contentRoot);
const markdown = files.filter((file) => extname(file) === '.md');
const mdx = files.filter((file) => extname(file) === '.mdx');
if (mdx.length) problems.push(`${mdx.length} .mdx files remain`);

const published = markdown.filter((file) => !draftCollections.has(collectionOf(file)));
const routeKeys = new Set();
const metaByFile = new Map();
for (const file of markdown) {
  const source = readFileSync(file, 'utf8');
  const meta = frontmatter(source);
  if (!meta) {
    problems.push(`${relative(contentRoot, file)} has no frontmatter`);
    continue;
  }
  metaByFile.set(file, meta);
  checkPortableMarkdown(file, source);
  const collection = collectionOf(file);
  if (draftCollections.has(collection)) {
    if (meta.date !== undefined) problems.push(`${relative(contentRoot, file)} is a draft but declares a release date`);
    if (meta.order !== undefined) problems.push(`${relative(contentRoot, file)} is a draft but declares a published order`);
    continue;
  }
  for (const field of ['title', 'path', 'order', 'section', 'meta_title']) {
    if (!(field in meta) || meta[field] === '') problems.push(`${relative(contentRoot, file)} is missing ${field}`);
  }
  const key = `${collection}:${meta.path}`;
  if (routeKeys.has(key)) problems.push(`${relative(contentRoot, file)} duplicates ${key}`);
  routeKeys.add(key);
}

for (const [collection, expected] of Object.entries(expectedDocuments)) {
  const actual = published.filter((file) => collectionOf(file) === collection).length;
  if (actual !== expected) problems.push(`${collection} has ${actual} Markdown documents; expected ${expected}`);
}

for (const file of markdown) {
  const collection = collectionOf(file);
  if (!draftCollections.has(collection) && !(collection in expectedDocuments)) {
    problems.push(`${relative(contentRoot, file)} is outside every known collection`);
  }
}

const metaFiles = files.filter((path) => path.endsWith('meta.json'));
if (metaFiles.length !== expectedMetaFiles) {
  problems.push(`${metaFiles.length} meta.json files found; expected ${expectedMetaFiles}`);
}

for (const file of metaFiles) {
  try {
    const meta = JSON.parse(readFileSync(file, 'utf8'));
    if (!Array.isArray(meta.pages)) {
      problems.push(`${relative(contentRoot, file)} has no pages array`);
      continue;
    }
    for (const page of meta.pages) {
      if (typeof page !== 'string' || page.startsWith('---')) continue;
      const directory = dirname(file);
      if (!existsSync(resolve(directory, `${page}.md`)) && !existsSync(resolve(directory, page))) {
        problems.push(`${relative(contentRoot, file)} references missing page ${page}`);
      }
    }
  } catch (error) {
    problems.push(`${relative(contentRoot, file)} is invalid JSON: ${error.message}`);
  }
}

const indexedCollections = new Set(['docs', 'guides', 'registry']);
const artifacts = {
  index: resolve(repositoryRoot, 'includes/llm/blockstudio-llm.txt'),
  full: resolve(repositoryRoot, 'includes/llm/blockstudio-llm-full.txt'),
};
let indexedFiles = new Set();

function slugToTitle(slug) {
  return slug
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function groupFor(file, meta) {
  const parts = [slugToTitle(collectionOf(file)), meta.section ?? '', meta.subsection ?? ''];
  return parts.filter((part, position) => part !== '' && part !== parts[position - 1]).join(' / ');
}

function routeFor(file, meta) {
  const collection = collectionOf(file);
  return meta.path === '.' ? `/${collection}` : `/${collection}/${meta.path}`;
}

function readIndexEntries(source) {
  const entries = [];
  let group = '';
  let entry = null;
  for (const line of source.split(/\r?\n/)) {
    const heading = line.match(/^## (.+)$/);
    if (heading) {
      group = heading[1];
      continue;
    }
    if (line.startsWith('### ')) {
      entry = { group, title: line.slice(4), route: '', file: '' };
      entries.push(entry);
      continue;
    }
    if (!entry) continue;
    if (line.startsWith('route: ')) entry.route = line.slice(7);
    if (line.startsWith('file: ')) entry.file = line.slice(6);
  }
  return entries;
}

for (const [name, path] of Object.entries(artifacts)) {
  if (!existsSync(path)) {
    problems.push(`the ${name} LLM artifact is missing; run npm run build:llm`);
  }
}

if (existsSync(artifacts.index)) {
  const entries = readIndexEntries(readFileSync(artifacts.index, 'utf8'));
  indexedFiles = new Set(entries.map((entry) => entry.file));
  const documents = new Map(
    published.map((file) => [relative(repositoryRoot, file).replaceAll('\\', '/'), file]),
  );

  for (const entry of entries) {
    if (!existsSync(resolve(repositoryRoot, entry.file))) {
      problems.push(`the documentation index references missing ${entry.file}`);
      continue;
    }
    const file = documents.get(entry.file);
    if (!file) continue;
    const meta = metaByFile.get(file);
    if (!meta) continue;
    const group = groupFor(file, meta);
    const route = routeFor(file, meta);
    if (entry.group !== group) {
      problems.push(`the documentation index files ${entry.file} under "${entry.group}"; expected "${group}"`);
    }
    if (entry.route !== route) {
      problems.push(`the documentation index routes ${entry.file} to ${entry.route}; expected ${route}`);
    }
  }

  for (const file of published) {
    const path = relative(repositoryRoot, file).replaceAll('\\', '/');
    if (indexedCollections.has(collectionOf(file)) && !indexedFiles.has(path)) {
      problems.push(`${path} is missing from the documentation index; run npm run build:llm`);
    }
  }
}

const expectedTotal = Object.values(expectedDocuments).reduce((total, count) => total + count, 0);
if (published.length !== expectedTotal) problems.push(`${published.length} published Markdown documents found; expected ${expectedTotal}`);
if (routeKeys.size !== expectedTotal) problems.push(`${routeKeys.size} unique collection paths found; expected ${expectedTotal}`);

if (problems.length) {
  console.error(`Markdown content check failed:\n- ${problems.join('\n- ')}`);
  process.exit(1);
}

console.log(
  `Markdown content valid: ${published.length} published documents, ${routeKeys.size} unique collection paths, ${markdown.length - published.length} unpublished drafts, ${indexedFiles.size} files in the LLM index, no MDX runtime syntax.`,
);
