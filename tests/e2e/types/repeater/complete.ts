import { Page, Frame } from '@playwright/test';
import { testType, text } from '../../utils/playwright-utils';

testType('repeater-complete', false, () => {
  return [
    {
      description: 'check repeater values',
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, '"checkbox":[{"value":"three","label":"Three"}]');
        await text(canvas, '"color":{"name":"red","value":"#f00","slug":"red"}');
        await text(canvas, '"text":"Default value"');
        await text(canvas, '"toggle":true');
        await text(canvas, '"number":10');
        await text(canvas, '"range":10');
        await text(canvas, '"radio":{"value":"three","label":"Three"}');
        await text(canvas, '"select":{"value":"three","label":"Three"}');
        await text(canvas, '"gradient":{"name":"JShine"');
      },
    },
  ];
});
