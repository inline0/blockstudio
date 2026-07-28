#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { dirname, extname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const excludedPaths = [
  'includes/admin/assets/',
  'includes/icons/',
  'includes/llm/',
  'lib/',
  'node_modules/',
  'vendor/',
];
const excludedFiles = new Set([
  'composer.lock',
  'package-lock.json',
  'scripts/audit-product-neutrality.mjs',
]);
const textExtensions = new Set([
  '.css',
  '.html',
  '.js',
  '.json',
  '.md',
  '.mjs',
  '.php',
  '.scss',
  '.ts',
  '.tsx',
  '.txt',
  '.twig',
  '.yaml',
  '.yml',
]);
const forbidden = [
  {
    label: 'consumer product identifier',
    pattern: new RegExp(`\\b${'di' + 'vine'}\\b`, 'i'),
  },
  {
    label: 'consumer custom-element prefix',
    pattern: new RegExp(`<\\/?\\s*${'d' + 'v'}-`, 'i'),
  },
  {
    label: 'consumer branch concept',
    pattern: new RegExp(`\\b${'work' + 'tree'}s?\\b`, 'i'),
  },
  {
    label: 'consumer filesystem type',
    pattern: new RegExp(`${'virtual' + 'theme' + 'filesystem'}`, 'i'),
  },
  {
    label: 'consumer-specific absolute source path',
    pattern: new RegExp(`/${'work' + 'space'}/`, 'i'),
  },
];

const listed = execFileSync(
  'git',
  ['ls-files', '--cached', '--others', '--exclude-standard', '-z'],
  { cwd: repositoryRoot, encoding: 'utf8' },
)
  .split('\0')
  .filter(Boolean)
  .sort();
const problems = [];

for (const repositoryPath of listed) {
  const normalized = repositoryPath.replaceAll('\\', '/');
  if (
    excludedFiles.has(normalized) ||
    excludedPaths.some((prefix) => normalized.startsWith(prefix))
  ) {
    continue;
  }

  const absolutePath = resolve(repositoryRoot, repositoryPath);
  if (!existsSync(absolutePath) || !statSync(absolutePath).isFile()) {
    continue;
  }

  for (const { label, pattern } of forbidden) {
    if (pattern.test(normalized)) {
      problems.push(`${normalized}: path contains ${label}`);
    }
  }

  if (!textExtensions.has(extname(normalized).toLowerCase())) {
    continue;
  }

  const source = readFileSync(absolutePath, 'utf8');
  source.split(/\r?\n/).forEach((line, index) => {
    for (const { label, pattern } of forbidden) {
      if (pattern.test(line)) {
        problems.push(
          `${relative(repositoryRoot, absolutePath)}:${index + 1} contains ${label}`,
        );
      }
    }
  });
}

if (problems.length) {
  console.error(`Product-neutrality audit failed:\n- ${problems.join('\n- ')}`);
  process.exit(1);
}

console.log(
  `Product-neutrality audit passed across ${listed.length} tracked and untracked repository files.`,
);
