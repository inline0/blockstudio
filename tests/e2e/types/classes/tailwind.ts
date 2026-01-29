import { Page } from '@playwright/test';
import { click, count, fill, testType } from '../../utils/playwright-utils';

testType('classes-tailwind', '"classes":"text-red-500"', () => {
  return [
    {
      description: 'add class',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-classes-tailwind"]');
        await fill(editor, '.components-form-token-field input', 'bg-');
        await count(editor, 'text=bg-amber-100', 1);
      },
    },
  ];
});
