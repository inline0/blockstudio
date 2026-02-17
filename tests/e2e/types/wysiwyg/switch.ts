import { Page, Frame } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('wysiwyg-switch', 'Default text', () => [
  {
    description: 'type and switch',
    testFunction: async (page: Page, canvas: Frame) => {
      await page.click('text=Switch to Code');
      await page.click('.cm-line');
      await page.keyboard.press('ControlOrMeta+A');
      await page.keyboard.press('Backspace');
      await page.keyboard.type('<h1>Switch Test</h1>');
      await count(page, 'text=Switch Test', 1);
      await page.click('text=Switch to Visual');
      await count(page, 'text=Switch Test', 1);
      await count(canvas, 'text=Switch Test', 1);
    },
  },
]);
