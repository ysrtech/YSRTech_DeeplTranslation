# YSRTech DeepL Translation

A Magento 1 / OpenMage module that translates product and category content between store views using the [DeepL REST API](https://www.deepl.com/docs-api).

## What it does

- Translates configurable product and category text attributes (name, description, etc.) from your default store view into any other active store view
- Supports both **flagged** translation (only items marked `auto_translate = Yes`) and **bulk** translation (everything, regardless of the flag)
- Batches all API calls efficiently — up to 50 strings per DeepL request
- Works with both **DeepL Free** and **DeepL Pro** API keys

## Ways to trigger translation

### 1. Manually from System Configuration
Go to **System → Configuration → Services → DeepL Translation** and use the *Manual Translation* panel. Pick a destination store view, then click one of the four buttons:

- **Translate ALL Categories** — translates every category
- **Translate Flagged Categories** — only categories with `auto_translate` set to Yes
- **Translate ALL Products** — translates every product (runs in batches to avoid timeouts)
- **Translate Flagged Products** — only products with `auto_translate` set to Yes

Output streams live into a console-style box on the page.

### 2. Per-item button in the admin edit page
A **Translate with DeepL** button appears in the toolbar of the product edit page and the category edit page. Click it, choose the destination store view, and translate just that one item.

### 3. Scheduled cron
Enable the cron in **System → Configuration → Services → DeepL Translation → Scheduled Translation**. When it runs, it translates all flagged items from the default store view to every other active store view automatically.

### 4. Shell script (CLI)
```bash
# Translate flagged items (default)
php -f shell/ysrtech_deepl_translate.php -- default nl_nl

# Translate all products only
php -f shell/ysrtech_deepl_translate.php -- default nl_nl --onlyProducts --allProducts

# Translate all categories only
php -f shell/ysrtech_deepl_translate.php -- default nl_nl --onlyCategories --allCategories
```

## Configuration

All settings live under **System → Configuration → Services → DeepL Translation**:

| Setting | Description |
|---|---|
| API Key | Your DeepL API key (stored encrypted) |
| Formality | Preferred formality level sent to DeepL |
| Product Attributes | Which product attributes to translate |
| Category Attributes | Which category attributes to translate |
| Cron Enable / Schedule | Turn on automatic scheduled translation |

## The `auto_translate` flag

Both products and categories get an `auto_translate` attribute (Yes/No, per store view). Set it to **Yes** on the destination store view to mark that item for translation on the next cron run or flagged manual run. The flag is automatically reset to **No** after a successful translation.

## Requirements

- Magento 1.x / OpenMage LTS
- PHP `curl` and `mbstring` extensions
- A DeepL API key (Free or Pro)

## Changelog

### 1.0.5
- **description and meta fields were never translated on stores with the flat product catalog
  enabled.** The product collection is loaded under emulation of the source store view, a
  frontend context, so with *Use Flat Catalog Product* = Yes it read the flat table, which only
  holds attributes marked "used in product listing". Every other configured attribute was
  silently skipped by all three entry points (cron, config page, edit button). The collection
  now forces the EAV tables via `catalog/product_flat` `disableFlatCollection()`.

### 1.0.4
- **Config-page buttons post to the admin controller.** The *Manual Translation* buttons used a
  frontend route (`ysrtech_deepl/translate/run`) with a one-time cache token. A URL built from
  the admin carries the admin store code, and OpenMage's standard router never matches for the
  admin store, so every click returned HTTP 404 (with or without a custom admin path). They now
  post to `adminhtml/deeplTranslate/run`, form-key and ACL protected like the edit-page buttons;
  that action accepts the four run types and the batch offset. The frontend controller, its
  router and a dead duplicate controller are removed.

### 1.0.3
- **Configurable queue flag**: *Queue Flag Attribute* (default `auto_translate`). A store
  migrating from Fballiano_FullCatalogTranslate can point it at `fb_translate` to keep the
  existing "Translate automatically?" checkbox.
- **Migration from Fballiano_FullCatalogTranslate**: `upgrade-1.0.1-1.0.2.php` copies existing
  `fb_translate` flags (products and categories, all store views) into `auto_translate`;
  `upgrade-1.0.2-1.0.3.php` then removes the `fb_translate` attributes, Fballiano's config rows
  and its setup record. Both are no-ops on a store that never had Fballiano.
- **URL rewrites refreshed after saving** a translated product or category, so a new `url_key`
  is live immediately and (with *Create Permanent Redirect for old URL* on) the old path becomes
  a 301. `catalog/product_action::updateAttributes()` and `saveAttribute()` skip the URL
  rewrite indexer, so before this the store's URL stayed stale until a full reindex.
- **Cron processes the whole queue**: the cron now loops over all product batches per store
  view, like the shell script; previously one run stopped after the first 20 products.
- **Cron skips destination store views in the source language** (e.g. a second website's
  Dutch view when the default store view is Dutch).
- **Log always written**: the module log is forced, so the store's Developer > Log Settings
  level no longer drops its INFO lines.
