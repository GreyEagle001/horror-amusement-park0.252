<?php
if (!defined('ABSPATH')) exit;

class HAP_DB {
    private static $instance;
    private $wpdb;
    private $tables = [];

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tables = [
            'items'       => $wpdb->prefix . 'hap_items',
            'itemmeta'    => $wpdb->prefix . 'hap_itemmeta',
            'inventory'   => $wpdb->prefix . 'hap_inventory',
            'custom_items'=> $wpdb->prefix . 'hap_custom_items'
        ];
    }

    public static function init() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ================ 商品操作方法 ================
    public function get_item($item_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['items']} WHERE item_id = %d AND status = 'publish'",
            $item_id
        );
        return $this->wpdb->get_row($sql);
    }

    public function get_items($args = []) {
        $defaults = [
            'type'       => '',
            'subtype'    => '',
            'status'     => 'publish',
            'page'       => 1,
            'per_page'   => 20,
            'search'     => '',
            'orderby'    => 'created_at',
            'order'      => 'DESC'
        ];
        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if ($args['type']) {
            $where[] = 'item_type = %s';
            $params[] = $args['type'];
        }

        if ($args['subtype']) {
            $where[] = 'item_subtype = %s';
            $params[] = $args['subtype'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ($args['search']) {
            $where[] = 'item_name LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }

        $where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $order = "ORDER BY {$args['orderby']} {$args['order']}";
        $limit = $this->wpdb->prepare(
            "LIMIT %d, %d",
            ($args['page'] - 1) * $args['per_page'],
            $args['per_page']
        );

        $sql = "SELECT * FROM {$this->tables['items']} {$where} {$order} {$limit}";
        
        if ($params) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql);
    }

    public function get_item_meta($item_id, $key = '', $single = true) {
        if (!$key) {
            $sql = $this->wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$this->tables['itemmeta']} WHERE item_id = %d",
                $item_id
            );
            $results = $this->wpdb->get_results($sql);
            $meta = [];
            foreach ($results as $result) {
                $meta[$result->meta_key] = maybe_unserialize($result->meta_value);
            }
            return $meta;
        }

        $sql = $this->wpdb->prepare(
            "SELECT meta_value FROM {$this->tables['itemmeta']} 
            WHERE item_id = %d AND meta_key = %s",
            $item_id, $key
        );

        if ($single) {
            return maybe_unserialize($this->wpdb->get_var($sql));
        }

        return array_map('maybe_unserialize', $this->wpdb->get_col($sql));
    }

    // ================ 库存操作方法 ================
    public function get_user_inventory($user_id, $args = []) {
        $defaults = [
            'type'     => '',
            'page'     => 1,
            'per_page' => 20
        ];
        $args = wp_parse_args($args, $defaults);

        $join = '';
        $where = [
            $this->wpdb->prepare("i.user_id = %d", $user_id)
        ];
        $params = [];

        if ($args['type']) {
            $join = "INNER JOIN {$this->tables['items']} it ON i.item_id = it.item_id";
            $where[] = "it.item_type = %s";
            $params[] = $args['type'];
        }

        $where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = $this->wpdb->prepare(
            "LIMIT %d, %d",
            ($args['page'] - 1) * $args['per_page'],
            $args['per_page']
        );

        $sql = "SELECT i.*, it.item_name, it.item_type 
                FROM {$this->tables['inventory']} i
                {$join}
                {$where}
                ORDER BY acquired_at DESC
                {$limit}";

        if ($params) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql);
    }

    // ================ 自定义道具方法 ================
    public function create_custom_item($user_id, $data) {
        $defaults = [
            'item_name' => '',
            'item_type' => '',
            'item_data' => [],
            'status'    => 'draft'
        ];
        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->tables['custom_items'],
            [
                'user_id'   => $user_id,
                'item_name' => $data['item_name'],
                'item_type' => $data['item_type'],
                'item_data' => json_encode($data['item_data']),
                'status'    => $data['status'],
                'created_at'=> current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }
}