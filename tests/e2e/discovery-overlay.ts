import { expect, test } from '@playwright/test';

test('logical discovery composes parent metadata with overlay templates', async ({
  request,
}) => {
  const response = await request.get(
    'http://localhost:8888/wp-json/blockstudio-test/v1/discovery-overlay',
  );

  expect(response.ok()).toBe(true);

  const result = await response.json();

  expect(result.blockTemplate).toContain(
    '/discovery-overlay/overlay/blocks/card/index.php',
  );
  expect(result.blockStyle).toContain(
    '/discovery-overlay/parent/blocks/card/style.css',
  );
  expect(result.pageTemplate).toContain(
    '/discovery-overlay/overlay/pages/docs/start/index.php',
  );
  expect(result.pageLayout).toContain(
    '/discovery-overlay/parent/pages/docs/layout.php',
  );
});
