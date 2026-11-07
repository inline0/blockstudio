import { Page, expect, Frame } from '@playwright/test';
import { saveAndReload, testType } from '../../utils/playwright-utils';

const metaValue = 'a\\b and q"x\\y"z';
const bothValue = 'a\\\\b and trailing\\';

async function expectStoredMeta(page: Page) {
  const meta = await page.evaluate(
    () => (window as any).wp.data.select('core/editor').getCurrentPost().meta,
  );
  expect(meta.storage_test_meta).toBe(metaValue);
  expect(meta.storage_test_both).toBe(bothValue);
}

testType('storage-meta', false, () => {
  return [
    {
      description: 'fill and save meta fields',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-storage-meta"]');
        await page.fill('[data-id="meta_text"] input', metaValue);
        await page.fill('[data-id="block_and_meta"] input', bothValue);
        await saveAndReload(page);
        await expectStoredMeta(page);
      },
    },
    {
      description: 'check values persist after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-storage-meta"]');
        await expect(page.locator('[data-id="meta_text"] input')).toHaveValue(
          metaValue,
        );
        await expect(
          page.locator('[data-id="block_and_meta"] input'),
        ).toHaveValue(bothValue);
      },
    },
    {
      description:
        'unchanged field values preserve backslashes when saved again',
      testFunction: async (page: Page) => {
        const excerpt = await page.evaluate(() => {
          const { select, dispatch } = (window as any).wp.data;
          const current =
            select('core/editor').getEditedPostAttribute('excerpt');
          dispatch('core/editor').editPost({
            excerpt: `${current} Storage backslash resave regression`,
          });
          return current;
        });
        try {
          await saveAndReload(page);
          await expectStoredMeta(page);
        } finally {
          await page.evaluate((original) => {
            (window as any).wp.data.dispatch('core/editor').editPost({
              excerpt: original,
            });
          }, excerpt);
          await saveAndReload(page);
        }
      },
    },
  ];
});
