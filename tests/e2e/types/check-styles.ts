import { test } from '@playwright/test';

test('check blockstudioAdmin.styles', async ({ page }) => {
  // Login
  await page.goto('http://localhost:8888/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  await page.waitForTimeout(3000);

  // Check blockstudio admin page first
  console.log('\n=== BLOCKSTUDIO ADMIN PAGE ===');
  await page.goto('http://localhost:8888/wp-admin/admin.php?page=blockstudio');
  await page.waitForTimeout(3000);

  const adminCheck = await page.evaluate(() => {
    const admin = (window as any).blockstudioAdmin;
    return {
      hasStyles: !!admin?.styles,
      stylesCount: admin?.styles ? Object.keys(admin.styles).length : 0,
      cssClasses: admin?.cssClasses,
    };
  });
  console.log('Blockstudio admin page:', JSON.stringify(adminCheck, null, 2));

  // Now check block editor
  console.log('\n=== BLOCK EDITOR (post-new.php) ===');
  await page.goto('http://localhost:8888/wp-admin/post-new.php');
  await page.waitForTimeout(5000);

  const pageContent = await page.content();

  // Count declarations
  let count = 0;
  let idx = 0;
  while (true) {
    const nextIdx = pageContent.indexOf('var blockstudioAdmin = ', idx);
    if (nextIdx === -1) break;
    count++;
    const before = pageContent.substring(Math.max(0, nextIdx - 100), nextIdx);
    console.log(`\nDeclaration ${count} before:`, before.substring(before.length - 80));
    idx = nextIdx + 1;
    if (count > 5) break;
  }
  console.log('\nTotal declarations:', count);

  // Check footer script
  const hasFooterScript = pageContent.includes('window.blockstudioAdmin.styles = ');
  console.log('Has footer script:', hasFooterScript);

  // Check JS window
  const editorCheck = await page.evaluate(() => {
    const admin = (window as any).blockstudioAdmin;
    return {
      hasStyles: !!admin?.styles,
      stylesCount: admin?.styles ? Object.keys(admin.styles).length : 0,
      cssClasses: admin?.cssClasses,
    };
  });
  console.log('Block editor window:', JSON.stringify(editorCheck, null, 2));
});
