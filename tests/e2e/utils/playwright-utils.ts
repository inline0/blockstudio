/**
 * E2E Test Utilities for WordPress Playground.
 * Works with Playground's nested iframe structure (iframe#playground > iframe#wp).
 */

import { expect, FrameLocator } from "@playwright/test";
import { test } from "./fixtures";

type Editor = FrameLocator;

export const click = async (editor: Editor, selector: string) => {
  await editor.locator(selector).click();
};

export const fill = async (editor: Editor, selector: string, value: string) => {
  await editor.locator(selector).fill(value);
};

export const type = async (editor: Editor, selector: string, text: string, options?: { delay?: number }) => {
  await editor.locator(selector).pressSequentially(text, options);
};

export const press = async (editor: Editor, key: string) => {
  await editor.locator("body").press(key);
};

export const waitForSelector = async (editor: Editor, selector: string) => {
  await editor.locator(selector).waitFor({ state: "visible" });
};

export const locator = (editor: Editor, selector: string) => {
  return editor.locator(selector);
};

export const count = async (
  editor: Editor,
  selector: string,
  expectedCount: number
) => {
  await expect(editor.locator(selector)).toHaveCount(expectedCount);
};

export const text = async (editor: Editor, value: string) => {
  await editor.locator("body").evaluate(
    (body, val) => {
      return new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(
          () => reject(new Error(`Text "${val}" not found`)),
          30000
        );
        const check = () => {
          if (body.innerHTML.includes(val)) {
            clearTimeout(timeout);
            resolve();
          } else {
            requestAnimationFrame(check);
          }
        };
        check();
      });
    },
    value
  );

  const html = await editor.locator("body").evaluate((body) => body.innerHTML);
  expect(html.includes(value)).toBeTruthy();
};

export const countText = async (
  editor: Editor,
  value: string,
  expectedCount: number
) => {
  const escapedValue = value.replace(/([.*+?^=!:${}()|[\]/\\])/g, "\\$1");
  const regexPattern = new RegExp(escapedValue, "g");

  await editor.locator(".is-root-container").evaluate(
    (container, { pattern, count }) => {
      return new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(
          () => reject(new Error(`Expected ${count} matches for "${pattern}"`)),
          30000
        );
        const check = () => {
          const matches = container.innerHTML.match(new RegExp(pattern, "g")) || [];
          if (matches.length === count) {
            clearTimeout(timeout);
            resolve();
          } else {
            requestAnimationFrame(check);
          }
        };
        check();
      });
    },
    { pattern: escapedValue, count: expectedCount }
  );

  const html = await editor.locator(".is-root-container").innerHTML();
  const matches = html.match(regexPattern) || [];
  expect(matches.length).toBe(expectedCount);
};

export const innerHTML = async (
  editor: Editor,
  selector: string,
  content: string,
  first = true
) => {
  if (first) {
    await expect(editor.locator(selector).first()).toHaveText(content);
  } else {
    await expect(editor.locator(selector).last()).toHaveText(content);
  }
};

export const checkStyle = async (
  editor: Editor,
  selector: string,
  property: string,
  value: string,
  not = false
) => {
  await editor.locator(selector).first().waitFor({ state: "visible" });

  const computedValue = await editor.locator(selector).first().evaluate(
    (el, prop) => getComputedStyle(el)[prop as any],
    property
  );

  if (not) {
    expect(computedValue).not.toBe(value);
  } else {
    expect(computedValue).toBe(value);
  }
};

export const delay = (ms: number) =>
  new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Navigate to the frontend (View Post) from the editor.
 * Uses the "View Post" link in the post-publish panel or editor bar.
 */
export const navigateToFrontend = async (editor: Editor) => {
  // Try to find View Post link (appears after saving)
  const viewPostLink = editor.locator('a:has-text("View Post")').first();
  const viewPostVisible = await viewPostLink.isVisible().catch(() => false);

  if (viewPostVisible) {
    await viewPostLink.click();
  } else {
    // Try the post-publish panel link
    const panelLink = editor.locator('.post-publish-panel__postpublish-buttons a').first();
    if (await panelLink.isVisible().catch(() => false)) {
      await panelLink.click();
    } else {
      throw new Error('No View Post link found. Make sure the post is saved first.');
    }
  }

  // Wait for frontend to load
  await delay(2000);
};

/**
 * Navigate back to the editor from frontend.
 * Uses the "Edit Post" link in the WordPress admin bar if available,
 * otherwise navigates via posts list.
 */
export const navigateToEditor = async (editor: Editor) => {
  // Try admin bar first (visible on frontend when logged in and enabled)
  const adminBarEdit = editor.locator('#wp-admin-bar-edit a');
  const adminBarVisible = await adminBarEdit.isVisible().catch(() => false);

  if (adminBarVisible) {
    await adminBarEdit.click();
  } else {
    // Fallback: navigate to posts list and click the first post
    // Retry logic for transient Playground errors (Bad Gateway, etc.)
    for (let attempt = 0; attempt < 3; attempt++) {
      await editor.locator('body').evaluate(() => {
        (window as any).location.href = '/wp-admin/edit.php';
      });

      const loaded = await editor.locator('.wp-list-table')
        .waitFor({ state: 'visible', timeout: 15000 })
        .then(() => true)
        .catch(() => false);

      if (loaded) break;
      await delay(2000);
    }

    await editor.locator('.wp-list-table .row-title').first().click();
  }

  // Wait for editor to load
  await editor.locator(".is-root-container").waitFor({ state: "visible", timeout: 30000 });
};

export const save = async (editor: Editor) => {
  const publishButton = editor.locator('button:has-text("Publish")').first();
  const saveButton = editor.locator('button:has-text("Save draft")').first();
  const updateButton = editor.locator('button:has-text("Update")').first();

  if (await updateButton.isVisible().catch(() => false)) {
    await updateButton.click();
  } else if (await publishButton.isVisible().catch(() => false)) {
    await publishButton.click();

    const prePublishPanel = editor.locator(".editor-post-publish-panel");
    const panelVisible = await prePublishPanel
      .waitFor({ state: "visible", timeout: 2000 })
      .then(() => true)
      .catch(() => false);

    if (panelVisible) {
      // Use specific class to avoid matching "Publish: Immediately" toggle
      const confirmButton = prePublishPanel.locator('.editor-post-publish-button');
      await confirmButton.click();
    }
  } else if (await saveButton.isVisible().catch(() => false)) {
    await saveButton.click();
  } else {
    await editor.locator(".editor-post-publish-button").click();
  }

  // Wait for snackbar to appear indicating save completed
  await editor.locator(".components-snackbar").waitFor({ state: "visible", timeout: 10000 });

  // Wait for Save button to be enabled (not disabled, not "Saving...")
  // This ensures the save is fully complete before continuing
  for (let attempt = 0; attempt < 20; attempt++) {
    const saveBtn = editor.locator('.editor-header__settings button').first();
    const isDisabled = await saveBtn.isDisabled().catch(() => true);
    if (!isDisabled) {
      break;
    }
    await delay(500);
  }
};

export const saveAndReload = async (editor: Editor) => {
  await save(editor);
  await delay(2000);

  // Navigate to posts list and back to simulate a "reload"
  // Playground doesn't persist on actual page reload
  // Retry logic for transient Playground errors
  for (let attempt = 0; attempt < 3; attempt++) {
    await editor.locator('body').evaluate(() => {
      (window as any).location.href = '/wp-admin/edit.php';
    });

    const loaded = await editor.locator('.wp-list-table')
      .waitFor({ state: 'visible', timeout: 15000 })
      .then(() => true)
      .catch(() => false);

    if (loaded) break;
    await delay(2000);
  }

  await editor.locator('.wp-list-table .row-title').first().click();
  await editor.locator(".is-root-container").waitFor({ state: "visible", timeout: 30000 });
};

export const removeBlocks = async (editor: Editor) => {
  const root = await editor.locator(".is-root-container").isVisible();
  if (root) {
    await editor.locator(".is-root-container").click();
  }
  await editor.locator("body").evaluate(() => {
    (window as any).wp.data.dispatch("core/block-editor").resetBlocks([]);
  });
};

export const openBlockInserter = async (editor: Editor) => {
  const inserterButton = editor.locator(".editor-document-tools__inserter-toggle");
  const blockList = editor.locator(".block-editor-inserter__block-list");

  // Retry because button aria-pressed state can desync after resetBlocks()
  for (let attempt = 0; attempt < 3; attempt++) {
    const sidebarVisible = await blockList.isVisible().catch(() => false);

    if (sidebarVisible) {
      break;
    }

    // Button shows pressed but sidebar isn't visible - click to sync state
    const buttonPressed = await inserterButton.getAttribute("aria-pressed");
    if (buttonPressed === "true") {
      await inserterButton.click();
      await delay(300);
    }

    await inserterButton.click();

    const opened = await blockList
      .waitFor({ state: "visible", timeout: 3000 })
      .then(() => true)
      .catch(() => false);

    if (opened) {
      break;
    }

    await delay(500);
  }

  await count(editor, ".block-editor-inserter__block-list", 1);
};

export const openSidebar = async (editor: Editor) => {
  const fieldsVisible = await editor
    .locator(".blockstudio-fields")
    .isVisible()
    .catch(() => false);

  if (!fieldsVisible) {
    await editor.locator('[aria-controls="edit-post:block"]').click();
  }
};

export const addBlock = async (editor: Editor, type: string) => {
  await openBlockInserter(editor);
  await delay(500);

  // Search because Blockstudio blocks aren't in the default inserter view
  const searchInput = editor.locator('.block-editor-inserter__search input');
  await searchInput.fill(type);
  await delay(500);

  await editor.locator(`.editor-block-list-item-blockstudio-${type}`).click();
  await delay(500);

  // Close Block Inserter if still open (Playground sometimes leaves it open)
  const inserterButton = editor.locator('button[aria-label="Block Inserter"]');
  const isPressed = await inserterButton.getAttribute('aria-pressed').catch(() => null);
  if (isPressed === 'true') {
    await inserterButton.click();
    await delay(300);
  }
};

export const searchForBlock = async (
  editor: Editor,
  searchTerm: string,
  selector: string
) => {
  await editor.locator('[placeholder="Search"]').fill(searchTerm);
  await editor.locator(selector).click();
};

export const checkForLeftoverAttributes = async (editor: Editor) => {
  const leftoverAttrs = [
    // General
    "useBlockProps",
    "tag",
    // InnerBlocks
    "allowedBlocks",
    "renderAppender",
    "template",
    "templateLock",
    // RichText
    "attribute",
    "placeholder",
    "allowedFormats",
    "autocompleters",
    "multiline",
    "preserveWhiteSpace",
    "withoutInteractiveFormatting",
  ];

  for (const item of leftoverAttrs) {
    await count(editor, `[${item.toLowerCase()}]`, 0);
  }
};

export const clickEditorToolbar = async (
  editor: Editor,
  type: string,
  show: boolean
) => {
  const toolbarSelector = `#blockstudio-editor-toolbar-${type}`;
  const elementSelector = `#blockstudio-editor-${type}`;

  const elementExists = await editor
    .locator(elementSelector)
    .isVisible()
    .catch(() => false);

  if (!elementExists && show) {
    await editor.locator(toolbarSelector).click();
    await delay(1000);
    await count(editor, elementSelector, 1);
  }
  if (elementExists && !show) {
    await editor.locator(toolbarSelector).click();
    await delay(1000);
    await count(editor, elementSelector, 0);
  }
};

export const addTailwindClass = async (
  editor: Editor,
  selector: string,
  className: string
) => {
  const listViewVisible = await editor
    .locator(".editor-list-view-sidebar")
    .isVisible()
    .catch(() => false);

  if (!listViewVisible) {
    await editor
      .locator(".editor-document-tools__document-overview-toggle")
      .click();
  }
  await editor
    .locator(".block-editor-list-view-block__contents-container")
    .click();
  await editor
    .locator('.blockstudio-builder__controls [role="combobox"]')
    .click();
  await editor
    .locator('.blockstudio-builder__controls [role="combobox"]')
    .fill(className);
  await editor.locator("body").press("ArrowDown");
  await editor.locator("body").press("Enter");
};

/** Creates a standardized test suite for a field type with defaults and field tests. */
export const testType = (
  field: string,
  defaultValue: any = false,
  testCallback: ((editor: Editor) => any[]) | false = false,
  removeAtEnd = true
) => {
  test.describe.configure({ mode: "serial" });

  test.describe(field, () => {
    test.describe("defaults", () => {
      test("add block", async ({ editor, resetBlocks }) => {
        await resetBlocks();
        await openBlockInserter(editor);
        await addBlock(editor, `type-${field}`);
        await count(editor, ".is-root-container > .wp-block", 1);
      });

      test("has correct defaults", async ({ editor }) => {
        if (defaultValue) {
          await text(editor, `${defaultValue}`);
        }
      });

      test("remove block", async ({ resetBlocks }) => {
        await resetBlocks();
      });
    });

    test.describe("fields", () => {
      for (const type of ["outer", "inner"]) {
        test.describe(type, () => {
          if (type === "outer") {
            test("add block", async ({ editor, resetBlocks }) => {
              await resetBlocks();
              await addBlock(editor, `type-${field}`);
              await count(editor, ".is-root-container > .wp-block", 1);
              await openSidebar(editor);
            });
          } else {
            test("add InnerBlocks", async ({ editor, resetBlocks }) => {
              await resetBlocks();
              await addBlock(editor, "component-innerblocks-bare-spaceless");
              await count(editor, ".is-root-container > .wp-block", 1);
              await editor
                .locator(".editor-document-tools__document-overview-toggle")
                .click();
              await editor
                .locator('[aria-label="Native InnerBlocks bare spaceless"]')
                .click();
              await editor
                .locator('button[aria-label="Block Inserter"]')
                .click();
              await editor
                .locator(`.editor-block-list-item-blockstudio-type-${field}`)
                .click();
              await delay(1000);
              // Close Block Inserter if still open
              const inserterButton = editor.locator('button[aria-label="Block Inserter"]');
              const isPressed = await inserterButton.getAttribute('aria-pressed');
              if (isPressed === 'true') {
                await inserterButton.click();
              }
              // Click on the inner block to select it
              await editor.locator(`[data-type="blockstudio/type-${field}"]`).click();
              await delay(500);
              // Open sidebar for the inner block
              await openSidebar(editor);
            });
          }

          if (testCallback && typeof testCallback === "function") {
            // Repeater doesn't work inside InnerBlocks
            if (type === "inner" && field === "repeater") {
              return;
            }

            // Placeholder needed because fixture isn't available at describe time
            const testItems = testCallback({} as Editor);

            if (Array.isArray(testItems)) {
              for (const testItem of testItems) {
                if (
                  Array.isArray(testItem.params) &&
                  typeof testItem.generateTestCases === "function"
                ) {
                  for (const param of testItem.params) {
                    const testCases = testItem.generateTestCases(param);
                    if (Array.isArray(testCases)) {
                      for (const testCase of testCases) {
                        if (
                          testCase.groupName &&
                          Array.isArray(testCase.testCases)
                        ) {
                          test.describe(testCase.groupName, () => {
                            for (const nestedTestCase of testCase.testCases) {
                              const { description, testFunction } =
                                nestedTestCase;
                              test(description, async ({ editor }) => {
                                await testFunction(editor);
                              });
                            }
                          });
                        }
                      }
                    }
                  }
                } else if (
                  testItem.groupName &&
                  Array.isArray(testItem.testCases)
                ) {
                  test.describe(testItem.groupName, () => {
                    for (const testCase of testItem.testCases) {
                      const { description, testFunction } = testCase;
                      test(description, async ({ editor }) => {
                        await testFunction(editor);
                      });
                    }
                  });
                } else if (
                  testItem.description &&
                  typeof testItem.testFunction === "function"
                ) {
                  test(testItem.description, async ({ editor }) => {
                    await testItem.testFunction(editor);
                  });
                }
              }
            }
          }
        });
      }

      if (removeAtEnd) {
        test("remove block", async ({ resetBlocks }) => {
          await resetBlocks();
        });
      }
    });
  });
};

/** Creates a test suite for context/editor tests. */
export const testContext = (
  type: string,
  testCallback: ((editor: Editor) => any[]) | false = false
) => {
  test.describe(type, () => {
    test.describe("open structure", () => {
      test("open first level", async ({ editor }) => {
        await editor
          .locator("#instance-plugins-blockstudio-includes-library")
          .click();
        await count(
          editor,
          "#folder-plugins-blockstudio-includes-library-blockstudio-element",
          1
        );
      });

      test("open second level", async ({ editor }) => {
        await editor
          .locator(
            "#folder-plugins-blockstudio-includes-library-blockstudio-element"
          )
          .click();
        await count(
          editor,
          "#folder-plugins-blockstudio-includes-library-blockstudio-element-code",
          1
        );
      });

      test("open third level", async ({ editor }) => {
        await editor
          .locator(
            "#folder-plugins-blockstudio-includes-library-blockstudio-element-code"
          )
          .click();
        await count(
          editor,
          "#file-plugins-blockstudio-includes-library-blockstudio-element-code-block-json",
          1
        );
      });
    });

    if (testCallback && typeof testCallback === "function") {
      const testItems = testCallback({} as Editor);
      if (Array.isArray(testItems)) {
        for (const testItem of testItems) {
          if (
            testItem.description &&
            typeof testItem.testFunction === "function"
          ) {
            test(testItem.description, async ({ editor }) => {
              await testItem.testFunction(editor);
            });
          }
        }
      }
    }
  });
};

// Re-export test and expect for convenience
export { test, expect } from "./fixtures";
