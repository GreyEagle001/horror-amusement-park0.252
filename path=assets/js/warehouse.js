jQuery(document).ready(function($) {
  // 加载自定义道具
  loadCustomItems();
});

// 加载自定义道具
function loadCustomItems(page = 1) {
  $.ajax({
    url: hapAjaxUrl,
    type: "POST",
    data: {
      action: "hap_get_custom_items",
      page: page,
    },
    success: function (response) {
      if (response.success) {
        renderCustomItems(response.data.items);
        renderPagination(response.data, loadCustomItems);
      } else {
        console.error("加载自定义道具失败：", response);
      }
    },
    error: function (jqXHR, textStatus, errorThrown) {
      console.error("加载自定义道具失败：", textStatus, errorThrown);
    },
  });
} 