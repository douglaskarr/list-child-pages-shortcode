=== List Child Pages Shortcode ===
Contributors: douglaskarr
Tags: child pages, subpages, page list, shortcode, parent page
Requires at least: 4.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://dknewmedia.com/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=list-child-pages-shortcode&utm_content=donate

List a page's child pages anywhere with one shortcode, with optional featured images, excerpts, custom ordering, and CSS classes.

== Description ==

List Child Pages Shortcode adds a `[listchildpages]` shortcode that lists the child pages of the current page, or of any page you choose, as a simple linked list. Each item can show the page's featured image and excerpt.

* Works in any page, post, widget, or template that renders shortcodes.
* Choose the parent by ID, slug/path, or the current page.
* Sort by date, title, menu order (the **Order** field under Page Attributes), and more.
* Optional featured images at any registered image size.
* Add your own CSS classes to the list, items, and links.
* No settings, no database tables, no external services, no tracking.

= Usage =

The shortcode is `[listchildpages]...[/listchildpages]`. `[dknm_list_child_pages]` works the same way. It accepts several attributes:

* **ifempty**: Message or HTML to show if no child pages exist.
* **order**: `ASC` or `DESC`. Default: `DESC`, or `ASC` when `orderby="menu_order"`.
* **orderby**: Field to order by. Default: `publish_date` (maps to `date`). Also accepts `published` (maps to `date`), `title`, `name`, `modified`, `menu_order`, `ID`, `rand`, `type`, and `author`. Use `menu_order` to follow the **Order** numbers set under Page Attributes; pages with the same number sort by title.
* **displayimage**: Show featured image (`yes|no`). Also accepts `y`, `true`, `1`, and `on`. Default: `no`.
* **align**: CSS class to apply to the image (`alignleft`, `alignright`, etc.).
* **ulclass, liclass, aclass**: CSS classes for the list, list items, and links.
* **parent**: ID, slug/path, or `current` to choose the parent page. Default: `current`.
* **size**: Image size for thumbnails (`thumbnail`, `medium`, `large`, `full`, or custom size). Default: `thumbnail`.

Examples:

Example 1: Order the child pages by publish date in descending order:

    [listchildpages aclass="" ifempty="No child pages" orderby="publish_date" order="desc" displayimage="no"]
    <h3>Here are our child pages:</h3>
    [/listchildpages]

Example 2: Order the child pages by title in ascending order with the page's featured image aligned left:

    [listchildpages orderby="title" order="asc" displayimage="yes" align="alignleft"]
    <h3>Here are our child pages:</h3>
    [/listchildpages]

Example 3: Target a specific parent page by ID and use medium-sized images:

    [listchildpages parent="123" displayimage="yes" size="medium"]
    <h3>Resources</h3>
    [/listchildpages]

Example 4: Target a parent page by slug and show full-size featured images:

    [listchildpages parent="about/company" displayimage="yes" size="full"]
    <h3>Team Sections</h3>
    [/listchildpages]

Example 5: Follow the page order set under Page Attributes:

    [listchildpages orderby="menu_order"]
    <h3>Chapters</h3>
    [/listchildpages]

Example 6: Explicitly set the parent as the current page and use large images:

    [listchildpages parent="current" displayimage="yes" size="large"]
    <h3>Subpages</h3>
    [/listchildpages]

= Developer filters =

* `dknm_lcp_html` – filter the final HTML. Receives the HTML, the parent page ID, and the normalized attributes.
* `shortcode_atts_listchildpages` – the standard WordPress filter for the shortcode's attributes.
* `dklcp/listchildpages/html` – the 1.x output filter. It still runs but is deprecated; use `dknm_lcp_html`.

Built by [DK New Media](https://dknewmedia.com/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=list-child-pages-shortcode&utm_content=readme-author). Visit [Martech Zone](https://martech.zone/wordpress-list-child-pages-plugin/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=list-child-pages-shortcode&utm_content=readme-docs) for updates on this plugin and other marketing tools.

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New**, search for "List Child Pages Shortcode", and select **Install Now**. Or upload the `list-child-pages-shortcode` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Edit a parent page and add `[listchildpages]` where the list should appear.
4. Optional: add an excerpt and featured image to each child page to show them in the list.

== Frequently Asked Questions ==

= How can I add a description for each child page? =

Edit the child page and you'll find an excerpt section where you can enter a description that will be published on the list.

= How can I enable featured images on my site? =

Within your functions.php file, look for the add_theme_support post-thumbnails line and add page to the array. Or if you don't have that line, you can just add it:

`add_theme_support( 'post-thumbnails', array( 'post', 'page' ) );`

= How can I modify the output? =

There are additional class fields for the unordered list tag (ulclass), list item tag (liclass), and the anchor tag (aclass). You can modify the output utilizing your theme's CSS.

= How do I list child pages in my own order? =

Set the **Order** number under **Page Attributes** on each child page (1, 2, 3...), then use `[listchildpages orderby="menu_order"]`. Since 1.6.0 this sorts lowest number first. Add `order="desc"` to reverse it.

= I updated to 1.6.0. Do I need to change my shortcodes? =

No. `[listchildpages]` and every attribute work as before. The only change in output is that `orderby="menu_order"` without an `order` attribute now sorts ascending, which matches the order shown in WordPress.

= What happens if I deactivate or uninstall the plugin? =

The plugin stores nothing in the database. An uninstall.php file defensively removes any leftover `dknm_lcp_` or legacy `dklcp_` options or transients if they were ever created. Your pages, excerpts, and featured images are not changed. Shortcodes left in content will simply stop rendering until the plugin is activated again.

== Screenshots ==

1. The `[listchildpages]` shortcode in a Shortcode block, sorted by menu order with featured images.
2. The published list of child pages with featured images and excerpts (Twenty Twenty-Five, with a few lines of theme CSS for the thumbnails).

== Changelog ==

= 1.6.3 =
* Author, documentation, donate, and readme links now include campaign tracking. The Martech Zone link points at the plugin's documentation article.

= 1.6.2 =
* New WordPress.org banner and animated icon, with a consistent DK layout and a "Developed by Martech Zone" badge.
* Add uninstall.php to defensively remove any leftover plugin options or transients (the plugin does not store any).

= 1.6.1 =
* Readme: fix code example formatting on WordPress.org.

= 1.6.0 =
* Author is now DK New Media. The plugin name, slug, folder, main file, and `[listchildpages]` shortcode are unchanged.
* Fix: `orderby="menu_order"` now sorts ascending (the Page Attributes order) when no `order` is given, with title as a tie-breaker for pages that share a number.
* New: `[dknm_list_child_pages]` alias for the shortcode.
* New: `dknm_lcp_html` output filter. The 1.x `dklcp/listchildpages/html` filter still runs and is marked deprecated.
* Code now uses the `dknm_lcp_` prefix. The old `dklcp_` function names remain as wrappers.
* Tested up to WordPress 7.1 (7.1.5). Requires WordPress 4.6 or later.

= 1.5.1 =
* Align Version and Stable tag so WordPress.org serves the current code.
* Declare compatibility with WordPress 7.1.
* Raise minimum WordPress version to 3.1 (required by current APIs).
* Match the text domain to the plugin slug for translations.
* Keep default empty-state HTML out of the translatable string.
* Accept additional `orderby` values (`rand`, `type`, `author`) and the `published` alias.
* Accept `on` as a truthy `displayimage` value.
* Query child page IDs so the shortcode does not take over the main Loop. Default markup is unchanged.

= 1.4.1 =
* Updated keywords for plugin

= 1.4.0 =
* Added `parent` attribute to target a specific parent page by ID or slug.
* Added `size` attribute to choose featured image size.
* Hardened shortcode sanitization and escaping for security.
* Backward-compatible with previous versions.

= 1.3.2 =
* Tested in latest versions of WordPress

= 1.3.1 =
* Tested in latest versions of WordPress

= 1.3.0 =
* Added an option to display the featured image for the page.
* Added class outputs for the ul and li tags in addition to the anchor text.

= 1.2.2 =
* Corrected an undeclared variable error.

= 1.2.1 =
* Added documentation on where you can see all of the order and orderby parameters.

= 1.2.0 =
* Added another shortcode option for the order. Default is DESC.

= 1.1.0 =
* Added another shortcode option for the orderby. Default is publish_date.

= 1.0.0 =
* Initial Release

== Upgrade Notice ==

= 1.6.3 =
Author and documentation links now include campaign tracking. No shortcode changes.

= 1.6.2 =
New WordPress.org banner and animated icon, and an uninstall.php cleanup routine. No shortcode changes.

= 1.6.1 =
Fixes code example formatting on the WordPress.org plugin page.

= 1.6.0 =
Fixes menu_order sorting. Existing shortcodes keep working.

= 1.5.1 =
* Marks the plugin tested with WordPress 7.1, aligns the stable version, and adds optional orderby/displayimage aliases without changing default output.

= 1.4.0 =
* New `parent` and `size` attributes. Backward-compatible. Please review usage examples.

= 1.3.1 =
* Tested in latest versions of WordPress

= 1.3.0 =
* Added more options!

= 1.2.2 =
* Corrected an undeclared variable error.

= 1.2.1 =
* Added documentation on where you can see all of the order and orderby parameters.

= 1.2.0 =
* Added another shortcode option for the order. Default is DESC.

= 1.1.0 =
* Added another shortcode option for the order. Default is publish_date.

= 1.0.0 =
* Initial Release
