#!/usr/bin/env node

import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, extname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const docsRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const contentRoot = resolve(docsRoot, 'content');
const problems = [];
const expectedDocuments = {
  docs: 69,
  guides: 9,
  registry: 14,
  blog: 7,
};
const expectedMetaFiles = 18;

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

const routeKeys = new Set();
for (const file of markdown) {
  const source = readFileSync(file, 'utf8');
  const meta = frontmatter(source);
  if (!meta) {
    problems.push(`${relative(contentRoot, file)} has no frontmatter`);
    continue;
  }
  for (const field of ['title', 'path', 'order', 'section', 'meta_title']) {
    if (!(field in meta) || meta[field] === '') problems.push(`${relative(contentRoot, file)} is missing ${field}`);
  }
  const collection = relative(contentRoot, file).split(/[\\/]/)[0];
  const key = `${collection}:${meta.path}`;
  if (routeKeys.has(key)) problems.push(`${relative(contentRoot, file)} duplicates ${key}`);
  routeKeys.add(key);
  checkPortableMarkdown(file, source);
}

for (const [collection, expected] of Object.entries(expectedDocuments)) {
  const actual = markdown.filter(
    (file) => relative(contentRoot, file).split(/[\\/]/)[0] === collection,
  ).length;
  if (actual !== expected) problems.push(`${collection} has ${actual} Markdown documents; expected ${expected}`);
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

const expectedTotal = Object.values(expectedDocuments).reduce((total, count) => total + count, 0);
if (markdown.length !== expectedTotal) problems.push(`${markdown.length} Markdown documents found; expected ${expectedTotal}`);
if (routeKeys.size !== expectedTotal) problems.push(`${routeKeys.size} unique collection paths found; expected ${expectedTotal}`);

if (problems.length) {
  console.error(`Markdown content check failed:\n- ${problems.join('\n- ')}`);
  process.exit(1);
}

console.log(`Markdown content valid: ${markdown.length} documents, ${routeKeys.size} unique collection paths, no MDX runtime syntax.`);
