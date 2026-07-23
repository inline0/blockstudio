#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const schemasRoot = resolve(repositoryRoot, 'schemas');
const manifest = JSON.parse(
  readFileSync(resolve(schemasRoot, 'manifest.json'), 'utf8'),
);
const expected = ['block', 'blockstudio', 'extend', 'field', 'page'];
const problems = [];

for (const name of expected) {
  const filename = `${name}.json`;
  const path = resolve(schemasRoot, filename);
  let bytes;
  let schema;

  try {
    bytes = readFileSync(path);
    schema = JSON.parse(bytes.toString('utf8'));
  } catch (error) {
    problems.push(`${filename}: ${error.message}`);
    continue;
  }

  const actualHash = createHash('sha256').update(bytes).digest('hex');
  const expectedHash = manifest.files?.[filename];
  if (actualHash !== expectedHash) {
    problems.push(
      `${filename}: checksum ${actualHash} does not match manifest ${expectedHash ?? '(missing)'}`,
    );
  }

  if (
    typeof schema !== 'object' ||
    schema === null ||
    schema.type !== 'object' ||
    typeof schema.properties !== 'object' ||
    schema.properties === null
  ) {
    problems.push(`${filename}: expected an object JSON Schema with properties`);
  }
}

const manifestFiles = Object.keys(manifest.files ?? {}).sort();
const expectedFiles = expected.map((name) => `${name}.json`).sort();
if (JSON.stringify(manifestFiles) !== JSON.stringify(expectedFiles)) {
  problems.push('manifest.json does not list exactly the five public schemas');
}

if (problems.length) {
  console.error(`Schema check failed:\n- ${problems.join('\n- ')}`);
  process.exit(1);
}

console.log('Schema check passed: five valid, checksum-pinned JSON schemas.');
