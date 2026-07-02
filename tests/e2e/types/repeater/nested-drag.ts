import { expect, Page, Frame } from '@playwright/test';
import { testType } from '../../utils/playwright-utils';

// @hello-pangea/dnd needs a real pointer gesture: press, cross the drag
// threshold, move onto the target, settle, release. Keyboard reorder uses a
// separate code path (moveUp/moveDown), so a drag is the only way to exercise
// the top-level DragDropContext's onDragEnd for a nested list.
const dragRowAfter = async (page: Page, source: string, target: string) => {
  const sourceEl = page.locator(source).first();
  await sourceEl.scrollIntoViewIfNeeded();
  const sb = await sourceEl.boundingBox();
  const tb = await page.locator(target).first().boundingBox();
  if (!sb || !tb) throw new Error('Could not resolve drag row bounds');

  const sx = sb.x + 20;
  const sy = sb.y + 8;
  const tx = tb.x + 20;
  const ty = tb.y + tb.height - 8;

  await page.mouse.move(sx, sy);
  await page.mouse.down();
  await page.mouse.move(sx, sy + 10, { steps: 6 });
  await page.mouse.move(tx, ty, { steps: 15 });
  await page.mouse.move(tx, ty + 2, { steps: 4 });
  await page.mouse.up();
};

// A repeater nested inside another repeater renders without its own
// DragDropContext, so dragging one of its rows is handled by the ancestor
// list. This guards against that ancestor losing the nested list's row data.
testType('repeater-nested-drag', false, () => [
  {
    description: 'reorders nested rows via drag',
    testFunction: async (page: Page, _canvas: Frame) => {
      const rowA = '[data-rfd-draggable-id="outer[0].inner[0]"]';
      const rowB = '[data-rfd-draggable-id="outer[0].inner[1]"]';
      const labelOf = (row: string) =>
        page.locator(`${row} .blockstudio-fields__field--text input`).first();

      await labelOf(rowA).fill('nested-a');
      await labelOf(rowB).fill('nested-b');
      await expect(labelOf(rowA)).toHaveValue('nested-a');
      await expect(labelOf(rowB)).toHaveValue('nested-b');

      await dragRowAfter(page, rowA, rowB);

      await expect(labelOf(rowA)).toHaveValue('nested-b');
      await expect(labelOf(rowB)).toHaveValue('nested-a');
    },
  },
]);
