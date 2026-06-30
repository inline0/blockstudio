import { Frame, Page, expect } from '@playwright/test';
import { count, saveAndReload, testType } from '../utils/playwright-utils';

testType('custom-field-type-missing', false, () => [
  {
    description: 'missing custom editor control preserves saved data',
    testFunction: async (page: Page, canvas: Frame) => {
      await canvas.click(
        '[data-type="blockstudio/type-custom-field-type-missing"]',
      );

      await expect(
        page.locator('.blockstudio-fields__field--test-no-control'),
      ).toHaveCount(1);
      await expect(
        page.locator('.blockstudio-fields__custom-missing'),
      ).toContainText('test/no-control');

      await saveAndReload(page);
    },
  },
  {
    description: 'missing custom editor control value renders on frontend',
    testFunction: async (page: Page) => {
      await page.goto('http://localhost:8888/native-single/');

      await count(
        page,
        '.custom-field-type-missing-output[data-settings-top="stored"]',
        1,
      );
      await count(
        page,
        '.custom-field-type-missing-output[data-settings-bottom="value"]',
        1,
      );
      await page.goto(
        'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
      );
    },
  },
]);
