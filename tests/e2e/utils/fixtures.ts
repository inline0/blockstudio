import { test as base, Page, FrameLocator, Locator, expect } from "@playwright/test";

let sharedPage: Page;
let wpFrame: FrameLocator;

/**
 * Editor utilities for interacting with the WordPress block editor.
 */
export class EditorUtils {
  private readonly wp: FrameLocator;

  constructor(wp: FrameLocator) {
    this.wp = wp;
  }

  /**
   * Wait for the block editor to be fully loaded.
   */
  async waitForEditor(): Promise<void> {
    // Wait for the editor canvas
    await this.wp
      .locator('.block-editor-block-list__layout, iframe[name="editor-canvas"]')
      .first()
      .waitFor({ state: "visible", timeout: 30000 });
  }

  /**
   * Get the editor canvas - handles both iframe and non-iframe editor modes.
   */
  async getEditorCanvas(): Promise<FrameLocator | Locator> {
    const iframe = this.wp.locator('iframe[name="editor-canvas"]');
    const isIframeEditor = await iframe.count() > 0;

    if (isIframeEditor) {
      return this.wp.frameLocator('iframe[name="editor-canvas"]');
    }
    return this.wp.locator(".block-editor-block-list__layout");
  }

  /**
   * Open the block inserter.
   */
  async openBlockInserter(): Promise<void> {
    // Click the inserter toggle button
    await this.wp.locator('button[aria-label="Toggle block inserter"]').click();

    // Wait for the inserter panel to appear
    await this.wp
      .locator(".block-editor-inserter__content")
      .waitFor({ state: "visible" });
  }

  /**
   * Close the block inserter if open.
   */
  async closeBlockInserter(): Promise<void> {
    const inserterButton = this.wp.locator(
      'button[aria-label="Toggle block inserter"][aria-pressed="true"]'
    );
    if ((await inserterButton.count()) > 0) {
      await inserterButton.click();
    }
  }

  /**
   * Search for a block in the inserter.
   */
  async searchBlock(searchTerm: string): Promise<void> {
    await this.openBlockInserter();

    // Type in the search field
    const searchInput = this.wp.locator(
      '.block-editor-inserter__search input[type="search"]'
    );
    await searchInput.fill(searchTerm);
    await searchInput.press("Enter");

    // Wait for search results
    await this.wp.locator(".block-editor-inserter__panel-content").waitFor();
  }

  /**
   * Insert a block by name (e.g., "blockstudio/type-text").
   */
  async insertBlock(blockName: string): Promise<void> {
    // Use the slash command inserter for more reliable insertion
    const canvas = await this.getEditorCanvas();

    // Click on empty paragraph or add block button
    const emptyParagraph = (canvas as FrameLocator).locator(
      '.block-editor-default-block-appender__content, [data-empty="true"]'
    );

    if ((await emptyParagraph.count()) > 0) {
      await emptyParagraph.first().click();
    } else {
      // Click add block button
      await this.wp.locator('button[aria-label="Add block"]').first().click();
    }

    // Type slash command
    await this.wp.locator("body").press("/");
    await this.wp.locator("body").type(blockName.replace("blockstudio/", ""));

    // Wait for autocomplete and select first result
    await this.wp
      .locator('.components-autocomplete__result button')
      .first()
      .waitFor({ timeout: 5000 })
      .catch(() => {
        // Fallback: use block inserter
      });

    await this.wp.locator('.components-autocomplete__result button').first().click();
  }

  /**
   * Select a block by its block name.
   */
  async selectBlock(blockName: string): Promise<Locator> {
    const canvas = await this.getEditorCanvas();
    const block = (canvas as FrameLocator).locator(
      `[data-type="${blockName}"]`
    );
    await block.first().click();
    return block.first();
  }

  /**
   * Get the block inspector sidebar.
   */
  getInspector(): Locator {
    return this.wp.locator(".block-editor-block-inspector");
  }

  /**
   * Open the Settings sidebar (block inspector).
   */
  async openSettings(): Promise<void> {
    const settingsButton = this.wp.locator(
      'button[aria-label="Settings"][aria-expanded="false"]'
    );
    if ((await settingsButton.count()) > 0) {
      await settingsButton.click();
    }
  }

  /**
   * Get a control in the inspector by label.
   */
  getControl(label: string): Locator {
    return this.getInspector().locator(
      `.components-base-control:has-text("${label}")`
    );
  }

  /**
   * Fill a text input in the inspector.
   */
  async fillTextControl(label: string, value: string): Promise<void> {
    const control = this.getControl(label);
    const input = control.locator("input, textarea").first();
    await input.fill(value);
  }

  /**
   * Toggle a toggle control in the inspector.
   */
  async toggleControl(label: string): Promise<void> {
    const control = this.getControl(label);
    const toggle = control.locator('input[type="checkbox"]');
    await toggle.click();
  }

  /**
   * Select an option from a select control.
   */
  async selectOption(label: string, optionValue: string): Promise<void> {
    const control = this.getControl(label);
    const select = control.locator("select");
    await select.selectOption(optionValue);
  }

  /**
   * Get the rendered block preview.
   */
  async getBlockPreview(blockName: string): Promise<string> {
    const canvas = await this.getEditorCanvas();
    const block = (canvas as FrameLocator).locator(
      `[data-type="${blockName}"]`
    );
    return await block.first().innerHTML();
  }

  /**
   * Save the post as draft.
   */
  async saveDraft(): Promise<void> {
    await this.wp
      .locator('button:has-text("Save draft"), button:has-text("Save")')
      .first()
      .click();

    // Wait for save to complete
    await this.wp
      .locator('.editor-post-saved-state.is-saved, :text("Saved")')
      .waitFor({ timeout: 10000 });
  }

  /**
   * Publish the post.
   */
  async publish(): Promise<void> {
    await this.wp.locator('button:has-text("Publish")').first().click();

    // Handle the publish confirmation panel
    const confirmButton = this.wp.locator(
      '.editor-post-publish-panel button:has-text("Publish")'
    );
    if ((await confirmButton.count()) > 0) {
      await confirmButton.click();
    }
  }

  /**
   * View the published post.
   */
  async viewPost(): Promise<void> {
    await this.wp.locator('a:has-text("View Post")').click();
  }
}

type Fixtures = {
  wp: FrameLocator;
  editor: EditorUtils;
};

export const test = base.extend<Fixtures>({
  wp: async ({ browser }, use) => {
    if (!sharedPage) {
      sharedPage = await browser.newPage();
      await sharedPage.goto("/");
      await sharedPage.waitForFunction("window.playgroundReady === true", {
        timeout: 120000,
      });
      const playgroundFrame = sharedPage.frameLocator("iframe#playground");
      wpFrame = playgroundFrame.frameLocator("iframe#wp");
    }
    await use(wpFrame);
  },

  editor: async ({ wp }, use) => {
    const editor = new EditorUtils(wp);
    await editor.waitForEditor();
    await use(editor);
  },
});

export { expect } from "@playwright/test";
