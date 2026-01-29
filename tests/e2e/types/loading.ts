import { Page } from '@playwright/test';
import { click, count, testType } from '../utils/playwright-utils';

testType('loading', false, () => {
  return [
    {
      description: 'check loading state',
      testFunction: async (editor: Page) => {
        // Outer shows "blockstudio/type-loading" with "Click to load"
        // Inner auto-loads and shows "ID: blockstudio-type-loading"
        // Wait for either state to appear (block may still be loading)
        const loadingState = editor.locator('text=blockstudio/type-loading');
        const loadedState = editor.locator('text=ID: blockstudio-type-loading');

        // Wait up to 15s for either state
        await Promise.race([
          loadingState.waitFor({ state: 'visible', timeout: 15000 }).catch(() => null),
          loadedState.waitFor({ state: 'visible', timeout: 15000 }).catch(() => null),
        ]);

        const hasLoadingState = await loadingState.count();
        const hasLoadedState = await loadedState.count();
        if (hasLoadingState === 0 && hasLoadedState === 0) {
          throw new Error('Block not found in loading or loaded state');
        }
      },
    },
    {
      description: 'click element',
      testFunction: async (editor: Page) => {
        // Wait for either state then click
        const loadingState = editor.locator('text=blockstudio/type-loading');
        const loadedState = editor.locator('text=ID: blockstudio-type-loading');

        await Promise.race([
          loadingState.waitFor({ state: 'visible', timeout: 15000 }).catch(() => null),
          loadedState.waitFor({ state: 'visible', timeout: 15000 }).catch(() => null),
        ]);

        if (await loadingState.count() > 0) {
          await loadingState.click();
        } else {
          await loadedState.click();
        }
        await count(editor, '.blockstudio-test__block', 1);
      },
    },
  ];
});
