import { Page } from '@playwright/test';
import {
  count,
  openBlockInserter,
  removeBlocks,
  testType,
} from '../../../playwright-utils';

testType(
  'text',
  false,
  () => {
    return [
      {
        description: 'load multiple reusables',
        testFunction: async (page: Page) => {
          await removeBlocks(page);
          await openBlockInserter(page);
          await page.click('[role="tab"]:has-text("Patterns")');
          await page.click('button:has-text("My patterns")');
          await page.click('#core\\/block\\/2644');
          await page.click('#core\\/block\\/2643');
          await count(page, '[data-type="blockstudio/type-text"]', 2);
          await count(page, '[data-type="blockstudio/type-textarea"]', 2);
        },
      },
    ];
  },
  false
);
