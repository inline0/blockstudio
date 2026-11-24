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

// Core parses a `source: 'html'` attribute from the block's markup, which is
// empty for a dynamic block, so `parsed` never carries the comment value.
const sourced = {
  ...definitions,
  title: {
    blockstudio: true,
    field: 'richtext',
    type: 'string',
    source: 'html',
  },
  body: { blockstudio: true, field: 'wysiwyg', type: 'string', source: 'html' },
};
const sourcedDefaults = { ...defaults, title: '', body: '' };

test('rich text fields are imported from the comment instead of the empty block markup', () => {
  const parsed = {
    ...sourcedDefaults,
    blockstudio: { attributes: sourcedDefaults },
  };
  const original = structuredClone(parsed);
  const result = normalize(
    parsed,
    { title: 'Hello <em>world</em>', body: '<p>Text</p>', side: 'left' },
    sourced,
  );
  assert.deepEqual(result.blockstudio, {
    attributes: {
      heading: '',
      side: 'left',
      title: 'Hello <em>world</em>',
      body: '<p>Text</p>',
    },
  });
  assert.equal('title' in result, false);
  assert.equal('body' in result, false);
  assert.deepEqual(parsed, original);
});

test('rich text fields keep explicit nested values and ignore values that are not strings', () => {
  const nested = {
    title: 'Stale',
    blockstudio: { attributes: { title: '' } },
  };
  assert.deepEqual(
    (normalize(nested, nested, sourced).blockstudio as any).attributes,
    { title: '' },
  );
  for (const title of [7, true, null, ['a'], { a: 1 }]) {
    const parsed = { title: '', blockstudio: { attributes: { title: '' } } };
    assert.equal(normalize(parsed, { title }, sourced), parsed);
  }
});
