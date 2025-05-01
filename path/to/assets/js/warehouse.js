// existing code...

// 在页面加载时获取并显示自定义道具
jQuery(document).ready(function() {
    loadCustomItems();
});

// 获取并显示自定义道具
function loadCustomItems() {
    jQuery.post(hap_ajax.ajax_url, {
        action: 'hap_get_custom_items',
        nonce: hap_ajax.nonce
    }).done(response => {
        jQuery('#hap-custom-items-container').html(renderItems(response.data.items));
    });
}

// existing code... 