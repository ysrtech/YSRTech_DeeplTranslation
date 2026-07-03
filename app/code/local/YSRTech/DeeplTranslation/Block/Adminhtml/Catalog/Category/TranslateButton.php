<?php
/**
 * YSRTech_DeeplTranslation_Block_Adminhtml_Catalog_Category_TranslateButton
 *
 * Rendered once in before_body_end on the full category management page.
 * Outputs the persistent modal overlay and a script that injects the
 * "Translate with DeepL" toolbar button — and re-injects it via
 * MutationObserver whenever the category form is replaced by AJAX.
 *
 * The current category ID is supplied by a tiny <script> tag appended to
 * each AJAX category edit response by the Observer model.
 */
class YSRTech_DeeplTranslation_Block_Adminhtml_Catalog_Category_TranslateButton
    extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $helper = Mage::helper('ysrtech_deepltranslation');
        if (!$helper->isEnabled() || !$helper->isShowEditButtons() || !$helper->getApiKey()) {
            return '';
        }

        $defaultStore = Mage::app()->getDefaultStoreView();
        $storeOptions = '';

        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            if ($store->getId() == $defaultStore->getId()) {
                continue;
            }
            $code         = htmlspecialchars($store->getCode(), ENT_QUOTES, 'UTF-8');
            $label        = htmlspecialchars(
                $store->getName() . ' (' . $store->getCode() . ')',
                ENT_QUOTES,
                'UTF-8'
            );
            $storeOptions .= "<option value=\"{$code}\">{$label}</option>";
        }

        if (!$storeOptions) {
            $storeOptions = '<option value="">(No non-default store views found)</option>';
        }

        $ajaxUrl = $this->getUrl('adminhtml/deeplTranslate/translateCategory');
        $formKey = Mage::getSingleton('core/session')->getFormKey();

        // If a category is already in registry on full-page load, capture its ID
        $category       = Mage::registry('category');
        $initialCatId   = ($category && $category->getId()) ? (int)$category->getId() : 0;

        return <<<HTML
<!-- YSRTech DeepL: Category Translate Modal (persistent, full-page) -->
<div id="ysrtech-deepl-cat-overlay"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.55);z-index:9000;"
     onclick="if(event.target===this){ysrtechDeeplCatClose();}">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                background:#fff;padding:24px 28px;border-radius:4px;width:540px;
                max-width:92vw;box-shadow:0 6px 24px rgba(0,0,0,.35);">
        <h3 style="margin:0 0 14px;font-size:15px;color:#333;border-bottom:1px solid #e0e0e0;padding-bottom:10px;">
            Translate Category with DeepL
        </h3>
        <p style="margin:0 0 14px;font-size:13px;color:#555;">
            Source: <strong>Default Store View</strong>
        </p>
        <div style="margin-bottom:16px;">
            <label for="ysrtech-deepl-cat-store"
                   style="display:block;margin-bottom:5px;font-weight:bold;font-size:13px;">
                Destination Store View
            </label>
            <select id="ysrtech-deepl-cat-store" style="width:100%;padding:5px 4px;font-size:13px;">
                {$storeOptions}
            </select>
        </div>
        <div style="margin-bottom:14px;">
            <button type="button" id="ysrtech-deepl-cat-btn"
                    onclick="ysrtechDeeplCatRun()"
                    class="scalable save"
                    style="margin-right:8px;">
                <span>Translate</span>
            </button>
            <button type="button" onclick="ysrtechDeeplCatClose()" class="scalable">
                <span>Close</span>
            </button>
        </div>
        <div id="ysrtech-deepl-cat-output"
             style="display:none;background:#1c1c1c;color:#d4d4d4;
                    border:1px solid #444;border-radius:4px;padding:12px;
                    max-height:260px;overflow-y:auto;
                    font-family:Consolas,'Courier New',monospace;
                    font-size:12px;white-space:pre-wrap;line-height:1.5;">
        </div>
    </div>
</div>
<script type="text/javascript">
//<![CDATA[
window.ysrtechDeeplCurrentCatId = {$initialCatId};

function ysrtechDeeplCatInjectBtn() {
    if (\$('ysrtech_deepl_cat_toolbar_btn')) { return; }
    var anchor = \$\$('#category-edit-container button.scalable.save, #category-edit-container button.scalable')[0]
                 || \$\$('button.scalable.save')[0]
                 || \$\$('button.scalable')[0];
    if (!anchor || !anchor.parentNode) { return; }
    var btn = new Element('button', {
        type:    'button',
        id:      'ysrtech_deepl_cat_toolbar_btn',
        'class': 'scalable'
    });
    btn.update('<span>Translate with DeepL</span>');
    btn.observe('click', function() { ysrtechDeeplCatModal(); });
    anchor.insert({before: btn});
    anchor.insert({before: '&nbsp;'});
}

function ysrtechDeeplCatModal() {
    if (!window.ysrtechDeeplCurrentCatId) {
        alert('No category selected.');
        return;
    }
    var out = \$('ysrtech-deepl-cat-output');
    out.style.display = 'none';
    out.innerHTML     = '';
    \$('ysrtech-deepl-cat-overlay').style.display = 'block';
}
function ysrtechDeeplCatClose() {
    \$('ysrtech-deepl-cat-overlay').style.display = 'none';
}
function ysrtechDeeplCatRun() {
    var storeCode = \$('ysrtech-deepl-cat-store').value;
    var output    = \$('ysrtech-deepl-cat-output');
    var btn       = \$('ysrtech-deepl-cat-btn');

    if (!storeCode) {
        alert('No destination store view available.');
        return;
    }

    output.style.display = 'block';
    output.innerHTML     = 'Translating\u2026';
    btn.disabled         = true;

    new Ajax.Request('{$ajaxUrl}', {
        method:     'post',
        parameters: {
            form_key:        '{$formKey}',
            category_id:     window.ysrtechDeeplCurrentCatId,
            dest_store_code: storeCode
        },
        onSuccess: function(response) {
            try {
                var json = response.responseText.evalJSON();
                output.innerHTML = json.output || 'Completed.';
            } catch (e) {
                output.innerHTML = response.responseText;
            }
        },
        onFailure: function(response) {
            output.innerHTML = 'Request failed (HTTP ' + response.status + ').';
        },
        onComplete: function() {
            btn.disabled     = false;
            output.scrollTop = output.scrollHeight;
        }
    });
}

// Inject button on initial full page load
document.observe('dom:loaded', ysrtechDeeplCatInjectBtn);

// Re-inject whenever the category form container is updated via AJAX
if (window.MutationObserver) {
    document.observe('dom:loaded', function() {
        var target = document.body;
        new MutationObserver(function() {
            ysrtechDeeplCatInjectBtn();
        }).observe(target, { childList: true, subtree: true });
    });
} else {
    // Fallback for older browsers
    setInterval(ysrtechDeeplCatInjectBtn, 600);
}
//]]>
</script>
HTML;
    }
}
