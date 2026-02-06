import { expect, Page, Frame } from '@playwright/test';
import { testType } from '../utils/playwright-utils';

testType(
  ['switch', 'switch'],
  false,
  () => [
    {
      description: 'verify action buttons exist for switch fields',
      testFunction: async (page: Page, _canvas: Frame) => {
        const actions = page.locator(
          '[data-id="title"] .blockstudio-fields__action, [data-id="content"] .blockstudio-fields__action',
        );
        await expect(actions).toHaveCount(2);
      },
    },
    {
      description: 'verify no action button for default fields',
      testFunction: async (page: Page, _canvas: Frame) => {
        const noSwitchActions = page.locator(
          '[data-id="no_switch"] .blockstudio-fields__action',
        );
        await expect(noSwitchActions).toHaveCount(0);
      },
    },
    {
      description: 'disable field and verify overlay',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click('[data-id="title"] .blockstudio-fields__action');
        await expect(
          page.locator('[data-id="title"] .blockstudio-fields__field-disabled'),
        ).toBeVisible();
        await expect(page.locator('[data-id="title"]')).toHaveAttribute(
          'aria-disabled',
          'true',
        );
      },
    },
    {
      description: 're-enable field and verify overlay gone',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click('[data-id="title"] .blockstudio-fields__action', {
          force: true,
        });
        await expect(
          page.locator('[data-id="title"] .blockstudio-fields__field-disabled'),
        ).toHaveCount(0);
        await expect(page.locator('[data-id="title"]')).toHaveAttribute(
          'aria-disabled',
          'false',
        );
      },
    },
  ],
);
