<?php
if (!defined('ABSPATH')) exit;

class HAP_Admin {
    private static $instance;
    private $item_types = [
        'consumable' => '消耗道具',
        'permanent' => '永久道具',
        'arrow' => '箭矢',
        'bullet' => '子弹',
        'equipment' => '装备',
        'spell' => '法术'
    ];
    
    private $qualities = [
        'common' => '普通',
        'uncommon' => '精良', 
        'rare' => '稀有',
        'epic' => '史诗',
        'legendary' => '传说'
    ];
    
    private $currencies = [
        'game_coin' => '游戏币',
        'skill_points' => '技巧值'
    ];

    public static function init() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_hap_save_item', [$this, 'save_item']);
    }
    
    public function add_admin_pages() {
        add_menu_page(
            '惊悚乐园商品管理',
            '惊悚乐园',
            'manage_options',
            'hap-item-manager',
            [$this, 'render_item_list'],
            'dashicons-store',
            6
        );
        
        add_submenu_page(
            'hap-item-manager',
            '添加新商品',
            '添加商品',
            'manage_options',
            'hap-add-item',
            [$this, 'render_add_item']
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'hap-item-manager') === false) return;
        
        wp_enqueue_style(
            'hap-admin-css',
            HAP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            filemtime(HAP_PLUGIN_DIR . 'assets/css/admin.css')
        );
        
        wp_enqueue_script(
            'hap-admin-js', 
            HAP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            filemtime(HAP_PLUGIN_DIR . 'assets/js/admin.js'),
            true
        );
        
        wp_localize_script('hap-admin-js', 'hap_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hap-admin-nonce')
        ]);
    }
    
    public function render_item_list() {
        // 商品列表逻辑保持不变
        include HAP_PLUGIN_DIR . 'templates/admin/item-list.php';
    }
    
    public function render_add_item() {
        include HAP_PLUGIN_DIR . 'templates/admin/add-item.php';
    }
    
    public function save_item() {
        check_ajax_referer('hap-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        $item_data = $this->sanitize_item_data($_POST);
        
        $item_manager = HAP_Item_Manager::init();
        $item_id = $item_manager->create_item($item_data);
        
        if ($item_id) {
            wp_send_json_success(['item_id' => $item_id]);
        } else {
            wp_send_json_error('保存商品失败');
        }
    }
    
    private function sanitize_item_data($data) {
        $sanitized = [
            'item_type' => sanitize_text_field($data['item_type']),
            'name' => sanitize_text_field($data['name']),
            'quality' => sanitize_text_field($data['quality']),
            'price' => floatval($data['price']),
            'currency' => sanitize_text_field($data['currency']),
            'duration' => isset($data['duration']) ? absint($data['duration']) : 0,
            'author_id' => get_current_user_id(),
            'status' => 'publish',
            'attributes' => [],
            'specs' => []
        ];
        
        // 处理通用属性
        if (!empty($data['attributes'])) {
            foreach ($data['attributes'] as $key => $value) {
                $sanitized['attributes'][sanitize_text_field($key)] = 
                    is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
            }
        }
        
        // 处理特殊属性
        switch ($sanitized['item_type']) {
            case 'equipment':
                $sanitized['specs'] = [
                    'equip_condition' => sanitize_textarea_field($data['equip_condition']),
                    'physical_defense' => absint($data['physical_defense']),
                    'magic_defense' => absint($data['magic_defense']),
                    'durability' => absint($data['durability'])
                ];
                break;
                
            case 'spell':
                $sanitized['specs'] = [
                    'level_required' => absint($data['level_required']),
                    'mp_cost' => absint($data['mp_cost']),
                    'learn_condition' => sanitize_textarea_field($data['learn_condition']),
                    'cooldown' => absint($data['cooldown']),
                    'aoe_range' => absint($data['aoe_range'])
                ];
                break;
        }
        
        return $sanitized;
    }
}