<?php
if (!defined('ABSPATH')) exit;

class HAP_Item_Manager
{
    private static $instance;
    private $wpdb;
    private $tables;

    public static function init()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = [
            'items' => $wpdb->prefix . 'hap_items',
            'equipment' => $wpdb->prefix . 'hap_equipment_specs',
            'skills' => $wpdb->prefix . 'hap_skill_specs',
            'transactions' => $wpdb->prefix . 'hap_transactions',
            'warehouse' => $wpdb->prefix . 'hap_warehouse'
        ];
    }

    // 创建商品
    public function create_item($data)
    {
        $defaults = [
            'item_type' => 'consumable',
            'name' => '',
            'quality' => 'common',
            'price' => 0.00,
            'currency' => 'game_coin',
            'duration' => null,
            'author_id' => 0,
            'status' => 'publish',
            'attributes' => [],
            'specs' => []
        ];

        $data = wp_parse_args($data, $defaults);

        // 插入主商品数据
        $this->wpdb->insert(
            $this->tables['items'],
            [
                'item_type' => $data['item_type'],
                'name' => $data['name'],
                'quality' => $data['quality'],
                'price' => $data['price'],
                'currency' => $data['currency'],
                'duration' => $data['duration'],
                'author_id' => $data['author_id'],
                'status' => $data['status']
            ],
            ['%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s']
        );

        $item_id = $this->wpdb->insert_id;

        // 插入特殊属性
        switch ($data['item_type']) {
            case 'equipment':
                $this->wpdb->insert(
                    $this->tables['equipment'],
                    [
                        'item_id' => $item_id,
                        'equip_condition' => $data['specs']['equip_condition'],
                        'physical_defense' => $data['specs']['physical_defense'],
                        'magic_defense' => $data['specs']['magic_defense'],
                        'durability' => $data['specs']['durability']
                    ],
                    ['%d', '%s', '%d', '%d', '%d']
                );
                break;

            case 'skill':
                $this->wpdb->insert(
                    $this->tables['skills'],
                    [
                        'item_id' => $item_id,
                        'level_required' => $data['specs']['level_required'],
                        'mp_cost' => $data['specs']['mp_cost'],
                        'learn_condition' => $data['specs']['learn_condition'],
                        'cooldown' => $data['specs']['cooldown'],
                        'aoe_range' => $data['specs']['aoe_range']
                    ],
                    ['%d', '%d', '%d', '%s', '%d', '%d']
                );
                break;
        }

        return $item_id;
    }

    public function get_items($args = [])
    {
        $defaults = [
            'type' => '',
            'status' => 'publish',
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ["status = %s"];
        $params = [$args['status']];

        if ($args['type']) {
            $where[] = "item_type = %s";
            $params[] = $args['type'];
        }

        if ($args['search']) {
            $where[] = "name LIKE %s";
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = $this->wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->tables['items']} 
            WHERE {$where_clause}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d, %d",
            array_merge($params, [$offset, $args['per_page']])
        );

        $items = $this->wpdb->get_results($sql);
        $total = $this->wpdb->get_var("SELECT FOUND_ROWS()");

        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        ];
    }


    // 通过item_id获取商品
    public function get_item($item_id)
    {
        $item = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['items']} WHERE item_id = %d",
                $item_id
            )
        );

        if (!$item) {
            return false;
        } else {
            return $item;
        }
    }

    // 搜索商品
    public function search_items($args = [])
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hap_items';

        $defaults = [
            'search' => '',
            'type' => '',
            'quality' => '',
            'page' => 1,
            'per_page' => 12
        ];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        if ($args['search']) {
            $where .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($args['search']) . '%');
        }
        if ($args['type']) {
            $where .= $wpdb->prepare(" AND item_type = %s", $args['type']);
        }
        if ($args['quality']) {
            $where .= $wpdb->prepare(" AND quality = %s", $args['quality']);
        }

        $offset = ($args['page'] - 1) * $args['per_page'];
        $sql = "SELECT * FROM $table_name WHERE $where LIMIT $offset, {$args['per_page']}";
        $items = $wpdb->get_results($sql, ARRAY_A);

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
        $pages = ceil($total / $args['per_page']);

        return [
            'items' => $items,
            'page' => $args['page'],
            'pages' => $pages
        ];
    }


    public function get_item_by_name($item_name)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hap_items';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE name = %s",
            $item_name
        ));
    }

    // 购买商品
    public function purchase_item($user_id, $item_id)
    {
        error_log('完成1.0'); // 调试信息
        // 验证商品存在
        $item = $this->get_item($item_id);
        if (!$item || $item->status !== 'publish') {
            return new WP_Error('invalid_item', '商品不存在或不可用');
        }
        error_log('完成1.1'); // 调试信息
        // 检查用户货币是否足够 (需要实现get_user_currency)
        $user_currency = $this->get_user_currency($user_id, $item->currency);
        if ($user_currency < $item->price) {
            wp_send_json_error(array(
                'message' => __('货币不足', 'horror-amusement-park')
            ));
            return new WP_Error('insufficient_funds', '货币不足');
        }
        error_log('完成1.2'); // 调试信息
        // 开始事务
        $this->wpdb->query('START TRANSACTION');
        error_log('完成1.3'); // 调试信息
        try {
            // 扣除用户货币 (需要实现update_user_currency)
            $this->update_user_currency(
                $user_id,
                $item->currency,
                $user_currency - $item->price
            );

            // 记录交易
            $this->wpdb->insert(
                $this->tables['transactions'],
                [
                    'user_id' => $user_id,
                    'item_id' => $item_id,
                    'amount' => $item->price,
                    'currency' => $item->currency,
                    'status' => 'completed',
                    'acquired_at' => current_time('mysql')
                ],
                ['%d', '%d', '%f', '%s', '%s', '%s']
            );
            error_log('完成1.4'); // 调试信息

            // 检查道具是否已存在
            $existing_item = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->tables['warehouse']} WHERE user_id = %d AND item_id = %d",
                    $user_id,
                    $item_id
                )
            );

            if ($existing_item) {
                // 如果道具已存在，则更新数量
                $this->wpdb->update(
                    $this->tables['warehouse'],
                    ['quantity' => $existing_item->quantity + 1],
                    ['id' => $existing_item->id],
                    ['%d'],
                    ['%d']
                );
            } else {
                // 如果道具不存在，则插入新记录
                $this->wpdb->insert(
                    $this->tables['warehouse'],
                    [
                        'user_id' => $user_id,
                        'item_id' => $item_id,
                        'quantity' => 1, // 新增数量为1
                        'purchase_price' => $item->price,
                        'currency' => $item->currency
                    ],
                    ['%d', '%d', '%d', '%f', '%s']
                );
            }

            $this->wpdb->query('COMMIT');

            return true;
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('transaction_failed', '交易失败: ' . $e->getMessage());
        }
    }

    public function update_sales_count($item_id)
    {
        global $wpdb;

        // 假设您的商品销售次数存储在一个名为 `wp_items` 的表中
        // 并且有一个名为 `sales_count` 的字段来记录销售次数
        $table_name = $wpdb->prefix . 'hap_items'; // 表名
        $item_id = absint($item_id); // 确保 item_id 是一个正整数

        // 更新销售次数
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET sales_count = sales_count + 1 WHERE item_id = %d",
            $item_id
        ));

        // 检查更新是否成功
        if ($result === false) {
            return new WP_Error('db_update_error', '更新销售次数失败');
        }

        return true; // 更新成功
    }

    // 需要实现以下方法
    private function get_user_currency($user_id, $currency_type)
    {
        // 从用户meta获取货币数量
        $data = get_user_meta($user_id, 'hap_profile_data', true);
        return $data['currency'][$currency_type] ?? 0;
    }

    private function update_user_currency($user_id, $currency_type, $amount)
    {
        // 更新用户meta中的货币数量
        $data = get_user_meta($user_id, 'hap_profile_data', true);
        $data['currency'][$currency_type] = max(0, $amount);
        update_user_meta($user_id, 'hap_profile_data', $data);
    }
}
