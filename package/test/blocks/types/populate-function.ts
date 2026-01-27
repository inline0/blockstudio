import { Page } from '@playwright/test';
import {
  count,
  saveAndReload,
  testType,
  text,
} from '../../../playwright-utils';

testType(
  'populate-function',
  '"postTypes":false,"postTypesArguments":false,"valueLabel":false,"wrongFunction":false',
  () => {
    return [
      {
        description: 'wrong function not returning array',
        testFunction: async (page: Page) => {
          await page.click('[data-type="blockstudio/type-populate-function"]');
          await count(
            page,
            '.blockstudio-fields .components-panel__body >> nth=3 .components-checkbox-control',
            0
          );
        },
      },
      {
        description: 'change values',
        testFunction: async (page: Page) => {
          await page.click(
            `.blockstudio-fields .components-panel__body:nth-of-type(1) .components-checkbox-control:nth-of-type(1) .components-checkbox-control__input`
          );
          await page.click(
            `.blockstudio-fields .components-panel__body:nth-of-type(1) .components-checkbox-control:nth-of-type(2) .components-checkbox-control__input`
          );
          await page.click(
            `.blockstudio-fields .components-panel__body:nth-of-type(2) .components-checkbox-control:nth-of-type(1) .components-checkbox-control__input`
          );
          await page.click(
            `.blockstudio-fields .components-panel__body:nth-of-type(2) .components-checkbox-control:nth-of-type(2) .components-checkbox-control__input`
          );
          await page.click(
            `.blockstudio-fields .components-panel__body:nth-of-type(3) .components-checkbox-control:nth-of-type(1) .components-checkbox-control__input`
          );
          await page.click(
            `.blockstudio-fields .components-panel__body:nth-of-type(3) .components-checkbox-control:nth-of-type(2) .components-checkbox-control__input`
          );
          await saveAndReload(page);
        },
      },
      {
        description: 'check values',
        testFunction: async (page: Page) => {
          await page.click('[data-type="blockstudio/type-populate-function"]');
          await text(
            page,
            '"postTypes":[{"value":"post","label":"post"},{"value":"page","label":"page"}],"postTypesArguments":["post","page"],"valueLabel":["option-1","option-2"],"wrongFunction":false'
          );
        },
      },
    ];
  }
);
