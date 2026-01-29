import { Page } from '@playwright/test';
import { click, count, fill, testType } from '../utils/playwright-utils';

testType('message', '"text":false', () => {
  return [
    {
      description: 'display message',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-message"]');
        await fill(editor, '.blockstudio-fields__field--text input', 'test');
        await count(
          editor,
          'text=Block name is blockstudio/type-message! Text value is: test',
          1
        );
      },
    },
  ];
});
