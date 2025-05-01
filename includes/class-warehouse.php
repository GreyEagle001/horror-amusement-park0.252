<?php
if (!defined('ABSPATH')) exit;

class HAP_Warehouse
{
    private static $instance;
    private $item_manager;

    public static function init()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->item_manager = HAP_Item_Manager::init();
        
        // 注册短代码
        add_shortcode('warehouse', [$this, 'render_warehouse']);
        add_shortcode('horror_amusement_park_warehouse', [$this, 'render_warehouse']);
        
        // 注册AJAX处理
        add_action('wp_ajax_hap_get_inventory', [$this, 'ajax_get_inventory']);
        add_action('wp_ajax_hap_save_custom_items', [$this, 'ajax_save_custom_items']);
        add_action('wp_ajax_nopriv_hap_save_custom_items', [$this, 'ajax_save_custom_items']);
        add_action('wp_ajax_hap_get_custom_items', [$this, 'ajax_get_custom_items']);
        add_action('wp_ajax_nopriv_hap_get_custom_items', [$this, 'ajax_get_custom_items']);
        
    }

    public function render_warehouse()
    {
        if (!is_user_logged_in()) {
            return '<div class="hap-notice">请登录后访问仓库</div>';
        }

        // 开始输出缓冲
        ob_start();
        
        // 添加调试信息
        error_log('开始渲染仓库内容');
        
        // 渲染HTML内容
        include(HAP_PLUGIN_DIR . 'templates/warehouse.php');
        
        // 获取并清理输出缓冲
        $output = ob_get_clean();
        
        return $output;
    }

    private function render_inventory($args = []) {
        $user_id = get_current_user_id();
        $result = $this->get_user_inventory($user_id, $args);
    
        // 类型安全校验
        if (empty($result['items']) || !is_array($result['items'])) {
            return []; // 始终返回数组类型
        }
    
        return $result['items']; // 明确返回数据项
    }
    

    public function get_user_inventory($user_id, $args = []) {
        global $wpdb;
    
        // 参数处理
        $type = isset($args['type']) ? sanitize_text_field($args['type']) : '';
        $page = isset($args['page']) ? max(1, absint($args['page'])) : 1;
        $per_page = isset($args['per_page']) ? absint($args['per_page']) : 20;
    
        // 第一步：从仓库表获取用户商品基础信息
        $warehouse_query = $wpdb->prepare(
            "SELECT item_id, purchase_price, quantity, currency 
             FROM {$wpdb->prefix}hap_warehouse 
             WHERE user_id = %d",
            $user_id
        );
        $warehouse_items = $wpdb->get_results($warehouse_query, ARRAY_A);
    
        if (empty($warehouse_items)) {
            return ['items' => []];
        }
    
        // 提取需要查询的item_id列表
        $item_ids = array_column($warehouse_items, 'item_id');
        $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
    
        // 第二步：获取商品详情（带类型筛选）
        $items_query = "
            SELECT 
                i.item_id, i.item_type, i.name, i.attributes, i.quality,
                i.restrictions, i.effects, i.duration, i.price, i.author,
                i.created_at, i.level, i.consumption, i.learning_requirements,i.adjust_type,i.adjust_date
            FROM {$wpdb->prefix}hap_items AS i
            WHERE i.item_id IN ($placeholders)
        ";

        // 添加类型筛选条件
        if (!empty($type)) {
            $items_query .= " AND i.item_type = %s";
            $query_params = array_merge($item_ids, [$type]);
        } else {
            $query_params = $item_ids;
        }
    
        $items_data = $wpdb->get_results(
            $wpdb->prepare($items_query, $query_params),
            ARRAY_A
        );
    
        // 合并数据
        $merged_items = [];
        foreach ($items_data as $item) {
            $warehouse_key = array_search($item['item_id'], $item_ids);
            if ($warehouse_key !== false) {
                $merged_items[] = array_merge(
                    $warehouse_items[$warehouse_key], // 仓库数据
                    $item // 商品详情数据
                );
            }
        }
    
        // 分页处理
        $total_items = count($merged_items);
        $paginated_items = array_slice(
            $merged_items,
            ($page - 1) * $per_page,
            $per_page
        );
    
        return [
            'items' => $paginated_items,
            'total' => $total_items,
            'total_pages' => ceil($total_items / $per_page)
        ];
    }
    
    


    private function render_inventory_item($item)
    {
        // 基础验证
        if (!is_array($item) || empty($item['item_id'])) {
            return null;
        }
    
        return [
            // 核心属性
            'item_id'      => $item['item_id'],
            'name'         => esc_html($item['name'] ?? '未命名道具'),
            'item_type'    => esc_html($this->get_type_name($item['item_type'] ?? 'unknown')),
            'quality'      => esc_html($item['quality'] ?? 'common'),
            'author'       => esc_html($item['author'] ?? '系统'),
            'created_at'   => $item['created_at'] ?? date('Y-m-d H:i:s'),
    
            // 交易信息
            'price'          => floatval($item['price'] ?? 0),
            'purchase_price' => floatval($item['purchase_price'] ?? 0),
            'currency'       => esc_html($item['currency'] ?? 'game_coin'),
            'quantity'       => intval($item['quantity'] ?? 1),
    
            // 游戏机制
            'effects'               => esc_html($item['effects'] ?? '无效果'),
            'attributes'            => esc_html($item['attributes'] ?? '无属性'),
            'restrictions'          => esc_html($item['restrictions'] ?? '无限制'),
            'duration'              => ($item['duration'] !== null) ? intval($item['duration']) : null, //$number = 
            'level'                 => $item['level'] ?? null,
            'consumption'           => $item['consumption'] ?? null,
            'learning_requirements' => $item['learning_requirements'] ?? null,
            'adjust_type' => $item['adjust_type'] ?? null,
            'adjust_date' => isset($item['adjust_date']) ? (new DateTime($item['adjust_date']))->format('Y-m-d') : null

        ];
    }
    
    


    public function ajax_get_inventory() {
        check_ajax_referer('hap-nonce', 'nonce');

        $request = array_map('wp_unslash', $_POST);

        try {
            $args = [
                'type'        => !empty($request['type']) ? sanitize_text_field($request['type']) : '',
                'page'        => max(1, absint($request['page'] ?? 1)),
            'per_page'    => min(50, absint($request['per_page'] ?? 12)),
            ];
    
            // 获取原始数据
            $rawItems = $this->render_inventory($args);
            
            // 处理数据项
            $dataItems = [];
            if (is_iterable($rawItems)) {
                foreach ($rawItems as $item) {
                    if ($dataItem = $this->render_inventory_item($item)) {
                        $dataItems[] = $dataItem;
                    }
                }
            }
    
            wp_send_json_success([
                'items' => $dataItems,
                'isEmpty' => empty($dataItems)
            ]);
    
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => '库存加载失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // 处理 AJAX 表单提交
    public function ajax_save_custom_items() {
        // 1. 请求开始标记
        error_log('[HAP][开始] AJAX保存请求开始 '.current_time('mysql'));
        
        try {
            // 2. 安全验证
            error_log('[HAP][阶段1] 开始Nonce验证');
            check_ajax_referer('hap-nonce', 'nonce');
            error_log('[HAP][阶段1] Nonce验证通过');
    
            // 3. 请求数据处理
            error_log('[HAP][阶段2] 原始POST数据: '.print_r($_POST, true));
            $request = array_map('wp_unslash', $_POST);
            error_log('[HAP][阶段2] 处理后的请求数据: '.print_r($request, true));
    
            // 4. 字段验证
            error_log('[HAP][阶段3] 开始字段验证');
            if (empty($request['name'])) {
                error_log('[HAP][错误] 道具名称为空');
                wp_send_json_error([
                    'message' => __('道具名称不能为空', 'hap'),
                    'code'    => 'HAP_ERR_NAME_EMPTY'
                ], 400);
            }
    
            if (empty($request['item_type']) || !in_array($request['item_type'], ['consumable', 'permanent', 'arrow', 'bullet', 'equipment', 'skill'])) {
                error_log('[HAP][错误] 无效道具类型: '.($request['item_type'] ?? '空值'));
                wp_send_json_error([
                    'message' => __('请选择有效的道具类型', 'hap'),
                    'code'    => 'HAP_ERR_INVALID_TYPE'
                ], 400);
            }
            error_log('[HAP][阶段3] 基础验证通过');
    
            // 5. 参数组装
            error_log('[HAP][阶段4] 开始构建参数数组');
            $args = [
                // 基础信息（空字符串转为空值）
                'name'        => !empty($request['name']) ? sanitize_text_field($request['name']) : null,
                'attributes'  => !empty($request['attributes']) ? sanitize_textarea_field($request['attributes']) : null,
                
                // 类型选择（保留默认值）
                'item_type'   => sanitize_text_field($request['item_type'] ?? 'consumable'),
                'quality'     => sanitize_text_field($request['quality'] ?? 'common'),
                
                // 数值类字段（空值不传）
                'level'         => isset($request['level']) ? intval($request['level']) : null,
                'restrictions'  => isset($request['restrictions']) ? intval($request['restrictions']) : null,
                'effects'       => !empty($request['effects']) ? sanitize_textarea_field($request['effects']) : null,
                'comment'       => !empty($request['comment']) ? sanitize_text_field($request['comment']) : null,
                'duration'      => isset($request['duration']) ? intval($request['duration']) : null,
                
                // 价格系统
                'price'     => isset($request['price']) ? floatval($request['price']) : null,
                'currency'  => sanitize_text_field($request['currency'] ?? 'game_coin'),
                
                // 消耗/学习要求
                'consumption'   => !empty($request['consumption']) ? sanitize_text_field($request['consumption']) : null,
                'learning_requirements'  => !empty($request['learning_requirements']) ? sanitize_textarea_field($request['learning_requirements']) : null,
                
                // 作者信息
                'author' => !empty($request['author']) ? sanitize_text_field($request['author']) : '匿名'
            ];
            error_log('[HAP][阶段4] 最终参数: '.print_r($args, true));
    
            // 6. 数据库操作
            error_log('[HAP][阶段5] 开始数据库保存');
            $results = $this->save_custom_items($args);
            error_log('[HAP][阶段5] 保存结果: '.print_r($results, true));
    
            // 7. 成功响应
            error_log('[HAP][成功] 准备返回成功响应');
            wp_send_json([
                'success' => true,
                'data'    => [
                    'item_id'   => $results['item_id'] ?? 0,
                    'view_url'  => add_query_arg('item_id', $results['item_id'] ?? 0, home_url('/item-view'))
                ],
                'message' => __('道具保存成功', 'hap')
            ]);
    
        } catch (Exception $e) {
            // 8. 异常处理
            error_log('[HAP][异常] 文件:'.$e->getFile().' 行号:'.$e->getLine());
            error_log('[HAP][异常] 错误信息:'.$e->getMessage());
            error_log('[HAP][异常] 堆栈追踪:'.$e->getTraceAsString());
            
            wp_send_json_error([
                'message' => $e->getMessage() ?: __('保存失败，请稍后重试', 'hap'),
                'code'    => 'HAP_ERR_'.uniqid()
            ], 500);
        } finally {
            // 9. 最终标记
            error_log('[HAP][结束] 请求处理完成 '.current_time('mysql'));
        }
    }

// 后端新增 AJAX 接口
public function ajax_get_custom_items() {
    global $wpdb;
    try {
        check_ajax_referer('hap-nonce', 'nonce');
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hap_custom_items WHERE user_id = " . get_current_user_id());
        
        if (is_wp_error($items)) {
            wp_send_json_error([
                'message' => '数据库查询失败',
                'console' => 'Error: Failed to fetch custom items. Check server logs for details.'
            ]);
        }
        wp_send_json_success(['items' => $items]);
        
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => '服务器异常',
            'console' => 'Error: ' . $e->getMessage() 
        ]);
    }
}


    // 辅助方法

    private function save_custom_items($args) {
        global $wpdb;
        
        
        error_log('[HAP][数据库] 开始保存道具数据 '.current_time('mysql'));
        error_log('[HAP][数据库] 接收参数: '.print_r($args, true));
    
        try {
            // 定义基础必填字段
            $item_data = [
                'user_id'    => get_current_user_id(),
                'name'       => isset($args['name']) ? sanitize_text_field($args['name']) : '未命名道具',
                'created_at' => current_time('mysql')
            ];
    
            // 可选字段映射表（字段名 => 数据类型）
            $optional_fields = [
                'attributes'  => 'string',
                'item_type'   => 'string',
                'quality'     => 'string',
                'level'       => 'int',
                'restrictions'=> 'int',
                'effects'     => 'string',
                'comment'     => 'string',
                'duration'    => 'int',
                'price'       => 'float',
                'currency'    => 'string',
                'consumption' => 'string',
                'learning_requirements' => 'string',
                'author'      => 'string'
            ];
    
            // 仅处理有输入的字段
            foreach ($optional_fields as $field => $type) {
                if (isset($args[$field]) && $args[$field] !== '') {
                    $item_data[$field] = $this->sanitize_field($args[$field], $type);
                }
            }
    
            // 动态生成占位符
            $placeholders = [];
            foreach (array_keys($item_data) as $key) {
                $placeholders[] = $this->get_placeholder_type($key, $optional_fields);
            }
    
            // 执行插入（仅包含有值的字段）
            $result = $wpdb->insert(
                "{$wpdb->prefix}hap_custom_items",
                $item_data,
                $placeholders
            );
    
            if (false === $result) {
                $error_msg = '数据库插入失败: ' . $wpdb->last_error;
                error_log('[HAP][数据库错误] ' . $error_msg);
                throw new Exception($error_msg);
            }
    
            return [
                'item_id'  => $wpdb->insert_id,
                'view_url' => add_query_arg('item_id', $wpdb->insert_id, home_url('/item-view')),
                'console'  => 'Item saved successfully. ID: ' . $wpdb->insert_id // 成功日志（可选）
            ];    
        } catch (Exception $e) {
            return [
                'error'   => true,
                'message' => $e->getMessage(),
                'console' => 'Error: ' . $e->getMessage() // 强制输出到前端控制台
            ];
        }
    }
    
    /**
     * 字段类型处理器
     */
    private function sanitize_field($value, $type) {
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'string':
                return sanitize_text_field($value);
            default:
                return $value;
        }
    }
    
    /**
     * 获取SQL占位符类型
     */
    private function get_placeholder_type($field, $field_types) {
        if (!isset($field_types[$field])) return '%s';
        
        switch ($field_types[$field]) {
            case 'int':   return '%d';
            case 'float': return '%f';
            default:      return '%s';
        }
    }
    
    private function get_type_name($type) {
        $types = [
            'consumable' => '消耗道具',
            'permanent' => '永久道具',
            'arrow' => '箭矢',
            'bullet' => '子弹',
            'equipment' => '装备',
            'skill' => '法术'
        ];
        return $types[$type] ?? $type;
    }

    private function get_quality_name($quality) {
        $qualities = [
            'common' => '普通',
            'uncommon' => '精良',
            'rare' => '稀有',
            'epic' => '史诗',
            'legendary' => '传说'
        ];
        return $qualities[$quality] ?? $quality;
    }
}