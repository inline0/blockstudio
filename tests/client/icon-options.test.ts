import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getIconOptions } from '../../src/blocks/utils/icon-options';

test('a fetched set lists every icon without the file extension', () => {
  assert.deepEqual(getIconOptions({ 'heart.svg': '<svg/>', 'star.svg': '' }), [
    { value: 'heart', label: 'heart' },
    { value: 'star', label: 'star' },
  ]);
});

test('an unfetched set lists only the stored icon', () => {
  assert.deepEqual(getIconOptions(undefined, 'heart'), [
    { value: 'heart', label: 'heart' },
  ]);
});

test('an unfetched set without a stored icon lists nothing', () => {
  assert.deepEqual(getIconOptions(undefined, undefined), []);
  assert.deepEqual(getIconOptions(undefined, null), []);
  assert.deepEqual(getIconOptions(undefined, ''), []);
});

test('a failed fetch lists nothing instead of the stored icon', () => {
  assert.deepEqual(getIconOptions({}, 'heart'), []);
});
