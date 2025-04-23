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
        add_shortcode('warehouse', [$this, 'render_warehouse']);
        add_action('wp_ajax_hap_get_inventory', [$this, 'ajax_get_inventory']);
        add_action('wp_ajax_hap_save_custom_items', [$this, 'ajax_save_custom_items']);
        add_action('wp_ajax_nopriv_hap_save_custom_items', [$this, 'ajax_save_custom_items']);
            }

    public function render_warehouse()
    {
        if (!is_user_logged_in()) {
            return '<div class="hap-notice">请登录后访问仓库</div>';
        }

        
?>
        <div class="hap-warehouse-container">
            <div class="hap-warehouse-tabs">
                <button class="hap-tab-btn active" data-tab="inventory">我的仓库</button>
                <button class="hap-tab-btn" data-tab="custom">自定义道具</button>
            </div>

            <div class="hap-tab-content active" id="hap-inventory-tab">
                <h3>我的仓库</h3>
                <div class="hap-inventory-filters">
                    <select id="hap-inventory-type">
                        <option value="*">所有类型</option>
                        <option value="consumable">消耗道具</option>
                        <option value="permanent">永久道具</option>
                        <option value="arrow">箭矢</option>
                        <option value="bullet">子弹</option>
                        <option value="equipment">装备</option>
                        <option value="skill">法术</option>
                    </select>
                </div>
                <button id="hap-warehouse-search-btn" class="hap-button">搜索</button>
                <div class="hap-inventory-grid" id="hap-inventory-container">
                </div>
            </div>

            <div class="hap-tab-content" id="hap-custom-tab">
                <h3>自定义道具</h3>
                <div class="hap-custom-item-container">
            <h4>添加自定义道具</h4>
            <div class="hap-custom-item-filters">
                <input type="text" id="hap-custom-item-name" placeholder="请输入道具名称" required>
                <select id="hap-custom-item-type" required>
                    <option value="">选择道具类型</option>
                    <option value="consumable">消耗道具</option>
                    <option value="permanent">永久道具</option>
                    <option value="arrow">箭矢</option>
                    <option value="bullet">子弹</option>
                    <option value="equipment">装备</option>
                    <option value="skill">法术</option>
                </select>
                <select id="hap-custom-item-quality" required>
                    <option value="">选择道具品质</option>
                    <option value="common">普通</option>
                    <option value="uncommon">精良</option>
                    <option value="rare">稀有</option>
                    <option value="epic">史诗</option>
                    <option value="legendary">传说</option>
                </select>
                <button id="hap-custom-item-save-btn" class="hapcustom-item-save-button">保存道具</button>
            </div>
    
            <div class="hap-custom-item-grid" id="hap-custom-item-container">
                <!-- 自定义道具列表将在此显示 -->
            </div>
    
            <div class="hap-custom-item-pagination" id="hap-custom-items-pagination"></div>
        </div>
            </div>
        </div>
    <?php
        return ob_get_clean();
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
        check_ajax_referer('hap-nonce', 'nonce');
        error_log('[HAP DEBUG] AJAX save initiated - ' . current_time('mysql'));
    
        $request = array_map('wp_unslash', $_POST);
        $args = [
            'name'        => !empty($request['name']) ? sanitize_text_field($request['name']) : '',
            'item_type'   => ($request['item_type'] ?? '') === '*' ? '' : sanitize_text_field($request['item_type'] ?? ''),
            'quality'     => ($request['quality'] ?? '') === '*' ? '' : sanitize_text_field($request['quality'] ?? ''),
        ];
    
        try {
            $results = $this->save_custom_items($args);
    
            // 结构标准化输出
            wp_send_json([
                'success' => true,
                'items' => array_values($results['items'] ?? []),
                'pagination' => $results['pagination'] ?? [
                    'total' => 0
                ],
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('保存失败，请稍后重试', 'hap'),
                'code'    => uniqid('HAP_ERR_')
            ], 500);
        }
    }

// 后端新增 AJAX 接口
public function ajax_get_custom_items() {
    global $wpdb;
    check_ajax_referer('hap-nonce', 'nonce');
    $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hap_custom_items WHERE user_id = " . get_current_user_id());
    wp_send_json_success(['items' => $items]);
}

    // 辅助方法

    private function save_custom_items($args) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}hap_custom_items", [
            'user_id' => get_current_user_id(),
            'name' => $args['name'],
            'item_type' => $args['item_type'],
            'quality' => $args['quality'],
            'created_at' => current_time('mysql')
        ]);
        return ['items' => [$wpdb->insert_id]];
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