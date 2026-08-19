=== Entity Link Engine ===
Contributors: eullrich
Tags: internal links, seo, geo, ai, rag
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic internal linking with entity mapping and fan-out queries — score-ranked links, not random ones.

== Description ==

Entity Link Engine builds the internal link structure of your site the way a retrieval system would: it maps the entities in your content to the posts that answer them, and only then inserts links.

= How it works =

1. **Entity mapping.** The plugin maintains an entity index of your content: post titles, headings, tags and categories become entities. You can also define a controlled vocabulary of manual entities (phrase + aliases + target post), which always wins over automatic extraction.
2. **Fan-out queries.** For every post, the engine generates many retrieval queries — the title, every heading, every mapped entity, taxonomy terms and title n-grams. One post becomes a fan-out of queries into your own content index.
3. **Retrieval and scoring.** Candidates are scored from multiple signals: taxonomy overlap, title and content term overlap, entity overlap, how many distinct fan-out query types retrieved the post, and existing editorial link relationships. A minimum score (default 2.5) filters weak candidates; the strongest ones are linked.
4. **Safe insertion.** Links are inserted at word boundaries only — never inside headings, code blocks, tables or quotes, never inside an existing link, never a duplicate URL, and at most one link per paragraph. Existing editorial links are always preserved.

= When does it run =

* **Automatic**: when a post is published (can be disabled in settings).
* **Manual**: from the post editor meta box ("Suggest links" shows scored candidates before anything is inserted; "Insert links" applies them; "Undo last run" restores the exact previous content).
* **Bulk**: run the engine over all posts in WP-Cron batches from the Bulk run page.

= Report =

The dashboard shows outgoing auto links, incoming links and orphans (posts without any incoming internal link) per post — the same link graph the reference implementation uses on eullrich.com.

= Privacy and external services =

By default this plugin is fully local: no data leaves your site. If you enable the optional semantic layer, the plugin sends short text excerpts (post titles, headings, excerpts) to an OpenAI-compatible embeddings endpoint you configure, and blends the returned similarity into the score. This is strictly opt-in, disclosed here, and failures degrade gracefully to the local lexical scoring.

= Per-post control =

* `_elink_auto_links` post meta = `0` disables the engine for a single post.
* `_elink_max_links` post meta overrides the maximum number of links for a single post.

== Installation ==

1. Upload the `entity-link-engine` folder to `/wp-content/plugins/`, or install the plugin through the WordPress admin.
2. Activate the plugin through the *Plugins* screen.
3. Go to *Entity Links → Bulk run* and click *Rebuild entity index* so the engine knows your content.
4. Optionally add manual entities under *Entity Links → Entity vocabulary* (phrase, aliases, target post).
5. Open a post, use the *Entity Link Engine* meta box to preview suggestions, or publish and let the engine run automatically.

== Frequently Asked Questions ==

= Does the plugin call any external AI service? =

Only if you explicitly enable the semantic layer in the settings and provide an API URL and key. Without that, everything runs locally on your server.

= Will the plugin change my existing links? =

No. Existing links are detected and preserved. The engine only inserts new links for URLs that are not yet present in the post.

= Can the engine link inside headings or code blocks? =

No. Headings, code blocks, pre, tables, blockquotes, figures and images are skipped by default (configurable in settings).

= How do I undo a run? =

Every applied run keeps a snapshot of the previous content. The post editor meta box offers *Undo last run*, which restores the exact content from before the run.

= Does it work with Gutenberg and classic editor? =

Yes. The engine processes the post content in both editors, including comment-delimited blocks.

= Why does my post not get three links? =

A post only receives links when candidates score above the minimum score and the anchor phrase actually appears in an insertable paragraph. Generic words are intentionally not linked.

== External services ==

This plugin optionally connects to an OpenAI-compatible embeddings API to score internal-link candidates semantically. This is strictly opt-in: the semantic layer is disabled by default, and no external request is made unless you enable it and configure your own API endpoint and key in the settings.

When enabled, the plugin sends short text excerpts (post titles, headings and excerpts) to the configured endpoint whenever the engine runs on a post (on publish, manual run or bulk run). The responses are used only to rank internal-link candidates and are cached locally. No data of your visitors is sent, and no personal data is collected.

The provider is user-configurable; the API is compatible with many providers. A common choice is OpenAI:

- OpenAI Terms of Use: https://openai.com/policies/terms-of-use
- OpenAI Privacy Policy: https://openai.com/policies/privacy-policy

== Changelog ==

= 1.0.0 =
* Initial release: entity mapping, fan-out query retrieval, score-ranked internal link insertion, dashboard report, per-post overrides, optional OpenAI-compatible embeddings.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
