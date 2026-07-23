import { readFileSync, writeFileSync, mkdirSync, rmSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';
import { execFileSync } from 'child_process';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const tmpDir = resolve(root, '.tmp-schemas');
const schemasDir = resolve(root, 'schemas');

(() => {
  const blockstudio = JSON.parse(
    readFileSync(resolve(schemasDir, 'blockstudio.json'), 'utf-8'),
  );
  const schema = JSON.parse(
    readFileSync(resolve(schemasDir, 'block.json'), 'utf-8'),
  );

  mkdirSync(tmpDir, { recursive: true });

  const blockJson = resolve(tmpDir, 'block.json');
  const blockstudioJson = resolve(tmpDir, 'blockstudio.json');

  try {
    writeFileSync(blockJson, JSON.stringify(schema));
    writeFileSync(blockstudioJson, JSON.stringify(blockstudio));

    execFileSync(
      'quicktype',
      [blockJson, '-s', 'schema', '-o', 'src/types/block.ts'],
      { cwd: root, stdio: 'inherit' },
    );
    execFileSync(
      'quicktype',
      [blockstudioJson, '-s', 'schema', '-o', 'src/types/blockstudio.ts'],
      { cwd: root, stdio: 'inherit' },
    );

    console.log('Generated src/types/block.ts and src/types/blockstudio.ts');
  } finally {
    rmSync(tmpDir, { recursive: true, force: true });
  }
})();
