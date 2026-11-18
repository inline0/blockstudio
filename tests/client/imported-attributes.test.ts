import assert from 'node:assert/strict';
import { test } from 'node:test';
import { normalizeImportedAttributes as normalize } from '../../src/blocks/utils/normalize-imported-attributes';

const definitions = {
  heading: { blockstudio: true, field: 'text' },
  side: { blockstudio: true, field: 'select' },
  rows: { blockstudio: true, field: 'repeater' },
  className: { type: 'string' },
};
const defaults = { heading: '', side: { value: 'right', label: 'Right' } };

test('imports original scalar selections instead of core defaults and keeps unrelated fields', () => {
  const parsed = {
    ...defaults,
    heading: 'Hello',
    blockstudio: { attributes: defaults },
    className: 'keep',
  };
  const original = structuredClone(parsed);
  const result = normalize(
    parsed,
    { heading: 'Hello', side: 'left', className: 'keep' },
    definitions,
  );
  assert.deepEqual(result, {
    blockstudio: { attributes: { heading: 'Hello', side: 'left' } },
    className: 'keep',
  });
  assert.deepEqual(parsed, original);
  assert.equal(normalize(result, result, definitions), result);
});

test('existing nested values including explicit empties win and metadata survives', () => {
  for (const value of [
    '',
    false,
    0,
    null,
    [],
    { value: 'left', label: 'Left' },
  ]) {
    const raw = {
      heading: 'Hello',
      side: 'right',
      blockstudio: {
        attributes: { side: value },
        disabled: ['side'],
        editor: true,
      },
    };
    const result = normalize(raw, raw, definitions);
    assert.deepEqual(result.blockstudio, {
      attributes: { heading: 'Hello', side: value },
      disabled: ['side'],
      editor: true,
    });
  }
});

test('scalar zero and clearing a selection do not fall back to defaults', () => {
  for (const side of ['', false, 0, null]) {
    const result = normalize(
      { ...defaults, blockstudio: { attributes: defaults } },
      { side },
      definitions,
    );
    assert.equal((result.blockstudio as any).attributes.side, side);
  }
});

test('nested-only and malformed imports are not rewritten', () => {
  for (const raw of [
    { blockstudio: { attributes: { heading: 'Hello' } } },
    { blockstudio: null },
    { blockstudio: { attributes: 'broken' } },
  ]) {
    assert.equal(normalize(raw, raw, definitions), raw);
  }
});

test('missing or malformed definitions do not adopt fields', () => {
  const raw = { heading: 'Hello' };
  for (const schema of [
    undefined,
    null,
    [],
    { heading: null },
    { heading: { blockstudio: true } },
  ]) {
    assert.equal(normalize(raw, raw, schema), raw);
  }
});

test('repeaters retain row values and unknown or core keys are not adopted', () => {
  const rows = [{ side: 'left', heading: 'Nested' }];
  const raw = { rows, className: 'keep', unknown: 'ignore' };
  const result = normalize(raw, raw, definitions);
  assert.deepEqual(result.blockstudio, { attributes: { rows } });
  assert.equal(result.className, 'keep');
  assert.equal(result.unknown, 'ignore');
});
