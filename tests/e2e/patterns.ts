import { expect, test, Page } from '@playwright/test';
import { count, pBlocks, delay } from './utils/playwright-utils';

test.describe('Patterns', () => {
  test('pattern appears in block inserter and inserts correct blocks', async ({
    browser,
  }) => {
    const page = await pBlocks(browser);

    // Open the block inserter
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    // Switch to the Patterns tab
    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    // Search for the test pattern
    await page.fill('[placeholder="Search"]', 'Blockstudio E2E Test Pattern');
    await delay(1000);

    // Wait for search results and check that the pattern appears
    const patternItem = page.locator(
      '.block-editor-block-patterns-list__item:has-text("Blockstudio E2E Test Pattern")'
    );
    await expect(patternItem.first()).toBeVisible({ timeout: 10000 });

    // Click on the first pattern (PHP template) to insert it
    await patternItem.first().click();
    await delay(500);

    // Close the inserter if still open
    await page.keyboard.press('Escape');

    // Verify the pattern content is inserted
    await count(page, '.wp-block-group', 1);
    await count(page, '.wp-block-heading', 1);
    await count(page, '.wp-block-paragraph', 1);
    await count(page, '.wp-block-buttons', 1);
    await count(page, '.wp-block-button', 1);

    // Verify the text content
    const heading = page.locator('.wp-block-heading');
    await expect(heading).toHaveText('Test Pattern Heading');

    const paragraph = page.locator('.wp-block-paragraph');
    await expect(paragraph).toHaveText('This is a test pattern paragraph.');

    const button = page.locator('.wp-block-button__link');
    await expect(button).toHaveText('Test Button');
  });

  test('pattern can be found by keywords', async ({ browser }) => {
    const page = await pBlocks(browser);

    // Open the block inserter
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    // Switch to the Patterns tab
    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    // Search for the pattern using a keyword
    await page.fill('[placeholder="Search"]', 'blockstudio test');
    await delay(1000);

    // Wait for search results and check that the pattern appears
    const patternItem = page.locator(
      '.block-editor-block-patterns-list__item:has-text("Blockstudio E2E Test Pattern")'
    );
    await expect(patternItem.first()).toBeVisible({ timeout: 10000 });
  });
});

test.describe('Patterns (Twig)', () => {
  const searchAndFindTwigPattern = async (page: Page) => {
    // Open the block inserter
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    // Switch to the Patterns tab
    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    // Search for the twig test pattern using keyword
    await page.fill('[placeholder="Search"]', 'twig');
    await delay(1500);

    // Wait for search results and check that the twig pattern appears
    return page.locator(
      '.block-editor-block-patterns-list__item:has-text("Twig")'
    );
  };

  test('twig pattern appears in block inserter and inserts correct blocks', async ({
    browser,
  }) => {
    const page = await pBlocks(browser);

    const patternItem = await searchAndFindTwigPattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });

    // Click on the pattern to insert it
    await patternItem.click();
    await delay(500);

    // Close the inserter if still open
    await page.keyboard.press('Escape');

    // Verify the pattern content is inserted
    await count(page, '.wp-block-group', 1);
    await count(page, '.wp-block-heading', 1);
    await count(page, '.wp-block-buttons', 1);
    await count(page, '.wp-block-button', 1);

    // Verify the heading text
    const heading = page.locator('.wp-block-heading');
    await expect(heading).toHaveText('Twig Pattern Heading');

    // Verify the Twig |upper filter was applied (TWIG in uppercase)
    const paragraph = page.locator('.wp-block-paragraph');
    await expect(paragraph).toContainText('TWIG');

    const button = page.locator('.wp-block-button__link');
    await expect(button).toHaveText('Twig Pattern Button');
  });

  test('twig pattern can be found by twig keyword', async ({ browser }) => {
    const page = await pBlocks(browser);

    const patternItem = await searchAndFindTwigPattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });
  });
});

test.describe('Patterns (Blade)', () => {
  const searchAndFindBladePattern = async (page: Page) => {
    // Open the block inserter
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    // Switch to the Patterns tab
    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    // Search for the blade test pattern using keyword
    await page.fill('[placeholder="Search"]', 'blade');
    await delay(1500);

    // Wait for search results and check that the blade pattern appears
    return page.locator(
      '.block-editor-block-patterns-list__item:has-text("Blade")'
    );
  };

  test('blade pattern appears in block inserter and inserts correct blocks', async ({
    browser,
  }) => {
    const page = await pBlocks(browser);

    const patternItem = await searchAndFindBladePattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });

    // Click on the pattern to insert it
    await patternItem.click();
    await delay(500);

    // Close the inserter if still open
    await page.keyboard.press('Escape');

    // Verify the pattern content is inserted
    await count(page, '.wp-block-group', 1);
    await count(page, '.wp-block-heading', 1);
    await count(page, '.wp-block-buttons', 1);
    await count(page, '.wp-block-button', 1);

    // Verify the heading text
    const heading = page.locator('.wp-block-heading');
    await expect(heading).toHaveText('Blade Pattern Heading');

    // Verify the strtoupper("Blade") was applied (BLADE in uppercase)
    const paragraph = page.locator('.wp-block-paragraph');
    await expect(paragraph).toContainText('BLADE');

    const button = page.locator('.wp-block-button__link');
    await expect(button).toHaveText('Blade Pattern Button');
  });

  test('blade pattern can be found by blade keyword', async ({ browser }) => {
    const page = await pBlocks(browser);

    const patternItem = await searchAndFindBladePattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });
  });
});
