import { FrameLocator } from '@playwright/test';
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
        testFunction: async (editor: FrameLocator) => {
          await click(editor, '[data-type="blockstudio/type-populate-function"]');
          await count(
            editor,
            '.blockstudio-fields .components-panel__body >> nth=3 .components-checkbox-control',
            0
          );
        },
      },
      {
        description: 'change values',
        testFunction: async (editor: FrameLocator) => {
          await click(
            editor,
            `.blockstudio-fields .components-panel__body:nth-of-type(1) .components-checkbox-control:nth-of-type(1) .components-checkbox-control__input`
          );
          await click(
            editor,
            `.blockstudio-fields .components-panel__body:nth-of-type(1) .components-checkbox-control:nth-of-type(2) .components-checkbox-control__input`
          );
          await click(
            editor,
            `.blockstudio-fields .components-panel__body:nth-of-type(2) .components-checkbox-control:nth-of-type(1) .components-checkbox-control__input`
          );
          await click(
            editor,
            `.blockstudio-fields .components-panel__body:nth-of-type(2) .components-checkbox-control:nth-of-type(2) .components-checkbox-control__input`
          );
          await click(
            editor,
            `.blockstudio-fields .components-panel__body:nth-of-type(3) .components-checkbox-control:nth-of-type(1) .components-checkbox-control__input`
          );
          await click(
            editor,
            `.blockstudio-fields .components-panel__body:nth-of-type(3) .components-checkbox-control:nth-of-type(2) .components-checkbox-control__input`
          );
          await saveAndReload(editor);
        },
      },
      {
        description: 'check values',
        testFunction: async (editor: FrameLocator) => {
          await click(editor, '[data-type="blockstudio/type-populate-function"]');
          await text(
            editor,
            '"postTypes":[{"value":"post","label":"post"},{"value":"page","label":"page"}],"postTypesArguments":["post","page"],"valueLabel":["option-1","option-2"],"wrongFunction":false'
          );
        },
      },
    ];
  }
);
