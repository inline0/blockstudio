import { Page, Frame } from '@playwright/test';
import { count, testType } from '../utils/playwright-utils';

testType('message', '"text":false', () => {
  return [
    {
      description: 'display message',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-message"]');
        await page.fill('.blockstudio-fields__field--text input', 'test');
        await count(
          page,
          'text=Block name is blockstudio/type-message! Text value is: test',
          1
        );
      },
    },
  ];
});
