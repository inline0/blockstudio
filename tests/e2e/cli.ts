import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

const wp = (cmd: string) => {
  const result = execSync(
    `cd tests/wp-env && npx wp-env run cli wp ${cmd}`,
    { encoding: 'utf-8', timeout: 30000 }
  ).trim();

  const lines = result.split('\n');
  const output = lines.filter(
    (l) => !l.startsWith('ℹ ') && !l.startsWith('✔ ')
  );
  return output.join('\n').trim();
};

test.describe('CLI - blocks', () => {
  test('lists blocks', () => {
    const out = wp('bs blocks list --format=json');
    const blocks = JSON.parse(out);
    expect(Array.isArray(blocks)).toBe(true);
    expect(blocks.length).toBeGreaterThan(0);
    expect(blocks[0].name).toBeDefined();
  });

  test('lists components only', () => {
    const out = wp('bs blocks list --components --format=json');
    const blocks = JSON.parse(out);
    expect(blocks.length).toBeGreaterThan(0);
    expect(blocks.every((b: any) => b.component === 'yes')).toBe(true);
  });
});

test.describe('CLI - db', () => {
  test('lists schemas', () => {
    const out = wp('bs db schemas --format=json');
    const schemas = JSON.parse(out);
    expect(Array.isArray(schemas)).toBe(true);
    expect(schemas.length).toBeGreaterThan(0);
    expect(schemas[0].block).toBeDefined();
    expect(schemas[0].storage).toBeDefined();
  });

  let createdId: string;

  test('creates a record', () => {
    const out = wp(
      'bs db create blockstudio/type-db-table default --title="CLI Test"'
    );
    expect(out).toContain('Record created');
    const match = out.match(/ID: (\d+)/);
    expect(match).not.toBeNull();
    createdId = match![1];
  });

  test('gets a record', () => {
    const out = wp(
      `bs db get blockstudio/type-db-table default ${createdId}`
    );
    expect(out).toContain('title: CLI Test');
  });

  test('lists records', () => {
    const out = wp('bs db list blockstudio/type-db-table default --format=json');
    const rows = JSON.parse(out);
    expect(rows.some((r: any) => r.title === 'CLI Test')).toBe(true);
  });

  test('updates a record', () => {
    const out = wp(
      `bs db update blockstudio/type-db-table default ${createdId} --title="Updated CLI"`
    );
    expect(out).toContain('updated');
  });

  test('deletes a record', () => {
    const out = wp(
      `bs db delete blockstudio/type-db-table default ${createdId}`
    );
    expect(out).toContain('deleted');
  });
});

test.describe('CLI - rpc', () => {
  test('lists functions', () => {
    const out = wp('bs rpc list --format=json');
    const fns = JSON.parse(out);
    expect(Array.isArray(fns)).toBe(true);
    expect(fns.length).toBeGreaterThan(0);
    expect(fns[0].block).toBeDefined();
    expect(fns[0].function).toBeDefined();
  });

  test('calls a function', () => {
    const out = wp(
      'bs rpc call blockstudio/type-functions greet --name=CLI'
    );
    const result = JSON.parse(out);
    expect(result.message).toBe('Hello, CLI!');
  });
});

test.describe('CLI - cron', () => {
  test('lists jobs', () => {
    const out = wp('bs cron list --format=json');
    const jobs = JSON.parse(out);
    expect(Array.isArray(jobs)).toBe(true);
    expect(jobs.length).toBeGreaterThan(0);
    expect(jobs[0].block).toBeDefined();
    expect(jobs[0].schedule).toBeDefined();
  });

  test('runs a job', () => {
    const out = wp('bs cron run blockstudio/type-cron cleanup');
    expect(out).toContain('Done');
  });
});

test.describe('CLI - settings', () => {
  test('lists settings', () => {
    const out = wp('bs settings list');
    const settings = JSON.parse(out);
    expect(typeof settings).toBe('object');
  });

  test('gets a setting', () => {
    const out = wp('bs settings get assets/enqueue');
    expect(out).toBeDefined();
  });
});
