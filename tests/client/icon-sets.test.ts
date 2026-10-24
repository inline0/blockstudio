import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { test } from 'node:test';
import { getDefaultSubSet, iconData } from '../../src/blocks/utils/icon-sets';

const iconsDir = join('includes', 'icons');

const files = (set: string) =>
  iconData[set].length
    ? iconData[set].map((subSet) => `${set}-${subSet}.json`)
    : [`${set}.json`];

test('every icon set resolves to files that ship in includes/icons', () => {
  for (const set of Object.keys(iconData)) {
    for (const file of files(set)) {
      assert.ok(existsSync(join(iconsDir, file)), `${set}: missing ${file}`);
    }
  }
});

test('every shipped icon file belongs to a set of the picker', () => {
  const mapped = new Set(Object.keys(iconData).flatMap(files));
  const shipped = readdirSync(iconsDir).filter((file) =>
    file.endsWith('.json'),
  );

  assert.deepEqual(shipped.filter((file) => !mapped.has(file)).sort(), []);
});

test('tabler icons are listed with the sub sets of their files', () => {
  assert.deepEqual([...iconData['tabler-icons']].sort(), ['filled', 'outline']);
});

test('the default sub set of font awesome stays solid', () => {
  assert.equal(getDefaultSubSet('fontawesome-free'), 'solid');
});

test('sets without a solid sub set default to their first sub set', () => {
  assert.equal(getDefaultSubSet('remix-icons'), 'arrows');
  assert.equal(getDefaultSubSet('tabler-icons'), 'outline');
  assert.equal(getDefaultSubSet('material-design-icons'), 'two-tone');
});

test('sets without sub sets have no default sub set', () => {
  assert.equal(getDefaultSubSet('octicons'), '');
  assert.equal(getDefaultSubSet('unknown-set'), '');
});

test('a configured sub set wins when the set has it', () => {
  assert.equal(getDefaultSubSet('fontawesome-free', ['regular']), 'regular');
  assert.equal(getDefaultSubSet('fontawesome-free', 'brands'), 'brands');
  assert.equal(
    getDefaultSubSet('fontawesome-free', ['outline', 'regular']),
    'regular',
  );
});

test('a configured sub set the set does not have is ignored', () => {
  assert.equal(getDefaultSubSet('remix-icons', ['solid']), 'arrows');
});
