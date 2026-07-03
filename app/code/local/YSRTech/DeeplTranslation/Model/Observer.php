<?php
/**
 * YSRTech_DeeplTranslation_Model_Observer
 *
 * Appends the "Translate with DeepL" modal + toolbar button (via JS) to the
 * admin product edit page.
 */
class YSRTech_DeeplTranslation_Model_Observer
{
    /**
     * Appends the translation modal overlay + JS after the product edit block HTML.
     * The JS also injects a toolbar button via DOM manipulation (avoids addButton
     * compatibility issues across OpenMage versions).
     *
     * Event: core_block_abstract_to_html_after
     *
     * @param Varien_Event_Observer $observer
     */
    public function appendProductTranslateModal(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        if (!($block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit)) {
            return;
        }

        $transport = $observer->getEvent()->getTransport();
        $transport->setHtml($transport->getHtml() . $this->_buildModalHtml($block));
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Builds the HTML + JS for the modal overlay and toolbar button injection.
     *
     * @param  Mage_Adminhtml_Block_Catalog_Product_Edit $block
     * @return string
     */
    protected function _buildModalHtml(Mage_Adminhtml_Block_Catalog_Product_Edit $block)
    {
        $productId    = (int)$block->getProduct()->getId();
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
            // No non-default stores — button will still be visible but modal will say so
            $storeOptions = '<option value="">(No non-default store views found)</option>';
        }

        $ajaxUrl = Mage::getSingleton('adminhtml/url')
            ->getUrl('adminhtml/deeplTranslate/translateProduct');
        $formKey = Mage::getSingleton('core/session')->getFormKey();

        return <<<HTML
<!-- YSRTech DeepL: Product Translate Modal -->
<div id="ysrtech-deepl-product-overlay"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.55);z-index:9000;"
     onclick="if(event.target===this){ysrtechDeeplProductClose();}">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                background:#fff;padding:24px 28px;border-radius:4px;width:540px;
                max-width:92vw;box-shadow:0 6px 24px rgba(0,0,0,.35);">
        <h3 style="margin:0 0 14px;font-size:15px;color:#333;border-bottom:1px solid #e0e0e0;padding-bottom:10px;">
            Translate Product with DeepL
        </h3>
        <p style="margin:0 0 14px;font-size:13px;color:#555;">
            Source: <strong>Default Store View</strong>
        </p>
        <div style="margin-bottom:16px;">
            <label for="ysrtech-deepl-prod-store"
                   style="display:block;margin-bottom:5px;font-weight:bold;font-size:13px;">
                Destination Store View
            </label>
            <select id="ysrtech-deepl-prod-store" style="width:100%;padding:5px 4px;font-size:13px;">
                {$storeOptions}
            </select>
        </div>
        <div style="margin-bottom:14px;">
            <button type="button" id="ysrtech-deepl-prod-btn"
                    onclick="ysrtechDeeplProductRun()"
                    class="scalable save"
                    style="margin-right:8px;">
                <span>Translate</span>
            </button>
            <button type="button" onclick="ysrtechDeeplProductClose()" class="scalable">
                <span>Close</span>
            </button>
        </div>
        <div id="ysrtech-deepl-prod-output"
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
// Inject toolbar button via DOM — avoids addButton() version incompatibilities
document.observe('dom:loaded', function() {
    if (\$('ysrtech_deepl_toolbar_btn')) { return; } // already added
    // Look for the save button or any scalable button in the header area
    var anchor = \$\$('button.scalable.save')[0] || \$\$('button.scalable')[0];
    if (!anchor || !anchor.parentNode) { return; }
    var btn = new Element('button', {
        type:  'button',
        id:    'ysrtech_deepl_toolbar_btn',
        'class': 'scalable'
    });
    btn.update('<span>Translate with DeepL</span>');
    btn.observe('click', function() { ysrtechDeeplProductModal(); });
    anchor.insert({before: btn});
    anchor.insert({before: '&nbsp;'});
});
function ysrtechDeeplProductModal() {
    var out = \$('ysrtech-deepl-prod-output');
    out.style.display = 'none';
    out.innerHTML     = '';
    \$('ysrtech-deepl-product-overlay').style.display = 'block';
}
function ysrtechDeeplProductClose() {
    \$('ysrtech-deepl-product-overlay').style.display = 'none';
}
function ysrtechDeeplProductRun() {
    var storeCode = \$('ysrtech-deepl-prod-store').value;
    var output    = \$('ysrtech-deepl-prod-output');
    var btn       = \$('ysrtech-deepl-prod-btn');

    if (!storeCode) {
        alert('No destination store view available.');
        return;
    }

    output.style.display = 'block';
    output.innerHTML     = 'Translating\u2026';
    btn.disabled         = true;

    new Ajax.Request('{$ajaxUrl}', {
        method:     'post',
        parameters: { form_key: '{$formKey}', product_id: {$productId}, dest_store_code: storeCode },
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
//]]>
</script>
HTML;
    }

    /**
     * Appends a tiny script that updates the persistent modal's current category ID.
     * The modal itself lives in before_body_end (TranslateButton block), never replaced by AJAX.
     *
     * Event: core_block_abstract_to_html_after
     *
     * @param Varien_Event_Observer $observer
     */
    public function appendCategoryTranslateModal(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        if (!($block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit)) {
            return;
        }

        $category   = Mage::registry('category');
        $categoryId = ($category && $category->getId()) ? (int)$category->getId() : 0;

        if (!$categoryId) {
            return;
        }

        $transport = $observer->getEvent()->getTransport();
        $transport->setHtml(
            $transport->getHtml() .
            '<script type="text/javascript">window.ysrtechDeeplCurrentCatId = ' . $categoryId . ';</script>'
        );
    }
}
