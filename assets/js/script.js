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
        // 检查 ShockBox 对象是否已存在
        if (typeof ShockBox !== 'undefined') {
          console.log("惊吓盒子模块已存在，跳过初始化"); // 调试信息
        } else {
          // 如果 ShockBox 对象不存在，则手动初始化搜索和购买功能
          initShockBox();
          console.log("惊吓盒子初始化成功"); // 调试信息
        }
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

  // 添加 initShockBox 函数，作为备用初始化方法
  function initShockBox() {
    console.log("手动初始化惊吓盒子功能");
    
    // 搜索功能
    $("#hap-search-btn").on("click", function() {
      console.log("搜索按钮被点击");
      // 如果 ShockBox 对象存在，使用它的方法
      if (typeof ShockBox !== 'undefined' && typeof ShockBox.loadItems === 'function') {
        ShockBox.loadItems(1);
      } else {
        // 否则，直接发送 AJAX 请求
        const searchParams = {
          action: "hap_search_items",
          nonce: hap_ajax.nonce,
          name: $("#hap-item-search").val().trim() || undefined,
          item_type: $("#hap-item-type").val() !== "*" ? $("#hap-item-type").val() : undefined,
          quality: $("#hap-item-quality").val() !== "*" ? $("#hap-item-quality").val() : undefined,
          page: 1,
          per_page: 20,
          fuzzy_search: true
        };
        
        // 清理空参数
        Object.keys(searchParams).forEach(key => {
          searchParams[key] === undefined && delete searchParams[key];
        });
        
        $.post(hap_ajax.ajax_url, searchParams)
          .then(function(response) {
            console.log("搜索响应:", response);
            if (response.success) {
              // 处理搜索结果
              renderSearchResults(response);
            } else {
              throw new Error(response.data || "搜索失败");
            }
          })
          .catch(function(error) {
            console.error("搜索错误:", error);
            $("#hap-items-container").html(`
              <div class="hap-error">
                <i class="icon-warning"></i>
                搜索失败: ${error.message}
                <button class="hap-retry-btn">重试</button>
              </div>
            `).find(".hap-retry-btn").click(function() {
              $("#hap-search-btn").click();
            });
          });
      }
    });
    
    // 购买功能
    $("#hap-items-container").on("click", ".hap-buy-btn", function(e) {
      const $btn = $(e.currentTarget);
      const itemId = $btn.data("item-id");
      console.log("购买按钮被点击", itemId);
      
      if (!confirm("确定要购买这个商品吗？")) return;
      
      $btn.prop("disabled", true).text("购买中...");
      
      $.post(hap_ajax.ajax_url, {
        action: "hap_buy_item",
        nonce: hap_ajax.nonce,
        item_id: itemId
      })
      .done(function(response) {
        if (response.success) {
          showSuccess("购买成功！");
          // 刷新页面或更新UI
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        } else {
          showError(response.data || "购买失败，请重试");
        }
      })
      .fail(function() {
        showError("网络错误，请重试");
      })
      .always(function() {
        $btn.prop("disabled", false).text("购买");
      });
    });
  }
});