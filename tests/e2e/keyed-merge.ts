import { test, expect, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

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

async function updatePostContent(request: any, content: string): Promise<void> {
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

async function waitForEditorBlocks(page: Page): Promise<void> {
  await page.waitForFunction(
    () => {
      const wp = (window as any).wp;
      return (
        typeof wp?.data?.select === 'function' &&
        wp.data.select('core/block-editor')?.getBlocks?.().length > 0
      );
    },
    null,
    { timeout: 30000 },
  );
}

async function saveEditorPost(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const wp = (window as any).wp;
    await wp.data.dispatch('core/editor').savePost();
  });

  await expect
    .poll(
      () =>
        page.evaluate(() => {
          const editor = (window as any).wp.data.select('core/editor');
          return editor.isSavingPost() || editor.isAutosavingPost();
        }),
      { timeout: 30000 },
    )
    .toBe(false);

  await expect
    .poll(
      () =>
        page.evaluate(() => {
          return (window as any).wp.data
            .select('core/editor')
            .isEditedPostDirty();
        }),
      { timeout: 30000 },
    )
    .toBe(false);
}

test.describe('Keyed Block Merging', () => {
  test.describe('Basic Merging', () => {
    test('initial sync creates page with keyed blocks', async ({ request }) => {
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

    test('editor save preserves keyed block metadata', async ({
      page,
      request,
    }) => {
      const template = '<p key="intro">Default intro text.</p>';

      try {
        await forceSync(request, template);

        await login(page, BASE);
        await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
        await waitForEditorBlocks(page);

        const key = await page.evaluate(() => {
          const [block] = (window as any).wp.data
            .select('core/block-editor')
            .getBlocks();
          return block?.attributes?.__BLOCKSTUDIO_KEY;
        });
        expect(key).toBe('intro');

        const editedText = 'Editor saved intro text.';
        await page.evaluate((content) => {
          const wp = (window as any).wp;
          const [block] = wp.data.select('core/block-editor').getBlocks();

          wp.data
            .dispatch('core/block-editor')
            .updateBlockAttributes(block.clientId, { content });
        }, editedText);
        await saveEditorPost(page);

        const afterSave = await getPostContent(request);
        expect(afterSave).toContain('"__BLOCKSTUDIO_KEY":"intro"');
        expect(afterSave).toContain(editedText);

        await triggerSync(
          request,
          `${template}
<p key="after">Second default.</p>`,
        );

        const afterSync = await getPostContent(request);
        expect(afterSync).toContain('"__BLOCKSTUDIO_KEY":"intro"');
        expect(afterSync).toContain('"__BLOCKSTUDIO_KEY":"after"');
        expect(afterSync).toContain(editedText);
        expect(afterSync).not.toContain('Default intro text.');
      } finally {
        await forceSync(request, ORIGINAL_TEMPLATE);
      }
    });

    test('editor-saved Blockstudio fields survive template resync', async ({
      page,
      request,
    }) => {
      const template = `<block name="blockstudio/type-text" key="custom-text" text="Template field v1" />
<p>Developer sentinel v1.</p>`;

      try {
        await forceSync(request, template);

        let content = await getPostContent(request);
        expect(content).toContain('"__BLOCKSTUDIO_KEY":"custom-text"');
        expect(content).toContain('"text":"Template field v1"');
        expect(content).not.toMatch(/"attributes":\{[^}]*"__BLOCKSTUDIO_KEY"/);

        await login(page, BASE);
        await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
        await waitForEditorBlocks(page);

        const editorValue = 'Editor persistence marker';
        await page.evaluate((text) => {
          const wp = (window as any).wp;
          const block = wp.data
            .select('core/block-editor')
            .getBlocks()
            .find(
              ({ name }: { name: string }) => name === 'blockstudio/type-text',
            );

          if (!block) {
            throw new Error(
              'Blockstudio text block was not loaded in the editor',
            );
          }

          const blockstudio = block.attributes.blockstudio ?? {};
          wp.data
            .dispatch('core/block-editor')
            .updateBlockAttributes(block.clientId, {
              blockstudio: {
                ...blockstudio,
                attributes: {
                  ...(blockstudio.attributes ?? {}),
                  text,
                },
              },
            });
        }, editorValue);
        await saveEditorPost(page);

        content = await getPostContent(request);
        expect(content).toContain(editorValue);
        expect(content).toContain('"__BLOCKSTUDIO_KEY":"custom-text"');

        await triggerSync(
          request,
          `<block name="blockstudio/type-text" key="custom-text" text="Template field v2" />
<p>Developer sentinel v2.</p>`,
        );

        content = await getPostContent(request);
        expect(content).toContain(editorValue);
        expect(content).not.toContain('Template field v2');
        expect(content).toContain('Developer sentinel v2.');
        expect(content).not.toContain('Developer sentinel v1.');
        expect(content).toContain('"__BLOCKSTUDIO_KEY":"custom-text"');
        expect(content).not.toMatch(/"attributes":\{[^}]*"__BLOCKSTUDIO_KEY"/);
      } finally {
        await forceSync(request, ORIGINAL_TEMPLATE);
      }
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
    test('template attribute update on keyed block', async ({ request }) => {
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

    test('block type change (same key), template wins', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace('Default intro text.', 'User intro text.');
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

    test('duplicate keys, old key map only stores first occurrence', async ({
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

    test('locked post skips sync entirely', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Default intro text.',
        'User intro before lock.',
      );
      await updatePostContent(request, edited);

      const lockRes = await request.post(`${TEST_API}/pages/lock/${postId}`, {
        data: { lock: true },
      });
      expect(lockRes.ok()).toBeTruthy();

      const newTemplate = ORIGINAL_TEMPLATE.replace(
        '<p key="intro">Default intro text.</p>',
        '<p key="intro">Template changed intro.</p>',
      );
      await triggerSync(request, newTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User intro before lock.');
      expect(after).not.toContain('Template changed intro.');

      await request.post(`${TEST_API}/pages/lock/${postId}`, {
        data: { lock: false },
      });
    });

    test('reordering keyed blocks in template', async ({ request }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content
        .replace('Default Title', 'User Title')
        .replace('Default outro text.', 'User outro.');
      await updatePostContent(request, edited);

      const reorderedTemplate = `<p key="outro">Default outro text.</p>
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
<h1 key="title">Default Title</h1>
<p key="intro">Default intro text.</p>`;

      await triggerSync(request, reorderedTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User Title');
      expect(after).toContain('User outro.');

      const outroPos = after.indexOf('"__BLOCKSTUDIO_KEY":"outro"');
      const titlePos = after.indexOf('"__BLOCKSTUDIO_KEY":"title"');
      expect(outroPos).toBeLessThan(titlePos);
    });

    test('keys only in nested blocks triggers merge', async ({ request }) => {
      const nestedOnlyTemplate = `<div>
  <p key="nested-intro">Nested intro text.</p>
  <p key="nested-outro">Nested outro text.</p>
</div>
<p>Top-level unkeyed.</p>`;

      await forceSync(request, nestedOnlyTemplate);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Nested intro text.',
        'User nested intro.',
      );
      await updatePostContent(request, edited);

      await triggerSync(request, nestedOnlyTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User nested intro.');
      expect(after).not.toContain('Nested intro text.');
    });

    test('simultaneous add, remove, and edit in one sync', async ({
      request,
    }) => {
      await forceSync(request, ORIGINAL_TEMPLATE);

      const content = await getPostContent(request);

      const edited = content
        .replace('Default Title', 'User Title')
        .replace('Default intro text.', 'User intro.')
        .replace('Default outro text.', 'User outro.');
      await updatePostContent(request, edited);

      const combinedTemplate = `<h1 key="title">Default Title</h1>
<p key="intro">Default intro text.</p>
<p>Unkeyed paragraph content.</p>
<div key="features">
  <h2>Features Section</h2>
  <p>Feature description here.</p>
</div>
<block name="core/cover" key="hero" url="https://example.com/new-bg.jpg">
  <h2>Hero Heading</h2>
  <p>Hero description.</p>
</block>
<hr />
<p key="cta">Call to action text.</p>`;

      await triggerSync(request, combinedTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User Title');
      expect(after).toContain('User intro.');
      expect(after).not.toContain('User outro.');
      expect(after).not.toContain('"__BLOCKSTUDIO_KEY":"outro"');
      expect(after).toContain('Call to action text.');
      expect(after).toContain('"__BLOCKSTUDIO_KEY":"cta"');
      expect(after).toContain('new-bg.jpg');
    });

    test('keyed block moves from nested to top-level', async ({ request }) => {
      const nestedTemplate = `<p key="top">Top paragraph.</p>
<div key="wrapper">
  <h2>Wrapper heading</h2>
  <p key="movable">Nested paragraph.</p>
</div>`;

      await forceSync(request, nestedTemplate);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Nested paragraph.',
        'User edited nested.',
      );
      await updatePostContent(request, edited);

      const flatTemplate = `<p key="top">Top paragraph.</p>
<p key="movable">Nested paragraph.</p>
<div key="wrapper">
  <h2>Wrapper heading</h2>
</div>`;

      await triggerSync(request, flatTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User edited nested.');
      expect(after).not.toContain('>Nested paragraph.<');
    });

    test('keyed block moves between different containers', async ({
      request,
    }) => {
      const template1 = `<div key="section-a">
  <h2>Section A</h2>
  <p key="migrating">Content to migrate.</p>
</div>
<div key="section-b">
  <h2>Section B</h2>
  <p>Static paragraph.</p>
</div>`;

      await forceSync(request, template1);

      const content = await getPostContent(request);

      const edited = content.replace(
        'Content to migrate.',
        'User migrating content.',
      );
      await updatePostContent(request, edited);

      const template2 = `<div key="section-a">
  <h2>Section A</h2>
</div>
<div key="section-b">
  <h2>Section B</h2>
  <p key="migrating">Content to migrate.</p>
  <p>Static paragraph.</p>
</div>`;

      await triggerSync(request, template2);

      const after = await getPostContent(request);
      expect(after).toContain('User migrating content.');
      expect(after).not.toContain('>Content to migrate.<');
    });

    test('empty key attribute is ignored', async ({ request }) => {
      const emptyKeyTemplate = `<p key="">Empty key paragraph.</p>
<p key="valid">Valid key paragraph.</p>`;

      await forceSync(request, emptyKeyTemplate);

      const content = await getPostContent(request);

      expect(content).not.toContain('"__BLOCKSTUDIO_KEY":""');
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"valid"');

      const edited = content
        .replace('Empty key paragraph.', 'User empty key edit.')
        .replace('Valid key paragraph.', 'User valid key edit.');
      await updatePostContent(request, edited);

      await triggerSync(request, emptyKeyTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User valid key edit.');
      expect(after).toContain('Empty key paragraph.');
      expect(after).not.toContain('User empty key edit.');
    });

    test('zero key attribute is preserved and mergeable', async ({
      request,
    }) => {
      const zeroKeyTemplate = `<p key="0">Zero key paragraph.</p>
<p key="valid">Valid key paragraph.</p>`;

      await forceSync(request, zeroKeyTemplate);

      const content = await getPostContent(request);

      expect(content).toMatch(/"__BLOCKSTUDIO_KEY":(?:"0"|0)/);
      expect(content).toContain('"__BLOCKSTUDIO_KEY":"valid"');

      const edited = content
        .replace('Zero key paragraph.', 'User zero key edit.')
        .replace('Valid key paragraph.', 'User valid key edit.');
      await updatePostContent(request, edited);

      await triggerSync(request, zeroKeyTemplate);

      const after = await getPostContent(request);
      expect(after).toContain('User zero key edit.');
      expect(after).toContain('User valid key edit.');
      expect(after).not.toContain('Zero key paragraph.');
      expect(after).not.toContain('Valid key paragraph.');
    });
  });
});
