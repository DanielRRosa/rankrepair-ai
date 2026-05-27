=== RankRepair AI ===
Contributors: drosa
Tags: seo, ai, metadata, content, optimization
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.4.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted SEO repair, bulk metadata editing, content drafting, image SEO, and internal link suggestions for WordPress.

== Description ==
RankRepair AI is evolving into a WordPress-native SEO and content recovery copilot. Version 3.4.7 improves quick AI repair UX, respects active SEO plugin title suffix variables, and updates dashboard/listing values immediately after AI improvements without requiring a page refresh.


RankRepair AI helps WordPress admins repair, improve, and scale SEO metadata across popular SEO plugins. It was built for migration cleanup, damaged metadata, weird keywords, old-domain references, duplicated titles, and large sites with hundreds or thousands of posts.

Supported SEO targets include Yoast SEO, Rank Math, SEOPress, All in One SEO, The SEO Framework, Slim SEO, SmartCrawl, and custom meta keys.

Main features:

* Scan posts, pages, products, and public post types.
* Find suspicious SEO titles, descriptions, and focus keywords.
* Bulk edit metadata with safe before/after previews.
* Generate AI SEO suggestions using OpenAI, Claude/Anthropic, or Gemini/Google.
* Add an Improve with AI action to post/page listings.
* Backup and rollback SEO metadata.
* AI Content Assistant, Builder/HTML to Gutenberg Recovery for draft creation with SEO metadata, excerpt, FAQ/schema, and image suggestions.
* Internal Link Assistant for anchor text/link suggestions.
* Image SEO assistant for alt text, title, caption, description, and recommended filenames.

== Privacy ==

This plugin sends selected post content, metadata, prompts, or image context to the external AI provider selected by the administrator only when the administrator explicitly requests AI generation.

Supported providers may include OpenAI, Anthropic Claude, and Google Gemini. The plugin itself does not sell or transmit data to third parties outside the selected AI provider request. Review your selected provider's data policies before use.

API keys are stored in WordPress options. Use on staging before production.


== External services ==

RankRepair AI can connect to third-party AI providers selected and configured by the site administrator. These services are used to generate SEO metadata, content suggestions, schema markup, image metadata, rewrite previews, content plans, and other AI-assisted suggestions.

Requests to external AI providers are sent only when an administrator explicitly uses an AI feature such as Improve with AI, Generate SEO, Generate Schema, Content Assistant, Image SEO, or similar AI actions.

Depending on the feature used, the plugin may send post or page titles, excerpts, content, existing SEO metadata, prompts entered by the administrator, URLs, image context, and related site context needed to generate the requested suggestion. API keys are stored in this WordPress site options table and are used only to authenticate requests to the selected provider.

OpenAI
Service: AI content and SEO generation.
Data sent: selected post/page content, titles, excerpts, SEO metadata, prompts, URLs, image context, and generation instructions.
Terms of use: https://openai.com/policies/terms-of-use
Privacy policy: https://openai.com/policies/privacy-policy

Anthropic Claude
Service: AI content and SEO generation.
Data sent: selected post/page content, titles, excerpts, SEO metadata, prompts, URLs, image context, and generation instructions.
Terms of service: https://www.anthropic.com/legal/consumer-terms
Privacy policy: https://www.anthropic.com/privacy

Google Gemini
Service: AI content and SEO generation.
Data sent: selected post/page content, titles, excerpts, SEO metadata, prompts, URLs, image context, and generation instructions.
Terms of service: https://policies.google.com/terms
Privacy policy: https://policies.google.com/privacy

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate RankRepair AI.
3. Open RankRepair AI in the WordPress admin menu.
4. Configure your SEO plugin and AI provider.
5. Start with a small scan and preview changes before applying.

== Frequently Asked Questions ==

= Does it only work with Yoast? =

No. It supports Yoast, Rank Math, SEOPress, AIOSEO, The SEO Framework, Slim SEO, SmartCrawl, and custom meta keys.

= Does it auto-publish AI content? =

No. The Content Assistant creates drafts or pending-review posts. Review before publishing.

= Can I rollback metadata changes? =

Yes. The plugin stores a latest backup before updates.

== Changelog ==

= 2.8.0 =
* Added Backup & Rollback Center for JSON snapshots.
* Added Activity Log for RankRepair-related SEO/content/schema changes.
* Added Broken Link and Redirect Helper for migration cleanup.
* Added Media Optimizer audit for missing image metadata and filename issues.
* Added Cost & Safety Center with token estimation and compatibility safeguards.
* Added WP-CLI command stubs for agency workflows.
* Added multilingual and dynamic builder/Crocoblock safety settings.

= 2.6.0 =
* Added RankRepair AI editor sidebar/meta box for posts, pages, products, and enabled public post types.
* Added list table integrations for enabled post types.
* Added Safe Compatibility Mode for Elementor, Divi, WPBakery, Beaver Builder, and Crocoblock/JetEngine-heavy sites.
* Added settings to control editor tools, list tools, post type coverage, schema rendering, and content overwrite protection.
* Metadata-only AI fixes remain available while risky content rewrites/recovery are blocked on builder-managed content by default.


= 2.1.0 =
* Added AI Content Assistant, Builder/HTML to Gutenberg Recovery.
* Added Internal Link Assistant.
* Added Image SEO Metadata assistant with apply action.
* Improved professional navigation and ecosystem positioning.

= 2.0.0 =
* Renamed to RankRepair AI.
* Added broader SEO plugin support.
* Added post listing Improve with AI action.


= 2.2.0 =
* Added Builder and HTML to Gutenberg Content Recovery module.
* Scans current content, revisions, Elementor data, Divi shortcodes, and raw HTML.
* Converts Divi modules, Elementor widgets, headings, paragraphs, lists, tables, images, buttons, and raw HTML into Gutenberg-friendly blocks.
* Adds preview-before-apply workflow and stores a content backup before replacement.


= Version 2.3.0 =
* Expanded Content Recovery from Divi-only to Builder/HTML recovery.
* Added Elementor JSON conversion for headings, text, images, buttons, boxes, accordions/toggles, and HTML widgets.
* Added raw HTML to Gutenberg conversion with heading hierarchy preservation.
* Added source selector: Auto detect, Divi, Elementor, or HTML/current content.

= 2.4.0 =
* Added AI Content Audit Center.
* Added AI Bulk Rewrite preview/apply workflow.
* Added AI Schema Builder for JSON-LD storage.
* Added AI Content Planner and topic cluster roadmap generator.
* Improved navigation for strategy, audit, rewrite, and schema workflows.


= 2.8.0 =
* Added Backup & Rollback Center for JSON snapshots.
* Added Activity Log for RankRepair-related SEO/content/schema changes.
* Added Broken Link and Redirect Helper for migration cleanup.
* Added Media Optimizer audit for missing image metadata and filename issues.
* Added Cost & Safety Center with token estimation and compatibility safeguards.
* Added WP-CLI command stubs for agency workflows.
* Added multilingual and dynamic builder/Crocoblock safety settings.

= 2.6.0 =
* Added Image Recovery & Replacement module for missing featured images, broken attachment references, old-domain image URLs, and missing alt text.
* Added AI image guidance with search query, image prompt, alt text, caption, and recommended filename.
* Added safe featured image replacement by Media Library attachment ID or legal image URL with backup.

= 2.9.0 =
* Simplified admin navigation into essential pages.
* Full-width WordPress-native layout improvements.
* Fixed oversized checkbox styling.
* Added sortable RankRepair AI column in post/page lists for SEO health.


= 3.1.0 =
* Fixed admin overflow issues and checkbox sizing.
* Added bulk media Improve with AI workflow.
* Added provider/model-aware automatic AI throttling and removed manual delay control.
* Improved editor-side content tools availability and AI workflow messaging.

= 3.2.0 =
* Fixed SEO health logic so missing SEO title, meta description, or focus keyword are always issues.
* Improved dashboard with actionable SEO issue links and image issue thumbnails.
* Removed scan depth from the user interface and moved deeper scanning behind the scenes.
* Reworked settings into cleaner feature columns with only practical fields.
* Added stronger overflow and checkbox CSS fixes for WordPress admin screens.


= 3.4.1 =
* Added first-run Launch Setup when AI credentials are missing.
* Added uninstall cleanup for RankRepair-owned database data.
* Reorganized scan services under Scan & Repair.
* Converted Settings layout into clearer row-based groups.
* Reduced sidebar/menu clutter from advanced tools.


== Changelog ==

= 3.4.1 =
* Respect active SEO plugin title suffix variables such as %%sep%% %%sitename%%, %sep% %sitename%, and provider-specific equivalents.
* Dashboard repair buttons now improve the item in place instead of redirecting to the scan page.
* Quick AI actions update visible SEO values/status immediately after success.
* Improved status recalculation after quick AI metadata updates.
