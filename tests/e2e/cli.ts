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

const cleanWpOutput = (result: string) => {
  return result
    .split('\n')
    .filter(
      (line) =>
        !line.startsWith('ℹ ') &&
        !line.startsWith('✔ ') &&
        !line.startsWith('Ran '),
    )
    .join('\n')
    .trim();
};

const wp = (cmd: string) => {
  const result = execSync(
    `cd tests/wp-env && npx wp-env run cli -- wp ${cmd}`,
    { encoding: 'utf-8', timeout: 30000 },
  ).trim();

  return cleanWpOutput(result);
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
    'eval \'foreach (get_posts(array("post_type"=>"bs_content_sync","post_status"=>"any","numberposts"=>-1)) as $post) { wp_delete_post($post->ID, true); }\'',
  );
  wp(
    'eval \'for ($i = 0; $i < 5; $i++) { $terms = get_terms(array("taxonomy"=>"bs_content_topic","hide_empty"=>false,"orderby"=>"parent","order"=>"DESC")); if (is_wp_error($terms) || empty($terms)) { break; } foreach ($terms as $term) { wp_delete_term($term->term_id, "bs_content_topic"); } }\'',
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

  test('validates nested stored fields with JSON, exit codes and no post writes', () => {
    const ids = JSON.parse(
      wp(
        `eval '$ids = array(); foreach (array("one", "unknown-option") as $value) { $block = array("blockName" => "blockstudio/type-select", "attrs" => array("blockstudio" => array("attributes" => array("defaultValueLabel" => $value))), "innerBlocks" => array(), "innerHTML" => "", "innerContent" => array()); $content = "<!-- wp:group -->" . serialize_block($block) . "<!-- /wp:group -->"; $ids[] = wp_insert_post(wp_slash(array("post_type" => "post", "post_status" => "draft", "post_title" => "Validation CLI", "post_content" => $content))); } echo wp_json_encode($ids);'`,
      ),
    );
    try {
      const before = ids.map((id: number) =>
        JSON.parse(wp(`post get ${id} --format=json`)),
      );
      expect(
        JSON.parse(wp(`bs blocks validate ${ids[0]} --format=json`)),
      ).toEqual([]);
      expect(wp(`bs blocks validate ${ids[0]}`)).toContain(
        'No stored field problems found',
      );
      let failure: { status: number; stdout: string } | undefined;
      try {
        wp(`bs blocks validate ${ids.join(' ')} --format=json`);
      } catch (error) {
        failure = error as typeof failure;
      }
      expect(failure?.status).toBe(1);
      const findings = JSON.parse(cleanWpOutput(failure!.stdout));
      expect(findings).toHaveLength(1);
      expect(findings[0]).toMatchObject({
        post_id: ids[1],
        block_path: '0/0',
        block_name: 'blockstudio/type-select',
        code: 'unknown_option',
        path: 'defaultValueLabel',
        source: 'block',
        value: 'unknown-option',
      });
      expect(findings[0].expected).toEqual(['one', 'two', 'three']);
      expect(() => wp(`bs blocks validate ${ids[1]}`)).toThrow();
      expect(() => wp('bs blocks validate')).toThrow();
      expect(() => wp('bs blocks validate 0 --format=json')).toThrow();
      expect(() => wp(`bs blocks validate ${ids[0]} --format=csv`)).toThrow();
      expect(
        ids.map((id: number) => JSON.parse(wp(`post get ${id} --format=json`))),
      ).toEqual(before);
    } finally {
      wp(`post delete ${ids.join(' ')} --force`);
    }
  });

  test('reports unknown top-level values and missing wrappers without changing posts', () => {
    const attributes = [
      { text: 'Valid', className: 'custom-class' },
      { txt: 'Unknown field' },
      { attributes: { text: 'Missing wrapper' } },
    ];
    const encoded = Buffer.from(JSON.stringify(attributes)).toString('base64');
    const ids: number[] = JSON.parse(
      wp(
        `eval '$ids = array(); foreach (json_decode(base64_decode("${encoded}"), true) as $attrs) { $block = array("blockName" => "blockstudio/type-text", "attrs" => $attrs, "innerBlocks" => array(), "innerHTML" => "", "innerContent" => array()); $ids[] = wp_insert_post(wp_slash(array("post_type" => "post", "post_status" => "draft", "post_title" => "Top-level validation CLI", "post_content" => serialize_block($block)))); } echo wp_json_encode($ids);'`,
      ),
    );
    try {
      const before = ids.map((id) =>
        JSON.parse(wp(`post get ${id} --format=json`)),
      );
      expect(
        JSON.parse(wp(`bs blocks validate ${ids[0]} --format=json`)),
      ).toEqual([]);
      let failure: { status: number; stdout: string } | undefined;
      try {
        wp(`bs blocks validate ${ids.join(' ')} --format=json`);
      } catch (error) {
        failure = error as typeof failure;
      }
      expect(failure?.status).toBe(1);
      const findings = JSON.parse(cleanWpOutput(failure!.stdout));
      expect(findings).toHaveLength(2);
      expect(findings[0]).toMatchObject({
        post_id: ids[1],
        block_name: 'blockstudio/type-text',
        block_path: '0',
        code: 'unknown_attribute',
        source: 'block',
        path: 'txt',
        value: 'Unknown field',
      });
      expect(findings[0].expected).toContain('text');
      expect(findings[1]).toMatchObject({
        post_id: ids[2],
        block_name: 'blockstudio/type-text',
        block_path: '0',
        code: 'missing_container',
        path: 'attributes',
        expected: 'blockstudio/attributes',
      });
      expect(
        ids.map((id) => JSON.parse(wp(`post get ${id} --format=json`))),
      ).toEqual(before);
    } finally {
      wp(`post delete ${ids.join(' ')} --force`);
    }
  });
});

test.describe('CLI - server defaults', () => {
  test('renders min repeater rows without an editor save or content mutation', async ({
    page,
  }) => {
    const content = '<!-- wp:blockstudio/type-repeater-min-rows /-->';
    const id = wp(
      `eval 'echo wp_insert_post(array("post_title" => "Server repeater defaults", "post_status" => "publish", "post_content" => "${content}"));'`,
    );
    try {
      const response = await page.goto(`http://localhost:8888/?p=${id}`);
      expect(response?.status()).toBe(200);
      const text = await page
        .locator('#blockstudio-type-repeater-min-rows code')
        .first()
        .textContent();
      const values = JSON.parse(text!.replace(/^Attributes:\s*/, ''));
      expect(values.entries).toEqual(
        Array.from({ length: 3 }, () => ({
          name: 'Default Name',
          value: 42,
          enabled: true,
        })),
      );
      expect(wp(`post get ${id} --field=post_content`)).toBe(content);
    } finally {
      wp(`post delete ${id} --force`);
    }
  });
});

test.describe('CLI - block rendering', () => {
  test('reports renders using defaults and explicit JSON attributes', () => {
    const result = JSON.parse(
      wp('bs blocks render blockstudio/type-text --format=json'),
    );
    expect(result).toEqual([
      { block: 'blockstudio/type-text', case: 'defaults', problems: [] },
    ]);
    const override = JSON.parse(
      wp(
        `bs blocks render blockstudio/type-text --attributes='{"text":"Render check"}' --format=json`,
      ),
    );
    expect(override).toEqual([
      { block: 'blockstudio/type-text', case: 'attributes', problems: [] },
    ]);
  });

  test('accepts attribute files and rejects arrays, malformed JSON, and unknown blocks', () => {
    const file = 'tests/theme/render-attributes-e2e.json';
    const theme = wp("eval 'echo get_stylesheet_directory();'");
    try {
      writeFileSync(file, JSON.stringify({ text: 'From a file' }));
      const result = JSON.parse(
        wp(
          `bs blocks render blockstudio/type-text --attributes=@${theme}/render-attributes-e2e.json --format=json`,
        ),
      );
      expect(result[0].case).toBe('attributes');
      expect(result[0].problems).toEqual([]);
      for (const attributes of ['[]', '[1]', 'null', '{']) {
        expect(() =>
          wp(
            `bs blocks render blockstudio/type-text --attributes='${attributes}' --format=json`,
          ),
        ).toThrow();
      }
      expect(() =>
        wp('bs blocks render blockstudio/no-such-block --format=json'),
      ).toThrow();
    } finally {
      rmSync(file, { force: true });
    }
  });

  test('returns a failure exit code with parseable JSON for template warnings', () => {
    const theme = wp("eval 'echo get_stylesheet_directory();'");
    try {
      wp(
        `bs blocks render blockstudio-test/render-warning --require=${theme}/fixtures/render-check.php --format=json`,
      );
      throw new Error('Expected the render command to fail.');
    } catch (error) {
      const failure = error as { status: number; stdout: string | Buffer };
      expect(failure.status).toBe(1);
      const report = JSON.parse(cleanWpOutput(String(failure.stdout)));
      expect(report[0].problems).toHaveLength(1);
      expect(report[0].problems[0]).toMatchObject({
        level: 'Warning',
        message: 'Render fixture warning',
      });
    }
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
        'option get blockstudio_pages_successful_source_identity --format=json',
      ),
    );
    expect(stored.hash).toBe(report.sourceIdentity.hash);
  });
});

test.describe('CLI - WPML', () => {
  test('omits opted-out fields without omitting the translated block', () => {
    const generated = wp('bs wpml config');
    const entry = generated.match(
      /<gutenberg-block type="blockstudio\/test-wpml-translate"[^>]*>[\s\S]*?<\/gutenberg-block>/,
    )?.[0];

    expect(entry).toBeDefined();
    expect(entry).toContain('translate="1"');
    expect(entry).toContain('<key name="heading"');
    expect(entry).not.toContain('<key name="account"');
    expect(entry).not.toContain('<key name="contact_email"');
  });

  test('prints configuration and updates only its managed XML section', () => {
    const generated = wp('bs wpml config');
    expect(generated).toContain('<wpml-config>');
    expect(generated).toContain(
      '<gutenberg-block type="blockstudio/type-text" translate="1"',
    );
    expect(generated).toContain('<key name="blockstudio">');
    expect(generated).toContain('<key name="attributes">');
    expect(generated).toContain('type="post-ids" sub-type="attachment"');

    const file = 'tests/theme/wpml-config-e2e.xml';
    const theme = wp("eval 'echo get_stylesheet_directory();'");
    const command = `bs wpml config --file=${theme}/wpml-config-e2e.xml`;
    const prefix =
      '<wpml-config>\n<custom-types><custom-type translate="1">book</custom-type></custom-types>\n<gutenberg-blocks>\n<gutenberg-block type="other/map" translate="0" />\n';
    const suffix = '\n</gutenberg-blocks>\n</wpml-config>\n';

    try {
      expect(existsSync(file)).toBe(false);
      expect(wp(command)).toContain('Wrote');
      const created = readFileSync(file, 'utf8');
      expect(created.trim()).toBe(generated);
      expect(wp(command)).toContain('is up to date');
      expect(readFileSync(file, 'utf8')).toBe(created);

      writeFileSync(
        file,
        `${prefix}<!-- blockstudio:start -->\n<!-- blockstudio:end -->${suffix}`,
      );
      expect(wp(command)).toContain('Wrote');
      const merged = readFileSync(file, 'utf8');
      expect(merged.startsWith(prefix)).toBe(true);
      expect(merged.endsWith(suffix)).toBe(true);
      expect(merged).toContain(
        '<gutenberg-block type="blockstudio/type-text" translate="1"',
      );
      expect(wp(command)).toContain('is up to date');
      expect(readFileSync(file, 'utf8')).toBe(merged);

      const unmanaged = '<wpml-config><gutenberg-blocks /></wpml-config>';
      writeFileSync(file, unmanaged);
      expect(() => wp(command)).toThrow();
      expect(readFileSync(file, 'utf8')).toBe(unmanaged);
    } finally {
      rmSync(file, { force: true });
    }
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
      'bs db create blockstudio/type-db-table default --title="CLI Test"',
    );
    expect(out).toContain('Record created');
    const match = out.match(/ID: (\d+)/);
    expect(match).not.toBeNull();
    createdId = match![1];
  });

  test('gets a record', () => {
    const out = wp(`bs db get blockstudio/type-db-table default ${createdId}`);
    expect(out).toContain('title: CLI Test');
  });

  test('lists records', () => {
    const out = wp(
      'bs db list blockstudio/type-db-table default --format=json',
    );
    const rows = JSON.parse(out);
    expect(rows.some((r: any) => r.title === 'CLI Test')).toBe(true);
  });

  test('updates a record', () => {
    const out = wp(
      `bs db update blockstudio/type-db-table default ${createdId} --title="Updated CLI"`,
    );
    expect(out).toContain('updated');
  });

  test('deletes a record', () => {
    const out = wp(
      `bs db delete blockstudio/type-db-table default ${createdId}`,
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
    const out = wp('bs rpc call blockstudio/type-functions greet --name=CLI');
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

test.describe('CLI - cache', () => {
  test('reports the legacy lock backlog', () => {
    const out = wp('bs cache status');
    expect(out).toContain('legacy build lock');
  });

  test('drains the legacy lock backlog', () => {
    const out = wp('bs cache cleanup');
    expect(out).toContain('Removed');
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
      'post create --post_type=bs_content_sync --post_status=publish --post_title="Content Sync Parent" --post_name=content-sync-parent --post_content="<p>Parent Body</p>" --porcelain',
    );
    const childId = wp(
      `post create --post_type=bs_content_sync --post_status=publish --post_title="Content Sync Child" --post_name=content-sync-child --post_parent=${parentId} --post_content="<p>Child Body</p>" --porcelain`,
    );
    const parentTermId = wp(
      'term create bs_content_topic "Content Sync Topic" --slug=content-sync-topic --porcelain',
    );
    const childTermId = wp(
      `term create bs_content_topic "Content Sync Child Topic" --slug=content-sync-child-topic --parent=${parentTermId} --porcelain`,
    );

    wp(`post meta update ${parentId} _my_subtitle "Parent Subtitle"`);
    wp(`post meta update ${childId} _my_subtitle "Child Subtitle"`);
    wp(`post meta update ${childId} _related_posts '[${parentId}]'`);
    wp(
      `eval 'wp_set_object_terms(${childId}, array(${childTermId}), "bs_content_topic", false);'`,
    );

    const pullRows = JSON.parse(wp('bs content pull --format=json'));
    expect(pullRows.some((row: any) => row.action === 'written')).toBe(true);

    const pulledFiles = readFilesRecursive(contentPath);
    expect(Object.keys(pulledFiles)).toEqual(
      expect.arrayContaining([
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-parent\.[a-f0-9-]+\.json$/,
        ),
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-child\.[a-f0-9-]+\.json$/,
        ),
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-parent\.[a-f0-9-]+\.html$/,
        ),
        expect.stringMatching(
          /^posts\/bs_content_sync\/content-sync-child\.[a-f0-9-]+\.html$/,
        ),
        expect.stringMatching(
          /^terms\/bs_content_topic\/content-sync-topic\.[a-f0-9-]+\.json$/,
        ),
        expect.stringMatching(
          /^terms\/bs_content_topic\/content-sync-child-topic\.[a-f0-9-]+\.json$/,
        ),
      ]),
    );

    wp(`post delete ${childId} ${parentId} --force`);
    wp(`term delete bs_content_topic ${childTermId} ${parentTermId}`);

    const pushRows = JSON.parse(wp('bs content push --format=json'));
    expect(
      pushRows.filter(
        (row: any) => row.action === 'created' && row.entity === 'post',
      ),
    ).toHaveLength(2);
    expect(
      pushRows.filter(
        (row: any) => row.action === 'created' && row.entity === 'term',
      ),
    ).toHaveLength(2);

    const terms = JSON.parse(
      wp(
        'term list bs_content_topic --hide-empty=0 --fields=term_id,slug,parent --format=json',
      ),
    );
    const parentTerm = terms.find(
      (term: any) => term.slug === 'content-sync-topic',
    );
    const childTerm = terms.find(
      (term: any) => term.slug === 'content-sync-child-topic',
    );

    const posts = JSON.parse(
      wp(
        'post list --post_type=bs_content_sync --post_status=any --fields=ID,post_name,post_parent --format=json',
      ),
    );
    const parent = posts.find(
      (post: any) => post.post_name === 'content-sync-parent',
    );
    const child = posts.find(
      (post: any) => post.post_name === 'content-sync-child',
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
          `eval 'echo wp_json_encode(array_map("intval", wp_get_object_terms(${child.ID}, "bs_content_topic", array("fields"=>"ids"))));'`,
        ),
      ),
    ).toEqual([Number(childTerm.term_id)]);

    const statusRows = JSON.parse(wp('bs content status --format=json'));
    expect(statusRows.every((row: any) => row.action === 'unchanged')).toBe(
      true,
    );
    expect(statusRows).toHaveLength(4);

    const secondPushRows = JSON.parse(wp('bs content push --format=json'));
    expect(secondPushRows.every((row: any) => row.action === 'unchanged')).toBe(
      true,
    );
    expect(secondPushRows).toHaveLength(4);

    wp('bs content pull --format=json');
    expect(readFilesRecursive(contentPath)).toEqual(pulledFiles);
  });
});
