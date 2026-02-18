import { expect, Page, Frame } from '@playwright/test';
import {
  delay,
  openSidebar,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';
const defaultChecks = [
  `"defaultNumberBothWrongDefault":false`,
  `"defaultValueLabel":"Three"`,
  `"defaultNumberArray":3`,
  `"defaultNumberArrayBoth":{"value":3,"label":3}`,
  `"defaultValueStringNumber":"2"`,
  `"defaultValueStringNumberBoth":{"value":"3","label":"3"}`,
  `"populateQueryIdBefore":1388`,
  `"populateCustom":"three"`,
  `"populateCustomArrayBeforeBoth":{"value":"three","label":"three"}`,
  `"populateCustomOnly":"custom-1"`,
];

const valuesSelect = [
  { defaultStylised: '', index: 4, valueStylised: 'Three', data: `"defaultNumberBothWrongDefault":{"value":3,"label":"Three"}` },
  { defaultStylised: 'Three', index: 2, valueStylised: 'Two', data: `"defaultValueLabel":"Two"` },
  { defaultStylised: '3', index: 2, valueStylised: '2', data: `"defaultNumberArray":2` },
  { defaultStylised: '3', index: 2, valueStylised: '2', data: `"defaultNumberArrayBoth":{"value":2,"label":2}` },
  { defaultStylised: 'Two', index: 2, valueStylised: 'Two', data: `"defaultValueStringNumber":"2"` },
  { defaultStylised: '3', index: 3, valueStylised: '3', data: `"defaultValueStringNumberBoth":{"value":"3","label":"3"}` },
  { defaultStylised: 'Native Render', index: 1, valueStylised: 'One', data: `"populateQuery":"one"` },
  { defaultStylised: 'native-render', index: 1, valueStylised: 'native-single', data: `"populateQueryIdBefore":1483` },
  { defaultStylised: 'Three', index: 5, valueStylised: 'Custom 2', data: `"populateCustom":"custom-2"` },
  { defaultStylised: 'three', index: 5, valueStylised: 'two', data: `"populateCustomArrayBeforeBoth":{"value":"two","label":"two"}` },
  { defaultStylised: 'custom-1', index: 2, valueStylised: 'custom-2', data: `"populateCustomOnly":"custom-2"` },
  { defaultStylised: 'Native Render', index: 2, valueStylised: 'Native Render', data: `"populateOnlyQuery":{"ID":1388` },
  { defaultStylised: 'Test User 644', index: 2, valueStylised: 'Aaron Kessler', data: `"populateOnlyQueryUser":{"data":{"ID":"704"`, skip: true },
  { defaultStylised: 'Test Category 6', index: 3, valueStylised: 'fabrikat', data: `"populateOnlyQueryTerm":{"term_id":`, skip: true },
];

testType('radio-button-group', false, () => {
  return [
    ...defaultChecks.map((check, i) => ({
      description: `check default ${i}`,
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, check);
      },
    })),
    ...valuesSelect.slice(1).flatMap((item, i) => {
      const index = i + 1;
      return item.skip ? [] : [{
        description: `default input ${index}`,
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type^="blockstudio/type-radio-button-group"]');
          await openSidebar(page);
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--radio .components-button.is-primary`)
            .nth(index - 1);
          await expect(el).toHaveText(item.defaultStylised);
        },
      }];
    }),
    ...valuesSelect.map((item, index) => ({
      description: `change attribute ${index}`,
      testFunction: async (page: Page, _canvas: Frame) => {
        await delay(500);
        await page
          .locator(`.blockstudio-fields .components-panel__body:nth-of-type(${index + 1}) .components-button:nth-of-type(${item.index === 4 ? 3 : item.index})`)
          .click();
      },
    })),
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check data ${index}`,
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, item.data);
        },
      }]
    ),
    {
      description: 'save and reload',
      testFunction: async (page: Page, _canvas: Frame) => {
        await saveAndReload(page);
      },
    },
    {
      description: 'select block after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type^="blockstudio/type-radio-button-group"]');
        await openSidebar(page);
      },
    },
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted data ${index}`,
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, item.data);
        },
      }]
    ),
  ];
});
