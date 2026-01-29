# E2E Testing Migration: Playground to wp-env

## Problem Statement

WordPress Playground (WebAssembly-based) is unstable for E2E testing:
- Random crashes with "Bad Gateway" errors
- Memory issues causing browser/context crashes
- Flaky test results (same test passes/fails randomly)
- Difficult to debug PHP errors (no proper error logs)
- InnerBlocks tests particularly unstable

## Solution

Migrate E2E tests from WordPress Playground to **wp-env** (Docker-based WordPress environment).

### Benefits of wp-env

1. **Stability**: Native PHP execution, no WebAssembly limitations
2. **Debugging**: Full access to PHP error logs, xdebug support
3. **Persistence**: Database persists between test runs
4. **Standard tooling**: Same environment as production WordPress
5. **Isolation**: Docker containers provide clean isolation

## Architecture

```
blockstudio7/
├── .wp-env.json              # wp-env configuration
├── tests/
│   ├── e2e/                  # E2E tests (wp-env)
│   │   ├── types/            # Field type tests (from _reference)
│   │   ├── utils/            # Test utilities
│   │   └── fixtures/         # Test fixtures
│   └── unit/                 # Unit tests (keep Playground)
│       ├── playground-server.ts
│       └── *.test.ts
```

## Implementation Plan

### Phase 1: Setup wp-env

1. **Install wp-env**
   ```bash
   npm install @wordpress/env --save-dev
   ```

2. **Create .wp-env.json**
   ```json
   {
     "core": "WordPress/WordPress#6.4",
     "plugins": ["."],
     "config": {
       "WP_DEBUG": true,
       "WP_DEBUG_LOG": true,
       "SCRIPT_DEBUG": true
     },
     "port": 8888,
     "testsPort": 8889,
     "mappings": {
       "wp-content/plugins/blockstudio-test-blocks": "./tests/blocks"
     }
   }
   ```

3. **Add npm scripts**
   ```json
   {
     "scripts": {
       "wp-env": "wp-env",
       "wp-env:start": "wp-env start",
       "wp-env:stop": "wp-env stop",
       "wp-env:clean": "wp-env clean",
       "test:e2e": "wp-env start && playwright test --config=playwright.wp-env.config.ts"
     }
   }
   ```

### Phase 2: Copy Tests from Reference

1. **Copy type tests from _reference**
   ```bash
   cp -r _reference/tests/e2e/types/* tests/e2e/types/
   ```

2. **Update imports and utilities**
   - Adjust for wp-env URL (localhost:8888 instead of Playground)
   - Remove Playground-specific workarounds
   - Simplify `saveAndReload` (standard page reload works in wp-env)

### Phase 3: Create wp-env Playwright Config

**playwright.wp-env.config.ts**
```typescript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  retries: 1,
  workers: 1,

  use: {
    baseURL: 'http://localhost:8888',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: {
    command: 'npm run wp-env:start',
    url: 'http://localhost:8888',
    reuseExistingServer: true,
    timeout: 120000,
  },
});
```

### Phase 4: Update Test Utilities

**Key changes to playwright-utils.ts:**

1. **Remove Playground iframe handling**
   - wp-env runs in regular browser, no nested iframes

2. **Simplify navigation**
   ```typescript
   // Before (Playground)
   await editor.locator('body').evaluate(() => {
     (window as any).location.href = '/wp-admin/edit.php';
   });

   // After (wp-env)
   await page.goto('/wp-admin/edit.php');
   ```

3. **Standard reload**
   ```typescript
   // Before (Playground workaround)
   // Navigate to edit.php and back...

   // After (wp-env)
   await page.reload();
   await page.waitForSelector('.is-root-container');
   ```

4. **Direct page access**
   ```typescript
   // No more frameLocator chains
   const editor = page; // Direct page access
   ```

### Phase 5: Test Data Setup

**wp-env CLI for test data:**
```bash
# Create test posts
wp-env run cli wp post create --post_title="Test Post" --post_status=publish

# Create test media
wp-env run cli wp media import ./tests/fixtures/test-image.jpg

# Create test user
wp-env run cli wp user create testuser test@example.com --role=editor
```

**Or use Playwright fixtures:**
```typescript
test.beforeAll(async ({ request }) => {
  // Use REST API to create test data
  await request.post('/wp-json/wp/v2/posts', {
    data: { title: 'Test Post', status: 'publish' }
  });
});
```

## Migration Checklist

- [ ] Install @wordpress/env
- [ ] Create .wp-env.json configuration
- [ ] Create playwright.wp-env.config.ts
- [ ] Copy tests from _reference/tests/e2e/types/
- [ ] Update playwright-utils.ts for wp-env
- [ ] Remove Playground-specific code (iframe handling, workarounds)
- [ ] Add wp-env npm scripts
- [ ] Set up test data fixtures
- [ ] Run and fix failing tests
- [ ] Update CI/CD configuration
- [ ] Update documentation

## Commands Reference

```bash
# Start wp-env
npm run wp-env:start

# Stop wp-env
npm run wp-env:stop

# Run E2E tests
npm run test:e2e

# Access WordPress admin
open http://localhost:8888/wp-admin
# Username: admin
# Password: password

# View PHP logs
npm run wp-env run cli cat /var/www/html/wp-content/debug.log

# Run WP-CLI commands
npm run wp-env run cli wp <command>

# Clean and reset
npm run wp-env:clean
```

## Ports

| Service | Port |
|---------|------|
| wp-env WordPress | 8888 |
| wp-env Tests | 8889 |
| Unit Tests (Playground) | 9401 |

## Success Criteria

1. All E2E tests pass consistently (no flaky crashes)
2. InnerBlocks tests work reliably
3. PHP errors are visible in logs for debugging
4. Test execution time is reasonable (<10 min for full suite)
5. Easy local debugging with browser DevTools

## Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Docker not installed | Document Docker Desktop requirement |
| Port conflicts | Make ports configurable |
| Slower than Playground | Optimize with parallel workers |
| Database state pollution | Reset between test files |

## Timeline

1. **Day 1**: Set up wp-env, create config files
2. **Day 2**: Copy and adapt test utilities
3. **Day 3**: Copy type tests, fix imports
4. **Day 4**: Run tests, fix failures
5. **Day 5**: Documentation and cleanup
