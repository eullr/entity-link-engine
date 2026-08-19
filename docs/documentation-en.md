# Entity Link Engine — Documentation

**Version:** 1.0.0 · **Author:** Eugen Ullrich · **License:** GPLv2 or later

Entity Link Engine builds your site's internal link structure the way a retrieval
system would: it maps the **entities** in your content to the posts that answer
them, expands every post into **fan-out queries**, scores the retrieved
candidates, and only then inserts internal links. It never inserts random or
weak links.

---

## 1. How it works

Every run is a four-stage pipeline:

### 1.1 Entity mapping

The plugin maintains an **entity index**: a table mapping every indexed phrase
to the post(s) it points at. The index is built from two sources:

| Source | What is indexed | Weight |
|---|---|---|
| Post title | Full title (2–8 words) | 100 |
| Post title | Long single words (≥ 5 chars) | 25 |
| Headings | H2/H3 text (2–6 words, non-instructional) | 60 |
| Tags | Tag names | 50 |
| Categories | Category names | 40 |
| **Manual vocabulary** | Phrase + aliases + target post | 1000 + priority |

Before matching, every phrase is **normalized**: lowercase, diacritics stripped
(NFKD), `ß → ss`, whitespace collapsed. "SEO-Audit" and "SEO Audit" become one
key (`seo-audit`).

The **manual vocabulary always wins** over auto-extracted entities. Define
phrases that are unambiguous and multi-word (exactly like a controlled
vocabulary should be) — short generic words produce weak anchors by design and
are intentionally not indexed.

Mention matching enforces **word boundaries** and treats hyphens and
underscores as part of a word, so the engine never links the "audit" inside
"GEO-Audit", but does link "GEO-Audit" as a whole.

### 1.2 Fan-out queries

One post becomes many retrieval queries, each with a weight:

| Query type | Source | Weight |
|---|---|---|
| `title` | Full post title | 1.0 |
| `heading` | Every H2/H3 | 0.8 |
| `entity` | Every mapped entity mention | 0.7 |
| `tag` | Every tag | 0.6 |
| `category` | Every category | 0.5 |
| `ngram` | Title bigrams | 0.4 |

Each query is tokenized (stopwords removed) and run against the entity index.

### 1.3 Retrieval and scoring

Candidates are scored from six signal groups:

| Signal | Contribution |
|---|---|
| Shared tags | +2.0 each (max 4) |
| Shared categories | +1.5 each (max 3) |
| Shared objectives (`_elink_objectives`) | +3.0 each (max 3) |
| Same format (`_elink_use_tag`) | +0.5 |
| Title term overlap | +1.25 each (max 4) |
| Supporting term overlap | +0.25 each (max 4) |
| Fan-out coverage (distinct query types) | +0.75 each (max 4) |
| Source already links candidate | +2.5 |
| Candidate already links source | +1.5 |

Candidates below the **minimum score** (default 2.5) are dropped; the strongest
`max_links` (default 3) are kept, ties broken by recency.

### 1.4 Safe insertion

Links are inserted only when every rule holds:

- word boundaries only (never inside a longer word);
- never inside headings, code, `pre`, tables, blockquotes, figures or images;
- never inside an existing link;
- never a URL that is already present in the post;
- at most **one auto link per paragraph** (also across repeated runs);
- existing editorial links are never modified or removed.

Inserted links carry `class="elink-auto-link"` and `data-elink="<post-id>"` for
reporting and safe re-runs.

---

## 2. Installation

1. Upload the `entity-link-engine` folder to `/wp-content/plugins/`, or install
   via the WordPress admin.
2. Activate the plugin on the **Plugins** screen.
3. Go to **Entity Links → Bulk run** and click **Rebuild entity index**.

Requires WordPress 6.0+ and PHP 7.4+.

---

## 3. Quick start

1. **Rebuild the index** — *Entity Links → Bulk run → Rebuild entity index*.
2. **Add manual entities** — *Entity Links → Entity vocabulary*: enter the
   phrase, comma-separated aliases and the target post for your most important
   pages (e.g. `SEO-Audit → /seo-audit/`).
3. **Preview suggestions** — open any post; the *Entity Link Engine* meta box
   shows scored candidates via **Suggest links**.
4. **Apply or undo** — **Insert links** applies them; **Undo last run** restores
   the exact previous content.
5. **Let it run automatically** — publishing a post runs the engine
   automatically (default on; disable in settings).

---

## 4. Settings reference

*Entity Links → Settings.*

| Setting | Default | Description |
|---|---|---|
| Post types | `post` | Content types the engine scans and links. |
| Max links per post | 3 | Upper bound per run. Per-post override: `_elink_max_links`. |
| Min score | 2.5 | Candidates below this score are not linked. |
| Automatic run on publish | on | Run the engine when a post is published. |
| Add CSS class | on | Adds `elink-auto-link` (class name configurable). |
| Skip blocks | h1–h6, pre, code, table, blockquote, figure, img | Block types that never receive auto links. |
| Semantic layer | off | Optional OpenAI-compatible embeddings (see §8). |

---

## 5. Entity vocabulary

*Entity Links → Entity vocabulary.*

Each manual entity maps a phrase (plus aliases) to exactly one target post:

- **Entity name** — the primary phrase (e.g. `GEO-Audit`).
- **Aliases** — comma-separated alternates (`GEO Audit`).
- **Target post** — the destination.
- **Priority** — higher wins on ties; manual entities always outrank
  auto-extracted ones.

Aliases are matched with word boundaries, longest first. A target is linked at
most once per post.

---

## 6. Editor workflow (meta box)

The *Entity Link Engine* meta box appears on all enabled post types.

- **Suggest links** — dry-run: shows candidates with scores and reasons, changes
  nothing.
- **Insert links** — applies the run and stores a snapshot.
- **Undo last run** — restores the exact content from before the run.

The meta box also shows the last run's result and whether auto-linking is
disabled for that post.

---

## 7. Bulk run

*Entity Links → Bulk run.*

- **Rebuild entity index** — re-extracts entities from all posts.
- **Start bulk run** — processes all posts in WP-Cron batches (5 per tick) and
  keeps a per-post snapshot, so every run can be undone in the editor.

---

## 8. Semantic layer (optional)

By default the plugin is **fully local** — no data leaves your site.

If you enable the semantic layer, the plugin sends short text excerpts (post
titles, headings, excerpts) to an **OpenAI-compatible embeddings endpoint** you
configure, and blends the returned similarity into the score:

```
final score = lexical score + blend × 5 × mean cosine similarity
```

- **API URL** — base URL, user-configured (no default; the field hints at `https://api.openai.com/v1`).
- **API key** — your key.
- **Model** — e.g. `text-embedding-3-small`.
- **Blend weight** — 0–1 (default 0.4).

Embeddings are cached in post meta; failures degrade gracefully to the local
lexical scoring. This is an external service call and is disclosed here and in
the plugin's readme.

---

## 9. Per-post overrides (post meta)

| Meta key | Effect |
|---|---|
| `_elink_auto_links` | Set to `0` to disable auto-linking for this post. |
| `_elink_max_links` | Override the max links per run for this post. |
| `_elink_objectives` | Comma-separated list; shared objectives add +3.0 each. |
| `_elink_use_tag` | Format label; shared value adds +0.5. |
| `_elink_lang` | Language marker; stored on index rows for multilingual sites. |

---

## 10. REST API

Base: `/wp-json/entity-link-engine/v1/` (requires edit capabilities).

| Endpoint | Method | Body | Effect |
|---|---|---|---|
| `/suggest` | POST | `{ "post_id": 123 }` | Dry-run candidates (no changes). |
| `/run` | POST | `{ "post_id": 123 }` | Apply the run. |
| `/undo` | POST | `{ "post_id": 123 }` | Restore the snapshot. |
| `/rebuild` | POST | — | Rebuild the entity index (manage_options). |

All endpoints require a REST nonce (`X-WP-Nonce`) and validate `edit_post` /
`manage_options` capabilities.

---

## 11. Hooks and filters

- **`elink_manual_entities`** — filter to add entities from code:
  `array( 'entity_label' => string, 'aliases' => array, 'target_post_id' => int, 'priority' => int )`.
- **`elink_bulk_tick`** — cron event that drives the bulk run.

---

## 12. Data model

**Tables** (prefix `wp_elink_`):

- `elink_entity_index` — entity key, label, post id, source, weight, lang.
- `elink_links` — source id, target id, anchor, score, mode, status, created.

**Options:** `elink_settings`, `elink_entities_manual`, `elink_index_built`,
`elink_bulk_*`.

**Post meta:** `_elink_snapshot`, `_elink_inserted_links`, `_elink_last_run`,
`_elink_embedding`, `_elink_auto_links`, `_elink_max_links`, `_elink_objectives`,
`_elink_use_tag`, `_elink_lang`.

Uninstall removes all options, tables and `_elink_*` post meta.

---

## 13. Frequently asked questions

**Does the plugin call an external AI service?**
Only if you explicitly enable the semantic layer and provide an API URL and key.
Without that, everything runs locally.

**Will it change my existing links?**
No. Existing links are detected and preserved; the engine only adds links for
URLs not yet present in the post.

**Can it link inside headings or code?**
No. Headings, code, pre, tables, blockquotes, figures and images are skipped
(configurable).

**Why does my post not get three links?**
A post only receives links when candidates score above the minimum score *and*
their anchor phrase actually appears in an insertable paragraph. Generic words
are intentionally not linked.

**How do I undo?**
The editor meta box offers *Undo last run*; it restores the exact pre-run
content from the stored snapshot.

**Does it work with Gutenberg and the classic editor?**
Yes, including comment-delimited blocks and single-newline classic markup.

---

## 14. Troubleshooting

- **No candidates appear** — the entity index is probably empty. Rebuild it
  (*Bulk run → Rebuild entity index*). Manual entities need their target post to
  exist (resolved by slug).
- **Anchors are weak/generic** — add manual entities for your most important
  pages; auto-extracted single words are a fallback, not the primary signal.
- **Semantic layer not contributing** — check the API URL/key, and that the
  endpoint is reachable; on failure the plugin silently falls back to lexical
  scoring.
- **Links appear in the wrong place** — review *Skip blocks* in settings.

---

## 15. Privacy and data handling

- No data leaves the site unless the optional semantic layer is enabled.
- With the semantic layer enabled, short text excerpts (titles, headings,
  excerpts) are sent to the configured embeddings endpoint; responses are used
  only to score candidates and are cached locally.
- No personal data is collected. Uninstalling removes all plugin data.

---

## 16. License and author

**Entity Link Engine** is free software released under the
[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Author: **Eugen Ullrich** — <https://eullrich.com>
