<?php
if (!defined('ABSPATH')) exit;

class HAP_Shock_Box
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

        // 短代码和AJAX钩子注册
        add_shortcode('shock_box', [$this, 'render_shock_box']);
        add_action('wp_ajax_hap_search_items', [$this, 'ajax_search_items']);
        add_action('wp_ajax_nopriv_hap_search_items', [$this, 'ajax_search_items']);
        add_action('wp_ajax_hap_purchase_item', [$this, 'ajax_purchase_item']);
        add_action('wp_ajax_nopriv_hap_purchase_item', [$this, 'ajax_purchase_item']);

        // 修正后的详情钩子（移除init检查）
        add_action('wp_ajax_hap_get_full_details', [$this, 'handle_full_details']);
        add_action('wp_ajax_nopriv_hap_get_full_details', [$this, 'handle_full_details']);
    }

    public function handle_full_details()
    {
        try {
            // 1. 安全验证
            if (!check_ajax_referer('hap-nonce', 'nonce', false)) {
                throw new RuntimeException('Invalid security nonce', 403);
            }

            // 2. 获取请求参数
            $requested_fields = isset($_POST['fields'])
                ? explode(',', sanitize_text_field($_POST['fields']))
                : null; // null表示返回全部字段

            $items = array_map(function ($item) {
                return [
                    'name'      => sanitize_text_field($item['name'] ?? ''),
                    'item_type' => $this->validate_enum($item['item_type'] ?? '', ['consumable', 'equipment', 'material']),
                    'quality'   => $this->validate_enum($item['quality'] ?? '', ['common', 'uncommon', 'rare', 'epic', 'legendary'])
                ];
            }, $_POST['items'] ?? []);

            if (empty($items)) {
                throw new InvalidArgumentException('No items specified', 400);
            }

            // 3. 构建动态查询
            global $wpdb;
            $results = [];

            foreach ($items as $item) {
                // 3.1 基础WHERE条件
                $where = ["name = %s"];
                $params = [$item['name']];

                // 3.2 添加类型/品质条件（如果提供）
                if (!empty($item['item_type'])) {
                    $where[] = "item_type = %s";
                    $params[] = $item['item_type'];
                }
                if (!empty($item['quality'])) {
                    $where[] = "quality = %s";
                    $params[] = $item['quality'];
                }

                // 3.3 动态选择字段
                $select_fields = $requested_fields
                    ? implode(', ', array_map('sanitize_key', $requested_fields))
                    : 'name, item_type, attributes, quality, restrictions, effects, 
                       price, currency, author, sales_count, level, 
                       consumption, learning_requirements, created_at';

                $sql = $wpdb->prepare(
                    "SELECT {$select_fields} 
                     FROM {$wpdb->prefix}hap_items 
                     WHERE " . implode(' AND ', $where),
                    $params
                );

                $data = $wpdb->get_row($sql, ARRAY_A);

                // 3.4 处理空值
                if ($data) {
                    // 转换特殊字段
                    $data['attributes'] = $data['attributes'] ? json_decode($data['attributes'], true) : [];
                    $data['learning_requirements'] = $data['learning_requirements']
                        ? json_decode($data['learning_requirements'], true)
                        : [];

                    // 保留原始数据用于调试
                    $data['_raw_sql'] = $wpdb->last_query;
                }

                $results[] = $data ?: ['error' => 'Item not found', 'request' => $item];
            }

            // 4. 返回标准化响应
            wp_send_json_success([
                'items' => $results,
                'meta' => [
                    'api_version' => '2.0',
                    'timestamp' => current_time('mysql'),
                    'field_count' => $requested_fields ? count($requested_fields) : 'all'
                ]
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => WP_DEBUG ? $e->getTrace() : null
            ], $e->getCode() ?: 500);
        }
    }

    // 辅助方法：验证枚举值
    private function validate_enum($value, array $allowed)
    {
        return in_array($value, $allowed) ? $value : $allowed[0];
    }


    public function render_shock_box()
    {
        if (!is_user_logged_in()) {
            return '<div class="hap-notice">请登录后访问惊吓盒子</div>';
        }

        ob_start();
?>
        <div class="hap-shock-box-container">

            <div class="hap-item-filters">
                <input type="text" id="hap-item-search" placeholder="搜索道具名称...">
                <select id="hap-item-type">
                    <option value="*">所有类型</option>
                    <option value="consumable">消耗道具</option>
                    <option value="permanent">永久道具</option>
                    <option value="arrow">箭矢</option>
                    <option value="bullet">子弹</option>
                    <option value="equipment">装备</option>
                    <option value="skill">法术</option>
                </select>
                <select id="hap-item-quality">
                    <option value="*">所有品质</option>
                    <option value="common">普通</option>
                    <option value="uncommon">精良</option>
                    <option value="rare">稀有</option>
                    <option value="epic">史诗</option>
                    <option value="legendary">传说</option>
                </select>
                <button id="hap-search-btn" class="hap-button">搜索</button>
            </div>

            <div class="hap-items-grid" id="hap-items-container">
            </div>

            <div class="hap-pagination" id="hap-items-pagination"></div>
        </div>
<?php
        return ob_get_clean();
    }

    function handle_full_item_details()
    {
        check_ajax_referer('hap-nonce', 'nonce');

        $request = json_decode(file_get_contents('php://input'), true);
        $items = $request['items'] ?? [];

        if (empty($items)) {
            wp_send_json_error(['message' => 'Empty items array']);
        }

        global $wpdb;
        $results = [];

        foreach ($items as $item) {
            // 构建动态WHERE条件
            $where = ["name = %s"];
            $params = [$item['name']];

            if (!empty($item['item_type'])) {
                $where[] = "item_type = %s";
                $params[] = $item['item_type'];
            }

            if (!empty($item['quality'])) {
                $where[] = "quality = %s";
                $params[] = $item['quality'];
            }

            $sql = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hap_items 
                 WHERE " . implode(' AND ', $where),
                $params
            );

            $results[] = $wpdb->get_row($sql, ARRAY_A) ?: $item;
        }

        wp_send_json_success(['items' => $results]);
    }

    public function ajax_search_items()
    {
        check_ajax_referer('hap-nonce', 'nonce');
        error_log('[HAP DEBUG] AJAX search initiated - ' . current_time('mysql'));

        $request = array_map('wp_unslash', $_POST);
        $args = [
            'name'        => !empty($request['name']) ? sanitize_text_field($request['name']) : '',
            'item_type'   => ($request['item_type'] ?? '') === '*' ? '' : sanitize_text_field($request['item_type'] ?? ''),
            'quality'   => ($request['quality'] ?? '') === '*' ? '' : sanitize_text_field($request['quality'] ?? ''),
            'page'        => max(1, absint($request['page'] ?? 1)),
            'per_page'    => min(50, absint($request['per_page'] ?? 12)),
            'debug_sql'   => filter_var($request['debug_sql'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'fuzzy_search' => filter_var($request['fuzzy_search'] ?? false, FILTER_VALIDATE_BOOLEAN)
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
    protected function query_items($args)
    {

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
            $where[] = true //$args['fuzzy_search'] 
                ? "name LIKE %s"
                : "name = %s";
            $params[] = true //$args['fuzzy_search']
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




    public function ajax_purchase_item()
    {
        check_ajax_referer('hap-nonce', 'nonce');
        error_log('AJAX purchase item called'); // 调试信息

        $user_id = get_current_user_id();
        $item_id = absint($_POST['item_id']);

        if (!$user_id) {
            wp_send_json_error(['message' => '请先登录']);
        }

        $result = $this->item_manager->purchase_item($user_id, $item_id);
        if (is_wp_error($result)) {
            error_log('购买失败: ' . $result->get_error_message()); // 输出错误信息
        } else {
            error_log('购买成功0！'); // 输出成功信息
        }
        error_log('完成1'); // 调试信息
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        error_log('完成2'); // 调试信息
        // 更新销售次数
        $this->item_manager->update_sales_count($item_id);
        error_log('item_id为：' . $item_id); // 调试信息

        error_log('完成3'); // 调试信息

        wp_send_json_success(['message' => '购买成功']);
    }



    // 辅助方法
    private function get_type_name($type)
    {
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

    private function get_quality_name($quality)
    {
        $qualities = [
            'common' => '普通',
            'uncommon' => '精良',
            'rare' => '稀有',
            'epic' => '史诗',
            'legendary' => '传说'
        ];
        return $qualities[$quality] ?? $quality;
    }

    private function render_pagination($result)
    {
        if ($result['pages'] <= 1) return;

        echo '<div class="hap-pagination">';
        for ($i = 1; $i <= $result['pages']; $i++) {
            $active = $i == $result['page'] ? 'active' : '';
            echo '<button class="hap-page-btn ' . esc_attr($active) . '" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</button>';
        }
        echo '</div>';
    }
}
