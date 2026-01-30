import { Page } from '@playwright/test';
import { countText, delay, testType } from '../../utils/playwright-utils';

testType(
  'repeater-nested',
  false,
  // '{"repeater":[{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"repeater":[{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"repeater":[{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]},{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]},{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]}]},{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"repeater":[{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]},{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]},{"filesSingle":false,"populateQueryIdBefore":1388,"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]}]}]}]}',
  () => {
    return Array.from({ length: 9 }).map((_, index) => {
      return {
        description: `add media ${index}`,
        testFunction: async (page: Page) => {
          if (
            index === 0 ||
            index === 2 ||
            index === 4 ||
            index === 6 ||
            index === 8
          ) {
            await delay(1000);
            await page.locator(`text=Open Media Library`).nth(index).click();
          }
          await delay(1000);
          await page.locator(`text=Open Media Library`).nth(index).click();
          await page.locator('#menu-item-browse:visible').click();
          await page.keyboard.down('Meta');
          await page.click('li[data-id="1604"]:visible');
          await page.click('li[data-id="1605"]:visible');
          await page.keyboard.up('Meta');
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          await countText(page, '"ID":1605', index + 1);
        },
      };
    });
  }
);
