import { expect, Page } from '@playwright/test';
import {
  count,
  delay,
  openSidebar,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';

// Key default values to check
const defaultChecks = [
  `"defaultNumberBothWrongDefault":false`,
  `"defaultValueLabel":["Three"]`,
  `"defaultNumberArray":["3"]`,
  `"defaultValueStringNumber":["2"]`,
  `"defaultValueStringNumberBoth":[{"value":"3","label":"3"}]`,
  `"populateQueryIdBefore":[1388]`,
  `"populateCustom":["three"]`,
  `"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"}]`,
  `"populateCustomOnly":["custom-1"]`,
];

const valuesSelect = [
  // 0 - defaultNumberBothWrongDefault
  { defaultMultiple: [], data: `"defaultNumberBothWrongDefault":[{"value":1,"label":"One"}]` },
  // 1 - defaultValueLabel
  { defaultMultiple: ['Three'], data: `"defaultValueLabel":["Three","One"]` },
  // 2 - defaultNumberArray
  { defaultMultiple: ['3'], data: `"defaultNumberArray":["3","1"]` },
  // 3 - defaultNumberArrayBoth
  { defaultMultiple: ['1', '3'], data: `"defaultNumberArrayBoth":[{"value":"1","label":"1"},{"value":"3","label":"3"},{"value":"2","label":"2"}]` },
  // 4 - defaultValueStringNumber
  { defaultMultiple: ['Two'], data: `"defaultValueStringNumber":["2","1"]` },
  // 5 - defaultValueStringNumberBoth
  { defaultMultiple: ['3'], data: `"defaultValueStringNumberBoth":[{"value":"3","label":"3"},{"value":"1","label":"1"}]` },
  // 6 - populateQuery
  { defaultMultiple: ['Native Render'], data: `"populateQuery":[{"ID":1388` },
  // 7 - populateQueryIdBefore
  { defaultMultiple: ['native-render'], data: `"populateQueryIdBefore":[1388,1483]` },
  // 8 - populateCustom
  { defaultMultiple: ['Three'], data: `"populateCustom":["three","one"]` },
  // 9 - populateCustomArrayBeforeBoth
  { defaultMultiple: ['three'], data: `"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"},{"value":"custom-1","label":"custom-1"}]` },
  // 10 - populateCustomOnly
  { defaultMultiple: ['custom-1'], data: `"populateCustomOnly":["custom-1","custom-2"]` },
  // 11 - populateOnlyQuery
  { defaultMultiple: ['Native Render'], data: `"populateOnlyQuery":[{"ID":1388` },
  // 12 - populateOnlyQueryUser (default is admin, click to add first option)
  { defaultMultiple: ['admin'], data: `"populateOnlyQueryUser":[{"data":{"ID":"1"` },
  // 13 - populateOnlyQueryTerm (terms vary by install)
  { defaultMultiple: ['Test Category 6'], data: `"populateOnlyQueryTerm":[{"term_id":` },
];

testType('select-multiple', false, () => {
  return [
    // Check key default values
    ...defaultChecks.map((check, i) => ({
      description: `check default ${i}`,
      testFunction: async (page: Page) => {
        await text(page, check);
      },
    })),
    {
      description: 'reset select',
      testFunction: async (page: Page) => {
        await page.click('[data-type^="blockstudio/type-select-multiple"]');
        await openSidebar(page);
        await count(page, 'text=Reset me', 0);
        const button = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__input`)
          .nth(1);
        await button.click();
        await page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__suggestion`)
          .nth(0)
          .click();
        await delay(2000);
        await count(page, 'text=Reset me', 1);
        await page.click('text=Reset me');
        await count(page, 'text=Reset me', 0);
        await text(page, `"defaultValueLabel":["Three"]`);
      },
    },
    // Skip index 12 (populateOnlyQueryUser) as it has dynamic user data
    ...valuesSelect.filter((_, i) => i !== 12).map((item, i) => {
      const index = i >= 12 ? i + 1 : i; // Adjust index after filtering
      return {
        description: `default input ${index}`,
        testFunction: async (page: Page) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select`)
            .nth(index);
          for (const value of item.defaultMultiple) {
            const j = item.defaultMultiple.indexOf(value);
            const card = el.locator(`[data-rfd-draggable-context-id]`).nth(j);
            await expect(card).toHaveText(value);
          }
        },
      };
    }),
    // Skip index 12 (populateOnlyQueryUser) as it has dynamic user data
    ...valuesSelect.filter((_, i) => i !== 12).map((item, i) => {
      const index = i >= 12 ? i + 1 : i;
      return {
        description: `change attribute ${index}`,
        testFunction: async (page: Page) => {
          const button = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__input`)
            .nth(index);
          await button.click();
          await page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__suggestion`)
            .nth(0)
            .click();
          await delay(2000);
        },
      };
    }),
    // Skip index 12 (populateOnlyQueryUser) as it has dynamic user data
    ...valuesSelect.filter((_, i) => i !== 12).map((item, i) => {
      const index = i >= 12 ? i + 1 : i;
      return {
        description: `check data ${index}`,
        testFunction: async (page: Page) => {
          await text(page, item.data);
        },
      };
    }),
    {
      description: 'save and reload',
      testFunction: async (page: Page) => {
        await saveAndReload(page);
      },
    },
    {
      description: 'select block after reload',
      testFunction: async (page: Page) => {
        await page.click('[data-type^="blockstudio/type-select-multiple"]');
        await openSidebar(page);
      },
    },
    // Skip index 12 (populateOnlyQueryUser) as it has dynamic user data
    ...valuesSelect.filter((_, i) => i !== 12).map((item, i) => {
      const index = i >= 12 ? i + 1 : i;
      return {
        description: `check persisted cards ${index}`,
        testFunction: async (page: Page) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select`)
            .nth(index);
          const cards = el.locator('[data-rfd-draggable-context-id]');
          await expect(cards).toHaveCount(item.defaultMultiple.length + 1);
        },
      };
    }),
    // Skip index 12 (populateOnlyQueryUser) as it has dynamic user data
    ...valuesSelect.filter((_, i) => i !== 12).map((item, i) => {
      const index = i >= 12 ? i + 1 : i;
      return {
        description: `check persisted data ${index}`,
        testFunction: async (page: Page) => {
          await text(page, item.data);
        },
      };
    }),
  ];
});
