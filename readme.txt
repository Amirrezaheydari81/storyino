=== Storyino ===
Contributors: clarotm
Tags: stories, instagram, shortcode, video, gallery
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instagram-style image and video stories for WordPress, with categories, shortcodes, and media library support.

== Description ==

Storyino shows image and video stories as a horizontal row of circles, similar to Instagram. Each category has its own cover image and shortcode. Clicking a circle opens a full-screen player.

The plugin UI is Persian (RTL). CSS, JavaScript, and the Vazirmatn font are loaded from the plugin folder. Nothing is loaded from a CDN.

= Features =

* Story categories with a circular cover image and a dedicated shortcode
* `[storyino]` for all categories and `[storyino cat="slug"]` for one category
* Images and videos from the WordPress media library
* Optional destination link on each story item
* Drag-and-drop order for categories and items
* Video frame preview in the admin screen
* Player closes after the last story
* Prefetch of the next story
* Optional local Vazirmatn font for circle labels and the link button
* Small, medium, or large ring size, with horizontal scroll when the row overflows
* Flat story rings without a drop shadow
* No CDN dependency for styles, scripts, or fonts

= Shortcodes =

`[storyino]`
Shows every category as Instagram-style rings.

`[storyino cat="products"]`
Shows one category.

`[storyino ids="12,18,25"]`
Plays specific media library attachments.

Optional attributes: `label`, `speed` (KB/s, `0` = real network speed), `duration` (image time in milliseconds).

= Source code =

This plugin is licensed under GPLv2 or later.

Unminified, human-readable source is included in the plugin:

* JavaScript source: `src/js/storyino.js` and `src/js/storyino-admin.js`
* CSS source: `src/css/storyino.css` and `src/css/storyino-admin.css`
* Readable copies also ship next to the minified files as `assets/js/storyino.js`, `assets/js/storyino-admin.js`, and `assets/css/storyino.css`

Public repository: https://github.com/Amirrezaheydari81/storyino

To rebuild minified assets:

`npm install`
`npm run build`

Build tools (devDependencies only, not loaded on the site): esbuild, PostCSS, Tailwind CSS, and clean-css. See `package.json` and `build.js`.

= Third-party resources =

* Vazirmatn font (SIL Open Font License), bundled as `assets/fonts/Vazirmatn-Medium.woff2`: https://github.com/rastikerdar/vazirmatn
* jQuery and jQuery UI Sortable: WordPress core
* Tailwind CSS (MIT): used only at build time for the admin stylesheet

== Installation ==

1. Upload the `storyino` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open **Storyino** in the admin menu.
4. Create a category, set a cover image, and add media.
5. Place `[storyino]` in a post, page, or a Shortcode widget.

== Frequently Asked Questions ==

= Where is the unminified JavaScript? =

In `src/js/`. Matching readable files are also in `assets/js/storyino.js` and `assets/js/storyino-admin.js`. Minified files use the `.min.js` suffix. The same code is on GitHub: https://github.com/Amirrezaheydari81/storyino

= Does the plugin load scripts from a CDN? =

No. Styles, scripts, and the optional Vazirmatn font are local files in `assets/`.

= Why is video muted? =

Browsers block autoplay with sound. Stories play muted so the player can start without a click.

= The story circles do not appear =

Confirm the plugin is active, the shortcode is in the content, the category has at least one image or video, and the theme calls `wp_footer()`.

== Screenshots ==

1. Category settings in the admin dashboard.
2. Story rings on the front end.
3. Full-screen story player.

== Changelog ==

= 1.1.9 =
* Added small, medium, and large sizes for story rings.
* Story row scrolls horizontally when rings overflow the page width.
* Removed the drop shadow behind story rings for a flat look.

= 1.1.8 =
* Added a setting to show or hide the story category title under each ring.
* Added a setting to set the story title color to black or white.

= 1.1.7 =
* Document public source, build steps, and third-party assets in readme.txt.
* Ship unminified JS/CSS next to minified files for WordPress.org review.
* Register front-end assets with a named `wp_enqueue_scripts` callback.

= 1.1.6 =
* Story categories with cover images and per-category shortcodes.
* Auto-close the player after the last story.
* Prefetch the next story and load media at the visitor's network speed.
* Optional local Vazirmatn font for ring labels and the link button.
* Safer URL and attachment sanitization.

= 1.0.1 =
* Asset minification and per-item story links.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.1.9 =
Adds story ring size options, horizontal scrolling when the row overflows, and flat rings without a drop shadow.

= 1.1.8 =
Adds options to hide story titles and choose black or white title color.

= 1.1.7 =
Adds WordPress.org readme.txt and ships readable source next to minified assets.
