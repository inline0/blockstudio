import { writeFileSync, mkdirSync, rmSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const tmpDir = resolve(root, '.tmp-schemas');
const schemasDir = resolve(root, 'docs/src/schemas');

(async () => {
  const { blockstudio } = await import(resolve(schemasDir, 'blockstudio.ts'));
  const { schema } = await import(resolve(schemasDir, 'schema.ts'));

  mkdirSync(tmpDir, { recursive: true });

  const blockJson = resolve(tmpDir, 'block.json');
  const blockstudioJson = resolve(tmpDir, 'blockstudio.json');

  writeFileSync(blockJson, JSON.stringify(await schema()));
  writeFileSync(blockstudioJson, JSON.stringify(blockstudio));

  execSync(`quicktype ${blockJson} -s schema -o src/types/block.ts`, { cwd: root, stdio: 'inherit' });
  execSync(`quicktype ${blockstudioJson} -s schema -o src/types/blockstudio.ts`, { cwd: root, stdio: 'inherit' });

  rmSync(tmpDir, { recursive: true });

  console.log('Generated src/types/block.ts and src/types/blockstudio.ts');
})();
