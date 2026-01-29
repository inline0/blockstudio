import { test } from '@playwright/test';

test('check blockstudioAdmin.styles', async ({ page }) => {
  // Login
  await page.goto('http://localhost:8888/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**');
  
  // Go to new post
  await page.goto('http://localhost:8888/wp-admin/post-new.php');
  await page.waitForSelector('.editor-canvas__iframe, .block-editor-writing-flow', { timeout: 30000 });
  
  // Check blockstudioAdmin
  const styles = await page.evaluate(() => {
    return {
      hasStyles: typeof (window as any).blockstudioAdmin?.styles !== 'undefined',
      stylesType: typeof (window as any).blockstudioAdmin?.styles,
      stylesKeys: (window as any).blockstudioAdmin?.styles ? Object.keys((window as any).blockstudioAdmin.styles).slice(0, 10) : null,
      cssClasses: (window as any).blockstudioAdmin?.cssClasses,
      wpBlockLibraryTheme: (window as any).blockstudioAdmin?.styles?.['wp-block-library-theme'],
    };
  });
  
  console.log('blockstudioAdmin check:', JSON.stringify(styles, null, 2));
});
