import { test, expect, Page } from '@playwright/test';
import {
  count,
  delay,
  openSidebar,
  removeBlocks,
  text,
} from '../../playwright-utils';

const defaults = {
  Select: `{"defaultNumberBothWrongDefault":false,"defaultValueLabel":"Three","defaultNumberArray":3,"defaultNumberArrayBoth":{"value":3,"label":3},"defaultValueStringNumber":"2","defaultValueStringNumberBoth":{"value":"3","label":"3"},"populateQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"populateQueryIdBefore":1388,"populateCustom":"three","populateCustomArrayBeforeBoth":{"value":"three","label":"three"},"populateCustomOnly":"custom-1","populateOnlyQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"populateOnlyQueryUser":{"data":{"ID":"644","user_login":"465media","user_pass":"$P$B6\\/GwtqWRpOXltZFXA6y\\/jslSllmBy\\/","user_nicename":"465media","user_email":"nbaldwin@465-media.com","user_url":"","user_registered":"2020-12-02 18:41:10","user_activation_key":"","user_status":"0","display_name":"Nathan Baldwin","spam":"0","deleted":"0"},"ID":644,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null},"populateOnlyQueryTerm":{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"}}`,
  'Select Stylised': `{"defaultNumberBothWrongDefault":false,"defaultValueLabel":"Three","defaultNumberArray":"3","defaultNumberArrayBoth":{"value":"3","label":"3"},"defaultValueStringNumber":"2","defaultValueStringNumberBoth":{"value":"3","label":"3"},"populateQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"populateQueryIdBefore":1388,"populateCustom":"three","populateCustomArrayBeforeBoth":{"value":"three","label":"three"},"populateCustomOnly":"custom-1","populateOnlyQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"populateOnlyQueryUser":{"data":{"ID":"644","user_login":"465media","user_pass":"$P$B6\\/GwtqWRpOXltZFXA6y\\/jslSllmBy\\/","user_nicename":"465media","user_email":"nbaldwin@465-media.com","user_url":"","user_registered":"2020-12-02 18:41:10","user_activation_key":"","user_status":"0","display_name":"Nathan Baldwin","spam":"0","deleted":"0"},"ID":644,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null},"populateOnlyQueryTerm":{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"}}`,
  'Select Multiple': `{"defaultNumberBothWrongDefault":false,"defaultValueLabel":["Three"],"defaultNumberArray":["3"],"defaultNumberArrayBoth":[{"value":"1","label":"1"},{"value":"3","label":"3"}],"defaultValueStringNumber":["2"],"defaultValueStringNumberBoth":[{"value":"3","label":"3"}],"populateQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"populateQueryIdBefore":[1388],"populateCustom":["three"],"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"}],"populateCustomOnly":["custom-1"],"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"populateOnlyQueryUser":[{"data":{"ID":"1","user_login":"dnnsjsk","user_pass":"$P$BvTf6FsYhcXfF3IHen2Kij9WXWlMCo0","user_nicename":"dnnsjsk","user_email":"dnnsjsk@gmail.com","user_url":"","user_registered":"2019-12-05 13:38:02","user_activation_key":"","user_status":"0","display_name":"dnnsjsk","spam":"0","deleted":"0"},"ID":1,"caps":{"administrator":true},"cap_key":"wp_309_capabilities","roles":["administrator"],"allcaps":{"switch_themes":true,"edit_themes":true,"activate_plugins":true,"edit_plugins":true,"edit_users":true,"edit_files":true,"manage_options":true,"moderate_comments":true,"manage_categories":true,"manage_links":true,"upload_files":true,"import":true,"unfiltered_html":true,"edit_posts":true,"edit_others_posts":true,"edit_published_posts":true,"publish_posts":true,"edit_pages":true,"read":true,"level_10":true,"level_9":true,"level_8":true,"level_7":true,"level_6":true,"level_5":true,"level_4":true,"level_3":true,"level_2":true,"level_1":true,"level_0":true,"edit_others_pages":true,"edit_published_pages":true,"publish_pages":true,"delete_pages":true,"delete_others_pages":true,"delete_published_pages":true,"delete_posts":true,"delete_others_posts":true,"delete_published_posts":true,"delete_private_posts":true,"edit_private_posts":true,"read_private_posts":true,"delete_private_pages":true,"edit_private_pages":true,"read_private_pages":true,"delete_users":true,"create_users":true,"unfiltered_upload":true,"edit_dashboard":true,"update_plugins":true,"delete_plugins":true,"install_plugins":true,"update_themes":true,"install_themes":true,"update_core":true,"list_users":true,"remove_users":true,"promote_users":true,"edit_theme_options":true,"delete_themes":true,"export":true,"view_shop_reports":true,"view_shop_sensitive_data":true,"export_shop_reports":true,"manage_shop_discounts":true,"manage_shop_settings":true,"edit_product":true,"read_product":true,"delete_product":true,"edit_products":true,"edit_others_products":true,"publish_products":true,"read_private_products":true,"delete_products":true,"delete_private_products":true,"delete_published_products":true,"delete_others_products":true,"edit_private_products":true,"edit_published_products":true,"manage_product_terms":true,"edit_product_terms":true,"delete_product_terms":true,"assign_product_terms":true,"view_product_stats":true,"import_products":true,"edit_shop_payment":true,"read_shop_payment":true,"delete_shop_payment":true,"edit_shop_payments":true,"edit_others_shop_payments":true,"publish_shop_payments":true,"read_private_shop_payments":true,"delete_shop_payments":true,"delete_private_shop_payments":true,"delete_published_shop_payments":true,"delete_others_shop_payments":true,"edit_private_shop_payments":true,"edit_published_shop_payments":true,"manage_shop_payment_terms":true,"edit_shop_payment_terms":true,"delete_shop_payment_terms":true,"assign_shop_payment_terms":true,"view_shop_payment_stats":true,"import_shop_payments":true,"edit_shop_discount":true,"read_shop_discount":true,"delete_shop_discount":true,"edit_shop_discounts":true,"edit_others_shop_discounts":true,"publish_shop_discounts":true,"read_private_shop_discounts":true,"delete_shop_discounts":true,"delete_private_shop_discounts":true,"delete_published_shop_discounts":true,"delete_others_shop_discounts":true,"edit_private_shop_discounts":true,"edit_published_shop_discounts":true,"manage_shop_discount_terms":true,"edit_shop_discount_terms":true,"delete_shop_discount_terms":true,"assign_shop_discount_terms":true,"view_shop_discount_stats":true,"import_shop_discounts":true,"view_licenses":true,"manage_licenses":true,"delete_licenses":true,"satispress_download_packages":true,"satispress_view_packages":true,"satispress_manage_options":true,"manage_security":true,"view_affiliate_reports":true,"export_affiliate_data":true,"export_referral_data":true,"export_customer_data":true,"export_visit_data":true,"export_payout_data":true,"manage_affiliate_options":true,"manage_affiliates":true,"manage_referrals":true,"manage_customers":true,"manage_visits":true,"manage_creatives":true,"manage_payouts":true,"manage_consumers":true,"administrator":true},"filter":null},{"data":{"ID":"446","user_login":"tdrayson","user_pass":"$P$BAzgiwUb24\\/MKzKAgJgVqAJ2LUGZAU1","user_nicename":"tdrayson","user_email":"taylor@thecreativetinker.com","user_url":"","user_registered":"2020-06-14 11:38:35","user_activation_key":"","user_status":"0","display_name":"Taylor Drayson","spam":"0","deleted":"0"},"ID":446,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null}],"populateOnlyQueryTerm":[{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"}]}`,
  Radio: `{"defaultNumberBothWrongDefault":false,"defaultValueLabel":"Three","defaultNumberArray":3,"defaultNumberArrayBoth":{"value":3,"label":3},"defaultValueStringNumber":"2","defaultValueStringNumberBoth":{"value":"3","label":"3"},"populateQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"populateQueryIdBefore":1388,"populateCustom":"three","populateCustomArrayBeforeBoth":{"value":"three","label":"three"},"populateCustomOnly":"custom-1","populateOnlyQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"populateOnlyQueryUser":{"data":{"ID":"644","user_login":"465media","user_pass":"$P$B6\\/GwtqWRpOXltZFXA6y\\/jslSllmBy\\/","user_nicename":"465media","user_email":"nbaldwin@465-media.com","user_url":"","user_registered":"2020-12-02 18:41:10","user_activation_key":"","user_status":"0","display_name":"Nathan Baldwin","spam":"0","deleted":"0"},"ID":644,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null},"populateOnlyQueryTerm":{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"}}`,
  Checkbox: `{"defaultNumberBothWrongDefault":false,"defaultValueLabel":["Three"],"defaultNumberArray":[3],"defaultNumberArrayBoth":[{"value":3,"label":3}],"defaultValueStringNumber":["2"],"defaultValueStringNumberBoth":[{"value":"3","label":"3"}],"populateQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"populateQueryIdBefore":[1388],"populateCustom":["three"],"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"}],"populateCustomOnly":["custom-1"],"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}],"populateOnlyQueryUser":[{"data":{"ID":"644","user_login":"465media","user_pass":"$P$B6\\/GwtqWRpOXltZFXA6y\\/jslSllmBy\\/","user_nicename":"465media","user_email":"nbaldwin@465-media.com","user_url":"","user_registered":"2020-12-02 18:41:10","user_activation_key":"","user_status":"0","display_name":"Nathan Baldwin","spam":"0","deleted":"0"},"ID":644,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null}],"populateOnlyQueryTerm":[{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"}]}`,
};

const blockMap = {
  select: 'select',
  'repeater-select': 'select',
  'select-stylised': 'select',
  'repeater-select-stylised': 'select',
  'select-multiple': 'select',
  'repeater-select-multiple': 'select',
  radio: 'radio',
  'repeater-radio': 'radio',
  'radio-button-group': 'radio',
  'repeater-radio-button-group': 'radio',
  checkbox: 'checkbox',
  'repeater-checkbox': 'checkbox',
};

const valuesSelect = [
  // 0
  {
    defaultSelect: '',
    defaultStylised: '',
    defaultRadio: '',
    defaultMultiple: [],
    index: 4,
    indexStylised: 2,
    valueSelect: '3',
    valueStylised: 'Three',
    data: `"defaultNumberBothWrongDefault":{"value":3,"label":"Three"}`,
    dataMultiple: `"defaultNumberBothWrongDefault":[{"value":1,"label":"One"}]`,
  },
  // 1
  {
    defaultSelect: 'three',
    defaultStylised: 'Three',
    defaultMultiple: ['Three'],
    index: 2,
    indexStylised: 1,
    valueSelect: 'two',
    valueStylised: 'Two',
    data: `"defaultValueLabel":"Two"`,
    dataMultiple: `"defaultValueLabel":["Three","One"]`,
  },
  // 2
  {
    defaultSelect: '3',
    defaultStylised: '3',
    defaultMultiple: ['3'],
    index: 2,
    indexStylised: 1,
    valueSelect: '2',
    valueStylised: '2',
    data: `"defaultNumberArray":2`,
    dataStylised: `"defaultNumberArray":"2"`,
    dataMultiple: `"defaultNumberArray":["3","1"]`,
  },
  // 3
  {
    defaultSelect: '3',
    defaultStylised: '3',
    defaultMultiple: ['1', '3'],
    index: 2,
    indexStylised: 1,
    valueSelect: '2',
    valueStylised: '2',
    data: `"defaultNumberArrayBoth":{"value":2,"label":2}`,
    dataStylised: `"defaultNumberArrayBoth":{"value":"2","label":"2"}`,
    dataMultiple: `"defaultNumberArrayBoth":[{"value":"1","label":"1"},{"value":"3","label":"3"},{"value":"2","label":"2"}]`,
  },
  // 4
  {
    defaultSelect: '2',
    defaultStylised: 'Two',
    defaultMultiple: ['Two'],
    index: 2,
    indexStylised: 1,
    valueSelect: '2',
    valueStylised: 'Two',
    data: `"defaultValueStringNumber":"2"`,
    dataMultiple: `"defaultValueStringNumber":["2","1"]`,
  },
  // 5
  {
    defaultSelect: '3',
    defaultStylised: '3',
    defaultMultiple: ['3'],
    index: 3,
    indexStylised: 2,
    valueSelect: '3',
    valueStylised: '3',
    data: `"defaultValueStringNumberBoth":{"value":"3","label":"3"}`,
    dataMultiple: `"defaultValueStringNumberBoth":[{"value":"3","label":"3"},{"value":"1","label":"1"}]`,
  },
  // 6
  {
    defaultSelect: '1388',
    defaultStylised: 'Native Render',
    defaultMultiple: ['Native Render'],
    index: 1,
    indexStylised: 0,
    valueSelect: 'one',
    valueStylised: 'One',
    data: `"populateQuery":"one"`,
    dataMultiple: `"populateQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},"one"]`,
  },
  // 7
  {
    defaultSelect: '1388',
    defaultStylised: 'native-render',
    defaultMultiple: ['native-render'],
    index: 1,
    indexStylised: 0,
    valueSelect: '1483',
    valueStylised: 'native-single',
    data: `"populateQueryIdBefore":1483`,
    dataMultiple: `"populateQueryIdBefore":[1388,1483]`,
  },
  // 8
  {
    defaultSelect: 'three',
    defaultStylised: 'Three',
    defaultMultiple: ['Three'],
    index: 5,
    indexStylised: 4,
    valueSelect: 'custom-2',
    valueStylised: 'Custom 2',
    data: `"populateCustom":"custom-2"`,
    dataMultiple: `"populateCustom":["three","one"]`,
  },
  // 9
  {
    defaultSelect: 'three',
    defaultStylised: 'three',
    defaultMultiple: ['three'],
    index: 5,
    indexStylised: 4,
    valueSelect: 'two',
    valueStylised: 'two',
    data: `"populateCustomArrayBeforeBoth":{"value":"two","label":"two"}`,
    dataMultiple: `"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"},{"value":"custom-1","label":"custom-1"}]`,
  },
  // 10
  {
    defaultSelect: 'custom-1',
    defaultStylised: 'custom-1',
    defaultMultiple: ['custom-1'],
    index: 2,
    indexStylised: 1,
    valueSelect: 'custom-2',
    valueStylised: 'custom-2',
    data: `"populateCustomOnly":"custom-2"`,
    dataMultiple: `"populateCustomOnly":["custom-1","custom-2"]`,
  },
  // 11
  {
    defaultSelect: '1388',
    defaultStylised: 'Native Render',
    defaultMultiple: ['Native Render'],
    index: 2,
    indexStylised: 1,
    valueSelect: '1388',
    valueStylised: 'Native Render',
    data: `"populateOnlyQuery":{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}`,
    dataMultiple: `"populateOnlyQuery":[{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"},`,
  },
  // 12
  {
    defaultSelect: '644',
    defaultStylised: 'Nathan Baldwin',
    defaultMultiple: ['dnnsjsk', 'Taylor Drayson'],
    index: 3,
    indexStylised: 2,
    valueSelect: '704',
    valueStylised: 'Aaron Kessler',
    data: `"populateOnlyQueryUser":{"data":{"ID":"704","user_login":"aaronkessler.de","user_pass":"$P$B0Z.hthHuRkxANiork157MC9J1Z5H5\\/","user_nicename":"aaronkessler-de","user_email":"mail@aaronkessler.de","user_url":"","user_registered":"2022-03-15 15:32:00","user_activation_key":"","user_status":"0","display_name":"Aaron Kessler","spam":"0","deleted":"0"},"ID":704,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null}`,
    dataMultiple: `"populateOnlyQueryUser":[{"data":{"ID":"1","user_login":"dnnsjsk","user_pass":"$P$BvTf6FsYhcXfF3IHen2Kij9WXWlMCo0","user_nicename":"dnnsjsk","user_email":"dnnsjsk@gmail.com","user_url":"","user_registered":"2019-12-05 13:38:02","user_activation_key":"1706924837:$P$BqRKkjZYfoz3NJjLCuZ2MsHYLjzRtj1","user_status":"0","display_name":"dnnsjsk","spam":"0","deleted":"0"},"ID":1,"caps":{"administrator":true},"cap_key":"wp_309_capabilities","roles":["administrator"],"allcaps":{"switch_themes":true,"edit_themes":true,"activate_plugins":true,"edit_plugins":true,"edit_users":true,"edit_files":true,"manage_options":true,"moderate_comments":true,"manage_categories":true,"manage_links":true,"upload_files":true,"import":true,"unfiltered_html":true,"edit_posts":true,"edit_others_posts":true,"edit_published_posts":true,"publish_posts":true,"edit_pages":true,"read":true,"level_10":true,"level_9":true,"level_8":true,"level_7":true,"level_6":true,"level_5":true,"level_4":true,"level_3":true,"level_2":true,"level_1":true,"level_0":true,"edit_others_pages":true,"edit_published_pages":true,"publish_pages":true,"delete_pages":true,"delete_others_pages":true,"delete_published_pages":true,"delete_posts":true,"delete_others_posts":true,"delete_published_posts":true,"delete_private_posts":true,"edit_private_posts":true,"read_private_posts":true,"delete_private_pages":true,"edit_private_pages":true,"read_private_pages":true,"delete_users":true,"create_users":true,"unfiltered_upload":true,"edit_dashboard":true,"update_plugins":true,"delete_plugins":true,"install_plugins":true,"update_themes":true,"install_themes":true,"update_core":true,"list_users":true,"remove_users":true,"promote_users":true,"edit_theme_options":true,"delete_themes":true,"export":true,"view_shop_reports":true,"view_shop_sensitive_data":true,"export_shop_reports":true,"manage_shop_discounts":true,"manage_shop_settings":true,"edit_product":true,"read_product":true,"delete_product":true,"edit_products":true,"edit_others_products":true,"publish_products":true,"read_private_products":true,"delete_products":true,"delete_private_products":true,"delete_published_products":true,"delete_others_products":true,"edit_private_products":true,"edit_published_products":true,"manage_product_terms":true,"edit_product_terms":true,"delete_product_terms":true,"assign_product_terms":true,"view_product_stats":true,"import_products":true,"edit_shop_payment":true,"read_shop_payment":true,"delete_shop_payment":true,"edit_shop_payments":true,"edit_others_shop_payments":true,"publish_shop_payments":true,"read_private_shop_payments":true,"delete_shop_payments":true,"delete_private_shop_payments":true,"delete_published_shop_payments":true,"delete_others_shop_payments":true,"edit_private_shop_payments":true,"edit_published_shop_payments":true,"manage_shop_payment_terms":true,"edit_shop_payment_terms":true,"delete_shop_payment_terms":true,"assign_shop_payment_terms":true,"view_shop_payment_stats":true,"import_shop_payments":true,"edit_shop_discount":true,"read_shop_discount":true,"delete_shop_discount":true,"edit_shop_discounts":true,"edit_others_shop_discounts":true,"publish_shop_discounts":true,"read_private_shop_discounts":true,"delete_shop_discounts":true,"delete_private_shop_discounts":true,"delete_published_shop_discounts":true,"delete_others_shop_discounts":true,"edit_private_shop_discounts":true,"edit_published_shop_discounts":true,"manage_shop_discount_terms":true,"edit_shop_discount_terms":true,"delete_shop_discount_terms":true,"assign_shop_discount_terms":true,"view_shop_discount_stats":true,"import_shop_discounts":true,"view_licenses":true,"manage_licenses":true,"delete_licenses":true,"satispress_download_packages":true,"satispress_view_packages":true,"satispress_manage_options":true,"view_affiliate_reports":true,"export_affiliate_data":true,"export_referral_data":true,"export_customer_data":true,"export_visit_data":true,"export_payout_data":true,"manage_affiliate_options":true,"manage_affiliates":true,"manage_referrals":true,"manage_customers":true,"manage_visits":true,"manage_creatives":true,"manage_payouts":true,"manage_consumers":true,"administrator":true},"filter":null},{"data":{"ID":"446","user_login":"tdrayson","user_pass":"$P$BAzgiwUb24\\/MKzKAgJgVqAJ2LUGZAU1","user_nicename":"tdrayson","user_email":"taylor@thecreativetinker.com","user_url":"","user_registered":"2020-06-14 11:38:35","user_activation_key":"1709031306:$P$B5ZblHUozZdhIpF.S\\/4Wso0XHz1h1k.","user_status":"0","display_name":"Taylor Drayson","spam":"0","deleted":"0"},"ID":446,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null},{"data":{"ID":"795","user_login":"1wpdev","user_pass":"$P$BBk\\/OKGz2rgVe13ACxvahbVKz25y6D1","user_nicename":"1wpdev","user_email":"help@1wp.dev","user_url":"","user_registered":"2023-03-25 22:40:51","user_activation_key":"","user_status":"0","display_name":"Vasilii Leitman","spam":"0","deleted":"0"},"ID":795,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null}]`,
  },
  // 13
  {
    defaultSelect: '6',
    defaultStylised: 'blockstudio-child',
    defaultMultiple: ['blockstudio-child'],
    index: 3,
    indexStylised: 2,
    valueSelect: '7',
    valueStylised: 'fabrikat',
    data: `"populateOnlyQueryTerm":{"term_id":7,"name":"fabrikat","slug":"fabrikat","term_group":0,"term_taxonomy_id":7,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"}`,
    dataMultiple: `"populateOnlyQueryTerm":[{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"},{"term_id":8,"name":"Backlog","slug":"backlog","term_group":0,"term_taxonomy_id":8,"taxonomy":"blockstudio-project-status","description":"","parent":0`,
  },
];

const valuesCheckbox = [
  // 0
  {
    index: [1],
    checked: 4,
    data: `"defaultNumberBothWrongDefault":[{"value":1,"label":"One"},{"value":2,"label":"Two"},{"value":3,"label":"Three"}]`,
  },
  // 1
  {
    index: [3, 2],
    checked: 4,
    data: `"defaultValueLabel":["One","Two","Three"]`,
  },
  // 2
  {
    index: [2, 1],
    checked: 3,
    data: `"defaultNumberArray":[1,2,3]`,
  },
  // 3
  {
    index: [1, 2],
    checked: 3,
    data: `"defaultNumberArrayBoth":[{"value":1,"label":1},{"value":2,"label":2},{"value":3,"label":3}]`,
  },
  // 4
  {
    index: [2, 1],
    checked: 1,
    data: `"defaultValueStringNumber":["1"]`,
  },
  // 5
  {
    index: [1, 2, 3],
    checked: 2,
    data: `"defaultValueStringNumberBoth":[{"value":"1","label":"1"},{"value":"2","label":"2"}]`,
  },
  // 6
  {
    index: [3, 1],
    checked: 3,
    data: `"populateQuery":["one","three",{"ID":1388,"post_author":"1","post_date":"2022-07-09 06:38:07","post_date_gmt":"2022-07-09 06:38:07","post_content":"","post_title":"Native Render","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"native-render","to_ping":"","pinged":"","post_modified":"2022-07-09 06:38:16","post_modified_gmt":"2022-07-09 06:38:16","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1388","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]`,
  },
  // 7
  {
    index: [8, 6, 1],
    checked: 4,
    data: `"populateQueryIdBefore":[1483,1388,"one","three"]`,
  },
  // 8
  {
    index: [1, 5],
    checked: 3,
    data: `"populateCustom":["one","three","custom-2"]`,
  },
  // 9
  {
    index: [5, 2],
    checked: 3,
    data: `"populateCustomArrayBeforeBoth":[{"value":"custom-2","label":"custom-2"},{"value":"two","label":"two"},{"value":"three","label":"three"}]`,
  },
  // 10
  {
    index: [3, 2],
    checked: 3,
    data: `"populateCustomOnly":["custom-1","custom-2","custom-3"]`,
  },
  // 11
  {
    index: [5, 2],
    checked: 1,
    data: `"populateOnlyQuery":[{"ID":1099,"post_author":"1","post_date":"2022-02-24 21:41:14","post_date_gmt":"2022-02-24 21:41:14","post_content":"","post_title":"Reusable","post_excerpt":"","post_status":"publish","comment_status":"closed","ping_status":"closed","post_password":"","post_name":"reusable","to_ping":"","pinged":"","post_modified":"2022-03-12 20:08:00","post_modified_gmt":"2022-03-12 20:08:00","post_content_filtered":"","post_parent":0,"guid":"https:\\/\\/fabrikat.local\\/blockstudio\\/?p=1099","menu_order":0,"post_type":"post","post_mime_type":"","comment_count":"0","filter":"raw"}]`,
  },
  // 12
  {
    index: [3, 2],
    checked: 1,
    data: `"populateOnlyQueryUser":[{"data":{"ID":"704","user_login":"aaronkessler.de","user_pass":"$P$B0Z.hthHuRkxANiork157MC9J1Z5H5\\/","user_nicename":"aaronkessler-de","user_email":"mail@aaronkessler.de","user_url":"","user_registered":"2022-03-15 15:32:00","user_activation_key":"","user_status":"0","display_name":"Aaron Kessler","spam":"0","deleted":"0"},"ID":704,"caps":{"subscriber":true},"cap_key":"wp_309_capabilities","roles":["subscriber"],"allcaps":{"read":true,"level_0":true,"subscriber":true},"filter":null}]`,
  },
  // 13
  {
    index: [3, 4],
    checked: 3,
    data: `"populateOnlyQueryTerm":[{"term_id":6,"name":"blockstudio-child","slug":"blockstudio-child","term_group":0,"term_taxonomy_id":6,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"},{"term_id":7,"name":"fabrikat","slug":"fabrikat","term_group":0,"term_taxonomy_id":7,"taxonomy":"wp_theme","description":"","parent":0,"count":1,"filter":"raw"},{"term_id":4,"name":"file_download","slug":"file_download","term_group":0,"term_taxonomy_id":4,"taxonomy":"edd_log_type","description":"","parent":0,"count":235,"filter":"raw"}]`,
  },
];

let page: Page;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  await page.goto(
    `https://fabrikat.local/blockstudio/wp-admin/post.php?post=1483&action=edit`
  );
  await page.fill('#user_login', 'testuser');
  await page.fill('#user_pass', 'testuser');
  await page.click('#wp-submit');
  const modal = await page.$('text=Welcome to the block editor');
  if (modal) {
    await page.click(
      '.components-modal__screen-overlay .components-button.has-icon'
    );
  }
  await count(page, '.is-root-container', 1);
  await removeBlocks(page);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('option block types', () => {
  Object.keys(blockMap).forEach((block) => {
    test.describe(`${block}`, () => {
      test.describe.configure({ mode: 'serial' });

      test('open block inserter', async () => {
        await page.click('.editor-document-tools__inserter-toggle');
        await count(page, '.block-editor-inserter__block-list', 1);
      });

      test('add block', async () => {
        await page.click(`.editor-block-list-item-blockstudio-type-${block}`);
        await count(page, '.is-root-container > .wp-block', 1);
      });

      test('has correct defaults', async () => {
        await text(page, defaults[block]);
      });

      if (block.includes('select')) {
        test('reset select', async () => {
          await page.click('.is-root-container > .wp-block');
          await openSidebar(page);
          await count(page, 'text=Reset me', 0);
          if (block === 'select' || block === 'repeater-select') {
            const el = page
              .locator(
                `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-select-control__input`
              )
              .nth(1);
            await el.selectOption({ index: valuesSelect[1].index - 1 });
          }
          if (
            block === 'select-stylised' ||
            block === 'repeater-select-stylised'
          ) {
            const button = page
              .locator(
                `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-combobox-control__input`
              )
              .nth(1);
            await button.click();
            await page
              .locator(
                `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-form-token-field__suggestion`
              )
              .nth(valuesSelect[1]?.indexStylised)
              .click();
          }
          if (
            block === 'select-multiple' ||
            block === 'repeater-select-multiple'
          ) {
            const button = page
              .locator(
                `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-form-token-field__input`
              )
              .nth(1);
            await button.click();
            await page
              .locator(
                `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-form-token-field__suggestion`
              )
              .nth(0)
              .click();
            await delay(2000);
          }
          await count(page, 'text=Reset me', 1);
          await page.click('text=Reset me');
          await count(page, 'text=Reset me', 0);
          await text(page, defaults[block]);
        });
      }

      test.describe(`default attribute fields`, async () => {
        test.beforeAll(async () => {
          await page.click('.is-root-container > .wp-block');
          await openSidebar(page);
        });

        valuesSelect.forEach((_, index) => {
          if (index === 12) return;

          test(`default input ${index}`, async () => {
            if (block === 'select' || block === 'repeater-select') {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-select-control__input`
                )
                .nth(index);
              await expect(el).toHaveValue(valuesSelect[index].defaultSelect);
            }
            if (
              block === 'select-stylised' ||
              block === 'repeater-select-stylised'
            ) {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-combobox-control__input`
                )
                .nth(index);
              await expect(el).toHaveValue(valuesSelect[index].defaultStylised);
            }
            if (
              block === 'select-multiple' ||
              block === 'repeater-select-multiple'
            ) {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]}`
                )
                .nth(index);

              for (const value of valuesSelect[index].defaultMultiple) {
                const i = valuesSelect[index].defaultMultiple.indexOf(value);
                const card = await el
                  .locator(`[data-rfd-draggable-context-id]`)
                  .nth(i);
                await expect(card).toHaveText(value);
              }
            }
            if (block === 'radio' || block === 'repeater-radio') {
              if (index >= 1) {
                const el = await page
                  .locator(
                    `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-radio-control__input:checked`
                  )
                  .nth(index - 1);
                await expect(el).toHaveValue(
                  valuesSelect[index]?.defaultRadio ||
                    valuesSelect[index].defaultSelect
                );
              }
            }
            if (
              block === 'radio-button-group' ||
              block === 'repeater-radio-button-group'
            ) {
              if (index >= 1) {
                const el = await page
                  .locator(
                    `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-button.is-primary`
                  )
                  .nth(index - 1);
                await expect(el).toHaveText(
                  valuesSelect[index]?.defaultRadio ||
                    valuesSelect[index].defaultStylised
                );
              }
            }
            if (block === 'checkbox' || block === 'repeater-checkbox') {
              if (index >= 1) {
                const el = await page
                  .locator(
                    `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-checkbox-control__input:checked`
                  )
                  .nth(index - 1);
                await expect(el).toHaveCount(1);
              }
            }
          });
        });
      });

      test.describe(`change attribute`, () => {
        valuesSelect.forEach((_, index) => {
          if (index === 12) return;

          test(`${index}`, async () => {
            await delay(500);
            if (block === 'select' || block === 'repeater-select') {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-select-control__input`
                )
                .nth(index);
              await el.selectOption({ index: valuesSelect[index].index - 1 });
            }
            if (
              block === 'select-stylised' ||
              block === 'repeater-select-stylised'
            ) {
              const button = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-combobox-control__input`
                )
                .nth(index);
              await button.click();
              await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-form-token-field__suggestion`
                )
                .nth(valuesSelect[index]?.indexStylised)
                .click();
            }
            if (
              block === 'select-multiple' ||
              block === 'repeater-select-multiple'
            ) {
              const button = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-form-token-field__input`
                )
                .nth(index);
              await button.click();
              await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-form-token-field__suggestion`
                )
                .nth(0)
                .click();
              await delay(2000);
            }
            if (block === 'radio') {
              await page
                .locator(
                  `.blockstudio-fields .components-panel__body:nth-of-type(${
                    index + 1
                  }) .components-radio-control__option:nth-of-type(${
                    valuesSelect[index].index === 4
                      ? 3
                      : valuesSelect[index].index
                  }) .components-radio-control__input`
                )
                .click();
            }
            if (block === 'repeater-radio') {
              await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--radio`
                )
                .nth(index)
                .locator(
                  `.components-radio-control__option:nth-of-type(${
                    valuesSelect[index].index === 4
                      ? 3
                      : valuesSelect[index].index
                  }) .components-radio-control__input`
                )
                .click();
            }
            if (block === 'radio-button-group') {
              await page
                .locator(
                  `.blockstudio-fields .components-panel__body:nth-of-type(${
                    index + 1
                  }) .components-button:nth-of-type(${
                    valuesSelect[index].index === 4
                      ? 3
                      : valuesSelect[index].index
                  })`
                )
                .click();
            }
            if (block === 'repeater-radio-button-group') {
              await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--radio`
                )
                .nth(index)
                .locator(
                  `.components-button:nth-of-type(${
                    valuesSelect[index].index === 4
                      ? 3
                      : valuesSelect[index].index
                  })`
                )
                .click();
            }
            if (block === 'checkbox') {
              for (const checkbox of valuesCheckbox[index].index) {
                await page
                  .locator(
                    `.blockstudio-fields .components-panel__body:nth-of-type(${
                      index + 1
                    }) .components-checkbox-control:nth-of-type(${checkbox}) .components-checkbox-control__input`
                  )
                  .click();
              }
            }
            if (block === 'repeater-checkbox') {
              for (const checkbox of valuesCheckbox[index].index) {
                await page
                  .locator(
                    `.blockstudio-fields .blockstudio-fields__field--checkbox`
                  )
                  .nth(index)
                  .locator(
                    `.components-checkbox-control:nth-of-type(${checkbox}) .components-checkbox-control__input`
                  )
                  .click();
              }
            }
          });
        });

        test.describe(`test attribute text`, () => {
          test.beforeAll(async () => {
            await delay(2000);
          });

          valuesSelect.forEach((attribute, index) => {
            if (index === 12) return;

            test(`${index}`, async () => {
              if (block === 'checkbox' || block === 'repeater-checkbox') {
                await text(page, valuesCheckbox[index].data);
              } else {
                await text(
                  page,
                  block === 'select-stylised' ||
                    block === 'repeater-select-stylised'
                    ? valuesSelect[index]?.dataStylised ||
                        valuesSelect[index]?.data
                    : block === 'select-multiple' ||
                      block === 'repeater-select-multiple'
                    ? valuesSelect[index]?.dataMultiple
                    : valuesSelect[index]?.data
                );
              }
            });
          });
        });

        test('save', async () => {
          await page.click('.editor-post-publish-button');
          await count(page, 'text=View post', 1);
          const save = async () => {
            await page.goto(
              'https://fabrikat.local/blockstudio/wp-admin/post.php?post=1483&action=edit'
            );
            await delay(20000);
            const block = await page.$('.is-root-container > .wp-block');
            if (!block) {
              await save();
            } else {
              await page.click('.is-root-container > .wp-block');
            }
          };
          await save();
        });

        valuesSelect.forEach((_, index) => {
          if (index === 12) return;

          test(`check block value ${index}`, async () => {
            if (block === 'checkbox' || block === 'repeater-checkbox') {
              await text(page, valuesCheckbox[index].data);
            } else {
              await text(
                page,
                block === 'select-stylised' ||
                  block === 'repeater-select-stylised'
                  ? valuesSelect[index]?.dataStylised ||
                      valuesSelect[index]?.data
                  : block === 'select-multiple' ||
                    block === 'repeater-select-multiple'
                  ? valuesSelect[index]?.dataMultiple
                  : valuesSelect[index]?.data
              );
            }
          });
          test(`check block input ${index}`, async () => {
            if (block === 'select' || block === 'repeater-select') {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-select-control__input`
                )
                .nth(index);
              await expect(el).toHaveValue(valuesSelect[index].valueSelect);
            }

            if (
              block === 'select-stylised' ||
              block === 'repeater-select-stylised'
            ) {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-combobox-control__input`
                )
                .nth(index);
              await expect(el).toHaveValue(valuesSelect[index].valueStylised);
            }

            if (
              block === 'select-multiple' ||
              block === 'repeater-select-multiple'
            ) {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]}`
                )
                .nth(index);
              const cards = await el.locator('[data-rfd-draggable-context-id]');
              await expect(cards).toHaveCount(
                valuesSelect[index].defaultMultiple.length + 1
              );
            }

            if (block === 'radio' || block === 'repeater-radio') {
              const el = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--${blockMap[block]} .components-radio-control__input[checked]`
                )
                .nth(index);
              await expect(el).toHaveValue(valuesSelect[index].valueSelect);
            }

            if (block === 'checkbox') {
              const els = await page.locator(
                `.blockstudio-fields .components-panel__body:nth-of-type(${
                  index + 1
                }) .components-checkbox-control__input[checked]`
              );
              await expect(els).toHaveCount(valuesCheckbox[index].checked);
            }

            if (block === 'repeater-checkbox') {
              const els = await page
                .locator(
                  `.blockstudio-fields .blockstudio-fields__field--checkbox`
                )
                .nth(index)
                .locator(`.components-checkbox-control__input[checked]`);
              await expect(els).toHaveCount(valuesCheckbox[index].checked);
            }
          });
        });

        test('remove block', async () => {
          await removeBlocks(page);
        });
      });
    });
  });
});
