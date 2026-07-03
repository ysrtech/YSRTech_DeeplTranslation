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
