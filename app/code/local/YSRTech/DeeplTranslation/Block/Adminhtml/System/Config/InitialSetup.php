<?php
/**
 * Renders the Initial Setup / Manual Translation UI in System > Configuration.
 */
class YSRTech_DeeplTranslation_Block_Adminhtml_System_Config_InitialSetup
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $defaultStore  = Mage::app()->getDefaultStoreView();
        $storeOptions  = '';
        $hasStores     = false;

        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            if ($store->getId() == $defaultStore->getId()) {
                continue;
            }
            $hasStores    = true;
            $code         = htmlspecialchars($store->getCode(), ENT_QUOTES, 'UTF-8');
            $label        = htmlspecialchars(
                $store->getName() . ' (' . $store->getCode() . ')',
                ENT_QUOTES,
                'UTF-8'
            );
            $storeOptions .= "<option value=\"{$code}\">{$label}</option>";
        }

        if (!$hasStores) {
            return '<p style="color:#999;">No non-default active store views found.</p>';
        }

        $ajaxUrl = Mage::getUrl('ysrtech_deepl/translate/run');
        $token   = md5(uniqid('ysrtech_deepl', true));
        Mage::app()->getCache()->save('1', 'ysrtech_deepl_token_' . $token, array(), 3600);

        $confirmCat  = 'WARNING: This will translate ALL categories using your DeepL API quota, regardless of the auto_translate flag.\n\nThis can be costly for large catalogues. Continue?';
        $confirmProd = 'WARNING: This will translate ALL products using your DeepL API quota, regardless of the auto_translate flag.\n\nThis can be costly for large catalogues. Continue?';

        return <<<HTML
<div id="ysrtech-deepl-setup" style="padding:4px 0;">
    <table cellspacing="0" cellpadding="4" style="margin-bottom:12px;">
        <tr>
            <td><strong>Destination Store View:</strong></td>
            <td>
                <select id="ysrtech_deepl_store_select" style="min-width:200px;">
                    {$storeOptions}
                </select>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 5px 0; font-weight:bold; color:#333;">Categories</p>
    <button type="button" class="scalable" onclick="ysrtechDeeplRun('categories_all')" id="ysrtech_btn_cat_all">
        <span>Translate ALL Categories</span>
    </button>
    &nbsp;
    <button type="button" class="scalable" onclick="ysrtechDeeplRun('categories_flagged')" id="ysrtech_btn_cat_flagged">
        <span>Translate Flagged Categories</span>
    </button>

    <p style="margin:14px 0 5px 0; font-weight:bold; color:#333;">Products</p>
    <button type="button" class="scalable" onclick="ysrtechDeeplRun('products_all')" id="ysrtech_btn_prod_all">
        <span>Translate ALL Products</span>
    </button>
    &nbsp;
    <button type="button" class="scalable" onclick="ysrtechDeeplRun('products_flagged')" id="ysrtech_btn_prod_flagged">
        <span>Translate Flagged Products</span>
    </button>

    <p style="margin:14px 0 0 0; padding:8px 10px; background:#f8f8d8; border:1px solid #e0d060; border-radius:3px; color:#555; font-size:11px; line-height:1.7; max-width:580px;">
        <strong>Flagged</strong> = only items with the <em>auto_translate</em> attribute set to Yes on the selected store view.<br>
        <strong>ALL</strong> = every item regardless of the flag &mdash; use with care, this can consume a large amount of your DeepL API quota.<br>
        The source is always the default store view. Keep this tab open until the run completes.
    </p>

    <div id="ysrtech_deepl_output"
         style="display:none; margin-top:14px; background:#1c1c1c; color:#d4d4d4;
                border:1px solid #444; border-radius:4px; padding:12px;
                max-height:350px; overflow-y:auto;
                font-family:Consolas,'Courier New',monospace; font-size:12px;
                white-space:pre-wrap; line-height:1.5;">
    </div>
</div>
<script type="text/javascript">
//<![CDATA[
var ysrtechDeeplToken = '{$token}';
window.ysrtechDeeplRun = function(type, batchOffset) {
    batchOffset = batchOffset || 0;
    var storeCode = $('ysrtech_deepl_store_select').value;
    var output    = $('ysrtech_deepl_output');
    var btnIds    = ['ysrtech_btn_cat_all','ysrtech_btn_cat_flagged','ysrtech_btn_prod_all','ysrtech_btn_prod_flagged'];

    if (!storeCode) {
        alert('Please select a destination store view.');
        return;
    }

    if (batchOffset === 0) {
        if (type === 'categories_all') {
            if (!confirm('{$confirmCat}')) { return; }
        }
        if (type === 'products_all') {
            if (!confirm('{$confirmProd}')) { return; }
        }
        output.style.display = 'block';
        output.innerHTML     = 'Running \u2014 please wait\u2026\\n';
        btnIds.each(function(id) { if ($(id)) { $(id).disabled = true; } });
    }

    new Ajax.Request('{$ajaxUrl}', {
        method:     'post',
        parameters: { token: ysrtechDeeplToken, store_code: storeCode, type: type, batch_offset: batchOffset },
        onSuccess: function(response) {
            try {
                var json = response.responseText.evalJSON();
                if (json.new_token) { ysrtechDeeplToken = json.new_token; }
                output.innerHTML += (json.output || '') + '\\n';
                output.scrollTop  = output.scrollHeight;
                if (json.next_offset !== null && json.next_offset !== undefined) {
                    output.innerHTML += '--- Continuing batch (offset ' + json.next_offset + ') ---\\n';
                    window.ysrtechDeeplRun(type, json.next_offset);
                    return;
                }
            } catch (e) {
                output.innerHTML += response.responseText;
            }
        },
        onFailure: function(response) {
            output.innerHTML += 'Request failed (HTTP ' + response.status + ').';
        },
        onComplete: function() {
            btnIds.each(function(id) { if ($(id)) { $(id).disabled = false; } });
            output.scrollTop = output.scrollHeight;
        }
    });
}
//]]>
</script>
HTML;
    }
}
