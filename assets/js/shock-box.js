/**
 * 惊吓盒子模块 - 完整实现
 * 功能：商品搜索、分页加载、购买交互、数据缓存
 * 依赖：jQuery 3.0+
 * 日期：2025-04-27
 */

// ==================== 模块初始化 ====================
(function ($) {
    'use strict';
  
    // 全局状态管理
    const hapState = {
      pendingRequests: [],
      isRequesting: false,
      cache: new Map(),
      debounceTimers: {}
    };
  
    // ==================== 主功能模块 ====================
    const ShockBox = {
      /**
       * 初始化所有功能
       */
      init: function() {
        console.log("惊吓盒子初始化成功");
        
        // 搜索功能
        $("#hap-search-btn").on("click", () => {
          console.log("搜索按钮被点击");
          this.loadItems(1);
        });
  
        // 初始加载
        this.loadItems(1);
  
        // 购买功能
        $("#hap-items-container").on("click", ".hap-buy-btn", this.handlePurchase.bind(this));
      },
  
      /**
       * 处理购买请求
       */
      handlePurchase: function(e) {
        const $btn = $(e.currentTarget);
        const itemId = $btn.data("item-id");
        console.log("购买按钮被点击", itemId);
      
        if (!confirm("确定要购买这个商品吗？")) return;
      
        $btn.prop("disabled", true).text("购买中...");
      
        // 直接使用 $.post() 的 Deferred 对象，不经过 queueRequest 的 Promise 包装
        $.post(hap_ajax.ajax_url, {
          action: "hap_purchase_item",
          nonce: hap_ajax.nonce,
          item_id: itemId,
        })
          .then((response) => {
            if (response?.success) {
              alert('购买成功！');
              const currentPage = $("#hap-items-container").data("current-page") || 1;
              this.loadItems(currentPage);
            } else {
              throw new Error(response?.data || "购买失败");
            }
          })
          .catch((error) => {
            this.showError(error.message);
          })
          .always(() => {
            $btn.prop("disabled", false).text("购买");
          });
      },
      
  
      /**
       * 加载商品数据（分两阶段）
       */
      loadItems: function(page = 1) {
        const $container = $("#hap-items-container");
  
        // 防重复加载
        if ($container.data("loading")) return;
        $container
          .data("loading", true)
          .html('<div class="hap-loading">加载中...</div>');
  
        // 构造查询参数
        const searchParams = {
          action: "hap_search_items",
          nonce: hap_ajax.nonce,
          name: $("#hap-item-search").val().trim() || undefined,
          item_type: $("#hap-item-type").val() !== "*" ? $("#hap-item-type").val() : undefined,
          quality: $("#hap-item-quality").val() !== "*" ? $("#hap-item-quality").val() : undefined,
          page: page,
          per_page: 20,
          fuzzy_search: true
        };
  
        // 清理空参数
        Object.keys(searchParams).forEach(key => {
          searchParams[key] === undefined && delete searchParams[key];
        });
  
        $.post(hap_ajax.ajax_url, searchParams)
          .then(async (baseResponse) => {
            if (!baseResponse?.success) {
              throw new Error(baseResponse?.data?.message || "加载失败");
            }
  
            const baseItems = baseResponse.items.map(item => ({
              name: item.name,
              item_type: item.item_type,
              quality: item.quality
            })) || [];
  
            if (baseItems.length === 0) {
              this.showEmptyMessage();
              return;
            }
  
            this.renderSkeletonItems(baseItems);
            const fullItems = await this.fetchFullDetails(baseItems);
            this.renderFullItems(fullItems);
            this.renderPagination(baseResponse.pagination);
          })
          .catch(error => {
            console.error("加载异常:", error);
            $container.html(`
              <div class="hap-error">
                <i class="icon-warning"></i>
                加载失败: ${error.message}
                <button class="hap-retry-btn">重试</button>
              </div>
            `).find(".hap-retry-btn").click(() => this.loadItems(page));
          })
          .always(() => {
            $container.data("loading", false);
          });
      },
  
      // ==================== 数据获取 ====================
      /**
       * 获取完整商品详情
       */
      fetchFullDetails: async function(baseItems) {
        const formData = new FormData();
        formData.append("action", "hap_get_full_details");
        formData.append("nonce", hap_ajax.nonce);
        formData.append("fields", "item_id,price,currency,effects,comment,name,item_type,quality,level,sales_count,created_at,author");
  
        baseItems.forEach((item, index) => {
          formData.append(`items[${index}][name]`, item.name);
          item.item_type && formData.append(`items[${index}][item_type]`, item.item_type);
          item.quality && formData.append(`items[${index}][quality]`, item.quality);
        });
  
        const response = await fetch(hap_ajax.ajax_url, {
          method: "POST",
          body: formData
        });
  
        if (!response.ok) throw new Error(`HTTP错误: ${response.status}`);
        
        const data = await response.json();
        if (!data.success) throw new Error(data.data?.message || "数据补全失败");
        
        return data.data?.items || [];
      },
  
      // ==================== 渲染相关 ====================
      /**
       * 渲染骨架屏
       */
      renderSkeletonItems: function(items) {
        $("#hap-items-container").html(
          items.map(item => `
            <div class="hap-item-card skeleton">
              <div class="skeleton-title"></div>
              <div class="skeleton-line"></div>
              <div class="skeleton-line"></div>
            </div>
          `).join("")
        );
      },
  
      /**
       * 渲染完整商品列表
       */
      renderFullItems: function(items) {
        const $container = $("#hap-items-container");
        $container.empty();
  
        const fragment = document.createDocumentFragment();
  
        items.forEach(item => {
          const card = document.createElement("div");
          card.className = `hap-item-card quality-${item.quality || "common"}`;
  
          card.innerHTML = `
            <header class="item-header">
              <h3>${this.escapeHtml(item.name)}</h3>
              <div class="meta-badges">   
                <h3 class="type-badge">${this.getTypeName(item.item_type)}</h3>
                <h4 class="quality-badge">${this.getQualityName(item.quality)}</h4>
                ${item.level ? `<span class="level-badge">Lv.${item.level}</span>` : ""}
              </div>
            </header>
            
            <section class="item-core">
              ${item.effects ? `<div class="effects-badge">特效：${item.effects}</div>` : ""}
              ${item.comment ? `<div class="effects-badge">备注：${item.comment}</div>` : ""}
              <div class="price-badge">价格：${item.price}${this.getCurrencyName(item.currency)}</div>
            </section>
          `;
  
          const footer = document.createElement("footer");
          footer.className = "item-footer";
          footer.innerHTML = `
            <div class="sales"><i class="icon-sales"></i>销量: ${item.sales_count || 0}</div>
            <time datetime="${item.created_at}">上架: ${item.created_at}</time>
            <div class="author"><i class="icon-author"></i>作者: ${item.author || "无名氏"}</div>
            <button class="hap-buy-btn" data-item-id="${item.item_id}">
              购买 (${item.price} ${this.getCurrencyName(item.currency)})
            </button>
          `;
  
          card.appendChild(footer);
          fragment.appendChild(card);
        });
  
        $container.append(fragment);
      },
  
      /**
       * 渲染分页控件
       */
      renderPagination: function(data) {
        const $pagination = $("#hap-items-pagination");
        if (data.total <= data.per_page) {
          $pagination.empty();
          return;
        }
  
        const totalPages = Math.ceil(data.total / data.per_page);
        const currentPage = data.page;
        let html = '<div class="hap-pagination-links">';
  
        // 上一页按钮
        if (currentPage > 1) {
          html += `<button class="hap-page-btn" data-page="${currentPage - 1}">上一页</button>`;
        }
  
        // 页码按钮
        for (let i = 1; i <= totalPages; i++) {
          if (i === currentPage) {
            html += `<span class="hap-current-page">${i}</span>`;
          } else if (Math.abs(i - currentPage) <= 2 || i === 1 || i === totalPages) {
            html += `<button class="hap-page-btn" data-page="${i}">${i}</button>`;
          } else if (Math.abs(i - currentPage) === 3) {
            html += '<span class="hap-page-dots">...</span>';
          }
        }
  
        // 下一页按钮
        if (currentPage < totalPages) {
          html += `<button class="hap-page-btn" data-page="${currentPage + 1}">下一页</button>`;
        }
  
        html += "</div>";
        $pagination.html(html).on("click", ".hap-page-btn", (e) => {
          this.loadItems(parseInt($(e.currentTarget).data("page")));
        });
      },
  
      // ==================== 工具方法 ====================
      showEmptyMessage: function() {
        $("#hap-items-container").html(`
          <div class="empty-state">
            <i class="icon-empty"></i>
            <p>暂无对应商品</p>
          </div>
        `);
      },
  
      showError: function(message) {
        $("#error-toast").text(message).fadeIn().delay(3000).fadeOut();
      },
  
      showSuccess: function(message) {
        $("#success-toast").text(message).fadeIn().delay(3000).fadeOut();
      },
  
      escapeHtml: function(unsafe) {
        return unsafe?.toString()
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;") || '';
      },
  
      getTypeName: function(type) {
        const types = {
          consumable: "消耗道具",
          permanent: "永久道具",
          arrow: "箭矢",
          bullet: "子弹",
          skill: "法术",
          equipment: "装备"
        };
        return types[type] || type;
      },
  
      getQualityName: function(quality) {
        const qualities = {
          common: "普通",
          uncommon: "精良",
          rare: "稀有",
          epic: "史诗",
          legendary: "传说"
        };
        return qualities[quality] || quality;
      },
  
      getCurrencyName: function(currency) {
        const currencies = {
          game_coin: "游戏币",
          skill_points: "技能点"
        };
        return currencies[currency] || currency;
      },
  
      processQueue: function() {
        if (hapState.isRequesting || hapState.pendingRequests.length === 0) return;
        
        hapState.isRequesting = true;
        const { requestFn, resolve, reject } = hapState.pendingRequests.shift();
        
        Promise.resolve(requestFn())
          .then(resolve)
          .catch(reject)
          .finally(() => {
            hapState.isRequesting = false;
            this.processQueue();
          });
      }
    };
  
    // DOM就绪后初始化
    $(document).ready(() => ShockBox.init());
  
  })(jQuery);
  