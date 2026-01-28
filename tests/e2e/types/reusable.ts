import { FrameLocator } from '@playwright/test';
import {
  click,
  count,
  openBlockInserter,
  removeBlocks,
  testType,
} from '../utils/playwright-utils';

testType(
  'text',
  false,
  () => {
    return [
      {
        description: 'load multiple reusables',
        testFunction: async (editor: FrameLocator) => {
          await removeBlocks(editor);
          await openBlockInserter(editor);
          await click(editor, '[role="tab"]:has-text("Patterns")');
          await click(editor, 'button:has-text("My patterns")');
          await click(editor, '#core\\/block\\/2644');
          await click(editor, '#core\\/block\\/2643');
          await count(editor, '[data-type="blockstudio/type-text"]', 2);
          await count(editor, '[data-type="blockstudio/type-textarea"]', 2);
        },
      },
    ];
  },
  false
);
