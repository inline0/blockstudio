import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;

const IB_SPACELESS_OPEN =
  '<!-- wp:blockstudio/component-innerblocks-bare-spaceless -->';
const IB_SPACELESS_CLOSE =
  '<!-- /wp:blockstudio/component-innerblocks-bare-spaceless -->';
const IB_BARE_OPEN = '<!-- wp:blockstudio/component-innerblocks-bare -->';
const IB_BARE_CLOSE = '<!-- /wp:blockstudio/component-innerblocks-bare -->';

const BLOCK_TYPES = ['text', 'number', 'textarea', 'range', 'toggle'] as const;

function generateBlockContent(): { content: string; totalBlocks: number } {
  const lines: string[] = [];
  let n = 0;

  function leaf(): string {
    n++;
    const type = BLOCK_TYPES[n % BLOCK_TYPES.length];
    switch (type) {
      case 'text':
        return `<!-- wp:blockstudio/type-text {"blockstudio":{"attributes":{"text":"Block ${n}"}}} /-->`;
      case 'number':
        return `<!-- wp:blockstudio/type-number {"blockstudio":{"attributes":{"number":${n},"numberZero":0}}} /-->`;
      case 'textarea':
        return `<!-- wp:blockstudio/type-textarea {"blockstudio":{"attributes":{"textarea":"Content ${n}"}}} /-->`;
      case 'range':
        return `<!-- wp:blockstudio/type-range {"blockstudio":{"attributes":{"range":${n},"rangeZero":0}}} /-->`;
      case 'toggle':
        return `<!-- wp:blockstudio/type-toggle {"blockstudio":{"attributes":{"toggle":${n % 3 === 0}}}} /-->`;
    }
  }

  // 5 levels deep, branching factor 2, 3 leaf blocks per node
  function nest(depth: number, maxDepth: number): void {
    const spaceless = depth % 2 === 0;
    lines.push(spaceless ? IB_SPACELESS_OPEN : IB_BARE_OPEN);

    for (let i = 0; i < 3; i++) {
      lines.push(leaf());
    }

    if (depth < maxDepth) {
      nest(depth + 1, maxDepth);
      nest(depth + 1, maxDepth);
    }

    lines.push(spaceless ? IB_SPACELESS_CLOSE : IB_BARE_CLOSE);
  }

  nest(0, 4);

  return { content: lines.join('\n'), totalBlocks: n };
}

const { content: BLOCK_CONTENT, totalBlocks: TOTAL_BLOCKS } =
  generateBlockContent();

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser, request }) => {
  const res = await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: BLOCK_CONTENT },
  });
  expect(res.ok()).toBeTruthy();

  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async ({ request }) => {
  await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: '' },
  });
  await page.close();
});

test.describe('Block Preloading', () => {
  test(`preloaded data contains all ${TOTAL_BLOCKS} blocks`, async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      () => {
        const bs = (window as any).blockstudio;
        if (!bs?.blockstudioBlocks) return false;
        const blocks = bs.blockstudioBlocks;
        return Array.isArray(blocks) ? blocks.length > 0 : Object.keys(blocks).length > 0;
      },
      { timeout: 30000 },
    );

    const data = await page.evaluate(() => {
      const raw = (window as any).blockstudio.blockstudioBlocks;
      const entries = (Array.isArray(raw) ? raw : Object.values(raw)) as any[];
      return {
        count: entries.length,
        blockNames: [
          ...new Set(entries.map((e: any) => e.blockName)),
        ],
        allHaveRendered: entries.every(
          (e: any) => typeof e.rendered === 'string' && e.rendered.length > 0,
        ),
      };
    });

    expect(data.allHaveRendered).toBe(true);
    expect(data.blockNames).toContain('blockstudio/type-text');
    expect(data.blockNames).toContain('blockstudio/type-number');
    expect(data.blockNames).toContain('blockstudio/type-textarea');
    expect(data.blockNames).toContain('blockstudio/type-range');
    expect(data.blockNames).toContain('blockstudio/type-toggle');
    expect(data.blockNames).toContain(
      'blockstudio/component-innerblocks-bare-spaceless',
    );
    expect(data.blockNames).toContain(
      'blockstudio/component-innerblocks-bare',
    );
    // Array format stores every block instance (no deduplication)
    expect(data.count).toBeGreaterThanOrEqual(TOTAL_BLOCKS);
  });

  test('no render API calls with preloaded blocks', async () => {
    const renderCalls: string[] = [];

    page.on('request', (req) => {
      const url = req.url();
      if (url.includes('/blockstudio/v1/gutenberg/block/render/')) {
        renderCalls.push(url);
      }
    });

    await page.reload();
    await getEditorCanvas(page);

    // Wait for any deferred batch render calls (500ms debounce + margin)
    await page.waitForTimeout(5000);

    expect(renderCalls).toEqual([]);
  });
});
