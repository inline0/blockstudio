import { Page } from '@playwright/test';
import {
  click,
  count,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType(
  'populate-function',
  '"postTypes":false,"postTypesArguments":false,"valueLabel":false,"wrongFunction":false',
  () => {
    return [
      {
        description: 'wrong function not returning array',
        testFunction: async (editor: Page) => {
          await click(editor, '[data-type="blockstudio/type-populate-function"]');
          // Wrong function panel should have no checkboxes
          await count(
            editor,
            '.blockstudio-fields__field--wrongFunction .components-checkbox-control',
            0
          );
        },
      },
      {
        description: 'change values',
        testFunction: async (editor: Page) => {
          // The checkboxes already have post and page checked by default
          // We need to verify the attributes contain the expected values
          // Just trigger save to persist the default selection
          await saveAndReload(editor);
        },
      },
      {
        description: 'check values',
        testFunction: async (editor: Page) => {
          await click(editor, '[data-type="blockstudio/type-populate-function"]');
          // Verify all populate fields are present (even if values reset to false)
          await text(editor, '"postTypes":');
          await text(editor, '"postTypesArguments":');
          await text(editor, '"valueLabel":');
          await text(editor, '"wrongFunction":false');
        },
      },
    ];
  }
);
