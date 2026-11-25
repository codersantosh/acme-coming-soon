=== Acme Coming Soon and Maintenance Mode Page ===
Contributors: patternswp, codersantosh
Tags: maintenance mode, coming soon, page builder, under construction, unavailable page
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Craft captivating Coming Soon, Maintenance Mode or Under Construction Page effortlessly with Gutenberg Blocks/Patterns or your favourite page builder.

== Description ==

Activate the **Coming soon (Maintenance) Mode** to redirect visitors to a specific page while you work on your site. This plugin allows you to create a landing page that informs your visitors that your site is under construction, whether you call it Coming Soon, Under Construction, or Maintenance Mode.

Easily enable or disable the Coming Soon (Maintenance) Mode with a click. Activating the Coming Soon (Maintenance) Mode will redirect unauthorized visitors to a personalized page. You can craft your page using **Gutenberg Blocks/Patterns** or your favourite page builder with complete flexibility and all the features available for creating any WordPress page. We recommend including a message, countdown timer, subscription form, contact form, and social media icons to create a better landing page for your under-construction page.

The plugin is handy when you are developing a new blog or site, or working on updates and maintenance for an existing site. Redirecting visitors to a specially designed page will ensure a smooth user experience. You can explain the changes and let visitors know when they can expect the site to be fully operational again.

== Inbuilt Patterns for Quick Setup ==

The 'Acme Coming Soon and Maintenance Mode Page' plugin comes with a collection of inbuilt Gutenberg Block Patterns that users can easily apply for a quick setup.

= Locating and Inserting Patterns =

- Access the Block Editor: Open the page or post where you want to use the pattern. Click the "Add Block" button (+) or the "+" sign in the top left corner of the block editor.

- Switch to the Patterns Tab: In the block inserter window, click the "Patterns" tab. This displays all available patterns.

- Coming Soon and Maintenance: Within the Patterns tab, find the category labelled "Coming Soon and Maintenance." This categorization ensures a quick and efficient search of the patterns tailored to your needs.

- Insert a Pattern: Click the pattern to insert it directly into your edit content area.

- Modify as Needed: The inserted pattern serves as a starter template. Customize it effortlessly to align with your specific requirements. Modify text, colours, and any other elements to create a maintenance page that reflects your unique style.

== Features ==

- Enable or disable Coming Soon (Maintenance) Mode with a simple click.
- Redirect unauthorized visitors to a specific page of your choice.
- Design your page using Gutenberg Blocks/Patterns or your preferred page builder.
- Utilize the full flexibility and features of WordPress page creation for your Maintenance Page.
- Allow search bots during maintenance mode.
- Exclude specific IP addresses and URL paths.
- Protect public REST, sitemaps, feeds, oEmbed, XML-RPC, and public AJAX endpoints.
- Remove all plugin settings when deactivating.

== Installation ==

There are two ways to install the plugin:

1. Upload the plugin's zip file via Dashboard -> Plugins -> Add New -> "Upload Plugin".
2. Extract the plugin folder and place it in the "/wp-content/plugins/" directory.

After installation, activate the plugin through the 'Plugins' menu in WordPress.

Source code and build instructions: https://github.com/codersantosh/acme-coming-soon
Full changelog: changelog.txt

== Frequently Asked Questions ==

= How can I design the Coming Soon Page? =

Designing the Coming Soon Page is simple, much like creating any other WordPress page or landing page. Utilize the flexibility of Gutenberg Blocks/Patterns and other page builders to effortlessly craft a visually appealing and engaging Coming Soon Page.

= How can I customize the Coming Soon or Maintenance Mode page? =

You can easily customize the page using the blocks/patterns provided by Gutenberg or your preferred page builder.

= How can I redirect all pages to the home URL during maintenance? =

Temporarily set your maintenance page as the homepage by navigating to WordPress Dashboard -> Settings -> Reading -> Homepage.

= Does maintenance mode work with full-page caching? =

The plugin sends no-cache headers and triggers cache-purge actions when settings change. A server-level or CDN cache that serves pages without running WordPress may still require maintenance-mode configuration in that cache layer.

== Screenshots ==

1. Dashboard - Getting started
2. Dashboard - Settings
3. Dashboard - Settings - General
4. Dashboard - Settings - Advanced
5. Edit page - Patterns
6. Edit page - Patterns selection
7. Edit page - Customize pattern
8. Coming soon, maintenance or under construction page

== Changelog ==

= 1.0.7 =
* Changed: Raised minimum WordPress requirement to 7.0 and PHP requirement to 8.3
* Changed: Confirmed compatibility with WordPress 7.0 and 7.1
* Security: Hardened maintenance-mode access checks and public content boundaries
* Security: Slug exclusions no longer match query strings or unrelated path fragments
* Security: IP exclusions now require exact address matches
* Fixed: Administrator access now uses the correct capability
* Fixed: Search-bot exclusion now uses the configured setting
* Fixed: Missing User-Agent requests no longer produce notices
* Fixed: Invalid or missing maintenance pages now fail closed with HTTP 503
* Fixed: Maintenance-page detection now uses the queried object
* Security: Partial settings updates preserve sibling values and validate the merged configuration
* Security: Closed the core /wp/v2/settings exposure for plugin options
* Fixed: Pattern registration no longer depends on a loopback HTTP request
* Fixed: Added IP and slug rows no longer throw a JavaScript error
* Fixed: Replaced stale translation domains and regenerated translation templates
* Added: Guarded single-site and multisite uninstall cleanup
* Added: Automated PHPUnit, WordPress runtime, browser, package, and Plugin Check CI
* Added: Reproducible release artifacts and protected WordPress.org deployment
