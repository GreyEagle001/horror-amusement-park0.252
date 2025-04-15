/**
 * 惊悚乐园插件 - 前端核心脚本
 * 已优化性能：添加防抖、请求队列、缓存和资源控制
 */
jQuery(document).ready(function($) {
    // 全局状态管理
    const hapState = {
        pendingRequests: [], //存储当前正在进行的请求
        isRequesting: false, // 是否有正在进行的请求
        cache: new Map(),
        debounceTimers: {}
    };

    // 初始化所有模块
    function initModules() {
        try{if ($('.hap-personal-center').length) {
            initPersonalCenter();
            console.log('个人中心初始化成功'); // 调试信息
        }
        if ($('.hap-item-filters').length) { // 检索全局中是否有hap-shock-box-container容器或父元素
            initShockBox();
            loadItems(1); // 初始加载第一页
            console.log('惊吓盒子初始化成功'); // 调试信息
        }
        if ($('.hap-warehouse-container').length) {
            initWarehouse();
            loadInventory(); // 初始加载库存
            console.log('仓库初始化成功'); // 调试信息
        }
        if ($('.hap-admin-center').length) {
            initAdminCenter();
            console.log('管理员页面初始化成功'); // 调试信息
        }
    }
        catch (error) {
            console.error('模块初始化失败:', error);
        }
    }

    function initWarehouse() {
        console.log('仓库初始化成功111'); // 调试信息
    }

    function initAdminCenter() {
        console.log('管理员中心已初始化');
    }

    function loadInventory() {
        console.log('加载库存');
    }

    $(document).ready(function() {
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
        return requestFn().then(data => {
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
            .then(data => {
                resolve(data);
                hapState.isRequesting = false;
                setTimeout(processQueue, 100); // 添加延迟防止密集请求
            })
            .catch(err => {
                reject(err);
                hapState.isRequesting = false;
                setTimeout(processQueue, 100);
            });
    }

    // ==================== 个人中心模块 ====================
    function initPersonalCenter() {
        //个人中心已初始化
        console.log('个人中心初始化成功111'); // 调试信息
        // 头像上传
        $('#hap-avatar-upload').on('change', function() { // 将id为hap-avatar-upload绑定函数，当元素的值变化时触发
            const file = this.files[0]; // 获取用户选择的第一个文件
            if (!file) return;
            
            const formData = new FormData();
            formData.append('action', 'hap_upload_avatar'); // 告诉服务器端动作名称
            formData.append('nonce', hap_ajax.nonce); // 安全措施
            formData.append('avatar', file); // 上传头像文件
            
            const $btn = $(this); //防止重复提交
            $btn.prop('disabled', true);
            
            hapQueueRequest(() => $.ajax({ // 基础配置
                url: hap_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }))
            .then(response => {
                if (response.success) {
                    $('#hap-avatar-id').val(response.data.id); //将服务器返回的头像 ID 存储在具有 ID hap-avatar-id 的隐藏输入框中
                    updateAvatarPreview(response.data.url);
                } else {
                    throw new Error(response.data || '上传失败');
                }
            })
            .catch(error => {
                alert(error.message);
            })
            .finally(() => {
                $btn.prop('disabled', false);
            });
        });

        // 表单提交
        $('#hap-profile-form').on('submit', function(e) {
            e.preventDefault(); //  阻止默认提交行为  
            submitProfileForm($(this));
        });

        // 编辑申请
        $('#hap-request-edit').on('click', function() {
            if (!confirm('确定要提交编辑申请吗？')) return;
            submitEditRequest($(this));
        });
    }

    function updateAvatarPreview(url) {
        $('.hap-avatar-preview').html(
            `<img src="${url}" alt="头像预览" style="max-width:100px;">`
        );
    }

    //记录注册表单信息
    function submitProfileForm($form) {
        const $btn = $form.find('.hap-submit'); //传入的表单元素 $form 中查找具有类名 hap-submit 的子元素，通常这是一个提交按钮。
        const originalText = $btn.text(); //将找到的提交按钮元素存储在变量 $btn 中
        
        $btn.prop('disabled', true).html('保存中... <span class="hap-loading"></span>'); //禁用提交按钮，防止用户在请求处理过程中重复点击
        
        const formData = collectFormData($form);
        
        hapQueueRequest(() => $.post(hap_ajax.ajax_url, formData))
            .then(response => {
                if (response.success) {
                    showSuccess('个人信息保存成功！');
                    location.reload(); // 重新加载当前页面，以反映保存后的最新数据。
                } else {
                    throw new Error(response.data || '保存失败');
                }
            })
            .catch(error => {
                showError(error.message);
            })
            .finally(() => {
                $btn.prop('disabled', false).text(originalText);
            });
    }

    //收集并处理注册表单信息
    function collectFormData($form) {
        const data = {
            action: 'hap_save_profile',
            nonce: hap_ajax.nonce,
            nickname: $form.find('input[name="nickname"]').val(),
            avatar_id: $form.find('input[name="avatar_id"]').val(),
            bio: $form.find('textarea[name="bio"]').val(),
            completed_scenarios: $form.find('textarea[name="completed_scenarios"]').val(),
            attributes: {},
            derived_attributes: {},
            specializations: {},
            currency: {
                game_coin: $form.find('input[name="currency[game_coin]"]').val(),
                skill_points: $form.find('input[name="currency[skill_points]"]').val()
            }
        };
        
        // 收集动态字段
        $form.find('input[name^="attributes["]').each(function() {
            const name = $(this).attr('name').match(/\[(.*?)\]/)[1];
            data.attributes[name] = $(this).val();
        });
        
        $form.find('input[name^="derived_attributes["]').each(function() {
            const name = $(this).attr('name').match(/\[(.*?)\]/)[1];
            data.derived_attributes[name] = $(this).val();
        });
        
        $form.find('input[name^="specializations["]').each(function() {
            const name = $(this).attr('name').match(/\[(.*?)\]/)[1];
            data.specializations[name] = $(this).val();
        });
        
        return data;
    }

    //申请再次编辑
    function submitEditRequest($btn) {
        const originalText = $btn.text(); // 获取按钮当前的文本内容
        $btn.prop('disabled', true).html('提交中... <span class="hap-loading"></span>');
        
        hapQueueRequest(() => $.post(hap_ajax.ajax_url, {
            action: 'hap_request_edit',
            nonce: hap_ajax.nonce
        }))
        .then(response => {
            if (response.success) {
                showSuccess('申请已提交，请等待管理员审核');
                location.reload();
            } else {
                throw new Error(response.data || '提交失败');
            }
        })
        .catch(error => {
            showError(error.message);
        })
        .finally(() => {
            $btn.prop('disabled', false).text(originalText);
        });
    }

    // ==================== 惊吓盒子模块 ====================
function initShockBox() {
    console.log('惊吓盒子初始化成功'); // 调试信息

    // 搜索功能
    $('#hap-search-btn').on('click', () => {
        console.log('搜索按钮被点击'); // 调试信息
        loadItems(1); // 点击后加载第一页搜索结果
    });

    $('#hap-item-search').on('input', () => {
        console.log('输入框内容变化'); // 调试信息
        hapDebounce('item_search', () => {
            console.log('防抖函数被触发'); // 调试信息
            loadItems(1);
        }, 500);
    });

    // 购买功能
    $('#hap-items-container').on('click', '.hap-buy-btn', function() {
        const $btn = $(this);
        const itemId = $btn.data('item-id');

        if (!confirm('确定要购买这个商品吗？')) return;

        $btn.prop('disabled', true).text('购买中...');

        // 使用 Promise.resolve 确保 hapQueueRequest 返回的是 Promise
        Promise.resolve(hapQueueRequest(() => $.post(hap_ajax.ajax_url, {
            action: 'hap_purchase_item',
            nonce: hap_ajax.nonce,
            item_id: itemId
        })))
        .then(response => {
            if (response.success) {
                showSuccess('购买成功！');
                const currentPage = $('#hap-items-container').data('current-page') || 1;
                loadItems(currentPage); // 刷新当前页
            } else {
                throw new Error(response.data || '购买失败');
            }
        })
        .catch(error => {
            showError(error.message); // 显示错误信息
        })
        .then(() => {
            $btn.prop('disabled', false).text('购买'); // 恢复按钮状态
        });
    });

// 加载商品列表（支持模糊查询 + 调试增强版）
function loadItems(page) {
    const $container = $('#hap-items-container');
    if ($container.data('loading')) return;

    $container.data('loading', true).html('<div class="hap-loading">加载中...</div>');

    // 获取搜索参数
    const searchText = $('#hap-item-search').val().trim(); // 用户输入的搜索词
    const itemType = $('#hap-item-type').val() || 'all';   // 物品类型

    // 构造请求参数（强制模糊查询逻辑）
    const searchParams = {
        action: 'hap_search_items',
        nonce: hap_ajax.nonce,
        name: searchText || '*',      // 空搜索时查询全部
        item_type: itemType,
        page: page,
        debug_sql: true,              // 要求返回SQL日志
        fuzzy_search: true            // 明确要求后端启用模糊查询
    };

    console.log('[HAP Debug] 请求参数:', { 
        ...searchParams,
        nonce: `...${searchParams.nonce.slice(-4)}` // 脱敏处理
    });

    // 缓存键（排除page参数以缓存同一搜索条件的不同分页）
    const cacheKey = JSON.stringify({ ...searchParams, page: '*' });

    Promise.resolve(hapCacheRequest(cacheKey, () => 
        $.post(hap_ajax.ajax_url, searchParams)
    ))
    .then(response => {
        if (response.success) {
            // 调试信息输出
            if (response.debug) {
                console.groupCollapsed('[HAP SQL] 数据库查询详情');
                console.log('执行SQL:', response.debug.sql);
                console.log('参数:', response.debug.params);
                console.log('耗时:', response.debug.time_ms + 'ms');
                console.groupEnd();
            }

            // 数据验证
            if (!Array.isArray(response.data.items)) {
                throw new Error('返回数据格式异常：items应为数组！');
            }

            console.log('[HAP Debug] 响应数据:', {
                items_count: response.data.items.length,
                pagination: response.data.pagination || '无分页信息'
            });

            // 渲染结果
            renderItems(response.data.items);
            renderPagination(response.data.pagination || { current: 1, total: 1 });
            $container.data('current-page', page);
        } else {
            throw new Error(response.data || '后端返回失败');
        }
    })
    .catch(error => {
        console.error('[HAP Error] 请求异常:', error);
        $container.html(`
            <div class="hap-error">
                ${error.message}<br>
                ${searchText ? `搜索词: "${searchText}"` : ''}
            </div>
        `);
    })
    .finally(() => {
        $container.data('loading', false);
    });
}




    // 渲染商品列表
    function renderItems(items = []) {
        const $container = $('#hap-items-container');
        $container.empty();

        if (items.length === 0) {
            $container.html('<div class="hap-no-items">没有找到符合条件的商品</div>');
            return;
        }

        const fragment = document.createDocumentFragment();

        items.forEach(item => {
            const itemCard = document.createElement('div');
            itemCard.className = 'hap-item-card';
            itemCard.innerHTML = `
                <h4>${escapeHtml(item.name)}</h4>
                <p>类型: ${escapeHtml(item.type)}</p>
                <p>价格: ${escapeHtml(item.price)} ${escapeHtml(item.currency)}</p>
                <button class="hap-buy-btn" data-item-id="${escapeHtml(item.id)}">购买</button>
            `;
            fragment.appendChild(itemCard);
        });

        $container.append(fragment);
    }

    // 渲染分页
    function renderPagination(data) {
        const $pagination = $('#hap-items-pagination');
        if (data.total <= data.per_page) {
            $pagination.empty();
            return;
        }

        const totalPages = Math.ceil(data.total / data.per_page);
        const currentPage = data.page;
        let html = '<div class="hap-pagination-links">';

        if (currentPage > 1) {
            html += `<button class="hap-page-btn" data-page="${currentPage - 1}">上一页</button>`;
        }

        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<span class="hap-current-page">${i}</span>`;
            } else if (Math.abs(i - currentPage) <= 2 || i === 1 || i === totalPages) {
                html += `<button class="hap-page-btn" data-page="${i}">${i}</button>`;
            } else if (Math.abs(i - currentPage) === 3) {
                html += '<span class="hap-page-dots">...</span>';
            }
        }

        if (currentPage < totalPages) {
            html += `<button class="hap-page-btn" data-page="${currentPage + 1}">下一页</button>`;
        }

        html += '</div>';
        $pagination.html(html);

        $pagination.on('click', '.hap-page-btn', function() {
            loadItems(parseInt($(this).data('page')));
        });
    }
}


    // ==================== 仓库模块 ====================
    function initWarehouse() {
        // 标签切换
        $('.hap-warehouse-tabs').on('click', '.hap-tab-btn', function() {
            const tab = $(this).data('tab');
            $('.hap-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            $('.hap-tab-content').removeClass('active');
            $(`#hap-${tab}-tab`).addClass('active');
            
            if (tab === 'inventory') {
                loadInventory();
            }
        });
        
        // 动态表单字段
        $('#hap-item-type-select').on('change', function() {
            loadItemFormFields($(this).val());
        });
        
        // 表单提交
        $('#hap-custom-item-form').on('submit', function(e) {
            e.preventDefault();
            submitCustomItemForm($(this));
        });
    }

    function loadInventory() {
        const $container = $('#hap-inventory-container');
        if ($container.data('loading')) return;
    
        $container.data('loading', true).html('<div class="hap-loading">加载中...</div>');
    
        hapCacheRequest('user_inventory', () =>
            $.post(hap_ajax.ajax_url, {
                action: 'hap_get_inventory',
                nonce: hap_ajax.nonce
            })
        )
        .then(response => {
            if (response.success) {
                renderInventory(response.data.items);
            } else {
                throw new Error(response.data || '加载失败');
            }
        })
        .catch(error => {
            $container.html(`<div class="hap-error">${error.message}</div>`);
        })
        .finally(() => {
            $container.data('loading', false);
        });
    }
    

    function renderInventory(items) {
        const $container = $('#hap-inventory-container');
        $container.empty();
    
        if (items.length === 0) {
            $container.html('<div class="hap-no-items">仓库空空如也</div>');
            return;
        }
    
        const fragment = document.createDocumentFragment();
    
        items.forEach(item => {
            const itemEl = document.createElement('div');
            itemEl.className = 'hap-inventory-item';
    
            // 显示道具名称和数量
            let html = `<h4>${escapeHtml(item.item_name)}</h4>
                       <p>数量: ${escapeHtml(item.quantity)}</p>`;
    
            itemEl.innerHTML = html;
            fragment.appendChild(itemEl);
        });
    
        $container.append(fragment);
    }

    function loadItemFormFields(itemType) {
        const $container = $('#hap-item-form-fields');
        $container.html('<div class="hap-loading">加载表单...</div>');
        
        // 这里可以根据不同类型加载不同的表单字段
        // 示例代码，实际需要根据需求实现
        let html = `
            <div class="hap-form-section">
                <label>道具名称</label>
                <input type="text" name="name" required>
            </div>
        `;
        
        switch (itemType) {
            case 'equipment':
                html += `
                    <div class="hap-form-section">
                        <label>装备属性</label>
                        <textarea name="attributes" rows="3"></textarea>
                    </div>
                `;
                break;
            case 'skill':
                html += `
                    <div class="hap-form-section">
                        <label>法术等级</label>
                        <input type="number" name="level" min="1" max="10">
                    </div>
                `;
                break;
            default:
                html += `
                    <div class="hap-form-section">
                        <label>特效描述</label>
                        <textarea name="effect" rows="3"></textarea>
                    </div>
                `;
        }
        
        $container.html(html);
    }

    function submitCustomItemForm($form) {
        const $btn = $form.find('.hap-submit');
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('创建中...');
        
        const formData = {
            action: 'hap_create_custom_item',
            nonce: $form.find('[name="hap_nonce"]').val(),
            item_type: $form.find('[name="item_type"]').val(),
            name: $form.find('[name="name"]').val()
        };
        
        // 根据不同类型收集不同字段
        const itemType = formData.item_type;
        if (itemType === 'equipment') {
            formData.attributes = $form.find('[name="attributes"]').val();
        } else if (itemType === 'skill') {
            formData.level = $form.find('[name="level"]').val();
        } else {
            formData.effect = $form.find('[name="effect"]').val();
        }
        
        hapQueueRequest(() => $.post(hap_ajax.ajax_url, formData))
        .then(response => {
            if (response.success) {
                showSuccess('自定义道具创建成功！');
                $form[0].reset();
                loadInventory();
            } else {
                throw new Error(response.data || '创建失败');
            }
        })
        .catch(error => {
            showError(error.message);
        })
        .finally(() => {
            $btn.prop('disabled', false).text(originalText);
        });
    }

    // ==================== 管理员模块 ====================
    function initAdminCenter() {
        $('.hap-admin-center').on('click', '.hap-approve-btn', function() {
            processRequest($(this), 'hap_approve_edit_request', '批准');
        });
        
        $('.hap-admin-center').on('click', '.hap-reject-btn', function() {
            if (confirm('确定要拒绝此申请吗？')) {
                processRequest($(this), 'hap_reject_edit_request', '拒绝');
            }
        });
    }

    function processRequest($btn, action, actionText) {
        const userId = $btn.data('user-id');
        const $row = $btn.closest('tr');
        
        $btn.prop('disabled', true).text('处理中...');
        
        hapQueueRequest(() => $.post(hap_ajax.ajax_url, {
            action: action,
            nonce: hap_ajax.nonce,
            user_id: userId
        }))
        .then(response => {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    checkEmptyTable();
                });
            } else {
                throw new Error(response.data || `${actionText}失败`);
            }
        })
        .catch(error => {
            showError(error.message);
            $btn.prop('disabled', false).text(actionText);
        });
    }

    function checkEmptyTable() {
        if ($('.hap-requests-table tbody tr').length === 0) {
            $('.hap-requests-table').replaceWith('<p>当前没有待处理的编辑申请。</p>');
        }
    }

    // ==================== 辅助函数 ====================
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
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
        alert('错误: ' + message); // 可以替换为更友好的错误显示
    }
});