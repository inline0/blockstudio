import { Page } from '@playwright/test';
import { testType, text } from '../../utils/playwright-utils';

testType('repeater-complete', false, () => {
  return [
    {
      description: 'check repeater values',
      testFunction: async (page: Page) => {
        await text(page, '"checkbox":[{"value":"three","label":"Three"}]');
        await text(page, '"color":{"name":"red","value":"#f00","slug":"red"}');
        await text(page, '"text":"Default value"');
        await text(page, '"toggle":true');
        await text(page, '"number":10');
        await text(page, '"range":10');
        await text(page, '"radio":{"value":"three","label":"Three"}');
        await text(page, '"select":{"value":"three","label":"Three"}');
        await text(page, '"gradient":{"name":"JShine"');
      },
    },
  ];
});
