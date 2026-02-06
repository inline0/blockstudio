import { Page, Frame } from '@playwright/test';
import { count, saveAndReload, testType } from '../../utils/playwright-utils';

testType('gradient-populate', false, () => {
  return [
    {
      description: 'check gradient populate',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-gradient-populate"]');
        await count(page, '.components-circular-option-picker__option', 3);
      },
    },
    {
      description: 'change gradient',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click(
          '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]'
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'check gradient',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-gradient-populate"]');
        await count(
          page,
          '.blockstudio-fields__field--gradient [aria-label="Gradient: Rastafarie"]',
          1
        );
      },
    },
  ];
});
