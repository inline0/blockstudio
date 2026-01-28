import { FrameLocator } from "@playwright/test";
import {
  click,
  fill,
  locator,
  saveAndReload,
  testType,
  expect,
} from "../utils/playwright-utils";

testType("number", '"number":10,"numberZero":0,"textOnZero":false', () => {
  return [
    {
      description: "change number",
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-number"]');
        // Use first() since there are multiple number fields
        await editor.locator(".blockstudio-fields__field--number input").first().fill("100");
        await saveAndReload(editor);
      },
    },
    {
      description: "check number",
      testFunction: async (editor: FrameLocator) => {
        await click(editor, '[data-type="blockstudio/type-number"]');
        await expect(
          locator(editor, ".blockstudio-fields__field--number input").nth(0)
        ).toHaveValue("100");
      },
    },
  ];
});
