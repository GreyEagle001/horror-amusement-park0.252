<?php
if (!defined('ABSPATH')) exit;

class HAP_Shock_Box {
    private static $instance;
    private $item_manager;

    public static function init() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->item_manager = HAP_Item_Manager::init();
        add_shortcode('shock_box', [$this, 'render_shock_box']);
        add_action('wp_ajax_hap_search_items', [$this, 'ajax_search_items']);
        add_action('wp_ajax_nopriv_hap_search_items', [$this, 'ajax_search_items']);
        add_action('wp_ajax_hap_purchase_item', [$this, 'ajax_purchase_item']);
        add_action('wp_ajax_nopriv_hap_purchase_item', [$this, 'ajax_purchase_item']);
    }

    public function render_shock_box() {
        if (!is_user_logged_in()) {
            return '<div class="hap-notice">请登录后访问惊吓盒子</div>';
        }

        ob_start();
        ?>
        <div class="hap-shock-box-container">
            <h2>惊吓盒子</h2>

            <div class="hap-item-filters">
                <input type="text" id="hap-item-search" placeholder="搜索道具名称...">
                <select id="hap-item-type">
                    <option value="">所有类型</option>
                    <option value="consumable">消耗道具</option>
                    <option value="permanent">永久道具</option>
                    <option value="arrow">箭矢</option>
                    <option value="bullet">子弹</option>
                    <option value="equipment">装备</option>
                    <option value="skill">法术</option>
                </select>
                <select id="hap-item-quality">
                    <option value="">所有品质</option>
                    <option value="common">普通</option>
                    <option value="uncommon">精良</option>
                    <option value="rare">稀有</option>
                    <option value="epic">史诗</option>
                    <option value="legendary">传说</option>
                </select>
                <button id="hap-search-btn" class="hap-button">搜索</button>
            </div>

            <div class="hap-items-grid" id="hap-items-container">
                <?php $this->render_items(); ?>
            </div>

            <div class="hap-pagination" id="hap-items-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_items($args = []) {
        // 默认显示最新上架的商品
        if (empty($args['search']) && empty($args['type']) && empty($args['quality'])) {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }
    
        $result = $this->item_manager->search_items($args);
    
        if (empty($result['items'])) {
            echo '<div class="hap-no-items">未找到符合条件的商品，请尝试其他搜索条件。</div>';
            return;
        }
    
        foreach ($result['items'] as $item) {
            $this->render_item_card($item);
        }
    
        $this->render_pagination($result);
    }

    private function render_item_card($item) {
        $quality_class = 'hap-quality-' . esc_attr($item['quality']);
        $currency = $item['currency'] === 'game_coin' ? '游戏币' : '技巧值';
        ?>
        <div class="hap-item-card <?php echo esc_attr($quality_class); ?>" data-item-id="<?php echo esc_attr($item['item_id']); ?>">
            <div class="hap-item-header">
                <h3><?php echo esc_html($item['name']); ?></h3>
                <span class="hap-item-type"><?php echo esc_html($this->get_type_name($item['item_type'])); ?></span>
            </div>
            <div class="hap-item-body">
                <div class="hap-item-price">
                    <?php echo esc_html($item['price']); ?> <?php echo esc_html($currency); ?>
                </div>
                <div class="hap-item-quality">
                    <?php echo esc_html($this->get_quality_name($item['quality'])); ?>
                </div>
                <button class="hap-buy-btn" data-item-id="<?php echo esc_attr($item['item_id']); ?>">购买</button>
            </div>
        </div>
        <?php
    }
    

    public function ajax_search_items() {
        check_ajax_referer('hap-nonce', 'nonce');
        error_log('[HAP DEBUG] AJAX search initiated - '.current_time('mysql'));
    
        $request = array_map('wp_unslash', $_POST);
        $args = [
            'name'        => !empty($request['name']) ? sanitize_text_field($request['name']) : '',
            'item_type'   => ($request['item_type'] ?? '') === '*' ? '' : sanitize_text_field($request['item_type'] ?? ''),
            'page'        => max(1, absint($request['page'] ?? 1)),
            'per_page'    => min(50, absint($request['per_page'] ?? 12)),
            'debug_sql'   => filter_var($request['debug_sql'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ];
    
        try {
            $results = $this->query_items($args);
            
            // 结构标准化输出
            wp_send_json([
                'success' => true,
                'items' => array_values($results['items'] ?? []),
                'pagination' => $results['pagination'] ?? [
                    'total' => 0,
                    'pages' => 0,
                    'page'  => $args['page']
                ],
                'debug' => $args['debug_sql'] ? [
                    'sql' => $results['debug_sql'] ?? 'N/A',
                    'time_ms' => number_format($results['query_time'] ?? 0, 2)
                ] : null
            ]);
    
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('查询失败，请稍后重试', 'hap'),
                'code'    => uniqid('HAP_ERR_')
            ], 500);
        }
    }
    
    
    
    /**
     * 实际查询方法
     */
    protected function query_items($args) {
        $args = [
            'quality' => $_POST['quality'] ?? $_GET['quality'] ?? '' // 兼容GET/POST
        ];//增加数据进入数据集
        
        // 添加严格验证
        if (empty($args['quality'])) {
            error_log("警告：未接收到quality参数，使用默认查询");
            // 可选：返回全部物品或抛出错误
        }
        error_log("[参数检查] 传入的quality参数值: " . print_r($args['quality'], true));
        global $wpdb;
        
        $start_time = microtime(true);
        $where = ["1=1"];
        $params = [];
    
        // 参数安全处理
        $args = wp_parse_args($args, [
            'page'        => 1,
            'per_page'    => 20,
            'fuzzy_search' => false,
            'name'        => '',
            'item_type'   => '',
            'quality'     => '',
            'debug_sql'   => false
        ]);
    
        // 1. 名称搜索（精确/模糊二选一）
        if (!empty($args['name'])) {
            $where[] = $args['fuzzy_search'] 
                ? "name LIKE %s" 
                : "name = %s";
            $params[] = $args['fuzzy_search']
                ? '%' . $wpdb->esc_like($args['name']) . '%'
                : $args['name'];
        }
    
        // 2. 类型过滤
        if (!empty($args['item_type'])) {
            $where[] = "item_type = %s";
            $params[] = $args['item_type'];
        }
    
        // 3. QUALITY 强化处理（支持多值查询）
        if (!empty($args['quality'])) {
            error_log('Quality filter active: ' . $args['quality']); // 确认参数到达
            if (is_array($args['quality'])) {
                // 多quality查询（如 ['rare', 'epic']）
                $placeholders = implode(',', array_fill(0, count($args['quality']), '%s'));
                $where[] = "quality IN ($placeholders)";
                $params = array_merge($params, $args['quality']);
            } else {
                // 单quality查询
                $where[] = "quality = %s";
                $params[] = $args['quality'];
            }
        }
    
        // 主查询
        $sql = "SELECT SQL_CALC_FOUND_ROWS name, item_type, quality
                FROM {$wpdb->prefix}hap_items 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY name ASC
                LIMIT %d, %d";
        
        // 分页参数（最后追加）
        $params[] = max(0, ($args['page'] - 1) * $args['per_page']);
        $params[] = max(1, $args['per_page']);
    
        // 执行查询
        $items = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        error_log("[SQL调试] 实际执行SQL: " . $wpdb->last_query);
        $total = $wpdb->get_var("SELECT FOUND_ROWS()");
    
        return [
            'items' => is_array($items) ? $items : [],
            'pagination' => [
                'total' => (int)$total,
                'pages' => ceil($total / $args['per_page']),
                'page'  => max(1, $args['page'])
            ],
            'debug_sql'   => $args['debug_sql'] ? $wpdb->last_query : null,
            'query_time'  => round((microtime(true) - $start_time) * 1000, 2)
        ];
    }
    
    
    

    public function ajax_purchase_item() {
        check_ajax_referer('hap-nonce', 'nonce');
        error_log('AJAX purchase item called'); // 调试信息

        $user_id = get_current_user_id();
        $item_id = absint($_POST['item_id']);

        if (!$user_id) {
            wp_send_json_error(['message' => '请先登录']);
        }

        $result = $this->item_manager->purchase_item($user_id, $item_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // 更新销售次数
        $this->item_manager->update_sales_count($item_id);

        wp_send_json_success(['message' => '购买成功']);
    }

    // 辅助方法
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

    private function render_pagination($result) {
        if ($result['pages'] <= 1) return;

        echo '<div class="hap-pagination">';
        for ($i = 1; $i <= $result['pages']; $i++) {
            $active = $i == $result['page'] ? 'active' : '';
            echo '<button class="hap-page-btn ' . esc_attr($active) . '" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</button>';
        }
        echo '</div>';
    }
}
