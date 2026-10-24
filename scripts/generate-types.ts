import { Effect, FileSystem } from 'effect';
import { resolve } from 'node:path';
import { command } from './lib/command.ts';
import { writeAtomic } from './lib/files.ts';
import { main } from './lib/runtime.ts';

const root = resolve(import.meta.dirname, '..');

export const generateTypes = Effect.scoped(
  Effect.gen(function* () {
    const fs = yield* FileSystem.FileSystem;
    const temporary = yield* fs.makeTempDirectoryScoped({
      prefix: 'blockstudio-types-',
    });
    const output = resolve(temporary, 'block.ts');
    yield* command(
      resolve(root, 'node_modules/.bin/quicktype'),
      [resolve(root, 'schemas/block.json'), '-s', 'schema', '-o', output],
      { cwd: root, inherit: true },
    );
    // src/types is ignored by the formatter, so format the temporary file to
    // keep regenerated output in the committed style.
    yield* command(
      resolve(root, 'node_modules/.bin/oxfmt'),
      ['-c', resolve(root, 'oxfmt.config.ts'), '--write', output],
      { cwd: root },
    );
    // quicktype's usage header and runtime comments are not kept in the repo.
    const source = (yield* fs.readFileString(output)).replace(
      /^[ \t]*\/\/.*\n/gm,
      '',
    );
    yield* writeAtomic(resolve(root, 'src/types/block.ts'), source);
    console.log('Generated src/types/block.ts');
  }),
);

if (import.meta.main) main(generateTypes);
