// @ts-ignore
import { expect, Page, test } from '@playwright/test';
import { existsSync, promises as fs } from 'fs';
import path from 'path';
import * as process from 'process';
import {
  addBlock,
  checkStyle,
  count,
  delay,
  openBlockInserter,
  pBlocks,
  pEditor,
  save,
  searchForBlock,
} from '../../playwright-utils';

const dist = path.join(
  process.cwd(),
  './test-blocks/single/element-script/_dist'
);

const deleteDirectoryRecursively = async (dirPath: string) => {
  try {
    const items = await fs.readdir(dirPath);
    for (const item of items) {
      const itemPath = path.join(dirPath, item);
      const itemStat = await fs.stat(itemPath);
      if (itemStat.isDirectory()) {
        await deleteDirectoryRecursively(itemPath);
      } else {
        await fs.unlink(itemPath);
      }
    }

    await fs.rmdir(dirPath);
  } catch (e) {
    return true;
  }
};

const verifyDirectoryContent = async (
  dirPath: string,
  expectedCount: number
) => {
  const items = await fs.readdir(dirPath);
  if (items.length !== expectedCount) {
    throw new Error(
      `Expected ${expectedCount} items in ${dirPath}, but found ${items.length}`
    );
  }
};

async function readFilesStartingWith(prefix: string, directory = '.') {
  const allFiles = await fs.readdir(directory);
  const matchingFiles = allFiles.filter((filename) =>
    filename.startsWith(prefix)
  );
  const fileContents = [];
  for (const file of matchingFiles) {
    const content = await fs.readFile(`${directory}/${file}`, 'utf-8');
    fileContents.push(content);
  }

  return fileContents;
}

test.describe('assets', () => {
  // test('get created', async ({ page }) => {
  //   const basePath = path.join(process.cwd(), './test-blocks/single');
  //   const expectedStructure = {
  //     'element-script/_dist': 5,
  //     'element-script/_dist/modules': 7,
  //     'element-script/_dist/modules/@srexi-purecounterjs': 1,
  //     'element-script/_dist/modules/@studio-freight-lenis': 1,
  //     'element-script/_dist/modules/@tgwf-co2': 1,
  //     'element-script/_dist/modules/canvas-confetti': 1,
  //     'element-script/_dist/modules/htm': 1,
  //     'element-script/_dist/modules/preact': 1,
  //     'element-script/_dist/modules/swiper': 4,
  //     'element-inline/_dist': 3,
  //     'element-inline/_dist/modules': 7,
  //     'element-inline/_dist/modules/@srexi-purecounterjs': 1,
  //     'element-inline/_dist/modules/@studio-freight-lenis': 1,
  //     'element-inline/_dist/modules/@tgwf-co2': 1,
  //     'element-inline/_dist/modules/canvas-confetti': 1,
  //     'element-inline/_dist/modules/htm': 1,
  //     'element-inline/_dist/modules/preact': 1,
  //     'element-inline/_dist/modules/swiper': 4,
  //     'element-inline-2/_dist': 3,
  //     'element-inline-2/_dist/modules': 7,
  //     'element-inline-2/_dist/modules/@srexi-purecounterjs': 1,
  //     'element-inline-2/_dist/modules/@studio-freight-lenis': 1,
  //     'element-inline-2/_dist/modules/@tgwf-co2': 1,
  //     'element-inline-2/_dist/modules/canvas-confetti': 1,
  //     'element-inline-2/_dist/modules/htm': 1,
  //     'element-inline-2/_dist/modules/preact': 1,
  //     'element-inline-2/_dist/modules/swiper': 4,
  //   };
  //
  //   await deleteDirectoryRecursively(
  //     path.join(basePath, 'element-script/_dist')
  //   );
  //   await deleteDirectoryRecursively(
  //     path.join(basePath, 'element-inline/_dist')
  //   );
  //   await deleteDirectoryRecursively(
  //     path.join(basePath, 'element-inline-2/_dist')
  //   );
  //
  //   await page.goto('http://fabrikat.local/blockstudio/native-single');
  //
  //   for (const [relPath, expectedCount] of Object.entries(expectedStructure)) {
  //     await verifyDirectoryContent(path.join(basePath, relPath), expectedCount);
  //   }
  // });
  //
  // test('scss gets compiled', async () => {
  //   const files = await readFilesStartingWith('style', dist);
  //   expect(files[0]).toBe('body #element-script{border:1px solid red}');
  // });
  //
  // test('variables result in empty file', async () => {
  //   const files = await readFilesStartingWith('variables', dist);
  //   expect(files[0]).toBe('');
  // });
  //
  // test('create random files and delete all', async ({ page }) => {
  //   const basePath = path.join(process.cwd(), './test-blocks/types/text/_dist');
  //   await fs.mkdir(basePath, { recursive: true });
  //   const extensions = ['.css', '.js', '.txt', '.md'];
  //   const folderCount = 3;
  //   for (let i = 0; i < folderCount; i++) {
  //     const folderPath = path.join(basePath, `folder${i}`);
  //     await fs.mkdir(folderPath, { recursive: true });
  //
  //     for (let j = 0; j < extensions.length; j++) {
  //       const filePath = path.join(folderPath, `file${j}${extensions[j]}`);
  //       await fs.writeFile(
  //         filePath,
  //         `This is some content for file${j}${extensions[j]}`
  //       );
  //     }
  //
  //     const subFolderPath = path.join(folderPath, `subfolder${i}`);
  //     await fs.mkdir(subFolderPath, { recursive: true });
  //     for (let j = 0; j < extensions.length; j++) {
  //       const subFilePath = path.join(
  //         subFolderPath,
  //         `subfile${j}${extensions[j]}`
  //       );
  //       await fs.writeFile(
  //         subFilePath,
  //         `This is some content for subfile${j}${extensions[j]}`
  //       );
  //     }
  //   }
  //
  //   await page.goto('http://fabrikat.local/blockstudio/native-single');
  //   expect(existsSync(basePath)).toBe(false);
  // });

  test('enqueue correctly on frontend', async ({ browser }) => {
    const page = await pBlocks(browser);
    let i = 0;
    await openBlockInserter(page);
    for (const item of [
      'element-inline',
      'element-inline-2',
      'element-script',
    ]) {
      i++;
      await page.click(`.editor-block-list-item-blockstudio-${item}`);
      await count(page, '.is-root-container > .wp-block', i);
    }

    await save(page);
    await count(page, '.components-snackbar', 1);
    await page.goto(`https://fabrikat.local/blockstudio/native-single`);
    await count(page, '#element-inline', 1);
    await count(page, '#element-inline2', 1);
    await count(page, '#element-script', 1);
    await count(page, "script[id^='blockstudio']", 6);
    await count(page, "link[id^='blockstudio']", 12);
  });

  test('change SCSS variable to blue and recompile', async ({ browser }) => {
    const page = await pEditor(browser);
    await searchForBlock(
      page,
      'variables',
      '#file-plugins-blockstudio-package-test-blocks-single-element-script-variables-scss'
    );
    await count(page, '.mtk23', 1);
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText('$primary: blue;');
    await page.click('text=Save Block');
    await count(page, '.is-busy', 1);
    await count(page, '.is-busy', 0);
    await page.goto(`https://fabrikat.local/blockstudio/native-single`);
    await checkStyle(page, '#element-script', 'borderColor', 'rgb(0, 0, 255)');
  });

  test('change SCSS variable to red and recompile', async ({ browser }) => {
    const page = await pEditor(browser);
    await searchForBlock(
      page,
      'variables',
      '#file-plugins-blockstudio-package-test-blocks-single-element-script-variables-scss'
    );
    await count(page, '.mtk23', 1);
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText('$primary: red;');
    await page.click('text=Save Block');
    await count(page, '.is-busy', 1);
    await count(page, '.is-busy', 0);
    await page.goto(`https://fabrikat.local/blockstudio/native-single`);
    await checkStyle(page, '#element-script', 'borderColor', 'rgb(255, 0, 0)');
  });

  test.describe('check in editor', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
      page = await pBlocks(browser);
    });
    for (const item of [
      'blockstudio-blocks-js-extra',
      'blockstudio-blocks-js',
      'blockstudio-blockstudio-element-gallery-script-inline-js',
      'blockstudio-blockstudio-element-gallery-style-inline-scss',
      'blockstudio-blockstudio-element-code-script-inline-js',
      'blockstudio-blockstudio-element-code-style-inline-scss',
      'blockstudio-blockstudio-element-slider-script-inline-js',
      'blockstudio-blockstudio-element-slider-style-inline-scss',
      'blockstudio-blockstudio-element-image-comparison-script-inline-js',
      'blockstudio-blockstudio-element-image-comparison-style-inline-scss',
      'blockstudio-blockstudio-element-icon-style-inline-scss',
      'blockstudio-blockstudio-element-inline-script-inline-js',
      'blockstudio-blockstudio-element-inline-11-0-3-swiper-min-css',
      'blockstudio-blockstudio-element-inline-11-0-3-swiper-bundle-css',
      'blockstudio-blockstudio-element-inline-11-0-3-modules-zoom-min-css',
      'blockstudio-blockstudio-element-inline-style-css',
      'blockstudio-blockstudio-native-script-inline-js',
      'blockstudio-blockstudio-native-script-js',
      'blockstudio-blockstudio-native-style-inline-css',
      'blockstudio-blockstudio-native-style-scoped-css',
      'blockstudio-blockstudio-native-style-css',
      'blockstudio-blockstudio-native-nested-script-inline-js',
      'blockstudio-blockstudio-native-nested-script-js',
      'blockstudio-blockstudio-native-nested-style-inline-css',
      'blockstudio-blockstudio-native-nested-style-scoped-css',
      'blockstudio-blockstudio-native-nested-style-css',
      'blockstudio-blockstudio-element-inline-2-script-inline-js',
      'blockstudio-blockstudio-element-inline-2-style-css',
      'blockstudio-blockstudio-element-script-script-inline-js',
      'blockstudio-blockstudio-element-script-script-js',
      'blockstudio-blockstudio-element-script-style-scss',
      'blockstudio-blockstudio-element-script-variables-scss',
      'blockstudio-blockstudio-component-useblockprops-default-style-css',
      'blockstudio-blockstudio-assets-dist-test-inline-css',
      'blockstudio-blockstudio-assets-dist-test-inline-js',
      'blockstudio-blockstudio-assets-dist-test-scoped-css',
      'blockstudio-blockstudio-assets-dist-test-css',
      'blockstudio-blockstudio-assets-dist-test-js',
      'blockstudio-blockstudio-assets-dist-test2-inline-css',
      'blockstudio-blockstudio-assets-dist-test2-inline-js',
      'blockstudio-blockstudio-assets-dist-test2-scoped-css',
      'blockstudio-blockstudio-assets-dist-test2-css',
      'blockstudio-blockstudio-assets-dist-test2-js',
      'blockstudio-blockstudio-assets-none-test-inline-css',
      'blockstudio-blockstudio-assets-none-test-inline-js',
      'blockstudio-blockstudio-assets-none-test-scoped-css',
      'blockstudio-blockstudio-assets-none-test-css',
      'blockstudio-blockstudio-assets-none-test-js',
      'blockstudio-blockstudio-assets-none-test2-inline-css',
      'blockstudio-blockstudio-assets-none-test2-inline-js',
      'blockstudio-blockstudio-assets-none-test2-scoped-css',
      'blockstudio-blockstudio-assets-none-test2-css',
      'blockstudio-blockstudio-assets-none-test2-js',
      'blockstudio-blockstudio-assets-test-inline-css',
      'blockstudio-blockstudio-assets-test-inline-js',
      'blockstudio-blockstudio-assets-test-scoped-css',
      'blockstudio-blockstudio-assets-test-css',
      'blockstudio-blockstudio-assets-test-js',
      'blockstudio-blockstudio-assets-test2-inline-css',
      'blockstudio-blockstudio-assets-test2-inline-js',
      'blockstudio-blockstudio-assets-test2-scoped-css',
      'blockstudio-blockstudio-assets-test2-css',
      'blockstudio-blockstudio-assets-test2-js',
      'blockstudio-blockstudio-assets-none-twig-test-inline-css',
      'blockstudio-blockstudio-assets-none-twig-test-inline-js',
      'blockstudio-blockstudio-assets-none-twig-test-scoped-css',
      'blockstudio-blockstudio-assets-none-twig-test-css',
      'blockstudio-blockstudio-assets-none-twig-test-js',
      'blockstudio-blockstudio-assets-none-twig-test2-inline-css',
      'blockstudio-blockstudio-assets-none-twig-test2-inline-js',
      'blockstudio-blockstudio-assets-none-twig-test2-scoped-css',
      'blockstudio-blockstudio-assets-none-twig-test2-css',
      'blockstudio-blockstudio-assets-none-twig-test2-js',
      'blockstudio-blockstudio-native-script-editor-js',
      'blockstudio-blockstudio-native-style-editor-css',
      'blockstudio-blockstudio-native-nested-script-editor-js',
      'blockstudio-blockstudio-native-nested-style-editor-css',
      'blockstudio-blockstudio-assets-dist-test-editor-css',
      'blockstudio-blockstudio-assets-dist-test-editor-js',
      'blockstudio-blockstudio-assets-dist-test2-editor-css',
      'blockstudio-blockstudio-assets-dist-test2-editor-js',
      'blockstudio-blockstudio-assets-none-test-editor-css',
      'blockstudio-blockstudio-assets-none-test-editor-js',
      'blockstudio-blockstudio-assets-none-test2-editor-css',
      'blockstudio-blockstudio-assets-none-test2-editor-js',
      'blockstudio-blockstudio-events-script-editor-js',
      'blockstudio-blockstudio-assets-test-editor-css',
      'blockstudio-blockstudio-assets-test-editor-js',
      'blockstudio-blockstudio-assets-test2-editor-css',
      'blockstudio-blockstudio-assets-test2-editor-js',
      'blockstudio-blockstudio-assets-none-twig-test-editor-css',
      'blockstudio-blockstudio-assets-none-twig-test-editor-js',
      'blockstudio-blockstudio-assets-none-twig-test2-editor-css',
      'blockstudio-blockstudio-assets-none-twig-test2-editor-js',
    ]) {
      test(`asset ${item} exists`, async () => {
        await count(page, `#${item}`, 1);
      });
    }

    test('global scripts exists', async () => {
      await count(page, '[id*="global-script-js"]', 3);
    });

    test('global styles exists', async () => {
      await count(page, '[id*="global-style-css"]', 2);
    });
  });
});
