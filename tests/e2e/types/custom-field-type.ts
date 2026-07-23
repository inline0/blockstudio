import { Frame, Page, expect } from '@playwright/test';
import { count, saveAndReload, testType } from '../utils/playwright-utils';

testType('custom-field-type', false, () => {
  const fillDimensions = async (
    scope: ReturnType<Page['locator']>,
    top: string,
    bottom: string,
  ) => {
    await scope.getByLabel('Top').fill(top);
    await scope.getByLabel('Bottom').fill(bottom);
  };

  return [
    {
      description: 'custom object controls save and reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-custom-field-type"]');

        await expect(
          page.locator('.blockstudio-fields__field--test-dimensions'),
        ).toHaveCount(3);

        await fillDimensions(page.locator('[data-id="margin"]'), 'xl', '2xl');
        await fillDimensions(
          page.locator('[data-id="reusable_spacing"]'),
          'lg',
          '3xl',
        );
        await fillDimensions(
          page.locator(
            '[data-rfd-draggable-id="items[0]"] [data-id="spacing"]',
          ),
          'sm',
          '4xl',
        );

        await saveAndReload(page);
      },
    },
    {
      description: 'custom object values persist in editor',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-custom-field-type"]');

        await expect(
          page.locator('[data-id="margin"]').getByLabel('Top'),
        ).toHaveValue('xl');
        await expect(
          page.locator('[data-id="margin"]').getByLabel('Bottom'),
        ).toHaveValue('2xl');
        await expect(
          page.locator('[data-id="reusable_spacing"]').getByLabel('Top'),
        ).toHaveValue('lg');
        await expect(
          page
            .locator('[data-rfd-draggable-id="items[0]"] [data-id="spacing"]')
            .getByLabel('Bottom'),
        ).toHaveValue('4xl');
      },
    },
    {
      description: 'custom object values render on frontend',
      testFunction: async (page: Page) => {
        await page.goto('http://localhost:8888/native-single/');

        await count(page, '.custom-field-type-output[data-margin-top="xl"]', 1);
        await count(
          page,
          '.custom-field-type-output[data-margin-bottom="2xl"]',
          1,
        );
        await count(
          page,
          '.custom-field-type-output[data-reusable-top="lg"]',
          1,
        );
        await count(
          page,
          '.custom-field-type-output[data-repeater-bottom="4xl"]',
          1,
        );
        await page.goto(
          'http://localhost:8888/wp-admin/post.php?post=1483&action=edit',
        );
      },
    },
  ];
});
