import assert from 'node:assert/strict';
import test from 'node:test';
import { blueprint } from '../playground.ts';

test('the blueprint carries the wp-env constants and activates theme and plugin', () => {
  const constants = { WP_DEBUG: true, SCRIPT_DEBUG: true };
  const { preferredVersions, steps } = blueprint(constants);
  assert.ok(preferredVersions.php && preferredVersions.wp);
  assert.deepEqual(
    steps.map((step) => step.step),
    [
      'defineWpConfigConsts',
      'activateTheme',
      'activatePlugin',
      'setSiteOptions',
    ],
  );
  assert.deepEqual(steps[0], {
    step: 'defineWpConfigConsts',
    consts: constants,
  });
});
