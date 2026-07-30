import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import {
  existsSync,
  readFileSync,
  readdirSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'fs';
import { join, relative } from 'path';

const wp = (cmd: string) => {
  const result = execSync(
    `cd tests/wp-env && npx wp-env run cli -- wp ${cmd}`,
    { encoding: 'utf-8', timeout: 30000 }
  ).trim();

  const lines = result.split('\n');
  const output = lines.filter(
    (l) =>
      !l.startsWith('ℹ ') &&
      !l.startsWith('✔ ') &&
      !l.startsWith('Ran ')
  );
  return output.join('\n').trim();
};

const themeJsonPath = 'tests/theme/blockstudio.json';
const contentPath = 'tests/theme/content-sync-e2e';

function readFilesRecursive(dir: string): Record<string, string> {
  if (!existsSync(dir)) return {};

  const files: Record<string, string> = {};
  const visit = (current: string) => {
    for (const entry of readdirSync(current)) {
      const path = join(current, entry);
      if (statSync(path).isDirectory()) {
        visit(path);
      } else {
        files[relative(dir, path)] = readFileSync(path, 'utf8');
      }
    }
  };

  visit(dir);
  return files;
}

function cleanContentSyncState() {
  wp(
    "eval 'foreach (get_posts(array(\"post_type\"=>\"bs_content_sync\",\"post_status\"=>\"any\",\"numberposts\"=>-1)) as $post) { wp_delete_post($post->ID, true); }'"
  );
  wp(
    "eval 'for ($i = 0; $i < 5; $i++) { $terms = get_terms(array(\"taxonomy\"=>\"bs_content_topic\",\"hide_empty\"=>false,\"orderby\"=>\"parent\",\"order\"=>\"DESC\")); if (is_wp_error($terms) || empty($terms)) { break; } foreach ($terms as $term) { wp_delete_term($term->term_id, \"bs_content_topic\"); } }'"
  );

  if (existsSync(contentPath)) {
    rmSync(contentPath, { recursive: true, force: true });
  }
}

function writeContentSyncSettings(original: string) {
  const settings = JSON.parse(original);
  settings.content = {
    enabled: true,
    id: 'e2e',
    path: 'content-sync-e2e',
    includePageSyncManaged: false,
    authors: 'ignore',
    postTypes: ['bs_content_sync'],
    meta: {
      include: ['_my_*', '_related_posts'],
      exclude: ['_edit_lock', '_edit_last', '_wp_old_slug'],
      references: {
        _related_posts: { kind: 'post', path: '*' },
      },
    },
    taxonomies: ['bs_content_topic'],
    media: 'manifest',
  };

  writeFileSync(themeJsonPath, `${JSON.stringify(settings, null, 2)}\n`);
}

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

test.describe('CLI - pages', () => {
  test('synchronizes the complete page inventory and stores its identity', () => {
    const report = JSON.parse(wp('bs pages sync --format=json'));

    expect(report.discovered).toBeGreaterThan(0);
    expect(report.failed).toBe(0);
    expect(report.errors).toEqual([]);
    expect(report.sourceIdentity.hash).toBe(report.sourceId);

    const stored = JSON.parse(
      wp(
        'option get blockstudio_pages_successful_source_identity --format=json'
      )
    );
    expect(stored.hash).toBe(report.sourceIdentity.hash);
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

test.describe.serial('CLI - content sync', () => {
  let originalSettings: string;

  test.beforeAll(() => {
    originalSettings = readFileSync(themeJsonPath, 'utf8');
    writeContentSyncSettings(originalSettings);
    cleanContentSyncState();
  });

  test.afterAll(() => {
    cleanContentSyncState();
    writeFileSync(themeJsonPath, originalSettings);
  });

  test('pulls files, pushes into an empty database, and remains stable', () => {
    const parentId = wp(
      'post create --post_type=bs_content_sync --post_status=publish --post_title="Content Sync Parent" --post_name=content-sync-parent --post_content="<p>Parent Body</p>" --porcelain'
    );
    const childId = wp(
      `post create --post_type=bs_content_sync --post_status=publish --post_title="Content Sync Child" --post_name=content-sync-child --post_parent=${parentId} --post_content="<p>Child Body</p>" --porcelain`
    );
    const parentTermId = wp(
      'term create bs_content_topic "Content Sync Topic" --slug=content-sync-topic --porcelain'
    );
    const childTermId = wp(
      `term create bs_content_topic "Content Sync Child Topic" --slug=content-sync-child-topic --parent=${parentTermId} --porcelain`
    );

    wp(`post meta update ${parentId} _my_subtitle "Parent Subtitle"`);
    wp(`post meta update ${childId} _my_subtitle "Child Subtitle"`);
    wp(`post meta update ${childId} _related_posts '[${parentId}]'`);
    wp(
      `eval 'wp_set_object_terms(${childId}, array(${childTermId}), "bs_content_topic", false);'`
    );

    const pullRows = JSON.parse(wp('bs content pull --format=json'));
    expect(pullRows.some((row: any) => row.action === 'written')).toBe(true);

    const pulledFiles = readFilesRecursive(contentPath);
    expect(Object.keys(pulledFiles)).toEqual(
      expect.arrayContaining([
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-parent\.[a-f0-9-]+\.json$/
        ),
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-child\.[a-f0-9-]+\.json$/
        ),
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-parent\.[a-f0-9-]+\.html$/
        ),
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-child\.[a-f0-9-]+\.html$/
        ),
        expect.stringMatching(
          /^terms\/bs_content_topic\/content-sync-topic\.[a-f0-9-]+\.json$/
        ),
        expect.stringMatching(
          /^terms\/bs_content_topic\/content-sync-child-topic\.[a-f0-9-]+\.json$/
        ),
      ])
    );

    wp(`post delete ${childId} ${parentId} --force`);
    wp(`term delete bs_content_topic ${childTermId} ${parentTermId}`);

    const pushRows = JSON.parse(wp('bs content push --format=json'));
    expect(
      pushRows.filter(
        (row: any) => row.action === 'created' && row.entity === 'post'
      )
    ).toHaveLength(2);
    expect(
      pushRows.filter(
        (row: any) => row.action === 'created' && row.entity === 'term'
      )
    ).toHaveLength(2);

    const terms = JSON.parse(
      wp(
        'term list bs_content_topic --hide-empty=0 --fields=term_id,slug,parent --format=json'
      )
    );
    const parentTerm = terms.find(
      (term: any) => term.slug === 'content-sync-topic'
    );
    const childTerm = terms.find(
      (term: any) => term.slug === 'content-sync-child-topic'
    );

    const posts = JSON.parse(
      wp(
        'post list --post_type=bs_content_sync --post_status=any --fields=ID,post_name,post_parent --format=json'
      )
    );
    const parent = posts.find(
      (post: any) => post.post_name === 'content-sync-parent'
    );
    const child = posts.find(
      (post: any) => post.post_name === 'content-sync-child'
    );

    expect(parent).toBeDefined();
    expect(child).toBeDefined();
    expect(parentTerm).toBeDefined();
    expect(childTerm).toBeDefined();
    expect(Number(child.post_parent)).toBe(Number(parent.ID));
    expect(Number(childTerm.parent)).toBe(Number(parentTerm.term_id));
    expect(wp(`post meta get ${child.ID} _my_subtitle`)).toBe('Child Subtitle');
    expect(JSON.parse(wp(`post meta get ${child.ID} _related_posts`))).toEqual([
      Number(parent.ID),
    ]);
    expect(
      JSON.parse(
        wp(
          `eval 'echo wp_json_encode(array_map("intval", wp_get_object_terms(${child.ID}, "bs_content_topic", array("fields"=>"ids"))));'`
        )
      )
    ).toEqual([Number(childTerm.term_id)]);

    const statusRows = JSON.parse(wp('bs content status --format=json'));
    expect(statusRows.every((row: any) => row.action === 'unchanged')).toBe(
      true
    );
    expect(statusRows).toHaveLength(4);

    const secondPushRows = JSON.parse(wp('bs content push --format=json'));
    expect(secondPushRows.every((row: any) => row.action === 'unchanged')).toBe(
      true
    );
    expect(secondPushRows).toHaveLength(4);

    wp('bs content pull --format=json');
    expect(readFilesRecursive(contentPath)).toEqual(pulledFiles);
  });
});
