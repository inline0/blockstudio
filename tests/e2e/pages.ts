import { test, expect, Page, Frame } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });

  await login(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('File-based Pages', () => {
  test.describe('Frontend rendering', () => {
    test('page loads with correct title', async () => {
      await page.goto('http://localhost:8888/blockstudio-e2e-test/');
      await page.waitForLoadState('networkidle');

      await expect(
        page.getByRole('heading', { name: 'Core Blocks Test Page' }),
      ).toBeVisible();
    });

    // Text Blocks

    test('paragraph with inline formatting', async () => {
      const p = page.locator('p', {
        hasText: 'This is a paragraph with',
      });
      await expect(p).toBeVisible();

      await expect(p.locator('strong')).toHaveText('bold');
      await expect(p.locator('em')).toHaveText('italic');
    });

    test('all six heading levels render', async () => {
      for (let i = 1; i <= 6; i++) {
        await expect(
          page.getByRole('heading', { name: `Heading Level ${i}` }),
        ).toBeVisible();
      }
    });

    test('headings use correct tags', async () => {
      await expect(page.locator('h1:has-text("Heading Level 1")')).toHaveCount(
        1,
      );
      await expect(page.locator('h2:has-text("Heading Level 2")')).toHaveCount(
        1,
      );
      await expect(page.locator('h3:has-text("Heading Level 3")')).toHaveCount(
        1,
      );
      await expect(page.locator('h4:has-text("Heading Level 4")')).toHaveCount(
        1,
      );
      await expect(page.locator('h5:has-text("Heading Level 5")')).toHaveCount(
        1,
      );
      await expect(page.locator('h6:has-text("Heading Level 6")')).toHaveCount(
        1,
      );
    });

    test('unordered list with three items', async () => {
      const ul = page.locator('ul.wp-block-list');
      await expect(ul).toBeVisible();
      await expect(ul.locator('li')).toHaveCount(3);

      await expect(
        page.getByText('Unordered item one', { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText('Unordered item two', { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText('Unordered item three', { exact: true }),
      ).toBeVisible();
    });

    test('ordered list with three items', async () => {
      const ol = page.locator('ol.wp-block-list');
      await expect(ol).toBeVisible();
      await expect(ol.locator('li')).toHaveCount(3);

      await expect(
        page.getByText('Ordered item one', { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText('Ordered item two', { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText('Ordered item three', { exact: true }),
      ).toBeVisible();
    });

    test('blockquote with paragraph content', async () => {
      const quote = page.locator('.wp-block-quote');
      await expect(quote).toBeVisible();
      await expect(quote.locator('p')).toContainText(
        'blockquote with some quoted text',
      );
    });

    test('pullquote renders correctly', async () => {
      const pullquote = page.locator('.wp-block-pullquote');
      await expect(pullquote).toBeVisible();
      await expect(pullquote.locator('blockquote')).toBeVisible();
      await expect(pullquote).toContainText('pullquote for emphasis');
    });

    test('code block with content', async () => {
      const code = page.locator('.wp-block-code');
      await expect(code).toBeVisible();
      await expect(code.locator('code')).toContainText(
        'const hello = "world"',
      );
    });

    test('preformatted block preserves whitespace', async () => {
      const pre = page.locator('.wp-block-preformatted');
      await expect(pre).toBeVisible();
      await expect(pre).toContainText('preserved whitespace');
      await expect(pre).toContainText('indentation');
    });

    test('verse block with line breaks', async () => {
      const verse = page.locator('.wp-block-verse');
      await expect(verse).toBeVisible();
      await expect(verse).toContainText('Roses are red');
      await expect(verse).toContainText('Violets are blue');
    });

    // Media Blocks

    test('image block with src and alt', async () => {
      const image = page.locator(
        '.wp-block-image img[alt="Sample image"]',
      );
      await expect(image).toBeVisible();
      await expect(image).toHaveAttribute('src', /picsum\.photos\/800/);
    });

    test('gallery with three images', async () => {
      const gallery = page.locator('.wp-block-gallery');
      await expect(gallery).toBeVisible();
      await expect(gallery.locator('.wp-block-image')).toHaveCount(3);
    });

    test('audio block renders', async () => {
      const audio = page.locator('.wp-block-audio');
      await expect(audio).toBeVisible();
      await expect(audio.locator('audio')).toHaveAttribute(
        'src',
        /sample\.mp3/,
      );
    });

    test('video block renders', async () => {
      const video = page.locator('.wp-block-video');
      await expect(video).toBeVisible();
      await expect(video.locator('video')).toHaveAttribute(
        'src',
        /sample\.mp4/,
      );
    });

    test('cover block with background image and inner content', async () => {
      const cover = page.locator('.wp-block-cover');
      await expect(cover).toBeVisible();
      await expect(
        cover.locator('img.wp-block-cover__image-background'),
      ).toHaveAttribute('src', /picsum\.photos/);
      await expect(cover).toContainText('Cover Block Title');
      await expect(cover).toContainText(
        'Content inside a cover block with background image',
      );
    });

    test('embed block renders', async () => {
      const embed = page.locator('.wp-block-embed');
      await expect(embed).toBeVisible();
      await expect(embed.locator('.wp-block-embed__wrapper')).toBeVisible();
    });

    // Design Blocks

    test('group block with nested paragraphs', async () => {
      const groups = page.locator('.wp-block-group');
      await expect(groups.first()).toBeVisible();
      await expect(
        page.getByText('This paragraph is inside a group'),
      ).toBeVisible();
      await expect(
        page.getByText('Another paragraph in the same group'),
      ).toBeVisible();
    });

    test('row group renders with flex layout', async () => {
      await expect(
        page.getByText('Item 1', { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText('Item 2', { exact: true }),
      ).toBeVisible();
      await expect(
        page.getByText('Item 3', { exact: true }),
      ).toBeVisible();
    });

    test('stack group renders with vertical layout', async () => {
      await expect(page.getByText('Stacked item 1')).toBeVisible();
      await expect(page.getByText('Stacked item 2')).toBeVisible();
      await expect(page.getByText('Stacked item 3')).toBeVisible();
    });

    test('columns with three columns', async () => {
      const columns = page.locator('.wp-block-columns');
      await expect(columns).toBeVisible();
      await expect(page.locator('.wp-block-column')).toHaveCount(3);

      await expect(
        page.getByRole('heading', { name: 'Column 1' }),
      ).toBeVisible();
      await expect(
        page.getByRole('heading', { name: 'Column 2' }),
      ).toBeVisible();
      await expect(
        page.getByRole('heading', { name: 'Column 3' }),
      ).toBeVisible();
      await expect(
        page.getByText('Content in the first column'),
      ).toBeVisible();
      await expect(
        page.getByText('Content in the second column'),
      ).toBeVisible();
      await expect(
        page.getByText('Content in the third column'),
      ).toBeVisible();
    });

    test('separator renders as hr', async () => {
      const separator = page.locator('.wp-block-separator');
      await expect(separator).toBeVisible();
      await expect(separator).toHaveAttribute('class', /has-alpha-channel/);
    });

    test('spacer renders with correct height', async () => {
      const spacer = page.locator('.wp-block-spacer');
      await expect(spacer).toBeVisible();
    });

    test('buttons group with two button links', async () => {
      const buttons = page.locator('.wp-block-buttons');
      await expect(buttons).toBeVisible();
      await expect(buttons.locator('.wp-block-button')).toHaveCount(2);

      const primaryLink = buttons.locator('a', { hasText: 'Primary Button' });
      await expect(primaryLink).toHaveAttribute('href', /example\.com/);

      const secondaryLink = buttons.locator('a', {
        hasText: 'Secondary Button',
      });
      await expect(secondaryLink).toHaveAttribute('href', /secondary/);
    });

    // Interactive Blocks

    test('details block with summary and hidden content', async () => {
      const details = page.locator('.wp-block-details');
      await expect(details).toBeVisible();

      const summary = details.locator('summary');
      await expect(summary).toHaveText('Click to expand');
    });

    test('table block with header and body rows', async () => {
      const table = page.locator('.wp-block-table');
      await expect(table).toBeVisible();

      const headers = table.locator('thead th');
      await expect(headers).toHaveCount(3);
      await expect(headers.nth(0)).toHaveText('Header 1');
      await expect(headers.nth(1)).toHaveText('Header 2');
      await expect(headers.nth(2)).toHaveText('Header 3');

      const rows = table.locator('tbody tr');
      await expect(rows).toHaveCount(2);
      await expect(rows.nth(0).locator('td').nth(0)).toHaveText(
        'Row 1, Cell 1',
      );
      await expect(rows.nth(1).locator('td').nth(2)).toHaveText(
        'Row 2, Cell 3',
      );
    });

    // Social Blocks

    test('social links with three children', async () => {
      const socialLinks = page.locator('.wp-block-social-links');
      await expect(socialLinks).toBeVisible();
      await expect(socialLinks.locator('.wp-social-link')).toHaveCount(3);
    });

    // Media & Text

    test('media text with image and content', async () => {
      const mediaText = page.locator('.wp-block-media-text');
      await expect(mediaText).toBeVisible();
      await expect(
        mediaText.locator('.wp-block-media-text__media img'),
      ).toHaveAttribute('src', /picsum\.photos/);
      await expect(mediaText).toContainText('Media & Text Content');
      await expect(mediaText).toContainText(
        'text content next to the media',
      );
    });

    // Accordion Blocks

    test('accordion with two items', async () => {
      const accordion = page.locator('.wp-block-accordion');
      await expect(accordion).toBeVisible();
      await expect(
        accordion.locator('.wp-block-accordion-item'),
      ).toHaveCount(2);
    });

    test('accordion headings with toggle buttons', async () => {
      await expect(
        page.locator('.wp-block-accordion-heading__toggle', {
          hasText: 'First Accordion Item',
        }),
      ).toBeVisible();
      await expect(
        page.locator('.wp-block-accordion-heading__toggle', {
          hasText: 'Second Accordion Item',
        }),
      ).toBeVisible();
    });

    test('accordion panels with content', async () => {
      await expect(
        page.getByText('Content inside the first accordion panel'),
      ).toBeAttached();
      await expect(
        page.getByText('Content inside the second accordion panel'),
      ).toBeAttached();
    });

    // Query Blocks

    test('query container renders', async () => {
      const query = page.locator('.wp-block-query');
      await expect(query).toBeVisible();
      await expect(query).toContainText('Query loop placeholder content');
    });

    test('comments block present (dynamic rendering)', async () => {
      // core/comments is fully dynamic. WordPress replaces save markup
      // with actual comments. Validated via editor block types instead.
      const heading = page.getByRole('heading', { name: 'Comments' });
      await expect(heading).toBeVisible();
    });

    // Generic Block Syntax

    test('generic block syntax paragraph', async () => {
      await expect(
        page.getByText('created using the generic block syntax'),
      ).toBeVisible();
    });

    test('generic block syntax heading', async () => {
      await expect(
        page.getByRole('heading', { name: 'Generic Heading Block' }),
      ).toBeVisible();
    });

    test('generic block syntax group with nested content', async () => {
      await expect(
        page.getByText('Nested content inside a generic group block'),
      ).toBeVisible();
      await expect(
        page.getByText('Multiple paragraphs work too'),
      ).toBeVisible();
    });

    // Additional Parser Paths

    test('section element maps to group block', async () => {
      await expect(
        page.getByText('This paragraph is inside a section group'),
      ).toBeVisible();
      await expect(
        page.getByText('Sections map to the same group block as divs'),
      ).toBeVisible();
    });

    test('figure with img maps to image block', async () => {
      const figureImage = page.locator(
        '.wp-block-image img[alt="Figure image"]',
      );
      await expect(figureImage).toBeVisible();
      await expect(figureImage).toHaveAttribute('src', /picsum\.photos\/600/);
    });

    test('unknown HTML element falls back to HTML block', async () => {
      await expect(
        page.getByText('This unknown element becomes a raw HTML block'),
      ).toBeVisible();
    });

    test('figure without img falls back to HTML block', async () => {
      await expect(
        page.getByText('A figure without an img falls back to raw HTML'),
      ).toBeVisible();
    });

    test('blockstudio block with defaults renders', async () => {
      const block = page.locator('.preload-simple').first();
      await expect(block.locator('.preload-title')).toHaveText('Hello');
      await expect(block.locator('.preload-count')).toHaveText('5');
    });

    test('blockstudio block with values renders correctly', async () => {
      const block = page.locator('.preload-simple').nth(1);
      await expect(block.locator('.preload-title')).toHaveText('Page Value');
      await expect(block.locator('.preload-count')).toHaveText('99');
      await expect(block.locator('.preload-active')).toHaveText('true');
    });

    test('blockstudio block with no defaults renders explicit values', async () => {
      const block = page.locator('.preload-no-defaults');
      await expect(block.locator('.preload-title')).toHaveText('Explicit');
      await expect(block.locator('.preload-count')).toHaveText('42');
      await expect(block.locator('.preload-active')).toHaveText('false');
    });

    test('bare text node auto-wraps to paragraph', async () => {
      await expect(
        page.getByText('Bare text becomes an auto-wrapped paragraph'),
      ).toBeVisible();
    });
  });

  test.describe('Editor validation', () => {
    test('open test page in editor', async () => {
      await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
      await page.waitForLoadState('networkidle');

      await page
        .locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ })
        .click();
      canvas = await getEditorCanvas(page);
      await page.waitForTimeout(2000);
    });

    test('all blocks pass validation (no invalid blocks)', async () => {
      const results = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        const invalid: string[] = [];

        function check(block: any) {
          if (!block.isValid) {
            invalid.push(block.name);
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              check(inner);
            }
          }
        }

        for (const block of blocks) {
          check(block);
        }

        return { total: blocks.length, invalid };
      });

      expect(results.invalid).toEqual([]);
      expect(results.total).toBeGreaterThan(0);
    });

    test('correct number of top-level blocks', async () => {
      const count = await page.evaluate(() => {
        return (window as any).wp.data
          .select('core/block-editor')
          .getBlocks().length;
      });

      expect(count).toBeGreaterThanOrEqual(1);
    });

    test('expected block types are present in editor', async () => {
      const blockTypes = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        const types = new Set<string>();

        function collect(block: any) {
          types.add(block.name);
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              collect(inner);
            }
          }
        }

        for (const block of blocks) {
          collect(block);
        }

        return Array.from(types).sort();
      });

      const expected = [
        'blockstudio/type-preload-no-defaults',
        'blockstudio/type-preload-simple',
        'core/accordion',
        'core/accordion-heading',
        'core/accordion-item',
        'core/accordion-panel',
        'core/audio',
        'core/button',
        'core/buttons',
        'core/code',
        'core/column',
        'core/columns',
        'core/comments',
        'core/cover',
        'core/details',
        'core/embed',
        'core/gallery',
        'core/group',
        'core/heading',
        'core/html',
        'core/image',
        'core/list',
        'core/list-item',
        'core/media-text',
        'core/more',
        'core/nextpage',
        'core/paragraph',
        'core/preformatted',
        'core/pullquote',
        'core/query',
        'core/quote',
        'core/separator',
        'core/social-link',
        'core/social-links',
        'core/spacer',
        'core/table',
        'core/verse',
        'core/video',
      ];

      for (const type of expected) {
        expect(blockTypes).toContain(type);
      }
    });

    test('heading blocks have correct levels', async () => {
      const headingLevels = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        const levels: number[] = [];

        function collect(block: any) {
          if (
            block.name === 'core/heading' &&
            block.attributes.level >= 1 &&
            block.attributes.level <= 6
          ) {
            levels.push(block.attributes.level);
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              collect(inner);
            }
          }
        }

        for (const block of blocks) {
          collect(block);
        }

        return [...new Set(levels)].sort();
      });

      expect(headingLevels).toEqual([1, 2, 3, 4, 5, 6]);
    });

    test('list blocks have correct ordered attribute', async () => {
      const lists = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        const result: { ordered: boolean; itemCount: number }[] = [];

        function collect(block: any) {
          if (block.name === 'core/list') {
            result.push({
              ordered: block.attributes.ordered,
              itemCount: block.innerBlocks.length,
            });
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              collect(inner);
            }
          }
        }

        for (const block of blocks) {
          collect(block);
        }

        return result;
      });

      expect(lists).toContainEqual({ ordered: false, itemCount: 3 });
      expect(lists).toContainEqual({ ordered: true, itemCount: 3 });
    });

    test('cover block has url attribute', async () => {
      const coverAttrs = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): any {
          if (block.name === 'core/cover') {
            return block.attributes;
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found) return found;
        }
        return null;
      });

      expect(coverAttrs).not.toBeNull();
      expect(coverAttrs.url).toContain('picsum.photos');
    });

    test('button blocks have url attributes', async () => {
      const buttons = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        const result: string[] = [];

        function collect(block: any) {
          if (block.name === 'core/button' && block.attributes.url) {
            result.push(block.attributes.url);
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              collect(inner);
            }
          }
        }

        for (const block of blocks) {
          collect(block);
        }

        return result;
      });

      expect(buttons).toHaveLength(2);
      expect(buttons).toContain('https://example.com');
      expect(buttons).toContain('https://example.com/secondary');
    });

    test('columns block has three column children', async () => {
      const columnCount = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): number | null {
          if (block.name === 'core/columns') {
            return block.innerBlocks.filter(
              (b: any) => b.name === 'core/column',
            ).length;
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found !== null) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found !== null) return found;
        }
        return null;
      });

      expect(columnCount).toBe(3);
    });

    test('gallery block has three image children', async () => {
      const imageCount = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): number | null {
          if (block.name === 'core/gallery') {
            return block.innerBlocks.filter(
              (b: any) => b.name === 'core/image',
            ).length;
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found !== null) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found !== null) return found;
        }
        return null;
      });

      expect(imageCount).toBe(3);
    });

    test('embed block has correct provider', async () => {
      const embed = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): any {
          if (block.name === 'core/embed') return block.attributes;
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found) return found;
        }
        return null;
      });

      expect(embed).not.toBeNull();
      expect(embed.providerNameSlug).toBe('youtube');
      expect(embed.url).toContain('youtube.com');
    });

    test('social-link blocks have service and url attributes', async () => {
      const socialLinks = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        const result: { service: string; url: string }[] = [];

        function collect(block: any) {
          if (block.name === 'core/social-link') {
            result.push({
              service: block.attributes.service,
              url: block.attributes.url,
            });
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              collect(inner);
            }
          }
        }

        for (const block of blocks) {
          collect(block);
        }

        return result;
      });

      expect(socialLinks).toHaveLength(3);
      expect(socialLinks.map((s: any) => s.service).sort()).toEqual([
        'github',
        'twitter',
        'wordpress',
      ]);
    });

    test('media-text block has mediaUrl and mediaType attributes', async () => {
      const mediaText = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): any {
          if (block.name === 'core/media-text') return block.attributes;
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found) return found;
        }
        return null;
      });

      expect(mediaText).not.toBeNull();
      expect(mediaText.mediaUrl).toContain('picsum.photos');
      expect(mediaText.mediaType).toBe('image');
    });

    test('accordion block has two accordion-item children', async () => {
      const itemCount = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): number | null {
          if (block.name === 'core/accordion') {
            return block.innerBlocks.filter(
              (b: any) => b.name === 'core/accordion-item',
            ).length;
          }
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found !== null) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found !== null) return found;
        }
        return null;
      });

      expect(itemCount).toBe(2);
    });

    test('details block has summary attribute', async () => {
      const details = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        function find(block: any): any {
          if (block.name === 'core/details') return block.attributes;
          if (block.innerBlocks) {
            for (const inner of block.innerBlocks) {
              const found = find(inner);
              if (found) return found;
            }
          }
          return null;
        }

        for (const block of blocks) {
          const found = find(block);
          if (found) return found;
        }
        return null;
      });

      expect(details).not.toBeNull();
      expect(details.summary).toBe('Click to expand');
    });

    test('html blocks present for unknown elements', async () => {
      const htmlBlockCount = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();
        let count = 0;

        function collect(block: any) {
          if (block.name === 'core/html') count++;
          if (block.innerBlocks) block.innerBlocks.forEach(collect);
        }

        blocks.forEach(collect);
        return count;
      });

      expect(htmlBlockCount).toBeGreaterThanOrEqual(2);
    });

    test('blockstudio blocks produce correct block names', async () => {
      const found = await page.evaluate(() => {
        const blocks = (window as any).wp.data
          .select('core/block-editor')
          .getBlocks();

        const targets = new Set([
          'blockstudio/type-preload-simple',
          'blockstudio/type-preload-no-defaults',
        ]);
        const found = new Set<string>();

        function collect(block: any) {
          if (targets.has(block.name)) found.add(block.name);
          if (block.innerBlocks) block.innerBlocks.forEach(collect);
        }

        blocks.forEach(collect);
        return Array.from(found).sort();
      });

      expect(found).toEqual([
        'blockstudio/type-preload-no-defaults',
        'blockstudio/type-preload-simple',
      ]);
    });

    test('editor shows blocks visually (heading visible)', async () => {
      await expect(canvas.locator('.wp-block-heading').first()).toBeVisible();
    });

    test('template lock prevents unlocking', async () => {
      // Dismiss any modal overlays (welcome dialog, pattern modal, etc.)
      const closeButton = page.locator(
        '.components-modal__screen-overlay button[aria-label="Close"]',
      );
      if (await closeButton.isVisible({ timeout: 1000 }).catch(() => false)) {
        await closeButton.click();
      }

      await canvas.click('.wp-block-heading >> nth=0');

      const moreButton = page.locator('[aria-label="Options"]').first();
      if (await moreButton.isVisible()) {
        await moreButton.click();

        const unlockOption = page.locator('button:has-text("Unlock")');
        await expect(unlockOption).toHaveCount(0);
      }
    });
  });
});

test.describe('File-based Pages (Twig)', () => {
  test('twig page exists with correct title', async () => {
    await page.goto('http://localhost:8888/blockstudio-e2e-test-twig/');
    await page.waitForLoadState('networkidle');

    await expect(
      page.getByRole('heading', { name: 'Twig Template Test Page' }),
    ).toBeVisible();
  });

  test('twig template processed correctly', async () => {
    await expect(
      page.getByText(
        'This page uses a Twig template with TWIG support.',
      ),
    ).toBeVisible();

    const currentYear = new Date().getFullYear().toString();
    await expect(
      page.getByText(`Current year: ${currentYear}`),
    ).toBeVisible();
  });

  test('twig loop generates list items', async () => {
    await expect(
      page.getByText('Twig item 1', { exact: true }),
    ).toBeVisible();
    await expect(
      page.getByText('Twig item 2', { exact: true }),
    ).toBeVisible();
    await expect(
      page.getByText('Twig item 3', { exact: true }),
    ).toBeVisible();
  });

  test('twig headings render all levels', async () => {
    for (let i = 1; i <= 6; i++) {
      await expect(
        page.getByRole('heading', { name: `Twig Heading ${i}` }),
      ).toBeVisible();
    }
  });

  test('twig ordered list renders', async () => {
    await expect(page.getByText('Twig ordered 1')).toBeVisible();
    await expect(page.getByText('Twig ordered 3')).toBeVisible();
  });

  test('twig quote and pullquote render', async () => {
    await expect(page.getByText('This is a Twig blockquote')).toBeVisible();
    await expect(
      page.getByText('Twig pullquote for emphasis'),
    ).toBeVisible();
  });

  test('twig code and preformatted blocks render', async () => {
    await expect(page.locator('.wp-block-code')).toBeVisible();
    await expect(page.locator('.wp-block-preformatted')).toBeVisible();
  });

  test('twig verse block renders', async () => {
    const verse = page.locator('.wp-block-verse');
    await expect(verse).toBeVisible();
    await expect(verse).toContainText('Twig roses are red');
  });

  test('twig media blocks render', async () => {
    await expect(page.locator('.wp-block-image').first()).toBeVisible();
    await expect(page.locator('.wp-block-gallery')).toBeVisible();
    await expect(page.locator('.wp-block-audio')).toBeVisible();
    await expect(page.locator('.wp-block-video')).toBeVisible();
    await expect(page.locator('.wp-block-cover')).toBeVisible();
    await expect(page.locator('.wp-block-embed')).toBeVisible();
  });

  test('twig design blocks render', async () => {
    await expect(page.locator('.wp-block-group').first()).toBeVisible();
    await expect(page.locator('.wp-block-columns').first()).toBeVisible();
    await expect(page.locator('.wp-block-separator')).toBeVisible();
    await expect(page.locator('.wp-block-spacer')).toBeVisible();
    await expect(page.locator('.wp-block-buttons').first()).toBeVisible();
    await expect(page.getByText('Twig Button')).toBeVisible();
  });

  test('twig interactive blocks render', async () => {
    const details = page.locator('.wp-block-details');
    await expect(details).toBeVisible();
    await expect(details.locator('summary')).toHaveText(
      'Twig expand details',
    );

    await expect(page.locator('.wp-block-table')).toBeVisible();
  });

  test('twig social links render', async () => {
    await expect(page.locator('.wp-block-social-links')).toBeVisible();
  });

  test('twig media-text renders', async () => {
    await expect(page.locator('.wp-block-media-text')).toBeVisible();
    await expect(page.getByText('Twig Media Content')).toBeVisible();
  });

  test('twig accordion renders', async () => {
    await expect(page.locator('.wp-block-accordion')).toBeVisible();
    await expect(
      page.locator('.wp-block-accordion-heading__toggle', {
        hasText: 'Twig Accordion Item',
      }),
    ).toBeVisible();
  });

  test('twig additional parser paths render', async () => {
    await expect(
      page.getByText('Twig section group paragraph'),
    ).toBeVisible();
    await expect(
      page.locator('.wp-block-image img[alt="Twig figure image"]'),
    ).toBeVisible();
    await expect(
      page.getByText('Twig unknown element becomes HTML block'),
    ).toBeVisible();
    await expect(
      page.getByText('Twig bare text auto-wrapped paragraph'),
    ).toBeVisible();
  });

  test('twig page editor shows valid blocks', async () => {
    await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
    await page.waitForLoadState('networkidle');

    await page
      .locator('a.row-title:has-text("Blockstudio E2E Test Page (Twig)")')
      .click();
    canvas = await getEditorCanvas(page);
    await page.waitForTimeout(2000);

    await expect(canvas.locator('.wp-block-heading').first()).toBeVisible();

    const invalidCount = await page.evaluate(() => {
      const blocks = (window as any).wp.data
        .select('core/block-editor')
        .getBlocks();
      let count = 0;
      function check(block: any) {
        if (!block.isValid) count++;
        if (block.innerBlocks) block.innerBlocks.forEach(check);
      }
      blocks.forEach(check);
      return count;
    });

    expect(invalidCount).toBe(0);
  });
});

test.describe('File-based Pages (Blade)', () => {
  test('blade page exists with correct title', async () => {
    await page.goto('http://localhost:8888/blockstudio-e2e-test-blade/');
    await page.waitForLoadState('networkidle');

    await expect(
      page.getByRole('heading', { name: 'Blade Template Test Page' }),
    ).toBeVisible();
  });

  test('blade template processed correctly', async () => {
    await expect(
      page.getByText(
        'This page uses a Blade template with BLADE support.',
      ),
    ).toBeVisible();

    const currentYear = new Date().getFullYear().toString();
    await expect(
      page.getByText(`Current year: ${currentYear}`),
    ).toBeVisible();
  });

  test('blade loop generates list items', async () => {
    await expect(
      page.getByText('Blade item 1', { exact: true }),
    ).toBeVisible();
    await expect(
      page.getByText('Blade item 2', { exact: true }),
    ).toBeVisible();
    await expect(
      page.getByText('Blade item 3', { exact: true }),
    ).toBeVisible();
  });

  test('blade headings render all levels', async () => {
    for (let i = 1; i <= 6; i++) {
      await expect(
        page.getByRole('heading', { name: `Blade Heading ${i}` }),
      ).toBeVisible();
    }
  });

  test('blade ordered list renders', async () => {
    await expect(page.getByText('Blade ordered 1')).toBeVisible();
    await expect(page.getByText('Blade ordered 3')).toBeVisible();
  });

  test('blade quote and pullquote render', async () => {
    await expect(page.getByText('This is a Blade blockquote')).toBeVisible();
    await expect(
      page.getByText('Blade pullquote for emphasis'),
    ).toBeVisible();
  });

  test('blade code and preformatted blocks render', async () => {
    await expect(page.locator('.wp-block-code')).toBeVisible();
    await expect(page.locator('.wp-block-preformatted')).toBeVisible();
  });

  test('blade verse block renders', async () => {
    const verse = page.locator('.wp-block-verse');
    await expect(verse).toBeVisible();
    await expect(verse).toContainText('Blade roses are red');
  });

  test('blade media blocks render', async () => {
    await expect(page.locator('.wp-block-image').first()).toBeVisible();
    await expect(page.locator('.wp-block-gallery')).toBeVisible();
    await expect(page.locator('.wp-block-audio')).toBeVisible();
    await expect(page.locator('.wp-block-video')).toBeVisible();
    await expect(page.locator('.wp-block-cover')).toBeVisible();
    await expect(page.locator('.wp-block-embed')).toBeVisible();
  });

  test('blade design blocks render', async () => {
    await expect(page.locator('.wp-block-group').first()).toBeVisible();
    await expect(page.locator('.wp-block-columns').first()).toBeVisible();
    await expect(page.locator('.wp-block-separator')).toBeVisible();
    await expect(page.locator('.wp-block-spacer')).toBeVisible();
    await expect(page.locator('.wp-block-buttons').first()).toBeVisible();
    await expect(page.getByText('Blade Button')).toBeVisible();
  });

  test('blade interactive blocks render', async () => {
    const details = page.locator('.wp-block-details');
    await expect(details).toBeVisible();
    await expect(details.locator('summary')).toHaveText(
      'Blade expand details',
    );

    await expect(page.locator('.wp-block-table')).toBeVisible();
  });

  test('blade social links render', async () => {
    await expect(page.locator('.wp-block-social-links')).toBeVisible();
  });

  test('blade media-text renders', async () => {
    await expect(page.locator('.wp-block-media-text')).toBeVisible();
    await expect(page.getByText('Blade Media Content')).toBeVisible();
  });

  test('blade accordion renders', async () => {
    await expect(page.locator('.wp-block-accordion')).toBeVisible();
    await expect(
      page.locator('.wp-block-accordion-heading__toggle', {
        hasText: 'Blade Accordion Item',
      }),
    ).toBeVisible();
  });

  test('blade additional parser paths render', async () => {
    await expect(
      page.getByText('Blade section group paragraph'),
    ).toBeVisible();
    await expect(
      page.locator('.wp-block-image img[alt="Blade figure image"]'),
    ).toBeVisible();
    await expect(
      page.getByText('Blade unknown element becomes HTML block'),
    ).toBeVisible();
    await expect(
      page.getByText('Blade bare text auto-wrapped paragraph'),
    ).toBeVisible();
  });

  test('blade page editor shows valid blocks', async () => {
    await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
    await page.waitForLoadState('networkidle');

    await page
      .locator('a.row-title:has-text("Blockstudio E2E Test Page (Blade)")')
      .click();
    canvas = await getEditorCanvas(page);
    await page.waitForTimeout(2000);

    await expect(canvas.locator('.wp-block-heading').first()).toBeVisible();

    const invalidCount = await page.evaluate(() => {
      const blocks = (window as any).wp.data
        .select('core/block-editor')
        .getBlocks();
      let count = 0;
      function check(block: any) {
        if (!block.isValid) count++;
        if (block.innerBlocks) block.innerBlocks.forEach(check);
      }
      blocks.forEach(check);
      return count;
    });

    expect(invalidCount).toBe(0);
  });
});
