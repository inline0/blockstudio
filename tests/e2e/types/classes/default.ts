import { FrameLocator } from '@playwright/test';
import { click, count, testType } from '../../utils/playwright-utils';

testType('classes', '"classes":"class-1 class-2"', () => {
  return [
    {
      description: 'add class',
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-classes"]');
        // Verify the existing default classes are present as buttons
        await count(editor, 'button:has-text("class-1")', 1);
        await count(editor, 'button:has-text("class-2")', 1);
      },
    },
  ];
});
