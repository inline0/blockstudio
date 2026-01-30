import { Page } from '@playwright/test';
import { count, testType, text } from '../utils/playwright-utils';

testType('message', '"text":false', () => {
  return [
    {
      description: 'display message',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-message"]');
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
