import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const PAGE_NAME = 'blockstudio-keyed-merge-test';

const ORIGINAL_TEMPLATE = `<h1 key="title">Default Title</h1>
<p key="intro">Default intro text.</p>
<p>Unkeyed paragraph content.</p>
<div key="features">
  <h2>Features Section</h2>
  <p>Feature description here.</p>
</div>
<block name="core/cover" key="hero" url="https://example.com/bg.jpg">
  <h2>Hero Heading</h2>
  <p>Hero description.</p>
</block>
<hr />
<p key="outro">Default outro text.</p>`;

test.describe.configure({ mode: 'serial' });

let postId: number;

test.beforeAll(async ({ request }) => {
  const res = await request.post(`${TEST_API}/pages/force-sync`, {
    data: { page_name: PAGE_NAME, template_content: ORIGINAL_TEMPLATE },
  });
  expect(res.ok()).toBeTruthy();
  const body = await res.json();
  postId = body.post_id;
  expect(postId).toBeGreaterThan(0);
});

test.afterAll(async ({ request }) => {
  await request.post(`${TEST_API}/pages/force-sync`, {
    data: { page_name: PAGE_NAME, template_content: ORIGINAL_TEMPLATE },
  });
});

async function getPostContent(request: any): Promise<string> {
  const res = await request.get(`${TEST_API}/pages/content/${postId}`);
  expect(res.ok()).toBeTruthy();
  const body = await res.json();
  return body.post_content;
}

async function updatePostContent(
  request: any,
  content: string,
): Promise<void> {
  const res = await request.post(`${TEST_API}/pages/content/${postId}`, {
    data: { content },
  });
  expect(res.ok()).toBeTruthy();
}

async function triggerSync(
  request: any,
  templateContent?: string,
): Promise<{ post_id: number; post_content: string }> {
  const data: Record<string, string> = { page_name: PAGE_NAME };
  if (templateContent) {
    data.template_content = templateContent;
  }
  const res = await request.post(`${TEST_API}/pages/trigger-sync`, {
    data,
  });
  expect(res.ok()).toBeTruthy();
  return res.json();
}

async function forceSync(
  request: any,
  templateContent?: string,
): Promise<{ post_id: number; post_content: string }> {
  const data: Record<string, string> = { page_name: PAGE_NAME };
  if (templateContent) {
    data.template_content = templateContent;
  }
  const res = await request.post(`${TEST_API}/pages/force-sync`, {
    data,
  });
  expect(res.ok()).toBeTruthy();
  return res.json();
}

test.describe('Keyed Block Merging', () => {
  test.describe('Basic Merging', () => {
    test('initial sync creates page with keyed blocks', async ({
      request,
    }) => {
      const content = await getPostContent(request);

      expect(content).toContain('__BLOCKSTUDIO_KEY');
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"title"');
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"intro"');
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"features"');
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"hero"');
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"outro"');
      expect(content).toContain('Default Title');
      expect(content).toContain('Default intro text.');
    });

    test('keyed leaf block preserves user text', async ({ request }) => {
      const content = await getPostContent(request);

      const edited = content.replace(
        'Default intro text.',
        'User edited intro text.',
      );
      await updatePostContent(request, edited);

      await triggerSync(request);

      const after = await getPostContent(request);
      expect(after).toContain('User edited intro text.');
      expect(after).not.toContain('Default intro text.');
    });

    test('multiple keyed blocks preserve independently', async ({
      request,
    }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content
        .replace('Default Title', 'User Title')
        .replace('Default outro text.', 'User outro text.');
      await updatePostContent(request, edited);

      await triggerSync(request);

      const after = await getPostContent(request);
      expect(after).toContain('User Title');
      expect(after).toContain('User outro text.');
      expect(after).not.toContain('Default Title');
      expect(after).not.toContain('Default outro text.');
    });

    test('unkeyed block is replaced', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Unkeyed paragraph content.',
        'User edited unkeyed content.',
      );
      await updatePostContent(request, edited);

      await triggerSync(request);

      const after = await getPostContent(request);
      expect(after).toContain('Unkeyed paragraph content.');
      expect(after).not.toContain('User edited unkeyed content.');
    });
  });

  test.describe('Container Blocks', () => {
    test('keyed group container preserves inner content', async ({
      request,
    }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Feature description here.',
        'User customized feature.',
      );
      await updatePostContent(request, edited);

      await triggerSync(request);

      const after = await getPostContent(request);
      expect(after).toContain('User customized feature.');
      expect(after).not.toContain('Feature description here.');
    });

    test('keyed block-syntax container preserves inner content', async ({
      request,
    }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace('Hero Heading', 'User Hero Heading');
      await updatePostContent(request, edited);

      await triggerSync(request);

      const after = await getPostContent(request);
      expect(after).toContain('User Hero Heading');
      expect(after).not.toContain('>Hero Heading<');
    });
  });

  test.describe('Template Changes', () => {
    test('template attribute update on keyed block', async ({
      request,
    }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Hero description.',
        'User hero description.',
      );
      await updatePostContent(request, edited);

      const newTemplate = ORIGINAL_TEMPLATE.replace(
        'url="https://example.com/bg.jpg"',
        'url="https://example.com/new-bg.jpg"',
      );
      await triggerSync(request, newTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('new-bg.jpg');
      expect(after).toContain('User hero description.');
    });

    test('new keyed block added to template', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const newTemplate =
        ORIGINAL_TEMPLATE + '\n<p key="new-block">Brand new block.</p>';
      await triggerSync(request, newTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('Brand new block.');
      expect(after).toContain('"__BLOCKSTUDIO_KEY":"new-block"');
    });

    test('keyed block removed from template', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const newTemplate = ORIGINAL_TEMPLATE.replace(
        '\n<p key="outro">Default outro text.</p>',
        '',
      );
      await triggerSync(request, newTemplate);

      const after = await getPostContent(request);
      expect(after).not.toContain('"__BLOCKSTUDIO_KEY":"outro"');
      expect(after).not.toContain('Default outro text.');
    });

    test('block type change (same key) — template wins', async ({
      request,
    }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Default intro text.',
        'User intro text.',
      );
      await updatePostContent(request, edited);

      const newTemplate = ORIGINAL_TEMPLATE.replace(
        '<p key="intro">Default intro text.</p>',
        '<h2 key="intro">Default intro heading.</h2>',
      );
      await triggerSync(request, newTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('Default intro heading.');
      expect(after).not.toContain('User intro text.');
      expect(after).toContain('wp:heading');
    });
  });

  test.describe('Cross-Level & Edge Cases', () => {
    test('cross-nesting key matching', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Default outro text.',
        'User outro preserved.',
      );
      await updatePostContent(request, edited);

      const newTemplate = ORIGINAL_TEMPLATE.replace(
        '<p key="outro">Default outro text.</p>',
        '<div>\n  <p key="outro">Default outro text.</p>\n</div>',
      );
      await triggerSync(request, newTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User outro preserved.');
    });

    test('no keys = full replacement', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content
        .replace('Default Title', 'User Title')
        .replace('Default intro text.', 'User intro.');
      await updatePostContent(request, edited);

      const keylessTemplate = `<h1>Keyless Title</h1>
<p>Keyless paragraph.</p>`;
      await triggerSync(request, keylessTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('Keyless Title');
      expect(after).toContain('Keyless paragraph.');
      expect(after).not.toContain('User Title');
      expect(after).not.toContain('User intro.');
    });

    test('force sync ignores keys', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content
        .replace('Default Title', 'User Title')
        .replace('Default intro text.', 'User intro.');
      await updatePostContent(request, edited);

      await forceSync(request);

      const after = await getPostContent(request);
      expect(after).toContain('Default Title');
      expect(after).toContain('Default intro text.');
      expect(after).not.toContain('User Title');
      expect(after).not.toContain('User intro.');
    });

    test('duplicate keys — old key map only stores first occurrence', async ({
      request,
    }) => {
      const dupTemplate = `<p key="intro">First intro.</p>
<p key="intro">Second intro.</p>
<p key="outro">Outro text.</p>`;

      await forceSync(request, dupTemplate);

      const content = await getPostContent(request);

      const edited = content
        .replace('First intro.', 'User first intro.')
        .replace('Second intro.', 'User second intro.');
      await updatePostContent(request, edited);

      await triggerSync(request, dupTemplate);

      const after = await getPostContent(request);
      // Old key map stores only the first "intro" block.
      // Both new template blocks with key="intro" match that same old block,
      // so both end up with the first block's user content.
      expect(after).toContain('User first intro.');
      expect(after).not.toContain('User second intro.');
    });
  });
});
