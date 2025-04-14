<?php
if (!defined('ABSPATH')) exit;

class HAP_Warehouse {
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
        add_shortcode('warehouse', [$this, 'render_warehouse']);
        add_action('wp_ajax_hap_get_inventory', [$this, 'ajax_get_inventory']);
    }
    
    function handle_buy_item() {
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }
    
        $item_id = intval($_POST['item_id']);
        $user_id = get_current_user_id();
    
        // 获取道具信息
        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hap_items WHERE item_id = %d",
            $item_id
        ));
    
        if (!$item) {
            wp_send_json_error('道具不存在');
        }
    
        // 检查玩家是否有足够的货币
        $user_currency = get_user_meta($user_id, 'game_coin', true);
        if ($user_currency < $item->price) {
            wp_send_json_error('货币不足');
        }
    
        // 扣除货币
        update_user_meta($user_id, 'game_coin', $user_currency - $item->price);
    
        // 将道具添加到仓库
        $warehouse_table = $wpdb->prefix . 'hap_warehouse';
        $wpdb->insert($warehouse_table, [
            'user_id' => $user_id,
            'item_name' => $item->name,
            'quantity' => 1, // 默认数量为 1
        ]);
    
        wp_send_json_success('购买成功');
    }

    public function render_warehouse() {
        if (!is_user_logged_in()) {
            return '<div class="hap-notice">请登录后访问仓库</div>';
        }
        
        ob_start();
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
                        <option value="">所有类型</option>
                        <option value="consumable">消耗道具</option>
                        <option value="permanent">永久道具</option>
                        <option value="arrow">箭矢</option>
                        <option value="bullet">子弹</option>
                        <option value="equipment">装备</option>
                        <option value="skill">法术</option>
                    </select>
                </div>
                <div class="hap-inventory-grid" id="hap-inventory-container">
                    <?php $this->render_inventory(); ?>
                </div>
            </div>
            
            <div class="hap-tab-content" id="hap-custom-tab">
                <h3>自定义道具</h3>
                <?php $this->render_custom_item_form(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_inventory($args = []) {
        $user_id = get_current_user_id();
        $result = $this->item_manager->get_user_inventory($user_id, $args);

        if (empty($result['items'])) {
            echo '<div class="hap-no-items">仓库空空如也</div>';
            return;
        }

        foreach ($result['items'] as $item) {
            $this->render_inventory_item($item);
        }
    }
    
    private function render_inventory_item($item) {
        // 从 wp_hap_items 中获取道具详细信息
        $item_details = $this->item_manager->get_item_by_name($item->item_name);

        if (!$item_details) {
            return; // 如果道具不存在，跳过渲染
        }

        $quality_class = 'hap-quality-' . $item_details->quality;
        ?>
        <div class="hap-inventory-item <?php echo esc_attr($quality_class); ?>">
            <div class="hap-item-image">
                <div class="hap-item-image-placeholder"></div>
            </div>
            <div class="hap-item-info">
                <h4><?php echo esc_html($item_details->name); ?></h4>
                <div class="hap-item-meta">
                    <span class="hap-item-type"><?php echo esc_html($this->get_type_name($item_details->type)); ?></span>
                    <span class="hap-item-quality"><?php echo esc_html($this->get_quality_name($item_details->quality)); ?></span>
                    <span class="hap-item-quantity">数量: <?php echo esc_html($item->quantity); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_get_inventory() {
        check_ajax_referer('hap-nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $args = [
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'page' => absint($_POST['page'] ?? 1),
            'per_page' => 12
        ];
        
        ob_start();
        $this->render_inventory($args);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    private function render_custom_item_form() {
        ?>
        <form id="hap-custom-item-form" class="hap-form">
            <?php wp_nonce_field('hap_create_custom_item', 'hap_nonce'); ?>
            
            <div class="hap-form-group">
                <label for="hap-custom-item-name">道具名称</label>
                <input type="text" id="hap-custom-item-name" name="name" required>
            </div>
            
            <div class="hap-form-group">
                <label for="hap-custom-item-type">道具类型</label>
                <select id="hap-custom-item-type" name="item_type" required>
                    <option value="">选择类型</option>
                    <option value="consumable">消耗道具</option>
                    <option value="permanent">永久道具</option>
                    <option value="arrow">箭矢</option>
                    <option value="bullet">子弹</option>
                    <option value="equipment">装备</option>
                    <option value="skill">法术</option>
                </select>
            </div>
            
            <div id="hap-custom-item-fields">
                <!-- 动态字段将通过JavaScript加载 -->
            </div>
            
            <button type="submit" class="hap-button">创建道具</button>
        </form>
        <?php
    }
    
    // 辅助方法（与Shock_Box类中的相同）
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