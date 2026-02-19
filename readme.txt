=== blockstudio ===
Contributors: dnnsjsk
Requires at least: 5.0
Tested up to: 7.0.0
Requires PHP: 8.2
Stable tag: 7.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The block framework for WordPress.

== Description ==

Create custom WordPress blocks by dropping a `block.json` and a PHP template into a folder. No JavaScript, no React, no build step.

= Features =

* **26 field types** including text, repeater, tabs, classes, color, files, and more
* **PHP, Twig, and Blade templates** with the same `$a` variable across all languages
* **File-based pages** that parse HTML templates into native block content with automatic syncing
* **File-based patterns** registered from template files without any PHP registration code
* **Extensions** to add custom fields to any core or third-party block via JSON
* **Tailwind CSS v4** compiled server-side via TailwindPHP with automatic caching
* **Storage** to persist field values in post meta or site options, queryable via WP_Query and REST API
* **Asset pipeline** with SCSS compilation, ES module imports from npm, and automatic minification
* **Scoped styles** that only load when a block is on the page
* **HTML-to-block parser** with a trait-based renderer architecture and custom element mapping
* **SEO integration** with Yoast SEO and Rank Math content analysis
* **50+ PHP and JS hooks** for customizing every aspect of the framework

= Installation =

**Composer (recommended):**

`composer require blockstudio/blockstudio`

Composer installations receive updates through Composer. The built-in updater is automatically disabled.

**Manual:**

Download the latest release zip from [GitHub Releases](https://github.com/inline0/blockstudio/releases), upload to your plugins directory, and activate. Manual installations receive automatic update notifications in the WordPress dashboard.

= Requirements =

* PHP 8.2+
* WordPress 6.7+

= Links =

* [Documentation](https://app.blockstudio.dev/docs)
* [GitHub](https://github.com/inline0/blockstudio)
* [Getting Started](https://app.blockstudio.dev/docs/getting-started)

== Changelog ==

= 7.0.0 (next) =
* New: Blockstudio is now free and open source
* New: automatic update notifications via GitHub releases (disabled when installed via Composer)
* New: file-based pages - create WordPress pages from file templates with automatic HTML to block parsing
* New: file-based patterns - create WordPress block patterns from file templates with automatic HTML to block parsing
* New: storage feature to store field values in post meta or options
* New: Tailwind CSS v4 with server-side compilation via TailwindPHP, candidate-based caching, and CSS-first configuration
* New: Interactivity API support with editor hydration (set `interactivity: true` in blockstudio config)
* New: custom fields - reusable field definitions via file system and PHP filter
* New: SEO content analysis integration. Blockstudio block content is now visible to Yoast SEO and Rank Math editor analysis
* New: complete codebase refactor
* New: repeater default rows - pre-fill repeater fields with a `default` array of row data on block insert
* New: canvas view with live mode, focus mode, blocks view, and SSE-based instant file change detection
* New: code field popout option to open editor in separate window
* New: devtools grabber for copying block template paths on the frontend (hold Cmd+C)
* New: assets/reset setting to remove WordPress core block styles on the frontend and in the editor
* Enhancement: apiVersion 3 support. Blocks now render correctly in the iframed editor (site editor, responsive previews)
* Enhancement: all PHP hooks now use snake_case naming (old camelCase names still supported)
* Enhancement: compiled Tailwind CSS now injected into the block editor alongside the CDN
* Enhancement: repeater `min` now generates rows with field-level defaults on both frontend and editor
* Enhancement: field switch is now off by default and uses a visible eye icon with disabled overlay
* Enhancement: dot notation for asset file suffixes (*.inline.css, *.editor.css, etc.). Dash notation still supported
* Enhancement: AI context file rebuilt as a static build from documentation and schemas (~48k tokens)
* Enhancement: Tailwind CSS compilation filters out numeric candidates to prevent TailwindPHP crashes
* Fix: undo no longer triggers API calls or spinners on any blocks (render cache persists across remounts)
* Fix: field changes no longer create individual undo checkpoints, preventing full block reload on undo
* Fix: block preloading now uses index-based matching instead of hash-based, fixing cache misses from custom field defaults
* Fix: context changes on mount no longer trigger unnecessary refetches
* Fix: code field undo/redo now works independently without triggering Gutenberg undo
* Fix: bs_render_block() no longer triggers a warning from WP_Block_Supports when using useBlockProps
* Fix: conditional fields not respecting default values on first load
* Fix: dollar signs stripped from <RichText /> content on frontend
* Fix: populate feature now works with taxonomies registered by other plugins
* Fix: optionsPopulate null error in WPML contexts
* Fix: JavaScript syntax highlighting now works without requiring script tags
* Deprecation: Admin Area removed
* Deprecation: Code Editor removed
* Deprecation: License Key removed
* Deprecation: Block library removed. The built-in element blocks (Gallery, Slider, Image Comparison, Code, Icon) are no longer included

= 6.0.2 (18.08.2025) =
* Enhancement: also preload blocks inside site editor
* Enhancement: better Twig error messages
* Fix: Twig paths error
* Fix: long loading times in site editor
* Fix: single block.json resulting in error in editor

= 6.0.1 (11.06.2025) =
* Fix: set required PHP header in plugin file
* New: add 'description' for fields

= 6.0.0 (12.05.2025) =
* Breaking: built-in modules rendering templates are now PHP files
* Breaking: don't include Timber
* Breaking: new context engine
* Breaking: scope matthiasmullie/minify
* Breaking: scope scssphp/scssphp
* Breaking: set minimum PHP version to 8.2
* Breaking: update Tailwind to version 4
* Breaking: update scssphp/scssphp to 2.0
* Enhancement: Improve plugin initialization to prevent conflicts when loaded multiple times
* Enhancement: minor UI fixes
* Enhancement: reduce zipped plugin file by 25%
* Enhancement: remove regex hacks to fix scssphp 1.x behaviour
* Enhancement: update all @wordpress package dependencies
* Fix: CSS files not imported in JS files when not ending in a semicolon
* Fix: child blocks not always updating on context changes
* Fix: truncate path properly in header
* New: Blade support
* New: contextual llm.txt generation for use with AI code tools
* New: no-fetch block preloading for blocks without extensions

= 5.6.15 (19.03.2025) =
* Enhancement: don't emit block/rendered event when loading is disabled
* Enhancement: emit block/loaded event when loading is disabled and block is clicked
* Fix: Remix icons not available/working
* Fix: rendering template resetting to old values

= 5.6.14 (22.01.2025) =
* Fix: minify JS bundles

= 5.6.13 (22.01.2025) =
* Enhancement: updated batch load engine
* Fix: 'false' displaying at end of preview
* Fix: 'false' not working inside conditions in edge cases
* Fix: more deprecation notices

= 5.6.12 (13.01.2025) =
* Fix: Tailwind only enabled/disabled in editor after reloading
* Fix: Tailwind only working in editor when "formatOnSave" was enabled
* Fix: Tailwind potentially not compiling styles on some hosts (switched to WP_Filesystem)
* Fix: checkbox/multiple select values not sorting correctly when populating with custom function
* Fix: trim() deprecation notice

= 5.6.11 (18.11.2024) =
* Fix: data attributes not applying when setting them via extensions
* Fix: save button not unlocking when typing inside RichText for the first time
* Fix: use .scss files for Blockstudio library

= 5.6.10 (12.11.2024) =
* Fix: anchor not saving
* Fix: undefined array key field error

= 5.6.9 (08.11.2024) =
* Enhancement: exporting single files will now download them instead of creating a zip
* Fix: defaults not being applied on extended attributes when adding them as template in InnerBlocks
* Fix: save button not unlocking when typing inside RichText for the first time
* New: 'blockstudio/assets/enable' filter to enable/disable processing and enqueuing of assets

= 5.6.8 (01.11.2024) =
* Fix: files can't be saved if they only contain PHP without closing bracket

= 5.6.7 (31.10.2024) =
* Fix: RichText not working properly when post is a draft
* Fix: don't register Tailwind store twice

= 5.6.6 (19.10.2024) =
* Enhancement: add search icon to file list search
* Fix: console error when using extensions
* Fix: id missing when using extensions

= 5.6.5 (05.10.2024)
* Enhancement: load all blocks initially in a single fetch call
* Fix: :root being prefixed with .editor-styles-wrapper in editor styles
* Fix: CSS not autocompleting anymore in code field
* Fix: block crashing when removing attribute in extensions

= 5.6.4 (25.08.2024) =
* Fix: RichText attributes not parsing correctly

= 5.6.3 (22.08.2024) =
* Fix: anchor not working in supports
* Fix: classname not working in supports

= 5.6.2 (21.08.2024) =
* Fix: RichText values resetting when block is being removed

= 5.6.1 (20.08.2024) =
* Fix: Remove Blade

= 5.6.0 (20.08.2024) =
* Breaking: deprecate Builder elements
* Breaking: deprecate preloader
* Enhancement: extensions can be now set on any attribute
* New: 'blockstudio/assets/process/css/content' filter to adjust CSS assets before compile steps
* New: 'blockstudio/assets/process/js/content' filter to adjust JS content before compile steps
* New: ability to sort Extensions
* New: attributes field
* New: automatic template insertion for attributes in editor
* New: classes field
* New: setting to add stylesheets whose CSS classes should be available for choice in the class field
* New: setting to add stylesheets whose CSS variables should be available for autocompletion in the code field

= 5.5.9 (07.08.2024) =
* Fix: editor rendering blocking when using a lot of extensions
* Fix: replace mb_convert_encoding function

= 5.5.8 (30.07.2024) =
* Fix: MediaPlaceholder not working when inside an element with useBlockProps
* Fix: resolved an issue where the spread operator (...) was incorrectly used to unpack associative arrays (better PHP 8 compatibility)

= 5.5.7 (21.07.2024) =
* Enhancement: mark setAttribute calls for defaults as not persistent
* Enhancement: update defaults handling, prevent synced patterns crashing
* Fix: certain labels not being truncated in editor
* Fix: minimized repeater rows not populating default values if inserted again
* Fix: move assets enqueuing to wp_enqueue_scripts hook
* Fix: prevent attributes setting multiple times even if they are the same

= 5.5.6 (08.07.2024) =
* Fix: extension styles not being applied correctly when applying other core styles
* Fix: extension styles not being applied when site editor is iframed
* Fix: Tailwind styles potentially not being generated on some hosts

= 5.5.5 (13.06.2024) =
* Fix: Blocks potentially registering multiple times
* Fix: allow wildcards in arrays when using extensions

= 5.5.4 (08.06.2024) =
* Fix: context not working correctly
* Fix: useBlockProps not passing block classes

= 5.5.3 (04.06.2024) =
* Enhancement: better preflight (Builder)
* Fix: Update Timber to 1.24.1
* Fix: code field asset mode not working correctly inside repeater
* Fix: prevent media files from being fetched multiple times when using files field
* New: 'index' & 'indexTotal' in $block variable to check count of blocks
* New: Element block (Builder)
* New: custom tags (Builder)
* New: paste structures (Builder)
* New: populate for 'color' and 'gradient' fields (custom & function only)
* New: possibility to add custom Tailwind classes
* New: use link or media as attribute values (Builder)

= 5.5.2 (15.05.2024) =
* Fix: 'initialOpen' attribute not working for group field
* Fix: code field assets mode not working inside the site editor

= 5.5.1 (14.05.2024) =
* Fix: code field assets mode not working when the block editor is inside iframe
* Fix: scope %selector% code field styles to root container
* New: Builder elements
* New: possibility to load block by clicking when loading is disabled

= 5.5.0 (13.05.2024) =
* Enhancement: better error handling for code field
* Enhancement: updated settings screen for better UX
* Fix: multiple semicolons potentially rendering when using extensions
* Fix: path inside global assets ids
* New: 'asset' attribute for code field
* New: 'mode' attribute for WYSIWYG field (visual, code or both)
* New: Ability to disable loading of blocks in Block Editor
* New: Tailwind support
* New: admin only assets
* New: block editor only assets
* New: dynamic %selector% support in code field

= 5.4.8 (05.05.2024) =
* Fix: blocks can't be selected at first in the editor when using useBlockProps
* New: experimental preload mode to load blocks without fetching when possible on the first load

= 5.4.7 (13.04.2024) =
* Fix: plugin version

= 5.4.6 (13.04.2024) =
* Fix: Variations not showing up since 5.5
* New: 'allowReset' property to reset select field to default value
* New: 'bs_attributes' function to get block attributes without echoing
* New: 'bs_variables' function to get block variables without echoing

= 5.4.5 (19.03.2024) =
* Fix: PHP 8.3 compatibility
* Fix: deleting extension in editor would delete all other files
* New: 'blockstudio/blocks/components/useblockprops/render' filter

= 5.4.4 (02.03.2024) =
* Fix: defaults not working correctly when setting it for extensions inside InnerBlocks templates

= 5.4.3 (29.02.2024) =
* Enhancement: don't render style and class tags if values are empty
* Enhancement: use custom block renderer inside Gutenberg, fixes loading issues caused by other plugins
* Fix: Blocks not loading after being renamed in Gutenberg
* Fix: defaults not working correctly when setting it for extensions inside InnerBlocks templates
* New: blockstudio/namespace/my-block/rendered JS event

= 5.4.2 (20.02.2024) =
* Fix: array values behaving correctly in extensions
* Fix: resize panel showing when preview button is off
* New: 'group' option to define position of block extensions

= 5.4.1 (19.02.2024) =
* Fix: PHP 8.2 error

= 5.4.0 (19.02.2024) =
* Enhancement: tabs can now be nested
* Fix: RichText losing state and resetting to previous value when using CMD + A and Backspace
* Fix: attribute values being overwritten when using RichText
* Fix: editor not changing tab after renaming file
* Fix: fatal error when populating non-existing taxonomy
* Fix: init.php saving returning error inside editor in edge cases
* Fix: resize panel showing in Editor when editing blocks from Gutenberg
* New: JS event for tab changes
* New: block extensions
* New: code field

= 5.3.2 (01.02.2024) =
* Enhancement: calculations in clamp don't have to be unquoted anymore when using SCSS
* Enhancement: don't include test files
* Enhancement: don't log PHP errors when using editor
* Enhancement: scoped styles will now be precompiled as files
* Enhancement: use SCSSPHP for prefixing CSS
* Fix: color field rendering object when using HTML utility helper
* Fix: don't show _dist folder in root folders inside editor file view
* Fix: various PHP notices

= 5.3.1 (10.01.2024) =
* Fix: file_get_content calls on folders

= 5.3.0 (07.01.2024) =
* Enhancement: better accessibility for drag and drop components
* Enhancement: files field can now be reordered using arrow keys
* Enhancement: repeater rows can now be reordered using arrow keys
* Enhancement: select with multiple can now be reordered using arrow keys
* Fix: styles being overridden in Group field
* New: MediaPlaceholder component
* New: Message field
* New: Overrides
* New: Unit field
* New: possibility to fetch options from an external API/URL in select field

= 5.2.21 (20.12.2023) =
* Fix: new CSS units compiling incorrectly in editor
* Fix: refreshOn 'save' not triggering
* Fix: refreshOn 'save' not working when nested inside other blocks

= 5.2.20 (19.12.2023) =
* Enhancement: ability to resize code/preview panes in editor
* Enhancement: block IDs not unique on frontend
* Enhancement: exported .zip file name will now include block names
* Fix: 'innerBlocks' defined in 'example' block.json not rendering inside block preview
* Fix: arrays and objects not rendering values when using the bs_render_attributes function
* Fix: exported nested folders adding full file paths in .zip
* New: 'blockstudio/assets/process/scss/importPaths' filter to add custom import paths
* New: 'blockstudio/render' filter to adjust final page output
* New: 'blockstudio/render/footer' filter to adjust footer output
* New: 'blockstudio/render/head' filter to adjust head output

= 5.2.19 (08.12.2023) =
* Enhancement: allow PHP autocompletions on multiple lines
* Enhancement: load CSS from NPM in header before block assets
* Fix: blocks not loading in editor when using context and not specifying parent
* Fix: same autocompletions in editor showing multiple times

= 5.2.18 (28.11.2023) =
* Fix: PHP 8.2 deprecation notices
* Fix: editor failing to load in edge cases

= 5.2.17 (23.11.2023) =
* Fix: select field not working correctly when using fetch and multiple together

= 5.2.16 (13.11.2023) =
* Enhancement: add current file name as placeholder to text input when renaming file/folder
* Fix: CSS files imported inside .js files not enqueuing in editor
* Fix: allow right-clicking currently active file in file list
* Fix: don't allow spaces in asset IDs
* Fix: old data persisting in editor after deleting block and creating new block with the same name
* Fix: rename modal not closing after renaming file/folder in edge cases
* Fix: save modal appearing after deleting block and trying to close editor

= 5.2.15 (09.11.2023) =
* Fix: race condition when inserting blocks with defaults inside InnerBlocks
* Fix: repeater element disappearing from the screen when dragging

= 5.2.14 (08.11.2023) =
* Enhancement: allow setting attributes in block.json
* Fix: assets not prefixing correctly

= 5.2.13 (06.11.2023)
* Fix: version

= 5.2.12 (06.11.2023) =
* Enhancement: allow field label and help text to be translated
* Enhancement: recompile SCSS files when imports have changed
* Enhancement: use 'str_starts_with' and 'str_ends_with' functions when available
* Fix: .scss not compiling correctly when using the Editor in Gutenberg
* Fix: Blocks failing to load in Gutenberg when using 'useBlockProps' on self-closing HTML elements
* Fix: Blocks failing to load in Gutenberg when using inline styles on RichText, InnerBlocks or elements marked with 'useBlockProps'
* Fix: additional element attributes missing in Gutenberg when using 'useBlockProps'
* Fix: compiled assets unnecessary being deleted and recompiled when saving block in Editor
* Fix: defaults not working for WYSIWYG field
* Fix: fatal error on Windows
* Fix: match self-closing InnerBlocks without spaces
* Fix: remove 'sabberworm/php-css-parser' dependency and replace with custom CSS prefixer
* New: import CSS files from NPM packages

= 5.2.11 (21.10.2023) =
* Fix: Settings class initializing too late for plugins
* Fix: potential stale data after renaming file in editor
* New: 'blockstudio/init/before' hook

= 5.2.10 (19.10.2023) =
* Enhancement: automatically clean up old processed assets and unused ES modules from _dist folder
* Enhancement: don't enqueue assets if they are empty
* Fix: compiled .css file not enqueueing correctly when using .scss extension
* Fix: don't return fatal error if SCSS compiling fails
* Fix: npm packages with @ in package name not being fetched correctly
* Fix: settings explicitly set to 'false' with filter not disabled in admin
* New: relative @imports when using SCSS
* New: setting to disable .scss file processing

= 5.2.9 (16.10.2023) =
* Enhancement: add block data to render filters
* Enhancement: allow multiple init.php files
* Enhancement: use Blockstudio events to initialise blocks in Gutenberg
* Fix: InnerBlocks initially not rendering correctly in editor when classes are set on element
* Fix: init.php files not saving in editor when missing ending brackets
* Fix: prevent NPM package preview fetching if no package name or version exists
* Fix: renderAppender behaving incorrectly when only one block is allowed in InnerBlocks
* New: 'blockstudio/settings/path' filter to change the path of the blockstudio.json file
* New: .scss file support

= 5.2.8 (03.10.2023) =
* Enhancement: assets can now be added and deleted in editor when used inside Gutenberg
* Enhancement: better UX for array based settings in admin area
* Enhancement: better security for all file operations in editor
* Enhancement: block assets in editor preview will now update accordingly when changed in settings or block.json
* Enhancement: cache assets fetching in editor
* Enhancement: close edit panel by default
* Enhancement: don't render editor-only assets in editor
* Enhancement: enqueued inline assets can be added to editor
* Enhancement: filter out 'richtext' types in editor preview
* Enhancement: load editor assets last
* Enhancement: show error message in editor preview if block template file has an error
* Enhancement: tabs can be selected by pressing enter or space when focused
* Enhancement: use $wp_filesystem for all file operations in editor
* Fix: admin styles leaking into editor
* Fix: editor/markup setting not showing in settings
* Fix: file name in tab not changing after renaming file
* Fix: frontend assets were not able to be added to editor
* Fix: settings values jumping back to previous value after changing tabs in editor
* Fix: short content width for 'New block' modal
* New: 'blockstudio/blocks/components/innerblocks/render' filter to adjust final InnerBlocks output
* New: 'blockstudio/blocks/components/richtext/render' filter to adjust final RichText output
* New: 'blockstudio/blocks/render' filter to adjust final block output
* New: possibility to add InnerBlocks data in editor

= 5.2.7 (18.09.2023) =
* Enhancement: anchor will now be added to element marked with useBlockProps if set
* Fix: anchor wasn't saving correctly
* Fix: context IDs and post type not working correctly
* Fix: endless loop inside editor when using blocks inside synced patterns

= 5.2.6 (05.09.2023) =
* Fix: prefixed styles not working in editor for nested styles

= 5.2.5 (03.09.2023) =
* Fix: wrong demo URL

= 5.2.4 (03.09.2023) =
* Enhancement: don't show spinner when editing attributes of the same block
* Enhancement: navigate file browser with arrow keys
* Fix: editor values not resetting correctly when switching blocks
* Fix: preview not working for .twig files
* New: open block in demo environment

= 5.2.3 (01.09.2023) =
* Fix: update error in edge cases

= 5.2.2 (31.08.2023)
* Fix: settings fields which allow multiple values were always disabled

= 5.2.1 (31.08.2023) =
* Fix: error when activating plugin on a fresh site

= 5.2.0 (31.08.2023) =
* Enhancement: assets registered with wp_enqueue_scripts, admin_enqueue_scripts or enqueue_block_editor_assets can now be added to the edtior
* Enhancement: attribute preview in editor will now display default values
* Enhancement: attribute preview in editor will now display populated data
* Enhancement: autocomplete CSS classes in editor for included assets and block files
* Enhancement: console will now display logs depending on the server action it does (create, delete etc.)
* Enhancement: export now allows exporting whole folders instead of just a single block
* Enhancement: import now allows importing whole folders instead of just a single block
* Enhancement: mimic add_editor_style behaviour by prefixing classes with .editor-styles-wrapper inside Gutenberg
* Enhancement: process SCSS and JS on the server
* Enhancement: remove esbuild dependency (-11mb)
* Enhancement: show full paths in editor console
* Enhancement: speed up editor loading by 90%
* Enhancement: zip files for export on the server
* Fix: blocks crashing in widgets since 6.3
* Fix: conditional logic not working correctly sometimes in groups and tabs
* Fix: don't remove 'placeholder' attribute from input and textarea elements
* Fix: editor block assets not working correctly
* Fix: files field only loading 10 files at once
* Fix: files field removing all files when deleting one that has not loaded yet
* Fix: files returning false for non images when 'returnFormat' is set to 'url'
* Fix: scoped class not working correctly in editor
* New: Settings API
* New: ability to edit blocks from Gutenberg
* New: ability to rename files in editor
* New: show NPM package information on hover in editor when using 'blockstudio/{package}@{version}' convention

= 5.1.2 (15.08.2023) =
* Fix: all files being removed when removing a single file

= 5.1.1 (07.08.2023) =
* Fix: attribute preview not changing when switching blocks
* Fix: attribute preview potentially crashing in editor
* Fix: make tested up to 6.3.0

= 5.1.0 (07.08.2023) =
* Enhancement: 'allowNull' will be used as input placeholder when using select and 'stylisedUi'
* Enhancement: add data-id to field elements
* Enhancement: options populated with function will automatically choose value and label keys if available and returnFormat not set
* Enhancement: repeater rows are not indexed
* Enhancement: use current WordPress admin theme color for all highlights in editor
* Enhancement: when populating options from a query, the default value will now correctly add the appropriate object to the options
* Fix: Checkbox field not sorting correctly when return format set to 'label'
* Fix: potential fatal error from revisions when option is not available anymore
* New: change InnerBlocks template using Select or Radio field
* New: fetch attribute for Select field
* New: multiple attribute for Select field
* New: support for Transforms API
* New: {index} can now be used to get the index for repeater rows when minimized

= 5.0.7 (24.07.2023) =
* Enhancement: fetch modules in parallel
* Enhancement: reduce zipped file size by 50%
* Enhancement: use transients for icons
* Fix: editor potentially failing to fetch more than 3 modules
* Fix: properly concatenate values for Repeater textMinimized object
* New: fallback attribute for fields
* New: hidden attribute for fields
* New: support for Variations API

= 5.0.6 (18.07.2023) =
* Enhancement: assets will not enqueue if block template returns nothing
* Fix: editor only fetching modules of changed files when updating block
* Fix: use UTF-8 encoding for RichText content

= 5.0.5 (29.06.2023) =
* Fix: blockstudio/editor/users/roles filter not working for REST requests
* Fix: loading animation not showing when .json file has been changed

= 5.0.4 (28.06.2023) =
* Fix: rename class files to lowercase due to update bug

= 5.0.2 (27.06.2023) =
* Fix: add __internalWidgetId to attributes so blocks don't crash in widget block editor
* Fix: editor content blank when Gutenberg plugin is active

= 5.0.1 (27.06.2023) =
* Fix: set tested up to 6.2.2

= 5.0.0 (27.06.2023) =
* Breaking: $c variable is now an alias to $context in all block templates
* Breaking: remove legacy ACF and MB integrations
* Enhancement: add alert dialog when deleting repeater row
* Enhancement: add loading screen in editor
* Enhancement: better error handling in editor
* Enhancement: don't register styles if blockstudio/assets filter disables enqueueing
* Enhancement: editor will now create new blocks in the selected folder
* Enhancement: move license page to admin dashboard
* Enhancement: send single request when processing SCSS in editor
* Enhancement: show folders first in editor
* Enhancement: update all included icon packs
* Enhancement: use CSS grid for Gutenberg sidebar layouts
* Fix: ES modules potentially not parsing correctly in editor
* Fix: InnerBlocks and RichText not working inside a div with useBlockProps
* Fix: RichText not working when InnerBlocks present in block
* Fix: assets not loading inside customizer
* Fix: autocompletions showing duplicate entries in editor
* Fix: blocks crashing in widgets block editor
* Fix: content resetting in editor when cutting content and switching tabs
* Fix: context not working in Twig templates
* Fix: correct editor permission when using editor/users/roles filter
* Fix: don't render component specific attributes on frontend
* Fix: editor assets not loading correctly
* Fix: icon field persisting icon value when empty after deleting repeater row
* Fix: potential preview errors in editor when code value is empty
* New: Tabs field
* New: UI routing in admin dashboard
* New: ability to add custom styles and class to Group field
* New: ability to add global assets to blocks
* New: ability to add init.php files to blocks
* New: ability to add unlimited assets to blocks
* New: ability to disable toggling fields
* New: ability to duplicate repeater rows
* New: ability to populate options from a function
* New: ability to render Radio field as button group
* New: blockstudio/init hook
* New: support for all file types and unlimited files in the editor
* New: text align in WYSIWYG field
* New: updated admin dashboard

= 4.2.0 =
* Enhancement: add file size to attributes
* Enhancement: apiVersion set by default
* Enhancement: save button will turn red if block contains an error and can't be saved
* New: RichText
* New: catch-all JS event when attribute has changed
* New: catch-all JS event when block is loaded
* New: filter to activate editor for user roles
* New: helper function to render block attributes as CSS variables in HTML
* New: helper function to render block attributes as data attributes in HTML
* New: possibility to add field value as minimized repeater value
* New: renderAppender prop for InnerBlocks

= 4.1.12 =
* Fix: replace intval with floatval for number and range fields

= 4.1.11 =
* Fix: $ sign misbehaving inside InnerBlocks
* Fix: 0 as default not working in number and range field
* Fix: 0 not working when set as condition value
* Fix: error when using nested useBlockProps inside innerBlocks

= 4.1.10 =
* Fix: context not working correctly in 6.2

= 4.1.9 =
* Fix: $id variable echoing automatically inside templates
* Fix: license activation not working correctly
* Fix: saved file size not showing in editor
* New: add unique block id to $block variable

= 4.1.8 =
* Fix: blocks sometimes sending two requests during first render
* Fix: color and gradient field defaults not working correctly
* Fix: color palettes not showing in repeater
* Fix: defaults not working when set as 'false'
* Fix: defaults not setting for deeply nested repeaters
* Fix: error when changing image size of files field inside repeater
* Fix: fatal error when using empty options
* Fix: overflowing labels
* Fix: unset button (in color and gradient field) crashing block when pressed inside a repeater

= 4.1.7 =
* Enhancement: Don't allow saving block if block.json in editor is not valid JSON or 'blockstudio' key is missing
* Enhancement: add default functionality for all field types
* Fix: attribute preview crashing if using populate options
* Fix: blocks without assets can't be exported
* Fix: defaults not working correctly in token field
* Fix: editor UI bugs when console is open
* Fix: fields preview having no background color in editor
* Fix: icon field not setting selections correctly
* Fix: potential critical error when using token field
* Fix: prevent double requests when initially rendering block in Gutenberg
* Fix: repeater reordering not working correctly in nested repeaters
* New: 'element' returnFormat for icon field
* New: 'url' returnFormat for files field
* New: JS events for handling attribute changes in Gutenberg
* New: JS events for handling blocks in Gutenberg
* New: ability to add help tooltips to labels
* New: ability to export blocks when right-clicking folder
* New: ability to remove files field in sidebar
* New: add SVG element to icon array
* New: refresh blocks after saving post using new 'refreshOn' option

= 4.1.6 =
* Fix: defaults not being applied when sidebar is not open
* Fix: defaults not working on single fields if repeater is present in block

= 4.1.5 =
* Enhancement: don't allow dragging repeaters if only one element
* Fix: 'disabled' attribute being dropped during option value transformation
* Fix: assets not rendering when using useBlockProps
* Fix: fields with options misbehaving inside repeaters
* Fix: fields with options misbehaving when using numbers as strings
* Fix: files field not disabling fields correctly and misbehaving when being reordered
* Fix: files field returning single object instead of array when 'multiple' is true
* Fix: potential fatal error if populated data didn't match field structure
* Fix: potential fatal error when attributes key is empty in group or repeater field
* Fix: prevent adding of repeaters if less than min value
* Fix: repeater data not being correctly initialised if defaults were set
* Fix: repeater fields not transforming values correctly
* Fix: useBlockProps misfunctioning in various scenarios
* New: 'allowNull' attribute for option fields

= 4.1.4 =
* Enhancement: don't crash block if InnerBlocks attributes parsing fails
* Fix: InnerBlocks only applying one class in frontend
* Fix: autocomplete functions not working in editor
* Fix: don't render InnerBlocks attributes in frontend
* Fix: editor console background bug
* New: filter to remove InnerBlocks wrapper on the frontend
* New: use a template element as outer Gutenberg wrapper (useBlockProps)

= 4.1.3 =
* Fix: editor crashing

= 4.1.2 =
* Fix: blocks rendering an extra div in editor if they don't contain InnerBlocks
* New: editor console

= 4.1.1 =
* Fix: modules would be deleted if non .js files would be saved in editor

= 4.1.0 =
* Enhancement: don't show empty instances when searching in editor
* Enhancement: make search in editor more performant
* Fix: PHP errors in Site Editor
* Fix: activating new plugin created from editor would fail in same cases
* Fix: block attributes ending up in wrong variable
* Fix: editor ending up in wrong block after deleting asset
* Fix: editor error when deleting file
* Fix: miscellaneous editor bugs
* Fix: more complex ES modules will now correctly build in the editor
* New: add possibility to open list context menu on click
* New: conditional logic for blocks
* New: context post ID attribute that changes if in query block
* New: context post type attribute that changes if in query block
* New: possibility to import/export blocks from editor
* New: possibility to set license key from PHP
* New: preview of block attributes in editor

= 4.0.4 =
* Fix: version

= 4.0.3 =
* Enhancement: add snackbar notifications after editor file actions
* Enhancement: enter button will now trigger primary modal actions in editor
* Enhancement: focus first input on modal mount
* Enhancement: updated Editor UI
* Fix: align admin menu icon when folded
* Fix: files not being sorted alphabetically in editor
* Fix: show correct files when deleting block in editor
* New: API to filter block attributes in editor and on frontend
* New: add ability to add new plugin instances from editor
* New: context menu for file list in editor

= 4.0.2 =
* Fix: don't use Regex to parse InnerBlocks attributes

= 4.0.1 =
* Enhancement: make plugin size smaller
* Fix: 'template' prop not working in InnerBlocks
* Fix: add 'templateLock' to InnerBlocks props
* New: block context support

= 4.0.0 =
* Enhancement: no more unnecessary block wrappers in editor
* Enhancement: use custom block renderer in editor
* New: InnerBlocks
* New: minimizable repeater rows
* New: post ID variable to be used inside block templates

= 3.2.3 =
* Enhancement: add class to blockList element
* Enhancement: apply display: 'contents' to all wrapper block divs
* Fix: /icons endpoint having wrong permissions
* Fix: pass correct props to Date field
* Fix: prevent WYSIWYG resetting all other repeater fields
* Fix: wrong dependencies being loaded for blocks Javascript

= 3.2.2 =
* Fix: conditions not working on option fields (select, radio etc.)
* Fix: conditions not working on repeater fields
* New: 'context' option for repeater conditions to target outer field ids

= 3.2.1 =
* Fix: remove old Icons implementation
* Fix: remove undefined bug if group field has no id

= 3.2.0 =
* Enhancement: add icon preview for icon field
* Enhancement: more performant icon caching
* Fix: examples in editor not correctly respecting returnFormat
* New: 'repeater' field type
* New: 'wysiwyg' field type

= 3.1.0 =
* Enhancement: UI tweaks when using editor on mobile
* Enhancement: also use prettier for JSON files in editor
* Enhancement: better loading state when blocks are being inserted in editor
* Enhancement: group field type can just be visual now and not change ID structure of inner fields
* Fix: color field not working correctly when using custom colors
* Fix: correctly enqueue compiled assets in the block preview
* Fix: correctly output block and attribute variables in template files
* Fix: example data not being correctly applied to preview
* New: 'blockstudio/blocks/meta' filter to adjust block metadata before registering
* New: 'icon' field type with 25.000+ included icons
* New: 'min' and 'max' properties for 'files' field type
* New: 'size' property for files
* New: ability to set custom SVG icon via block.json
* New: add $isEditor variable to block templates to check if block environment is editor
* New: add $isPreview variable to block templates to check if block environment is block inserter preview
* New: block library (Gallery, Slider, Image Comparison, Code, Icon)
* New: conditional logic for attributes
* New: include Timber with Plugin

= 3.0.11 =
* Enhancement: better autocompletion for PHP functions in editor
* Enhancement: delete old ES module versions on save block
* Enhancement: prepare plugin for element library
* Enhancement: return better data for files field
* Enhancement: set default returnFormat for files field to object
* Fix: don't show folder option in editor for native blocks
* Fix: exit editor modal not functioning correctly when block has been edited
* New: possibility to add example images to files field when working inside the editor

= 3.0.10 =
* New: bs_block function
* New: ability to add content slots to block render function
* Enhancement: remove border from calendar field in editor
* Enhancement: use dependency array created by wp-scripts when enqueuing editor and blocks scripts

= 3.0.9 =
* Enhancement: automatically focus editor when changing file
* Fix: block not saving due wrong type cast
* Fix: don't break blocks in editor if block has no attributes
* Fix: don't change to different block if deleting last asset in a native block
* Fix: don't create '_dist' folder if no assets are being saved
* Fix: don't leak blockstudio key into attributes
* New: ES modules

= 3.0.8 =
* Enhancement: add condition to Blocks plugin to check if Blockstudio is active
* Enhancement: move 'saveOnFormat' to settings panel
* Enhancement: open save modal before navigating to another block on unsaved changes
* Enhancement: prevent pointer events while block is saving
* Enhancement: reduce editor console warnings by adding editor minimaps
* Enhancement: replace loading spinners with 'isBusy' button
* Enhancement: sort tabs like they appear in file browser
* Enhancement: use srcdoc for editor iframe
* Fix: PHP autocompletion appearing in non PHP areas
* Fix: editor theme not applying
* New: Dracula editor theme
* New: ability to compile JS
* New: ability to compile SCSS
* New: ability to define plugin folder name when creating new instance in editor
* New: settings panel

= 3.0.7 =
* Enhancement: enable folder structure strokes for Safari 16+
* Enhancement: option to hide block inserters when in code editor
* Enhancement: show all files in file browser
* Fix: 'opensInNewTab' property for link field always returning false
* Fix: add block button not showing on nested blocks
* Fix: code editor not resizing properly when changing toolbar settings
* New: block search when in code editor
* New: option to format on save

= 3.0.6 =
* Enhancement: add cache busting to assets

= 3.0.5 =
* Enhancement: add all available PHP functions to auto complete in editor
* Enhancement: don't make error messages for wrong code dismissible in editor
* Enhancement: format .js and .css files with Prettier in editor
* Enhancement: reduce update time in editor to 500ms
* New: format button in toolbar

= 3.0.4 =
* Fix: PHP error if attribute id has changed
* Enhancement: allow simple array for 'options' attribute
* New: ability to populate toggle, select, radio with custom data
* New: 'default' attribute for text, textarea, number, range, toggle, select, radio, checkbox

= 3.0.3 =
* Enhancement: prevent special characters and spaces when creating new blocks via the editor
* Fix: block names parsing falsely when using folder blocks
* Fix: missing rel='stylesheet' for styles
* Fix: assets not enqueueing correctly

= 3.0.2 =
* Enhancement: add aria-disabled to disabled fields
* Enhancement: make all strings translatable
* New: deprecate combobox field type (use select with 'stylisedUi' property instead)
* New: Token field type
* New: add 'stylisedUi' for select field type
* New: conditional logic for attributes

= 3.0.1 =
* Enhancement: add 'textMediaButton' property to files field type
* Fix: Editor showing complete folder structure when using Bedrock
* Fix: use overflow:clip again for folder structure strokes in Editor (not showing in Safari until better support)
* New: Date field type
* New: Date time field type
* New: Link field type

= 3.0.0 =
* Breaking: remove 'blockstudio/assets/script_name' filter
* Breaking: remove 'blockstudio/assets/style_name' filter
* Breaking: remove Scripts class
* Breaking: (Native blocks) require 'blockstudio' property for registration
* Fix: (Editor) block search not working correctly
* Fix: (Editor) styling inconsistencies on Safari
* Fix: (Native blocks) files type not working correctly if multiple attribute disabled
* Fix: inline style error if file doesn't exist
* New: (Editor) add possibility to add additional assets to preview Iframe
* New: (Editor) color themes
* New: (Editor) preview sizes
* New: ApiVersion file header option
* New: advanced asset enqueuing engine
* New: scoped styles

= 2.6.0 =
* Fix: PHP notice if Twig block has no fields filled out
* Fix: Wrong asset path when using Bedrock
* New: (Native blocks) files field
* New: (Native blocks) min and max props for text and textarea fields
* New: (Native blocks) add returnFormat property to select, combobox, checkbox and radio fields
* New: (Native blocks) bs_get_group helper function

= 2.5.0 =
* Enhancement: (Editor) UI enhancements
* Enhancement: (Editor) ability to add blocks to instances that don't have any blocks in it yet
* Enhancement: (Editor) use pointer-events:none on currently selected block in the editor list
* Enhancement: (Native blocks) rename colors and gradients to options in color and gradient fields
* Enhancement: (Native blocks) return value and label for select, radio and combobox fields
* Enhancement: (Native blocks) return value, slug and name for color and gradient fields
* Enhancement: (Native blocks) update checkbox to work with multiple options
* Enhancement: default title if no title is set, making it possible to register blocks without file headers
* Enhancement: use better method to get WordPress root folder name
* Fix: (Editor) assets couldn't be deleted
* Fix: (Editor) empty code when switching blocks
* Fix: (Editor) native blocks preview not working
* Fix: (Native blocks) blocks not rendering in editor
* Fix: (Native blocks) undefined array key errors for native blocks
* Fix: assets not enqueued if block uses $register variable
* Fix: explode errors
* Fix: normalize paths on Windows
* Fix: undefined array key errors if colors, typography or spacing were not defined with >PHP8
* New: (Editor) ability to edit block.json for Native blocks
* New: (Editor) possibility to create custom plugin if no Blockstudio instance is found
* New: (Editor) save file with cmd + s (Mac) or ctrl + s (Windows)

= 2.4.0 =
* Enhancement: Build:init now accepts an array as the first argument (recommended)
* Enhancement: Editor block search now check whole path instead of file name, giving better results
* Enhancement: use DIRECTORY_SEPARATOR for all hardcoded slashes
* Fix: Editor block search not working for nested folders
* New: native blocks

= 2.3.5 =
* New: add AlignContent file header option to set default when inserting
* New: add AlignText file header option to set default when inserting
* New: add SupportsAlignWide file header option
* New: add SupportsColor file header option
* New: add SupportsColorBackground file header option
* New: add SupportsColorGradients file header option
* New: add SupportsColorLink file header option
* New: add SupportsColorText file header option
* New: add SupportsHTML file header option
* New: add SupportsInserter file header option
* New: add TypographyFontSize file header option
* New: add TypographyLineHeight file header option
* New: add SpacingPadding file header option
* New: add SpacingMargin file header option
* New: add SpacingBlockGap file header option

= 2.3.4 =
* Fix: license activation not working for PHP 8+

= 2.3.3 =
* Fix: fields not showing when block name includes underscores
* Fix: blocks with underscores in name not being rendered by function
* Fix: don't hardcode 'wp-content' folder
* Fix: prevent unnecessary error logging
* Fix: correctly pass parameters to acf_render_block function when using Twig

= 2.3.2 =
* Fix: prevent error if ACF block rendered with blockstudio_render_block can't be found
* New: 'blockstudio/assets' filter to disable all assets enqueuing

= 2.3.1 =
* Editor: sort files like in a file system in list view
* Editor: use 'blockstudio/editor/users' to enable editor
* Fix: prevent double wp_footer and wp_head calls made from blocks

= 2.3.0 =
* Enhancement: add $wp_block and $context to Twig blocks (ACF)
* Enhancement: automatically add blocks to 'blockstudio' category if none is set
* Fix: set required PHP version to 7.4.0
* New: built-in code editor (beta)

= 2.2.0 =
* Fix: only render inline scripts and styles once
* Fix: render inline scripts and styles in reusable blocks
* Fix: use DIRECTORY_SEPARATOR instead of hardcoding ''/' fixing block folders not working when using Local on Windows
* New: blockstudio_render_block function to render blocks outside the editor (ACF only for now)

= 2.1.1 =
* Fix: PHP 8 warnings

= 2.1.0 =
* New: support for inlining styles and scripts

= 2.0.5 =
* Enhancement: change admin page slug
* Enhancement: don't hardcode wp-content folder name
* New: React based admin settings page

= 2.0.3 =
* Fix: use block_categories_all to register 'Blockstudio' category for WordPress installs using version 5.8.0 and above

= 2.0.2 =
* Fix: don't initialise if ACF Pro is not active
* Fix: prevent missing $settings variable error

= 2.0.1 =
* Enhancement: style and scripts are now being enqueued for blocks in every folder

= 2.0.0 =
* New: add support for Metabox 'context' attribute
* New: folder components
* New: 'blockstudio/style_name' filter
* New: 'blockstudio/script_name' filter

= 1.4.7 =
* New: add support for Metabox previews
* New: add support for defining previews in Twig

= 1.4.6 =
* Fix: remove unused functions
* Fix: spaces being removed when registered fields in Twig
* New: add IconBackground and IconForeground file header options
* New: add SupportsAlignText file header option
* New: add SupportsAlignContent file header option
* New: add SupportsFullHeight file header option

= 1.4.5 =
* Enhancement: replace Twig::compile with Twig::render

= 1.4.4 =
* Fix: scripts not being available
* Fix: Twig templates not being loaded correctly

= 1.4.3 =
* Enhancement: hide license once activated

= 1.4.2 =
* New: add possibility to exclude folders

= 1.4.1 =
* Fix: add 5.7.1 compatibility

= 1.4.0 =
* New: Timber/Twig support
* Enhancement: add tests for Twig files
* Fix: remove code editor experiment
* Fix: remove inline fields experiment

= 1.3.2 =
* Enhancement: include editor package again
* Fix: remove library reference

= 1.3.1 =
* Fix: include correct scripts

= 1.3.0 =
* Enhancement: add field group and name to attribute object
* Enhancement: make code editor external module
* Enhancement: make sure to always add Blockstudio category
* Enhancement: recursively remove all empty values in block array
* Enhancement: rename Blockstudio\Field::render to Blockstudio\Field::inline
* Enhancement: show filename in code editor
* Fix: make tested up to version 5.7
* New: Blockstudio\Scripts for easier development start
* New: support for MB Blocks (metabox.io)

= 1.2.7 =
* Enhancement: make blockstudio attributes an object
* Fix: clean up filterClass
* Fix: make 'tested up to version' 5.6.2
* New: Blockstudio\Field class to generate and display inline fields
* New: Blockstudio\Editor class to enable code editing inside Gutenberg

= 1.2.6 =
* Fix: make 'tested up to version' 5.6
* Fix: remove previous library and prepare for starter blocks
* New: file header support for 'anchor'
* New: file header support for 'custom class name'
* New: file header support for 'reusable blocks'
* New: file header support for 'parent'

= 1.2.5 =
* Enhancement: add a test suite of blocks to troubleshoot issues
* Enhancement: remove beta tag from filterClass
* Fix: enable defaults with recursive arrays (thanks Calvin!)
* Fix: make it possible to define categories with the 'blockstudio/default' filter
* Fix: make it possible to register blocks using file headers and $register variable in the same folder
* New: $settings variable
* Updater: make deactivate function static

= 1.2.4 =
* Enhancement: add wrapper to reusable block layout
* Enhancement: change section block to use array for block registration to make use of filters
* Enhancement: register 'blockstudio' block category
* Enhancement: rename column component to row
* Fix: prevent fields inside blocks from spilling over to other blocks

= 1.2.3 =
* Fix: updater take two

= 1.2.2 =
* Fix: updater again

= 1.2.1 =
* Fix: updater

= 1.2.0 =
* Enhancement: add notice to blocks in editor if they have no content
* Enhancement: modularise column component
* Library: add custom CSS, so that the Gutenberg sidebar can be used without problems
* Library: removed grid and two column block in favour of a unified 'section' block

= 1.0.9 =
* Enhancement: add possibility to add array as name for classFilter
* Text Component: make use of array in classFilter

= 1.0.8 =
* Fix: updater error

= 1.0.7 =
* Fix: current version

= 1.0.6 =
* Fix: file download mixed content error

= 1.0.0 =
* New: initial release
