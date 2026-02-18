import { Browser, BrowserContext, expect, Frame, Page, test } from '@playwright/test';

export const login = async (page: Page, baseURL = 'http://localhost:8888'): Promise<void> => {
  await page.goto(`${baseURL}/wp-login.php`);
  await page.waitForLoadState('networkidle');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
};

// Shared page - created once, reused by ALL test files
let sharedPage: Page | null = null;
let sharedContext: BrowserContext | null = null;

// Get or create the shared page
export const getSharedPage = async (browser: Browser): Promise<Page> => {
  if (sharedPage && !sharedPage.isClosed()) {
    return sharedPage;
  }

  // First time: create context, page, and login
  sharedContext = await browser.newContext();
  sharedPage = await sharedContext.newPage();
  await sharedPage.setViewportSize({ width: 1920, height: 1080 });
  await sharedPage.emulateMedia({ reducedMotion: 'reduce' });

  // Handle all dialogs automatically (confirm dialogs, alerts, etc.)
  sharedPage.on('dialog', async (dialog) => {
    await dialog.accept();
  });

  await login(sharedPage);

  return sharedPage;
};

// Get the editor canvas frame (iframed editor with apiVersion 3)
export const getEditorCanvas = async (page: Page): Promise<Frame> => {
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 30000 });
  const frame = page.frame('editor-canvas');
  if (!frame) throw new Error('Editor canvas iframe not found');
  await frame.waitForLoadState('domcontentloaded');
  return frame;
};

// Reset page state - navigate to editor and remove all blocks
export const resetPageState = async (page: Page) => {
  await page.goto('http://localhost:8888/wp-admin/post.php?post=1483&action=edit');
  const canvas = await getEditorCanvas(page);
  await canvas.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });

  // Dismiss welcome modal if present
  const modal = await page.$('text=Welcome to the block editor');
  if (modal) {
    await page.click('.components-modal__screen-overlay .components-button.has-icon');
  }

  // Wait for root container and remove all blocks
  await canvas.waitForSelector('.is-root-container', { timeout: 10000 });
  // Press Escape to dismiss any popovers, then reset blocks via JS
  await page.keyboard.press('Escape');
  await page.evaluate(() => {
    (window as any).wp.data.dispatch('core/block-editor').resetBlocks([]);
  });
};

export const count = async (
  page: Page | Frame,
  selector: string,
  count: number
) => {
  await expect(
    typeof selector === 'string' ? page.locator(selector) : selector
  ).toHaveCount(count);
};

export const text = async (page: Page | Frame, value: string) => {
  const wait = await page.waitForFunction(
    (value: string) => document.documentElement.innerHTML.includes(value),
    value,
    {
      timeout: 30000,
    }
  );
  await wait;
  const val = (
    await page.evaluate(() => document.documentElement.innerHTML)
  ).includes(value);

  return expect(val).toBeTruthy();
};

export const countText = async (page: Page | Frame, value: string, count: unknown) => {
  const regexPattern = new RegExp(
    value.replace(/([.*+?^=!:${}()|[\]/\\])/g, '\\$1'),
    'g'
  );

  const wait = await page.waitForFunction(
    ({ regexPattern, count }) =>
      (
        document.querySelector('.is-root-container')?.innerHTML.match(regexPattern) || []
      ).length === count,
    { regexPattern, count },
    {
      timeout: 30000,
    }
  );
  await wait;

  const val = (
    (await page.locator('.is-root-container').innerHTML()).match(
      regexPattern
    ) || []
  ).length;

  return expect(val).toBe(count);
};

export const innerHTML = async (
  page: Page | Frame,
  selector: string,
  content: string,
  first = true
) => {
  if (first) {
    await expect(page.locator(selector).first()).toHaveText(content);
  } else {
    await expect(page.locator(selector).last()).toHaveText(content);
  }
};

export const checkStyle = async (
  page: Page | Frame,
  selector: string,
  type: string,
  value: string,
  not = false
) => {
  await page.waitForSelector(selector);
  const getStyle = async () =>
    page.$eval(
      selector,
      (e: Element, styleType: string) =>
        getComputedStyle(e)[styleType as keyof CSSStyleDeclaration],
      type
    );

  if (not) {
    await expect.poll(getStyle, { timeout: 30000 }).not.toBe(value);
    return;
  }

  await expect.poll(getStyle, { timeout: 30000 }).toBe(value);
};

export const pBlocks = async (
  browser: Browser,
  url = '',
  wait = '.wp-block-post-title',
  remove = true
) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });

  // Login first - robust approach to avoid race condition
  await page.goto('http://localhost:8888/wp-login.php');
  await page.waitForLoadState('networkidle');
  const userLogin = page.locator('#user_login');
  const userPass = page.locator('#user_pass');
  await userLogin.waitFor({ state: 'visible' });
  await userPass.waitFor({ state: 'visible' });
  await userLogin.click();
  await userLogin.fill('admin');
  await userPass.click();
  await userPass.fill('password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');

  // Navigate to test post (ID 1483 = "Native Single")
  const postUrl = url || 'http://localhost:8888/wp-admin/post.php?post=1483&action=edit';
  await page.goto(postUrl);
  const canvas = await getEditorCanvas(page);
  await canvas.locator(wait).waitFor({ state: 'visible' });
  const modal = await page.$('text=Welcome to the block editor');
  if (modal) {
    await page.click(
      '.components-modal__screen-overlay .components-button.has-icon'
    );
  }
  await count(canvas, '.is-root-container', 1);
  if (remove) {
    await removeBlocks(page);
  }
  return page;
};

export const pEditor = async (browser: Browser) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });
  page.on('dialog', async (dialog: any) => {
    await dialog.accept();
  });
  // Login first - robust approach to avoid race condition
  await page.goto('http://localhost:8888/wp-login.php');
  await page.waitForLoadState('networkidle');
  const userLogin = page.locator('#user_login');
  const userPass = page.locator('#user_pass');
  await userLogin.waitFor({ state: 'visible' });
  await userPass.waitFor({ state: 'visible' });
  await userLogin.click();
  await userLogin.fill('admin');
  await userPass.click();
  await userPass.fill('password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
  await page.goto(
    'http://localhost:8888/wp-admin/admin.php?page=blockstudio#/editor'
  );
  await page
    .locator('#instance-plugins-blockstudio-package-test-blocks')
    .first()
    .waitFor({ state: 'visible' });

  return page;
};

export const removeBlocks = async (page: Page) => {
  const canvas = await getEditorCanvas(page);
  const root = await canvas.$('.is-root-container');
  if (!root) {
    await page.reload({ waitUntil: 'load' });
    await removeBlocks(page);
    return;
  }
  // Press Escape to dismiss any popovers, then reset blocks via JS
  await page.keyboard.press('Escape');
  await page.evaluate(() => {
    (window as any).wp.data.dispatch('core/block-editor').resetBlocks([]);
  });
};

export const delay = (ms: number) =>
  new Promise((resolve) => setTimeout(resolve, ms));

export const save = async (page: Page) => {
  await page.click('.editor-post-publish-button');
  await count(page, '.components-snackbar', 1);
};

export const saveAndReload = async (page: Page) => {
  const reloadAndCheck = async () => {
    await page.reload();
    const canvas = await getEditorCanvas(page);
    if (!(await canvas.$('.wp-block-post-title'))) {
      await reloadAndCheck();
    }
  };

  await save(page);
  await reloadAndCheck();

  const canvas = await getEditorCanvas(page);
  await canvas.locator('.wp-block-post-title').waitFor({ state: 'visible' });
  await count(canvas, '.is-root-container', 1);
};

export const clickEditorToolbar = async (
  page: Page,
  type: string,
  show: boolean
) => {
  const toolbarSelector = `#blockstudio-editor-toolbar-${type}`;
  const elementSelector = `#blockstudio-editor-${type}`;
  const el = await page.$(elementSelector);
  if (!el && show) {
    await page.click(toolbarSelector);
    await delay(1000);
    await count(page, elementSelector, 1);
  }
  if (el && !show) {
    await page.click(toolbarSelector);
    await delay(1000);
    await count(page, elementSelector, 0);
  }
};

export const openBlockInserter = async (page: Page) => {
  async function openInserter() {
    const sidebar = await page.$('.block-editor-inserter__block-list');
    if (!sidebar) {
      await page.click('.editor-document-tools__inserter-toggle');
      await openInserter();
    }
  }

  await openInserter();
  await count(page, '.block-editor-inserter__block-list', 1);
};

export const openSidebar = async (page: Page) => {
  if (!(await page.$('.blockstudio-fields'))) {
    await page.click('[aria-controls="edit-post:block"]');
  }
};

export const addBlock = async (page: Page, type: string) => {
  await openBlockInserter(page);
  await delay(1000);
  await page.click(`.editor-block-list-item-blockstudio-${type}`);
};

export const searchForBlock = async (
  page: Page,
  type: string,
  selector: string
) => {
  await page.fill('[placeholder="Search"]', type);
  await page.locator(selector).click();
};

export const checkForLeftoverAttributes = async (page: Page | Frame) => {
  for (const item of [
    // General
    'useBlockProps',
    'tag',
    // InnerBlocks
    'allowedBlocks',
    'renderAppender',
    'template',
    'templateLock',
    // RichText
    'attribute',
    'placeholder',
    'allowedFormats',
    'autocompleters',
    'multiline',
    'preserveWhiteSpace',
    'withoutInteractiveFormatting',
  ]) {
    await count(page, `[${item.toLowerCase()}]`, 0);
  }
};

export const testType = async (
  field: string | [string, string],
  def: any = false,
  cb: any = false,
  remove = true
) => {
  let page: Page;

  // Support [displayName, blockName] or just string (uses same for both)
  const displayName = Array.isArray(field) ? field[0] : field;
  const blockName = Array.isArray(field) ? field[1] : field;

  test.describe.configure({ mode: 'serial' });

  test.beforeAll(async ({ browser }) => {
    // Get or create the shared page (reused across all test files)
    page = await getSharedPage(browser);
    // Reset state: navigate to editor + remove all blocks
    await resetPageState(page);
  });

  // Note: No afterAll close - page is shared and reused

  test.describe(displayName, () => {
    test.describe('defaults', () => {
      test('add block', async () => {
        await openBlockInserter(page);
        await addBlock(page, `type-${blockName}`);
        const canvas = await getEditorCanvas(page);
        await count(canvas, '.is-root-container > .wp-block', 1);
      });

      test('has correct defaults', async () => {
        if (def) {
          const canvas = await getEditorCanvas(page);
          await text(canvas, `${def}`);
        }
      });

      test('remove block', async () => {
        await removeBlocks(page);
      });
    });

    test.describe('fields', () => {
      ['outer', 'inner'].forEach((type) => {
        test.describe(type, () => {
          if (type === 'outer') {
            test('add block', async () => {
              await addBlock(page, `type-${blockName}`);
              const canvas = await getEditorCanvas(page);
              await count(canvas, '.is-root-container > .wp-block', 1);
              await openSidebar(page);
            });
          } else {
            test('add InnerBlocks', async () => {
              const canvas = await getEditorCanvas(page);
              await canvas.click('.wp-block');
              await removeBlocks(page);
              await addBlock(page, 'component-innerblocks-bare-spaceless');
              const canvas2 = await getEditorCanvas(page);
              await count(canvas2, '.is-root-container > .wp-block', 1);
              await page.click(
                '.editor-document-tools__document-overview-toggle'
              );
              await page.click(
                '[aria-label="Native InnerBlocks bare spaceless"]'
              );
              await page.click('.editor-document-tools__inserter-toggle');
              await page.click(
                `.editor-block-list-item-blockstudio-type-${blockName}`
              );
              await delay(2000);
            });
          }

          if (cb && typeof cb === 'function') {
            if (type === 'inner' && blockName === 'repeater') {
              return;
            }

            const testItems = cb(page);
            if (Array.isArray(testItems)) {
              for (const testItem of testItems) {
                if (
                  Array.isArray(testItem.params) &&
                  typeof testItem.generateTestCases === 'function'
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
                              test(description, async () => {
                                const canvas = await getEditorCanvas(page);
                                await testFunction(page, canvas);
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
                      test(description, async () => {
                        const canvas = await getEditorCanvas(page);
                        await testFunction(page, canvas);
                      });
                    }
                  });
                } else if (
                  testItem.description &&
                  typeof testItem.testFunction === 'function'
                ) {
                  test(testItem.description, async () => {
                    const canvas = await getEditorCanvas(page);
                    await testItem.testFunction(page, canvas);
                  });
                }
              }
            }
          }
        });
      });

      if (remove) {
        test('remove block', async () => {
          await removeBlocks(page);
        });
      }
    });
  });
};

export const testContext = async (type: string, cb: any = false) => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await pEditor(browser);
  });

  test.afterAll(async () => {
    await page.close();
  });

  test.describe(type, async () => {
    test.describe('open structure', () => {
      test('open first level', async () => {
        await page.click('#instance-plugins-blockstudio-includes-library');
        await count(
          page,
          '#folder-plugins-blockstudio-includes-library-blockstudio-element',
          1
        );
      });

      test('open second level', async () => {
        await page.click(
          '#folder-plugins-blockstudio-includes-library-blockstudio-element'
        );
        await count(
          page,
          '#folder-plugins-blockstudio-includes-library-blockstudio-element-code',
          1
        );
      });

      test('open third level', async () => {
        await page.click(
          '#folder-plugins-blockstudio-includes-library-blockstudio-element-code'
        );
        await count(
          page,
          '#file-plugins-blockstudio-includes-library-blockstudio-element-code-block-json',
          1
        );
      });
    });

    if (cb && typeof cb === 'function') {
      const testItems = cb(page);
      if (Array.isArray(testItems)) {
        for (const testItem of testItems) {
          if (
            Array.isArray(testItem.params) &&
            typeof testItem.generateTestCases === 'function'
          ) {
            for (const param of testItem.params) {
              const testCases = testItem.generateTestCases(param);
              if (Array.isArray(testCases)) {
                for (const testCase of testCases) {
                  if (testCase.groupName && Array.isArray(testCase.testCases)) {
                    test.describe(testCase.groupName, () => {
                      for (const nestedTestCase of testCase.testCases) {
                        const { description, testFunction } = nestedTestCase;
                        test(description, async () => {
                          await testFunction(page);
                        });
                      }
                    });
                  }
                }
              }
            }
          } else if (testItem.groupName && Array.isArray(testItem.testCases)) {
            test.describe(testItem.groupName, () => {
              for (const testCase of testItem.testCases) {
                const { description, testFunction } = testCase;
                test(description, async () => {
                  await testFunction(page);
                });
              }
            });
          } else if (
            testItem.description &&
            typeof testItem.testFunction === 'function'
          ) {
            test(testItem.description, async () => {
              await testItem.testFunction(page);
            });
          }
        }
      }
    }
  });
};

export const getFrame = async (page: Page, name: string) => {
  await page.waitForSelector(`[name="${name}"]`);
  const frame = page.frame(name);
  if (!frame) {
    throw new Error(`Frame with name ${name} not found`);
  }
  await frame.waitForLoadState('domcontentloaded');
  await frame.waitForTimeout(2000);

  return frame;
};

export const addTailwindClass = async (
  page: Page,
  _selector: string,
  className: string
) => {
  if (!(await page.$('.editor-list-view-sidebar'))) {
    await page.click('.editor-document-tools__document-overview-toggle');
  }
  await page.click(`.block-editor-list-view-block__contents-container`);
  await page.click('.blockstudio-builder__controls [role="combobox"]');
  await page.fill(
    '.blockstudio-builder__controls [role="combobox"]',
    className
  );
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
};
