/**
 * 惊悚乐园插件 - 前端核心脚本
 * 已优化性能：添加防抖、请求队列、缓存和资源控制
 */
jQuery(document).ready(function ($) {
  // 全局状态管理
  const hapState = {
    pendingRequests: [], //存储当前正在进行的请求
    isRequesting: false, // 是否有正在进行的请求
    cache: new Map(),
    debounceTimers: {},
  };

  // 初始化所有模块
  function initModules() {
    try {
      if ($(".hap-personal-center").length) {
        initPersonalCenter();
        console.log("个人中心初始化成功"); // 调试信息
      }
      if ($(".hap-item-filters").length) {
        // 检索全局中是否有hap-shock-box-container容器或父元素
        initShockBox();
        console.log("惊吓盒子初始化成功"); // 调试信息
      }
      if ($(".hap-warehouse-container").length) {
        initWarehouse();
        console.log("仓库初始化成功"); // 调试信息
      }
      if ($(".hap-admin-center").length) {
        initAdminCenter();
        console.log("管理员页面初始化成功"); // 调试信息
      }
    } catch (error) {
      console.error("模块初始化失败:", error);
    }
  }

  function initWarehouse() {
    console.log("仓库初始化成功111"); // 调试信息
  }

  function initAdminCenter() {
    console.log("管理员中心已初始化");
  }
  $(document).ready(function () {
    initModules();
  });

  // ==================== 通用工具函数 ====================
  function hapDebounce(key, callback, delay = 300) {
    clearTimeout(hapState.debounceTimers[key]);
    hapState.debounceTimers[key] = setTimeout(callback, delay);
  }

  function hapCacheRequest(key, requestFn, ttl = 300000) {
    const cached = hapState.cache.get(key);
    if (cached && Date.now() - cached.timestamp < ttl) {
      return Promise.resolve(cached.data);
    }
    return requestFn().then((data) => {
      hapState.cache.set(key, { data, timestamp: Date.now() });
      return data;
    });
  }

  function hapQueueRequest(requestFn) {
    return new Promise((resolve, reject) => {
      hapState.pendingRequests.push({ requestFn, resolve, reject });
      processQueue();
    });
  }

  function processQueue() {
    if (hapState.isRequesting || hapState.pendingRequests.length === 0) return;

    hapState.isRequesting = true;
    const { requestFn, resolve, reject } = hapState.pendingRequests.shift();

    requestFn()
      .then((data) => {
        resolve(data);
        hapState.isRequesting = false;
        setTimeout(processQueue, 100); // 添加延迟防止密集请求
      })
      .catch((err) => {
        reject(err);
        hapState.isRequesting = false;
        setTimeout(processQueue, 100);
      });
  }

  // ==================== 个人中心模块 ====================
  function initPersonalCenter() {
    //个人中心已初始化
    console.log("个人中心初始化成功111"); // 调试信息
    // 头像上传
    $("#hap-avatar-upload").on("change", function () {
      // 将id为hap-avatar-upload绑定函数，当元素的值变化时触发
      const file = this.files[0]; // 获取用户选择的第一个文件
      if (!file) return;

      const formData = new FormData();
      formData.append("action", "hap_upload_avatar"); // 告诉服务器端动作名称
      formData.append("nonce", hap_ajax.nonce); // 安全措施
      formData.append("avatar", file); // 上传头像文件

      const $btn = $(this); //防止重复提交
      $btn.prop("disabled", true);

      hapQueueRequest(() =>
        $.ajax({
          // 基础配置
          url: hap_ajax.ajax_url,
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
        })
      )
        .then((response) => {
          if (response.success) {
            $("#hap-avatar-id").val(response.data.id); //将服务器返回的头像 ID 存储在具有 ID hap-avatar-id 的隐藏输入框中
            updateAvatarPreview(response.data.url);
          } else {
            throw new Error(response.data || "上传失败");
          }
        })
        .catch((error) => {
          alert(error.message);
        })
        .finally(() => {
          $btn.prop("disabled", false);
        });
    });

    // 表单提交
    $("#hap-profile-form").on("submit", function (e) {
      e.preventDefault(); //  阻止默认提交行为
      submitProfileForm($(this));
    });

    // 编辑申请
    $("#hap-request-edit").on("click", function () {
      if (!confirm("确定要提交编辑申请吗？")) return;
      submitEditRequest($(this));
    });
  }

  function updateAvatarPreview(url) {
    $(".hap-avatar-preview").html(
      `<img src="${url}" alt="头像预览" style="max-width:100px;">`
    );
  }

  //记录注册表单信息
  function submitProfileForm($form) {
    const $btn = $form.find(".hap-submit"); //传入的表单元素 $form 中查找具有类名 hap-submit 的子元素，通常这是一个提交按钮。
    const originalText = $btn.text(); //将找到的提交按钮元素存储在变量 $btn 中

    $btn
      .prop("disabled", true)
      .html('保存中... <span class="hap-loading"></span>'); //禁用提交按钮，防止用户在请求处理过程中重复点击

    const formData = collectFormData($form);

    hapQueueRequest(() => $.post(hap_ajax.ajax_url, formData))
      .then((response) => {
        if (response.success) {
          showSuccess("个人信息保存成功！");
          location.reload(); // 重新加载当前页面，以反映保存后的最新数据。
        } else {
          throw new Error(response.data || "保存失败");
        }
      })
      .catch((error) => {
        showError(error.message);
      })
      .finally(() => {
        $btn.prop("disabled", false).text(originalText);
      });
  }

  //收集并处理注册表单信息
  function collectFormData($form) {
    const data = {
      action: "hap_save_profile",
      nonce: hap_ajax.nonce,
      nickname: $form.find('input[name="nickname"]').val(),
      avatar_id: $form.find('input[name="avatar_id"]').val(),
      bio: $form.find('textarea[name="bio"]').val(),
      completed_scenarios: $form
        .find('textarea[name="completed_scenarios"]')
        .val(),
      attributes: {},
      derived_attributes: {},
      specializations: {},
      currency: {
        game_coin: $form.find('input[name="currency[game_coin]"]').val(),
        skill_points: $form.find('input[name="currency[skill_points]"]').val(),
      },
    };

    // 收集动态字段
    $form.find('input[name^="attributes["]').each(function () {
      const name = $(this)
        .attr("name")
        .match(/\[(.*?)\]/)[1];
      data.attributes[name] = $(this).val();
    });

    $form.find('input[name^="derived_attributes["]').each(function () {
      const name = $(this)
        .attr("name")
        .match(/\[(.*?)\]/)[1];
      data.derived_attributes[name] = $(this).val();
    });

    $form.find('input[name^="specializations["]').each(function () {
      const name = $(this)
        .attr("name")
        .match(/\[(.*?)\]/)[1];
      data.specializations[name] = $(this).val();
    });

    return data;
  }

  //申请再次编辑
  function submitEditRequest($btn) {
    const originalText = $btn.text(); // 获取按钮当前的文本内容
    $btn
      .prop("disabled", true)
      .html('提交中... <span class="hap-loading"></span>');

    hapQueueRequest(() =>
      $.post(hap_ajax.ajax_url, {
        action: "hap_request_edit",
        nonce: hap_ajax.nonce,
      })
    )
      .then((response) => {
        if (response.success) {
          showSuccess("申请已提交，请等待管理员审核");
          location.reload();
        } else {
          throw new Error(response.data || "提交失败");
        }
      })
      .catch((error) => {
        showError(error.message);
      })
      .finally(() => {
        $btn.prop("disabled", false).text(originalText);
      });
  }

  // ==================== 惊吓盒子模块 ====================
  function initShockBox() {
    console.log("惊吓盒子初始化成功"); // 调试信息

    // 搜索功能
    $("#hap-search-btn").on("click", () => {
      console.log("搜索按钮被点击"); // 调试信息
      loadItems(1); // 点击后加载第一页搜索结果
    });

    // 页面加载时触发一次 loadItems(1);
    console.log("盒子1");
    loadItems(1);
    console.log("盒子2");

    // 购买功能
    $("#hap-items-container").on("click", ".hap-buy-btn", function () {
      const $btn = $(this);
      const itemId = $btn.data("item-id");
      console.log("购买按钮被点击", itemId); // 调试信息

      if (!confirm("确定要购买这个商品吗？")) return;

      $btn.prop("disabled", true).text("购买中...");

      // 使用 Promise.resolve 确保 hapQueueRequest 返回的是 Promise
      Promise.resolve(
        hapQueueRequest(() =>
          $.post(hap_ajax.ajax_url, {
            action: "hap_purchase_item",
            nonce: hap_ajax.nonce,
            item_id: itemId,
          })
        )
      )
        .then((response) => {
          if (response.success) {
            showSuccess("购买成功！");
            const currentPage =
              $("#hap-items-container").data("current-page") || 1;
            loadItems(currentPage); // 刷新当前页
          } else {
            throw new Error(response.data || "购买失败");
          }
        })
        .catch((error) => {
          showError(error.message); // 显示错误信息
        })
        .then(() => {
          $btn.prop("disabled", false).text("购买"); // 恢复按钮状态
        });
    });

    /**
     * 分页加载商品数据（分阶段加载模式）
     * @param {number} page - 当前页码
     */
    /**
     * 加载商品数据（分两阶段：基础数据 -> 详细数据）
     * @param {number} [page=1] - 当前页码
     */
    function loadItems(page) {
      const $container = $(".hap-items-grid");

      // 1. 防止重复加载
      if ($container.data("loading")) return;
      $container
        .data("loading", true)
        .html('<div class="hap-loading">加载商品基础信息中...</div>');

      // 2. 构造查询参数（自动过滤空值）
      const searchParams = {
        action: "hap_search_items",
        nonce: hap_ajax.nonce,
        name: $("#hap-item-search").val().trim() || undefined,
        item_type:
          $("#hap-item-type").val() !== "*"
            ? $("#hap-item-type").val()
            : undefined,
        quality:
          $("#hap-item-quality").val() !== "*"
            ? $("#hap-item-quality").val()
            : undefined,
        page: page,
        per_page: 20,
        fuzzy_search: true, // 启用模糊搜索
      };

      // 3. 清理空参数（优化版）
      Object.keys(searchParams).forEach((key) => {
        searchParams[key] === undefined && delete searchParams[key];
      });

      // 4. 使用Promise.resolve适配jQuery AJAX
      Promise.resolve($.post(hap_ajax.ajax_url, searchParams))
        .then(async (baseResponse) => {
          // 4.1 验证响应数据
          if (!baseResponse?.success) {
            throw new Error(baseResponse?.data?.message || "基础数据加载失败");
          }

          // 4.2 提取关键字段
          const baseItems =
            baseResponse.items.map((item) => ({
              name: item.name,
              item_type: item.item_type || null,
              quality: item.quality || null,
            })) || [];

          // 4.3 渲染骨架屏
          // 4.3 处理空数据情况
          if (baseItems.length === 0) {
            showEmptyMessage(); // 显示“暂无商品”提示
            return; // 终止后续逻辑
          }

          // 4.4 渲染骨架屏
          renderSkeletonItems(baseItems);

          // 5. 获取详细数据
          const fullItems = await fetchFullDetails(baseItems);
          renderFullItems(fullItems);

          // 6. 更新分页（修正函数名）
          renderPagination(baseResponse.pagination);
        })
        .catch((error) => {
          console.error("[HAP] 数据加载异常:", error);

          // 7. 错误降级处理
          $container
            .html(
              `
            <div class="hap-error">
                <i class="icon-warning"></i>
                加载失败: ${error.message}
                <button class="hap-retry-btn">重试</button>
            </div>
        `
            )
            .find(".hap-retry-btn")
            .click(() => loadItems(page));
        })
        .finally(() => {
          // 8. 重置加载状态
          $container.data("loading", false);
        });
    }

    // ==================== 辅助函数 ====================
    /**
     * 获取完整商品详情（内部调用）
     */
    // 修改 fetchFullDetails 函数，确保参数格式兼容
    async function fetchFullDetails(baseItems) {
      const formData = new FormData();
      formData.append("action", "hap_get_full_details");
      formData.append("nonce", hap_ajax.nonce);
      formData.append(
        "fields",
        "item_id,price,currency,effects,name,item_type,quality,restrictions,consumption,level,sales_count,created_at,attributes,learning_requirements,author"
      ); // 需要的字段

      baseItems.forEach((item, index) => {
        formData.append(`items[${index}][name]`, item.name);
        if (item.item_type)
          formData.append(`items[${index}][item_type]`, item.item_type);
        if (item.quality)
          formData.append(`items[${index}][quality]`, item.quality);
      });

      console.log("请求参数:", Array.from(formData.entries()));

      const response = await fetch(hap_ajax.ajax_url, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        console.error(`HTTP错误: ${response.status}`);
        throw new Error(`HTTP错误: ${response.status}`);
      }

      const data = await response.json();
      console.log("API响应数据:", data);

      if (!data.success) {
        console.error("API调用失败:", data.data?.message || "数据补全失败");
        throw new Error(data.data?.message || "数据补全失败");
      }

      // 这里检查 items 的访问
      const items = data.data?.items || [];
      console.log("返回的 items:", items); // 添加日志以检查 items

      if (items.length === 0) {
        console.warn("返回的 items 数组为空，检查可能的原因。");
        console.log("返回的完整数据:", data.data);
      }

      return items;
    }

    /**
     * 渲染骨架屏（临时占位），后续可以增加信息
     */
    function renderSkeletonItems(items) {
      const $container = $("#hap-items-container");
      $container.html(
        items
          .map(
            (item) => `
            <div class="hap-item-card skeleton">
                <div class="skeleton-title"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
            </div>
        `
          )
          .join("")
      );
    }

    /**
     * 显示空数据提示
     */
    function showEmptyMessage() {
      const emptyHtml = `
        <div class="empty-state">
            <i class="icon-empty"></i>
            <p>暂无对应商品</p>
        </div>
    `;
      $("#hap-items-container").html(emptyHtml); // 替换容器内容
    }

    /**
     * 显示错误提示
     */
    function showError(message) {
      $("#error-toast").text(message).fadeIn().delay(3000).fadeOut();
    }

    /**
     * 完整数据渲染
     */
    /**
     * 完整商品卡片渲染（支持所有字段）
     */
    function renderFullItems(items) {
      const $container = $("#hap-items-container");
      $container.empty();

      // 创建文档片段提升性能
      const fragment = document.createDocumentFragment();

      items.forEach((item) => {
        if (item.error) {
          fragment.appendChild(createErrorCard(item));
          return;
        }

        const card = document.createElement("div");
        card.className = `hap-item-card quality-${item.quality || "common"}`;

        // 1. 基础信息区块
        card.innerHTML = `
            <header class="item-header">
                <h3>${escapeHtml(item.name)}</h3>
                <div class="meta-badges">   
                    <h3 class="type-badge">${getTypeName(item.item_type)}</h3>
                    <h4 class="quality-badge">${getQualityName(
                      item.quality
                    )}</h4>
                    ${
                      item.level
                        ? `<span class="level-badge">可使用等级：Lv.${item.level}</span>`
                        : ""
                    }
                </div>
            </header>
            
            <!-- 2. 核心数据区块 -->
            <section class="item-core">
                    ${
                      item.effects
                        ? `<div class="effects-badge">特效：${item.effects}</div>`
                        : ""
                    }
                    <div class="price-badge">价格：${
                      item.price
                    }${getCurrencyName(item.currency)}</div>
            </section>
        `;

        // 4. 页脚区块
        const footer = document.createElement("footer");
        footer.className = "item-footer";

        // 创建一个数组用于存储页脚内容
        const footerContent = [];

        // 添加销量信息
        footerContent.push(`
            <div class="sales">
                <i class="icon-sales"></i>
                <span>销量: ${item.sales_count || 0}</span>
            </div>
        `);

        // 添加创建时间
        footerContent.push(`
            <time datetime="${item.created_at}">
                上架时间：${item.created_at}
            </time>
        `);

        // 添加作者
        footerContent.push(`
            <div class="author">
                <i class="author"></i>
                <span>作者: ${item.author || "无名氏"}</span>
            </div>
        `);

        // 添加购买按钮
        footerContent.push(`
            <button class="hap-buy-btn" 
                    data-item-id="${item.item_id || ""}"
                    data-price="${item.price || 0}">
                购买 (${item.price || "?"} ${getCurrencyName(item.currency)})
            </button>
        `);

        // 添加属性区块
        if (item.attributes?.length) {
          footerContent.push(
            createAttributesSection(item.attributes).outerHTML
          );
        }

        // 添加学习要求区块
        if (item.learning_requirements) {
          footerContent.push(item.learning_requirements).outerHTML;
        }

        // 将所有内容合并为一个字符串并设置为 footer 的 innerHTML
        footer.innerHTML = footerContent.join("");

        // 将页脚添加到卡片
        card.appendChild(footer);

        fragment.appendChild(card);
      });

      $container.append(fragment);
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

    // 渲染分页
    function renderPagination(data) {
      const $pagination = $("#hap-items-pagination");
      if (data.total <= data.per_page) {
        $pagination.empty();
        return;
      }

      const totalPages = Math.ceil(data.total / data.per_page);
      const currentPage = data.page;
      let html = '<div class="hap-pagination-links">';

      if (currentPage > 1) {
        html += `<button class="hap-page-btn" data-page="${
          currentPage - 1
        }">上一页</button>`;
      }

      for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
          html += `<span class="hap-current-page">${i}</span>`;
        } else if (
          Math.abs(i - currentPage) <= 2 ||
          i === 1 ||
          i === totalPages
        ) {
          html += `<button class="hap-page-btn" data-page="${i}">${i}</button>`;
        } else if (Math.abs(i - currentPage) === 3) {
          html += '<span class="hap-page-dots">...</span>';
        }
      }

      if (currentPage < totalPages) {
        html += `<button class="hap-page-btn" data-page="${
          currentPage + 1
        }">下一页</button>`;
      }

      html += "</div>";
      $pagination.html(html);

      $pagination.on("click", ".hap-page-btn", function () {
        loadItems(parseInt($(this).data("page")));
      });
    }
  }

  // ==================== 仓库模块 ====================
  function initWarehouse() {
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
  }

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
  const $container = $('#hap-inventory-container');

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
    type: $("#hap-inventory-type").val()
     !== "*" 
             ? $("#hap-inventory-type").val() 
             : undefined,
             _: new Date().getTime() // 添加时间戳
  };
  console.log("hap-inventory-type:", $("#hap-inventory-type").val()); 

  // 3. 清理空参数（优化版）
  Object.keys(searchParams).forEach((key) => {
    searchParams[key] === undefined && delete searchParams[key];
  });

  // 4. 使用Promise.resolve适配缓存层
  Promise.resolve(
    Promise.resolve($.post(hap_ajax.ajax_url, searchParams))
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
    const $container = $("#hap-inventory-container");
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

  $(document).ready(function() {
    // 绑定保存按钮点击事件
    $("#hap-custom-item-save-btn").on("click", function(e) {
        e.preventDefault(); // 阻止默认行为
        saveCustomItem(); // 调用保存函数
    });
});

function saveCustomItem() {
  const searchParams = {
      action: "hap_save_custom_items", // 确保这个动作与后端处理匹配
      nonce: hap_ajax.nonce, // 验证nonce
      name: $("#hap-custom-item-name").val().trim(), // 获取道具名称
      item_type: $("#hap-custom-item-type").val(), // 获取道具类型
      quality: $("#hap-custom-item-quality").val(), // 获取道具品质
  };

  // 清理空参数
  Object.keys(searchParams).forEach((key) => {
      if (!searchParams[key]) {
          delete searchParams[key]; // 删除空值参数
      }
  });

  // 发送保存请求
  $.post(hap_ajax.ajax_url, searchParams)
      .done((baseResponse) => {
          if (baseResponse.success) {
              alert('道具保存成功！');
              
              // 清空输入框
              $("#hap-custom-item-name").val('');
              $("#hap-custom-item-type").val('consumable'); // 重置类型选择
              $("#hap-custom-item-quality").val('common'); // 重置品质选择
              
              // 可选：在这里调用一个方法来更新道具列表
              loadCustomItems(); // 重新加载道具列表
          } else {
              alert('保存失败：' + baseResponse.data.message);
          }
      })
      .fail((jqXHR, textStatus, errorThrown) => {
          console.error("[HAP] 数据异常:", textStatus, errorThrown);
          
          // 错误降级处理
          const $container = $("#hap-custom-item-container");
          $container.html(
              `
              <div class="hap-error">
                  <i class="icon-warning"></i>
                  道具保存失败: ${errorThrown}
                  <button class="hap-retry-btn">重试</button>
              </div>
              `
          ).find(".hap-retry-btn").click(() => {
              saveCustomItem(); // 重新尝试保存
          });
      });
}

function loadCustomItems() {
  $.post(hap_ajax.ajax_url, {
      action: 'hap_get_custom_items',
      nonce: hap_ajax.nonce
  }).done(response => {
      $('#hap-custom-item-container').html(renderItems(response.data.items));
  });
}

  function submitCustomItemForm($form) {
    const $btn = $form.find(".hap-submit");
    const originalText = $btn.text();
    $btn.prop("disabled", true).text("创建中...");

    const formData = {
      action: "hap_create_custom_item",
      nonce: $form.find('[name="hap_nonce"]').val(),
      item_type: $form.find('[name="item_type"]').val(),
      name: $form.find('[name="name"]').val(),
    };

    // 根据不同类型收集不同字段
    const itemType = formData.item_type;
    if (itemType === "equipment") {
      formData.attributes = $form.find('[name="attributes"]').val();
    } else if (itemType === "skill") {
      formData.level = $form.find('[name="level"]').val();
    } else {
      formData.effect = $form.find('[name="effect"]').val();
    }

    hapQueueRequest(() => $.post(hap_ajax.ajax_url, formData))
      .then((response) => {
        if (response.success) {
          showSuccess("自定义道具创建成功！");
          $form[0].reset();
          loadInventory();
        } else {
          throw new Error(response.data || "创建失败");
        }
      })
      .catch((error) => {
        showError(error.message);
      })
      .finally(() => {
        $btn.prop("disabled", false).text(originalText);
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

  // ==================== 管理员模块 ====================
  function initAdminCenter() {
    $(".hap-admin-center").on("click", ".hap-approve-btn", function () {
      processRequest($(this), "hap_approve_edit_request", "批准");
    });

    $(".hap-admin-center").on("click", ".hap-reject-btn", function () {
      if (confirm("确定要拒绝此申请吗？")) {
        processRequest($(this), "hap_reject_edit_request", "拒绝");
      }
    });
  }

  function processRequest($btn, action, actionText) {
    const userId = $btn.data("user-id");
    const $row = $btn.closest("tr");

    $btn.prop("disabled", true).text("处理中...");

    hapQueueRequest(() =>
      $.post(hap_ajax.ajax_url, {
        action: action,
        nonce: hap_ajax.nonce,
        user_id: userId,
      })
    )
      .then((response) => {
        if (response.success) {
          $row.fadeOut(300, function () {
            $(this).remove();
            checkEmptyTable();
          });
        } else {
          throw new Error(response.data || `${actionText}失败`);
        }
      })
      .catch((error) => {
        showError(error.message);
        $btn.prop("disabled", false).text(actionText);
      });
  }

  function checkEmptyTable() {
    if ($(".hap-requests-table tbody tr").length === 0) {
      $(".hap-requests-table").replaceWith("<p>当前没有待处理的编辑申请。</p>");
    }
  }

  // ==================== 辅助函数 ====================
  function escapeHtml(unsafe) {
    if (!unsafe) return "";
    return unsafe
      .toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function showSuccess(message) {
    alert(message); // 可以替换为更友好的通知方式
  }

  function showError(message) {
    alert("错误: " + message); // 可以替换为更友好的错误显示
  }
});
