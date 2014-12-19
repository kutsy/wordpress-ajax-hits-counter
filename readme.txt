=== AJAX Hits Counter + Popular Posts Widget ===
Contributors: kutsy
Tags: hits, hit, view, views, postviews, post, posts, count, counter, ajax, nginx, javascript, popular, widget, top, customize, admin, page, pageview, links, performance
Requires at least: 3.0
Tested up to: 4.1
Stable tag: 0.9.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Counts page/posts hits via AJAX. Ideal for nginx whole-page-caching or caching plugins. Popular Posts Widget included.

== Description ==

Counts page/posts hits via AJAX. Ideal for nginx whole-page-caching or caching plugins.

For plugin's work you DON'T need any JS-framework, such as jQuery, MooTools, etc.

Popular Posts Widget included.

You can fully customize displaying of widget by using predefined placeholders, such as `{post_id}`, `{post_title}`, `{post_title_N}`, `{post_excerpt_N}`, `{post_author}`, `{post_author_link}`, `{permalink}`, `{post_date}`, `{thumbnail-[medium|...|64x64]}`, `{post_categories}`, `{post_hits}` and `{post_comments_count}`.

Also you can show hits count in Posts/Pages Loop. 
Simply add this line:
`<?php echo(ajax_hits_counter_get_hits(get_the_ID())); ?>`
or equivalent
`<?= ajax_hits_counter_get_hits(get_the_ID()) ?>`

Or you can use shortcode `[hits]`.

== Installation ==

1. Upload `ajax-hits-counter` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the "Plugins" menu in WordPress
1. You can enable "Popular Posts Widget" in Widgets Management
1. If you want to show hits count in Loop -- add this line: `<?php echo(ajax_hits_counter_get_hits(get_the_ID())); ?>` or equivalent `<?= ajax_hits_counter_get_hits(get_the_ID()) ?>`

== Screenshots ==

1. Popular Posts Widget
1. Edit Hits Count in Admin Dashboard
1. Tools: Import views count (hits) from plugin WP-PostViews to hits of AJAX Hits Counter
1. AJAX Hits Counter General Settings

== Changelog ==

= 0.9.9 [2014-12-19] =
* Added option in Settings: "Don't count hits of admin users". Currently it works only with normal (not "rapid") counting script. Thanks for the idea to [vvvv](https://wordpress.org/support/profile/vvvv).
* Updated screenshots with Wordpress 4.1

= 0.9.8 [2014-12-18] =
* Added possibility to set Hits Count format from predefined. Thanks for the idea to [emik91](https://wordpress.org/support/profile/emik91), [Dre_MPMG](https://wordpress.org/support/profile/dre_mpmg), [Pulseframe](https://wordpress.org/support/profile/pulseframe).
* Added possibility to exclude Posts/Pages by their IDs. Thanks for the idea to [pFrog](https://wordpress.org/support/profile/pfrog).

= 0.9.7 [2013-12-28] =
* Bug fixes and visual fixes for Wordpress v3.8

= 0.9.6 [2013-11-29] =
* Added translation in serbo-croatian (`sr_RS`) language. Thanks to [Borisa Djuraskovic](http://webhostinghub.com).

= 0.9.5 [2013-11-29] =
* Replace `filter_var()` for PHP < 5.2.0 support.

= 0.9.4 [2013-11-18] =
* Added translation in spanish (`es_ES`) language. Thanks to [Maria Ramos](http://webhostinghub.com).

= 0.9.3 [2013-11-14] =
* Added translations in russian (`ru_RU`) and ukrainian (`uk_UA`) languages.

= 0.9.2 [2013-10-22] =
* Bug fixes

= 0.9.1 [2013-10-22] =
* Added shortcode `[hits]` (or `[hits id=12345]`). Thanks for the idea to [AsankaD](http://wordpress.org/support/profile/asankad).
* Small bugs fixes

= 0.9.0 [2013-10-22] =
* Added very fast (rapid) alternative hits counter script that used WP constant `SHORTINIT`. You can choose script type via Settings checkbox in admin area.
* Changed design of Popular Posts Widget

= 0.8.8 [2013-09-15] =
* Added classes for `<li>` elements: `item-num-<NUMBER_IN_ORDER>` and `item-id-<POST_ID>`
* Added coefficients for Hits count and Comments count in `Sorting Algorithm`.
* Added hits column for Pages in admin dashboard.
* Added meta box for change hits count for Pages in admin dashboard.

= 0.8.7 [2013-09-03] =
* Added counting for pages type too. Now you can choose post types to display in Popular Posts Widget: `Pages & Posts`, `Posts only` or `Pages only`.

= 0.8.6 [2013-09-02] =
* Added import views count (hits) from plugin [WP-PostViews](http://wordpress.org/plugins/wp-postviews/) to hits of [AJAX Hits Counter](http://wordpress.org/plugins/ajax-hits-counter/). Accessible from menu: `Tools` > `Import` > `AJAX Hits Counter: Import from WP-PostViews`. Thanks for the idea to [fabinhoalmeida](http://wordpress.org/support/profile/fabinhoalmeida).

= 0.8.5 [2013-08-29] =
* Added `Exclude category` option. It means that Popular Posts Widget will exclude posts from selected category. In future I do it for more than one category. Thanks for the idea to [fenomeno0chris](http://wordpress.org/support/profile/fenomeno0chris).
* New look of Popular Posts Widget

= 0.8.4 [2013-08-28] =
* Small fixes

= 0.8.3 [2013-08-28] =
* Added Custom CSS box. Now you can add your styles from Widget Management

= 0.8.2 [2013-03-29] =
* Display meta box for change hits count only for administrators.
* Improved logic of hits counting

= 0.8.1 [2013-03-28] =
* Added meta box for change hits count in admin dashboard. Thanks for the idea to [benben123](http://wordpress.org/support/profile/benben123).
* Rewritten on objects.

= 0.8.0 [2013-03-28] =
* Added `Current Category / Any` option. It means that Popular Posts Widget will display only posts from current navigated category or for any category for other pages. Thanks for the idea to [benben123](http://wordpress.org/support/profile/benben123).
* Added Posts date range selection. Now you can select posts publication date range, such as `day`, `week`, `month`, `3 months`, `6 months`, `year` and `all time`. Thanks for the idea to [whatwillb](http://wordpress.org/support/profile/whatwillb).
* Added placeholder `{post_title_N}` to Popular Posts Widget, where `N` - is words count. Thanks for the idea to [fenomeno0chris](http://wordpress.org/support/profile/fenomeno0chris).
* Updated logic of `{post_excerpt_N}`.

= 0.7.6 [2013-02-27] =
* Cache lifetime bug fix in Popular Posts Widget

= 0.7.5 [2013-02-25] =
* Fix some bugs in Popular Posts Widget

= 0.7.4 [2013-02-22] =
* Fix some bugs in Popular Posts Widget

= 0.7.3 [2013-02-22] =
* Added category filter to Popular Posts Widget. Thanks for the idea to [benben123](http://wordpress.org/support/profile/benben123).
* Updated screenshot

= 0.7.2 [2013-01-22] =
* Added new function `ajax_hits_counter_get_hits($post_id)` for displaying hits count in your custom theme. 
Example: `echo(ajax_hits_counter_get_hits(get_the_ID()).' hits');`

= 0.7.1 [2013-01-03] =
* JS optimization

= 0.7.0 [2013-01-03] =
* For plugin's work you DON'T need anymore any JS-framework, such as jQuery, MooTools, script.aculo.us etc.

= 0.6.2 [2012-12-27] =
* Fix some bugs in Popular Posts Widget

= 0.6.1 [2012-12-27] =
* `readme.txt` updates

= 0.6.0 [2012-11-24] =
* Fix some bugs in Popular Posts Widget

= 0.5.9 [2012-11-24] =
* Fix some bugs in Popular Posts Widget

= 0.5.8 [2012-11-23] =
* Added placeholder `{post_excerpt_N}` to Popular Posts Widget, where `N` - is words count. Thanks for the idea to [meetanik](http://wordpress.org/support/profile/meetanik).
* Update screenshot of Popular Posts Widget

= 0.5.7 [2012-11-07] =
* Fix some bugs in Popular Posts Widget

= 0.5.6 [2012-11-06] =
* Update screenshot of Popular Posts Widget

= 0.5.5 [2012-11-06] =
* Added placeholders `{post_author}` and `{post_author_link}` to Popular Posts Widget. Thanks for the idea to [ebreuers](http://wordpress.org/support/profile/ebreuers).

= 0.5.4 [2012-11-06] =
* Added Sorting Algorithm (`Hits`, `Comments`, `Hits + Comments`) to Popular Posts Widget
* Added placeholder `{post_comments_count}` to Popular Posts Widget

= 0.5.3 [2012-09-22] =
* Added placeholder `{post_hits}` to Popular Posts Widget

= 0.5.2 [2012-09-05] =
* Fixed small bug

= 0.5.1 [2012-09-05] =
* Fixed small bug with already in cache data

= 0.5 [2012-09-05] =
* Added placeholders `{post_date}` and `{post_categories}` to Popular Posts Widget
* Some performance optimizations

= 0.4 [2012-09-04] =
* Now you can customize Popular Posts Widget output by using placeholders `{post_id}`, `{post_title}`, `{permalink}` and `{thumbnail-[thumbnail|large|medium|...|64x64]}`

= 0.3.1 [2012-09-03] =
* Fixed bug with displaying posts in Popular Posts Widget

= 0.3 [2012-09-03] =
* Fixed Transient cache usage for Popular Posts Widget
* Added cleaning widget transient cache on `save_post` action

= 0.2 [2012-09-03] =
* Added Popular Posts Widget

= 0.1 [2012-08-27] =
* AJAX Hits Counter initialization commit
