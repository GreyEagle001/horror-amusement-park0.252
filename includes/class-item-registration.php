<?php
if (!defined('ABSPATH')) exit;

class HAP_Item_Registration {
    private static $instance;
    
    private $item_types = [
        'consumable' => '消耗道具',
        'permanent' => '永久道具',
        'arrow' => '箭矢',
        'bullet' => '子弹',
        'equipment' => '装备',
        'skill' => '技能'
    ];

    private $qualities = [
        'common' => '普通',
        'uncommon' => '不寻常',
        'rare' => '稀有',
        'epic' => '史诗',
        'legendary' => '传奇'
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_item_registration']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('商品注册', 'horror-amusement-park'),
            __('商品注册', 'horror-amusement-park'),
            'manage_options',
            'hap_item_registration',
            [$this, 'render_item_registration_page'],
            'dashicons-cart'
        );
    }

    public function render_item_registration_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('注册商品', 'horror-amusement-park'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('hap_item_registration', 'hap_item_registration_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="item_type"><?php _e('商品类型', 'horror-amusement-park'); ?></label></th>
                        <td>
                            <select name="item_type" id="item_type" required>
                                <?php foreach ($this->item_types as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="name"><?php _e('名称', 'horror-amusement-park'); ?></label></th>
                        <td><input type="text" name="name" id="name" required></td>
                    </tr>
                    <tr>
                        <th><label for="attributes"><?php _e('属性', 'horror-amusement-park'); ?></label></th>
                        <td><input type="text" name="attributes" id="attributes" required></td>
                    </tr>
                    <tr>
                        <th><label for="quality"><?php _e('品质', 'horror-amusement-park'); ?></label></th>
                        <td>
                            <select name="quality" id="quality" required>
                                <?php foreach ($this->qualities as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="restrictions"><?php _e('限制', 'horror-amusement-park'); ?></label></th>
                        <td><input type="number" name="restrictions" id="restrictions" required></td>
                    </tr>
                    <tr>
                        <th><label for="effects"><?php _e('特效', 'horror-amusement-park'); ?></label></th>
                        <td><input type="text" name="effects" id="effects" required></td>
                    </tr>
                    <tr>
                        <th><label for="value"><?php _e('数值', 'horror-amusement-park'); ?></label></th>
                        <td><input type="number" name="value" id="value" step="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="duration"><?php _e('持续时间', 'horror-amusement-park'); ?></label></th>
                        <td><input type="number" name="duration" id="duration" required></td>
                    </tr>
                    <tr>
                        <th><label for="price"><?php _e('价格', 'horror-amusement-park'); ?></label></th>
                        <td><input type="number" name="price" id="price" required></td>
                    </tr>
                    <tr>
                        <th><label for="currency"><?php _e('货币', 'horror-amusement-park'); ?></label></th>
                        <td>
                            <select name="currency" id="currency" required>
                                <?php foreach ($this->currencies as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="author"><?php _e('作者', 'horror-amusement-park'); ?></label></th>
                        <td><input type="text" name="author" id="author" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('注册商品', 'horror-amusement-park'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_item_registration() {
        if (!isset($_POST['hap_item_registration_nonce']) || 
            !wp_verify_nonce($_POST['hap_item_registration_nonce'], 'hap_item_registration')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                    __('您没有权限注册商品！', 'horror-amusement-park') . '</p></div>';
            });
            return;
        }

        global $wpdb;

        $item_data = [
            'item_type' => sanitize_text_field($_POST['item_type']),
            'name' => sanitize_text_field($_POST['name']),
            'attributes' => sanitize_text_field($_POST['attributes']),
            'quality' => sanitize_text_field($_POST['quality']),
            'restrictions' => intval($_POST['restrictions']),
            'effects' => sanitize_text_field($_POST['effects']),
            'value' => floatval($_POST['value']),
            'duration' => intval($_POST['duration']),
            'price' => floatval($_POST['price']),
            'currency' => sanitize_text_field($_POST['currency']),
            'author' => sanitize_text_field($_POST['author']),
            'created_at' => current_time('mysql')
        ];

        $result = $wpdb->insert("{$wpdb->prefix}hap_items", $item_data);

        if ($result === false) {
            error_log('插入商品失败: ' . $wpdb->last_error);
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                    __('商品注册失败！', 'horror-amusement-park') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                    __('商品注册成功！', 'horror-amusement-park') . '</p></div>';
            });
        }
    }
}

// 初始化插件
HAP_Item_Registration::init();
