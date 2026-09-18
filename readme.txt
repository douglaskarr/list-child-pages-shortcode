=== List Child Pages Shortcode ===
Contributors: douglaskarr
Tags: page, parent page, child page, shortcode
Version: 1.4.1
Stable tag: 1.4.0
Tested up to: 6.7.2
Requires at least: 3.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://dknewmedia.com

A simple plugin to add list of child pages within the content of a parent page. 

== Description ==

I could not find an easy plugin that enabled me to use a shortcode that would enable me to publish a list of child pages under a parent page. So, I built one.

= Usage =

The shortcode is `[listchildpages]...[/listchildpages]`. It accepts several attributes:

* **ifempty**: Message or HTML to show if no child pages exist.
* **order**: `ASC` or `DESC`. Default: `DESC`.
* **orderby**: Field to order by. Default: `publish_date` (maps to `date`). Any valid `WP_Query` orderby value works.
* **displayimage**: Show featured image (`yes|no`). Default: `no`.
* **align**: CSS class to apply to the image (`alignleft`, `alignright`, etc.).
* **ulclass, liclass, aclass**: CSS classes for the list, list items, and links.
* **parent**: ID, slug/path, or `current` to choose the parent page. Default: `current`.
* **size**: Image size for thumbnails (`thumbnail`, `medium`, `large`, `full`, or custom size). Default: `thumbnail`.

Examples:

Example 1: Order the child pages by publish date in descending order:
`[listchildpages aclass="" ifempty="No child pages" orderby="publish_date" order="desc" displayimage="no"]
<h3>Here are our child pages:</h3>
[/listchildpages]`

Example 2: Order the child pages by title in ascending order with the page's featured image aligned left:
`[listchildpages orderby="title" order="asc" displayimage="yes" align="alignleft"]
<h3>Here are our child pages:</h3>
[/listchildpages]`

Example 3: Target a specific parent page by ID and use medium-sized images:
`[listchildpages parent="123" displayimage="yes" size="medium"]
<h3>Resources</h3>
[/listchildpages]`

Example 4: Target a parent page by slug and show full-size featured images:
`[listchildpages parent="about/company" displayimage="yes" size="full"]
<h3>Team Sections</h3>
[/listchildpages]`

Example 5: Explicitly set the parent as the current page and use large images:
`[listchildpages parent="current" displayimage="yes" size="large"]
<h3>Subpages</h3>
[/listchildpages]`

The shortcode accepts all of the Order and Orderby Parameters listed within the [WordPress class reference](https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters).

Built by [DK New Media](http://www.dknewmedia.com), visit [MarTech](https://martech.zone) to keep up on this plugin and other marketing tools to help you grow your online presence!

== Installation ==

1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= How can I add a description for each child page? =

Edit the child page and you'll find an excerpt section where you can enter a description that will be published on the list.

= How can I enable featured images on my site? =

Within your functions.php file, look for the add_theme_support post-thumbnails line and add page to the array. Or if you don't have that line, you can just add it:

`add_theme_support( 'post-thumbnails', array( 'post', 'page' ) );`

= How can I modify the output? =

There are additional class fields for the unordered list tag (ulclass), list item tag (liclass), and the anchor tag (aclass). You can modify the output utilizing your theme's CSS.

== Screenshots ==

1. View of the shortcode.
2. View of the output.

== Changelog ==

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
