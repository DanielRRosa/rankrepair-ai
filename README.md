# RankRepair AI

RankRepair AI is a WordPress SEO recovery plugin for auditing, repairing, and improving metadata at scale. It combines bulk SEO maintenance tools with optional AI-assisted suggestions for metadata, content drafts, image SEO, schema ideas, and internal linking.

Built for site owners, agencies, and migration projects where SEO metadata needs to be reviewed safely before changes are applied.

## Plugin Details

| Field | Value |
| --- | --- |
| Version | 3.4.8 |
| Requires WordPress | 6.0 or later |
| Tested up to | 7.0 |
| Requires PHP | 7.4 or later |
| License | GPLv2 or later |
| Text domain | `rankrepair-ai` |

## What It Does

RankRepair AI helps WordPress administrators find and fix SEO metadata problems across posts, pages, products, media, and public post types.

It is especially useful after site migrations, domain changes, builder rebuilds, content imports, SEO plugin changes, or long periods of inconsistent metadata editing.

## Core Features

- Scan content for missing, weak, duplicated, or suspicious SEO metadata.
- Review SEO titles, meta descriptions, and focus keywords before applying changes.
- Bulk edit metadata with safer before/after workflows.
- Generate optional AI suggestions through OpenAI, Anthropic Claude, or Google Gemini.
- Improve metadata directly from post and page listing screens.
- Back up SEO metadata before updates and roll back recent changes.
- Recover builder or HTML content into Gutenberg-friendly drafts.
- Draft content with SEO metadata, excerpts, FAQ/schema ideas, and image suggestions.
- Generate internal link suggestions with anchor text recommendations.
- Improve image SEO fields including alt text, title, caption, description, and filename ideas.

## Supported SEO Plugins

RankRepair AI can work with metadata from:

- Yoast SEO
- Rank Math
- SEOPress
- All in One SEO
- The SEO Framework
- Slim SEO
- SmartCrawl
- Custom meta keys

## AI Providers

AI features are optional and run only when an administrator triggers an AI action.

Supported provider families include:

- OpenAI
- Anthropic Claude
- Google Gemini

Depending on the selected action, RankRepair AI may send relevant post content, titles, excerpts, URLs, existing SEO metadata, prompts, image context, and generation instructions to the configured provider.

## Installation

1. Download or package the plugin folder as a ZIP file.
2. In WordPress, go to **Plugins > Add New > Upload Plugin**.
3. Upload and activate **RankRepair AI**.
4. Open the **RankRepair AI** admin menu.
5. Configure SEO plugin support and, optionally, an AI provider.
6. Run a small scan first and review suggested changes before applying them broadly.

## Recommended Workflow

1. Start on a staging site or a small set of posts.
2. Run a scan to identify metadata and image SEO issues.
3. Review results and apply manual edits where needed.
4. Use AI suggestions only for items that need assistance.
5. Preview changes before applying them.
6. Keep backups enabled so metadata can be restored if needed.

## Privacy And External Services

RankRepair AI does not send content to external AI providers automatically.

External requests happen only when an administrator explicitly uses an AI feature such as metadata improvement, content drafting, schema generation, image SEO assistance, or similar tools.

API keys are stored in WordPress options and are used only to authenticate requests to the configured provider.

Provider policies:

| Provider | Terms | Privacy Policy |
| --- | --- | --- |
| OpenAI | [Terms](https://openai.com/policies/terms-of-use) | [Privacy](https://openai.com/policies/privacy-policy) |
| Anthropic Claude | [Terms](https://www.anthropic.com/legal/consumer-terms) | [Privacy](https://www.anthropic.com/privacy) |
| Google Gemini | [Terms](https://policies.google.com/terms) | [Privacy](https://policies.google.com/privacy) |

Review the policy for your selected provider before using AI features on production content.

## Project Structure

```text
rankrepair-ai.php        Main plugin bootstrap file
includes/                Plugin classes and feature modules
assets/                  Admin assets and plugin icons
readme.txt               WordPress.org plugin directory readme
README.md                Project documentation
LICENSE                  GPLv2-or-later license notice
uninstall.php            Cleanup routine for plugin-owned data
```

## Assets

The plugin icon lives in `assets/`:

- `assets/icon.svg`
- `assets/icon-128x128.png`
- `assets/icon-256x256.png`

## Frequently Asked Questions

### Does RankRepair AI publish generated content automatically?

No. Content assistance features create drafts or pending-review content so an administrator can review everything before publishing.

### Can metadata changes be rolled back?

Yes. RankRepair AI stores metadata backups before updates so recent changes can be restored.

### Is an AI API key required?

No. Scanning and review workflows can be used without an AI provider. AI-assisted generation requires an API key for the provider selected in settings.

### Is this only for Yoast SEO?

No. RankRepair AI supports multiple SEO plugins and can also work with custom meta keys.

## Changelog

The WordPress.org-formatted changelog is maintained in [readme.txt](readme.txt).

## License

RankRepair AI is licensed under the GPLv2 or later. See [LICENSE](LICENSE) for details.
