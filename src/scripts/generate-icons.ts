import { readFileSync, writeFileSync, readdirSync, rmSync, mkdirSync, statSync, existsSync, copyFileSync } from 'fs';
import { dirname, resolve, join, basename, extname } from 'path';
import { fileURLToPath } from 'url';
import { parse } from 'node-html-parser';

const __dirname = dirname(fileURLToPath(import.meta.url));
const nodeModulesDir = resolve(__dirname, '../../node_modules');
const iconsOutputDir = resolve(__dirname, '../../includes/icons');

interface IconSource {
  package: string;
  path: string;
  outputName: string;
}

const iconSources: IconSource[] = [
  { package: '@fortawesome/fontawesome-free', path: 'svgs', outputName: 'fontawesome-free' },
  { package: 'ionicons', path: 'dist/svg', outputName: 'ion-icons' },
  { package: 'bootstrap-icons', path: 'icons', outputName: 'bootstrap-icons' },
  { package: 'boxicons', path: 'svg', outputName: 'box-icons' },
  { package: 'feather-icons', path: 'dist/icons', outputName: 'feather-icons' },
  { package: 'flat-color-icons', path: 'svg', outputName: 'flat-color-icons' },
  { package: '@primer/octicons', path: 'build/svg', outputName: 'octicons' },
  { package: 'grommet-icons', path: 'img', outputName: 'grommet-icons' },
  { package: 'heroicons', path: '24', outputName: 'heroicons' },
  { package: '@material-design-icons/svg', path: '', outputName: 'material-design-icons' },
  { package: 'remixicon', path: 'icons', outputName: 'remix-icons' },
  { package: 'simple-icons', path: 'icons', outputName: 'simple-icons' },
  { package: '@tabler/icons', path: 'icons', outputName: 'tabler-icons' },
  { package: '@vscode/codicons', path: 'src/icons', outputName: 'vscode-icons' },
  { package: 'css.gg', path: 'icons/svg', outputName: 'css-gg' },
];

function cleanSvg(svgContent: string): string {
  try {
    const root = parse(svgContent);
    const svg = root.querySelector('svg');
    if (svg) {
      svg.removeAttribute('class');
    }
    return root.toString();
  } catch {
    return svgContent;
  }
}

function getAllSvgFiles(dir: string): { path: string; subDir: string }[] {
  const results: { path: string; subDir: string }[] = [];

  if (!existsSync(dir)) {
    return results;
  }

  function walk(currentDir: string, relativePath: string = '') {
    const items = readdirSync(currentDir);

    for (const item of items) {
      if (item === '.DS_Store') continue;

      const fullPath = join(currentDir, item);
      const stat = statSync(fullPath);

      if (stat.isDirectory()) {
        walk(fullPath, relativePath ? `${relativePath}/${item}` : item);
      } else if (extname(item).toLowerCase() === '.svg') {
        results.push({
          path: fullPath,
          subDir: relativePath
        });
      }
    }
  }

  walk(dir);
  return results;
}

function processIconSource(source: IconSource): void {
  const sourcePath = join(nodeModulesDir, source.package, source.path);

  if (!existsSync(sourcePath)) {
    console.warn(`Source not found: ${sourcePath}`);
    return;
  }

  const svgFiles = getAllSvgFiles(sourcePath);

  if (svgFiles.length === 0) {
    console.warn(`No SVG files found in: ${sourcePath}`);
    return;
  }

  const iconsBySubDir: Map<string, Record<string, string>> = new Map();

  for (const { path: filePath, subDir } of svgFiles) {
    const fileName = basename(filePath).toLowerCase();
    const content = readFileSync(filePath, 'utf-8');
    const cleanedContent = cleanSvg(content);

    const normalizedSubDir = subDir.toLowerCase();

    if (!iconsBySubDir.has(normalizedSubDir)) {
      iconsBySubDir.set(normalizedSubDir, {});
    }

    iconsBySubDir.get(normalizedSubDir)![fileName] = cleanedContent;
  }

  for (const [subDir, icons] of iconsBySubDir) {
    const outputFileName = subDir
      ? `${source.outputName}-${subDir.replace(/\//g, '-')}.json`
      : `${source.outputName}.json`;

    const outputPath = join(iconsOutputDir, outputFileName);
    writeFileSync(outputPath, JSON.stringify(icons));

    const iconCount = Object.keys(icons).length;
    console.log(`  ${outputFileName}: ${iconCount} icons`);
  }
}

function main() {
  console.log('Generating icon sets...\n');

  if (existsSync(iconsOutputDir)) {
    const existingFiles = readdirSync(iconsOutputDir);
    for (const file of existingFiles) {
      if (file.endsWith('.json')) {
        rmSync(join(iconsOutputDir, file));
      }
    }
  } else {
    mkdirSync(iconsOutputDir, { recursive: true });
  }

  for (const source of iconSources) {
    console.log(`Processing: ${source.package}`);
    processIconSource(source);
  }

  console.log('\nDone!');
}

main();
