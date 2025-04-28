// ==================== 仓库模块 ====================
jQuery(document).ready(function($) {
  // 标签切换
  $(".hap-warehouse-tabs").on("click", ".hap-tab-btn", function () {
    const tab = $(this).data("tab");
    $(".hap-tab-btn").removeClass("active");
    $(this).addClass("active");

    $(".hap-tab-content").removeClass("active");
    $(`#hap-${tab}-tab`).addClass("active");

    if (tab === "inventory") {
      loadInventory(1);
    }
  });

  loadInventory(1);
  
  // 搜索功能
  $("#hap-warehouse-search-btn").on("click", () => {
    console.log("搜索按钮被点击"); // 调试信息
    loadInventory(1); // 点击后加载第一页搜索结果
  });    

  // 动态表单字段
  $("#hap-item-type-select").on("change", function () {
    loadItemFormFields($(this).val());
  });

  // 表单提交
  $("#hap-custom-item-form").on("submit", function (e) {
    e.preventDefault();
    submitCustomItemForm($(this));
  });

  // 绑定保存按钮点击事件
  $("#hap-custom-item-save-btn").on("click", function(e) {
    e.preventDefault(); // 阻止默认行为
    saveCustomItem(); // 调用保存函数
  });
});

// 确保Promise.finally在旧浏览器中可用
if (typeof Promise.prototype.finally === 'undefined') {
  Promise.prototype.finally = function(callback) {
    return this.then(
      value => Promise.resolve(callback()).then(() => value),
      reason => Promise.resolve(callback()).then(() => { throw reason })
    );
  };
}

function loadInventory(page) {
  const $container = jQuery('#hap-inventory-container');

  // 1. 防止重复加载
  if ($container.data("loading")) return;
  $container
    .data("loading", true)
    .html('<div class="hap-loading">加载库存数据中...</div>');

  // 2. 构造查询参数（自动过滤空值）
  const searchParams = {
    action: "hap_get_inventory",
    nonce: hap_ajax.nonce,
    page: page,
    per_page: 20,
    type: jQuery("#hap-inventory-type").val()
     !== "*" 
             ? jQuery("#hap-inventory-type").val() 
             : undefined,
             _: new Date().getTime() // 添加时间戳
  };
  console.log("hap-inventory-type:", jQuery("#hap-inventory-type").val()); 

  // 3. 清理空参数（优化版）
  Object.keys(searchParams).forEach((key) => {
    searchParams[key] === undefined && delete searchParams[key];
  });

  // 4. 使用Promise.resolve适配缓存层
  Promise.resolve(
    Promise.resolve(jQuery.post(hap_ajax.ajax_url, searchParams))
  )
    .then((response) => {
        try {
          if (!response || !response.success) {
            throw new Error(response?.data || "无效的响应数据");
          }
          renderInventory(response.data.items);
        } catch (syncError) {
          console.error("渲染错误:", syncError);
          throw syncError; // 传递给catch块
        }
      })
      .catch((error) => {
        const errorMsg = error instanceof Error ? error.message : String(error);
        $container.html(`<div class="hap-error">${errorMsg}</div>`);
      })
      .finally(() => {
        $container.data("loading", false);
      });
  }

  function renderInventory(items) {
    const $container = jQuery("#hap-inventory-container");
    $container.empty();
  
    // 空状态处理
    if (items.length === 0) {
      $container.html('<div class="hap-no-items">仓库空空如也</div>');
      return;
    }
  
    // 使用文档片段提升性能
    const fragment = document.createDocumentFragment();
  
    items.forEach((item) => {
      const itemEl = document.createElement("div");
      itemEl.className = `hap-inventory-item hap-quality-${item.quality || "common"}`;
  
      // 1. 主内容区块（保持与renderFullItems相同的HTML构建模式）
      itemEl.innerHTML = `
        <div class="hap-item-image">
          <div class="hap-item-image-placeholder"></div>
        </div>
        <div class="hap-item-info">
          <h4>${escapeHtml(item.name)}</h4>
          <div class="hap-item-meta">
            <span class="hap-item-type">类型：${getTypeName(item.item_type)}</span><br>
            <span class="hap-item-quality">品质：${getQualityName(item.quality)}</span><br>
            <span class="hap-item-attributes">属性：${getQualityName(item.attributes)}</span><br>
            ${
              item.level != null  // 显式检查 null 和 undefined
                ? `<span class="hap-item-level">可使用等级：${escapeHtml(item.level)}</span><br>`
                : ""
            }            
            <span class="hap-item-quantity">数量: ${item.quantity || 0}</span><br>
            <span class="hap-item-restrictions">单格携带数量: ${item.restrictions || 0}</span><br>
            ${
              item.effects 
                ? `<span class="hap-item-effects">特效：${escapeHtml(item.effects)}</span><br>`
                : ""
            }
            ${
              item.duration != null
                ? `<span class="hap-item-duration">持续时间：${escapeHtml(item.duration)}</span><br>`
                : ""
            }
            ${
              item.consumption != null
                ? `<span class="hap-item-consumption">单次使用消耗：${escapeHtml(item.consumption)}</span><br>`
                : ""
            }
            <span class="hap-item-author">作者：${escapeHtml(item.author)}</span><br>
            <span class="hap-item-price">购买时价格：${item.purchase_price} ${getCurrencyName(item.currency)}</span><br>
            ${
              item.adjust_type != null && item.adjust_date != null
                ? `<span class="hap-item-adjust">最近调整：于${escapeHtml(item.adjust_date)} ${getAdjustTypeName(item.adjust_type)}</span><br>`
                : ""
            }
          </div>
        </div>
      `;
  
      // 2. 动态交互元素（模仿renderFullItems的页脚构建逻辑）
      const actionBar = document.createElement("div");
      actionBar.className = "hap-item-actions";
      
      actionBar.innerHTML = `
        <button class="hap-use-btn" data-item-id="${item.item_id}">使用</button>
        <button class="hap-sell-btn" data-item-id="${item.item_id}">出售</button>
        ${
          item.tradable 
            ? `<button class="hap-trade-btn" data-item-id="${item.item_id}">交易</button>`
            : ""
        }
      `;
  
      itemEl.appendChild(actionBar);
      fragment.appendChild(itemEl);
    });
  
    $container.append(fragment);
  }

function saveCustomItem() {
  // 获取表单DOM引用
  const $form = jQuery('.hap-custom-item-filters');
  
  // 基础验证
  if (!jQuery('#hap-custom-item-name').val().trim()) {
      return alert('道具名称不能为空');
  }
  if (!jQuery('#hap-custom-item-type').val()) {
      return alert('请选择道具类型');
  }
  if (!jQuery('#hap-custom-item-price').val()) {
      return alert('请填写道具价格');
  }

  // 构建请求参数
  const requestData = {
    action: "hap_save_custom_items",
    nonce: hap_ajax.nonce,
    // 基础信息（空字符串转为undefined）
    name: jQuery('#hap-custom-item-name').val().trim() || undefined,
    attributes: jQuery('#hap-custom-item-attributes').val().trim() || undefined,
    
    // 类型选择（保留默认值逻辑）
    item_type: jQuery('#hap-custom-item-type').val(),
    quality: jQuery('#hap-custom-item-quality').val() || 'common',
    
    // 数值类字段（空值不传）
    level: jQuery('#hap-custom-item-level').val() ? parseInt(jQuery('#hap-custom-item-level').val()) : undefined,
    restrictions: jQuery('#hap-custom-item-restrictions').val() ? parseInt(jQuery('#hap-custom-item-restrictions').val()) : undefined,
    effects: jQuery('#hap-custom-item-effects').val().trim() || undefined,
    comment: jQuery('#hap-custom-item-comment').val().trim() || undefined,
    duration: jQuery('#hap-custom-item-duration').val() ? parseInt(jQuery('#hap-custom-item-duration').val()) : undefined,
    
    // 价格系统
    price: jQuery('#hap-custom-item-price').val() ? parseFloat(jQuery('#hap-custom-item-price').val()) : undefined,
    currency: jQuery('#hap-custom-item-currency').val(),
    
    // 消耗/学习要求
    consumption: jQuery('#hap-custom-item-consumption').val().trim() || undefined,
    learning_requirements: jQuery('#hap-custom-item-learning-req').val().trim() || undefined,
    
    // 作者信息（保留默认值）
    author: jQuery('#hap-custom-item-author').val().trim() || '匿名'
  };

  // 2. 清理空参数（与搜索逻辑保持一致）
  Object.keys(requestData).forEach(key => {
    requestData[key] === undefined && delete requestData[key];
  });

  // 3. 显示加载状态
  const $saveBtn = jQuery('#hap-custom-item-save-btn');
  $saveBtn.prop('disabled', true).html('<i class="icon-loading"></i> 保存中...');
  console.log('自定义1');

  // 4. 使用Promise链式调用
  Promise.resolve(jQuery.ajax({
    url: hap_ajax.ajax_url,
    type: 'POST',
    contentType: "application/x-www-form-urlencoded; charset=UTF-8",
    data: requestData,
    dataType: 'json'
  }))
    .then(response => {
      if (!response?.success) {
        throw new Error(response?.data?.message || '服务器错误');
      }
      
      // 成功处理
      alert('道具保存成功！');
      const formEl = document.getElementById('hap-custom-item-form');
if (formEl) formEl.reset();
      jQuery('#hap-custom-item-type').val('consumable');
      jQuery('#hap-custom-item-quality').val('common');
      jQuery('#hap-custom-item-currency').val('game_coin');
      
      // 可在此处调用刷新逻辑
      // loadCustomItems();
    })
    .catch(error => {
      console.error('保存失败:', error);
      alert(error.message.includes('Network') ? 
        '网络错误，请检查连接后重试' : 
        `保存失败：${error.message}`);
    })
    .finally(() => {
      // 确保始终执行的逻辑（替代.always）
      $saveBtn.prop('disabled', false).text('保存道具');
      console.log('自定义3');
    });
}

function loadCustomItems() {
  jQuery.post(hap_ajax.ajax_url, {
      action: 'hap_get_custom_items',
      nonce: hap_ajax.nonce
  }).done(response => {
      jQuery('#hap-custom-item-container').html(renderItems(response.data.items));
  });
}

  // 其他辅助函数
  function getTypeName(type) {
    const types = {
      consumable: "消耗道具",
      permanent: "永久道具",
      arrow: "箭矢",
      bullet: "子弹",
      skill: "法术",
      equipment: "装备",
    };
    return types[type] || type;
  }
  // 其他辅助函数
  function getQualityName(quality) {
    const qualitys = {
      common: "普通",
      uncommon: "精良",
      rare: "材料",
      epic: "史诗",
      legendary: "传说",
    };
    return qualitys[quality] || quality;
  }
  function getCurrencyName(currency) {
    const currencys = {
      game_coin: "游戏币",
      skill_points: "技巧值",
    };
    return currencys[currency] || currency;
  }
  function getAdjustTypeName(adjust_type) {
    const adjust_types = {
      buff: '<span style="color: #52c41a">增强</span>', // 绿色
      debuff: '<span style="color: #f5222d">削弱</span>' // 红色
    };
    return adjust_types[adjust_type] || adjust_type;
  }
