import { expect, Page, Frame } from '@playwright/test';
import { count, testType, text, saveAndReload, getEditorCanvas } from '../../utils/playwright-utils';

testType('classes', '"classes":"class-1 class-2"', () => {
  return [
    {
      description: 'add class from suggestions',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-classes"]');
        await page.fill('.components-form-token-field input', 'is-');
        await count(page, 'text=is-dark-theme', 1);
      },
    },
    {
      description: 'add custom class and retain on blur',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-classes"]');
        const input = page.locator('.components-form-token-field input');
        await input.fill('my-custom-class');
        await input.press('Enter');
        await expect(page.getByRole('button', { name: 'my-custom-class' })).toBeVisible();
      },
    },
    {
      description: 'custom class persists after save and reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await saveAndReload(page);
        canvas = await getEditorCanvas(page);
        await text(canvas, 'my-custom-class');
      },
    },
  ];
});
