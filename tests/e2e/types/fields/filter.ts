import { Page, Frame } from '@playwright/test';
import { saveAndReload, testType, text } from '../../utils/playwright-utils';

testType(['fields filter', 'fields-filter'], '"text":"Click Me"', () => {
  return [
    {
      description: 'check PHP filter registered field defaults',
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, '"text":"Click Me"');
      },
    },
    {
      description: 'change filter field value',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-fields-filter"]');
        const input = page.locator('.blockstudio-fields__field--text input').first();
        await input.fill('Sign Up');
        await saveAndReload(page);
      },
    },
    {
      description: 'verify filter field persists',
      testFunction: async (_page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-fields-filter"]');
        await text(canvas, '"text":"Sign Up"');
      },
    },
  ];
});
