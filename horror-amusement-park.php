<?php

/**
 * Plugin Name: 惊悚乐园 - 非盈利二创跑团平台
 * Plugin URI: 
 * Description: 为惊悚乐园网站提供完整的跑团平台功能，包含个人中心、惊吓盒子、仓库等模块
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: 
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: horror-amusement-park
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// 定义插件常量
define('HAP_VERSION', '2.0.1');
define('HAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HAP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('HAP_CACHE_EXPIRE', 6 * HOUR_IN_SECONDS); // 缓存6小时

require_once HAP_PLUGIN_DIR . 'includes/class-hap-db.php';
require_once HAP_PLUGIN_DIR . 'includes/class-hap-item-manager.php';

// 自动加载类文件
spl_autoload_register(function ($class) {
    $prefix = 'HAP_';
    $base_dir = HAP_PLUGIN_DIR . 'includes/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});



// 主插件类
class Horror_Amusement_Park
{
    private static $instance;
    private $modules = [];

    public static function init()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 注册激活/停用钩子
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // 初始化插件
        add_action('plugins_loaded', [$this, 'setup'], 5);
        add_action('init', [$this, 'register_post_types'], 0);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'check_requirements']);

        // 添加性能监控
        add_action('shutdown', [$this, 'log_performance']);

        // 添加商品注册页面
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_item_registration']);
    }

    private function upgrade_database()
    {
        $current_version = get_option('hap_db_version', '1.121');

        if (version_compare($current_version, HAP_VERSION, '<')) {
            $this->create_tables();
            update_option('hap_db_version', HAP_VERSION);
        }
    }

    public function setup()
    {
        // 初始化各功能模块
        $this->modules = [
            'personal_center' => HAP_Personal_Center::init(),
            'admin_center'    => HAP_Admin_Center::init(),
            'shock_box'       => HAP_Shock_Box::init(),
            'warehouse'       => HAP_Warehouse::init()
        ];

        // 注册自定义用户角色
        $this->register_roles();

        // 设置默认选项
        $this->set_default_options();
    }

    public function activate()
    {
        $this->upgrade_database();
        // 创建必要的数据库表
        $this->create_tables();

        // 添加自定义用户角色
        $this->register_roles();

        // 注册自定义文章类型
        $this->register_post_types();

        // 刷新重写规则
        flush_rewrite_rules();

        // 设置定时任务
        if (!wp_next_scheduled('hap_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'hap_daily_maintenance');
        }
    }

    public function deactivate()
    {
        // 清理定时任务
        wp_clear_scheduled_hook('hap_daily_maintenance');

        // 刷新重写规则
        flush_rewrite_rules();
    }

    public function register_post_types()
    {
        // 注册商品自定义文章类型
        register_post_type('hap_item', [
            'labels' => [
                'name'          => __('惊悚乐园商品', 'horror-amusement-park'),
                'singular_name' => __('商品', 'horror-amusement-park'),
                'menu_name'     => __('惊悚乐园', 'horror-amusement-park')
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'item'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'show_in_rest'       => true
        ]);
    }

    private function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 创建 hap_items 表
        $table_name_items = "{$wpdb->prefix}hap_items";
        $sql_items = "CREATE TABLE IF NOT EXISTS $table_name_items (
            item_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            item_type enum('consumable','permanent','arrow','bullet','equipment','skill') NOT NULL,
            name varchar(255) NOT NULL,
            attributes text DEFAULT NULL,
            quality enum('common','uncommon','rare','epic','legendary') DEFAULT 'common',
            restrictions int(11) DEFAULT NULL,
            effects text DEFAULT NULL,
            duration int(11) DEFAULT NULL,
            price decimal(10,2) NOT NULL DEFAULT '0.00',
            currency enum('game_coin','skill_points') NOT NULL DEFAULT 'game_coin',
            author varchar(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sales_count int(11) NOT NULL DEFAULT '0',
            level int(11) DEFAULT NULL,
            consumption decimal(10,2) DEFAULT NULL,
            learning_requirements text DEFAULT NULL,
            status enum('publish', 'unpublish') NOT NULL DEFAULT 'publish',  -- 新增的状态属性
            adjust_type enum('buff', 'debuff') DEFAULT NULL,  -- 新增的效果类型属性
            adjust_date datetime DEFAULT NULL,  -- 新增的效果日期属性
            PRIMARY KEY (item_id)
        ) $charset_collate ENGINE=InnoDB AUTO_INCREMENT=2;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_items);

        // 添加 hap_items 表字段注释
        $comments = [
            'item_id' => '商品序号，主键',
            'item_type' => '类型',
            'name' => '名称',
            'attributes' => '属性',
            'quality' => '品质',
            'restrictions' => '单格携带数量',
            'effects' => '特效',
            'duration' => '持续时间',
            'price' => '价格',
            'currency' => '货币',
            'author' => '作者',
            'created_at' => '上架时间',
            'sales_count' => '售出数量',
            'level' => '可使用等级',
            'consumption' => '单次使用消耗',
            'learning_requirements' => '学习条件',
            'status' => '商品状态（上架/下架）',  // 新增的状态字段注释
            'adjust_type' => '效果类型（削弱/增强）',  // 新增的效果类型字段注释
            'adjust_date' => '效果日期',  // 新增的效果日期字段注释
        ];

        // 添加字段注释
        foreach ($comments as $column => $comment) {
            // 获取列的数据类型
            $column_info = $wpdb->get_row("SHOW FULL COLUMNS FROM $table_name_items LIKE '$column'", ARRAY_A);
            if ($column_info) {
                $column_type = $column_info['Type']; // 获取列的数据类型

                // 使用 CHANGE 语句来修改列的注释
                $wpdb->query(
                    $wpdb->prepare(
                        "ALTER TABLE $table_name_items CHANGE $column $column $column_type COMMENT %s",
                        $comment
                    )
                );
            }
            if ($column_info === false) {
                error_log("SQL错误: " . $wpdb->last_error);
            }
        }
        error_log('购买更新数据库！'); // 输出成功信息
        error_log("[SQL调试] 实际执行SQL: " . $wpdb->last_query);

        // 添加表注释
        $wpdb->query("ALTER TABLE $table_name_items COMMENT '商品表，用于存储游戏内商品信息'");

        // 创建 hap_warehouse 表
        $table_name_warehouse = "{$wpdb->prefix}hap_warehouse";
        $sql_warehouse = "CREATE TABLE IF NOT EXISTS $table_name_warehouse (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            item_id int(11) NOT NULL,
            quantity int(11) NOT NULL,
            purchase_price decimal(10,2) NOT NULL DEFAULT '0.00',
            currency enum('game_coin','skill_points') NOT NULL DEFAULT 'game_coin',
            PRIMARY KEY (id)
        ) $charset_collate ENGINE=InnoDB AUTO_INCREMENT=1;";

        dbDelta($sql_warehouse);

        // 添加 hap_warehouse 表字段注释
        $comments_warehouse = [
            'id' => '主键，自增长',
            'user_id' => '用户序号',
            'item_id' => '商品序号',
            'quantity' => '数量',
            'purchase_price' => '购买时价格',
            'currency' => '货币类型'
        ];

        foreach ($comments_warehouse as $column => $comment) {
            // 获取列的数据类型
            $column_info = $wpdb->get_row("SHOW FULL COLUMNS FROM $table_name_warehouse LIKE '$column'", ARRAY_A);
            if ($column_info) {
                $column_type = $column_info['Type']; // 获取列的数据类型

                // 使用 CHANGE 语句来修改列的注释
                $wpdb->query(
                    $wpdb->prepare(
                        "ALTER TABLE $table_name_warehouse CHANGE $column $column $column_type COMMENT %s",
                        $comment
                    )
                );
            }
        }


        // 添加表注释
        $wpdb->query("ALTER TABLE $table_name_warehouse COMMENT '仓库表，用于存储物品库存信息'");

        // 创建 hap_transactions 表
        $table_name_transactions = "{$wpdb->prefix}hap_transactions";
        $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_name_transactions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            item_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT '0.00',
            currency enum('game_coin','skill_points') NOT NULL DEFAULT 'game_coin',
            status enum('completed', 'pending', 'failed') NOT NULL DEFAULT 'completed',
            acquired_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate ENGINE=InnoDB AUTO_INCREMENT=1;";

        dbDelta($sql_transactions);

        // 添加 hap_transactions 表字段注释
        $comments_transactions = [
            'id' => '主键，自增长',
            'user_id' => '用户序号',
            'item_id' => '商品序号',
            'amount' => '交易金额',
            'currency' => '货币类型',
            'status' => '交易状态',
            'acquired_at' => '获取时间'
        ];

        foreach ($comments_transactions as $column => $comment) {
            // 获取列的数据类型
            $column_info = $wpdb->get_row("SHOW FULL COLUMNS FROM $table_name_transactions LIKE '$column'", ARRAY_A);
            if ($column_info) {
                $column_type = $column_info['Type']; // 获取列的数据类型

                // 使用 CHANGE 语句来修改列的注释
                $wpdb->query(
                    $wpdb->prepare(
                        "ALTER TABLE $table_name_transactions CHANGE $column $column $column_type COMMENT %s",
                        $comment
                    )
                );
            }
        }

        // 添加表注释
        $wpdb->query("ALTER TABLE $table_name_transactions COMMENT '交易表，用于存储用户交易信息'");

        return ($wpdb->last_error === '');
    }


    public function add_admin_menu()
    {
        add_menu_page(
            __('商品注册', 'horror-amusement-park'),
            __('商品注册', 'horror-amusement-park'),
            'manage_options',
            'hap_item_registration',
            [$this, 'render_item_registration_page'],
            'dashicons-cart'
        );
    }

    public function render_item_registration_page()
    {
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
                                <option value="consumable"><?php _e('消耗道具', 'horror-amusement-park'); ?></option>
                                <option value="permanent"><?php _e('永久道具', 'horror-amusement-park'); ?></option>
                                <option value="arrow"><?php _e('箭矢', 'horror-amusement-park'); ?></option>
                                <option value="bullet"><?php _e('子弹', 'horror-amusement-park'); ?></option>
                                <option value="equipment"><?php _e('装备', 'horror-amusement-park'); ?></option>
                                <option value="skill"><?php _e('技能', 'horror-amusement-park'); ?></option>
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
                                <option value="common"><?php _e('普通', 'horror-amusement-park'); ?></option>
                                <option value="uncommon"><?php _e('不寻常', 'horror-amusement-park'); ?></option>
                                <option value="rare"><?php _e('稀有', 'horror-amusement-park'); ?></option>
                                <option value="epic"><?php _e('史诗', 'horror-amusement-park'); ?></option>
                                <option value="legendary"><?php _e('传奇', 'horror-amusement-park'); ?></option>
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
                        <td><input type="number" name="price" id="price" step="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="currency"><?php _e('货币', 'horror-amusement-park'); ?></label></th>
                        <td>
                            <select name="currency" id="currency" required>
                                <option value="game_coin"><?php _e('游戏币', 'horror-amusement-park'); ?></option>
                                <option value="skill_points"><?php _e('技巧值', 'horror-amusement-park'); ?></option>
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





    public function handle_item_registration()
    {
        if (
            isset($_POST['hap_item_registration_nonce']) &&
            wp_verify_nonce($_POST['hap_item_registration_nonce'], 'hap_item_registration')
        ) {

            if (!current_user_can('manage_options')) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('您没有权限注册商品！', 'horror-amusement-park') . '</p></div>';
                });
                return;
            }

            global $wpdb;

            // 收集并清理数据
            $item_type = sanitize_text_field($_POST['item_type']);
            $name = sanitize_text_field($_POST['name']);
            $attributes = sanitize_text_field($_POST['attributes']);
            $quality = sanitize_text_field($_POST['quality']);
            $restrictions = intval($_POST['restrictions']);
            $effects = sanitize_text_field($_POST['effects']);
            $value = floatval($_POST['value']);
            $duration = intval($_POST['duration']);
            $price = floatval($_POST['price']);
            $currency = sanitize_text_field($_POST['currency']);
            $author = sanitize_text_field($_POST['author']); // 获取自定义作者字符串

            // 插入数据
            $result = $wpdb->insert("{$wpdb->prefix}hap_items", [
                'item_type' => $item_type,
                'name' => $name,
                'attributes' => $attributes,
                'quality' => $quality,
                'restrictions' => $restrictions,
                'effects' => $effects,
                'value' => $value,
                'duration' => $duration,
                'price' => $price,
                'currency' => $currency,
                'author' => $author, // 插入自定义作者字符串
                'created_at' => current_time('mysql')
            ]);

            // 检查插入结果
            if ($result === false) {
                error_log('插入商品失败: ' . $wpdb->last_error);
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('商品注册失败！', 'horror-amusement-park') . '</p></div>';
                });
            } else {
                // 成功消息
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('商品注册成功！', 'horror-amusement-park') . '</p></div>';
                });
            }
        }
    }





    private function register_roles()
    {
        // 贡献者角色（比默认贡献者更多权限）
        if (!get_role('contributor')) {
            add_role('contributor', __('贡献者', 'horror-amusement-park'), [
                'read'                   => true,
                'edit_posts'             => true,
                'upload_files'           => true,
                'edit_hap_items'        => true,
                'edit_published_hap_items' => true,
                'delete_hap_items'       => true
            ]);
        }

        // 为管理员添加商品管理权限
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('edit_hap_items');
            $admin->add_cap('edit_others_hap_items');
            $admin->add_cap('publish_hap_items');
            $admin->add_cap('read_private_hap_items');
            $admin->add_cap('delete_hap_items');
        }
    }

    private function set_default_options()
    {
        $defaults = [
            'hap_currency_name'       => __('游戏币', 'horror-amusement-park'),
            'hap_skill_points_name'   => __('技巧值', 'horror-amusement-park'),
            'hap_max_inventory_items' => 500,
            'hap_item_price_min'      => 10,
            'hap_item_price_max'      => 10000
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    public function enqueue_assets()
    {
        // 主样式文件
        wp_enqueue_style(
            'hap-style',
            HAP_PLUGIN_URL . 'assets/css/style.css',
            [],
            filemtime(HAP_PLUGIN_DIR . 'assets/css/style.css')
        );

        // 主脚本文件
        wp_enqueue_script(
            'hap-script',
            HAP_PLUGIN_URL . 'assets/js/script.js',
            ['jquery', 'wp-util'],
            filemtime(HAP_PLUGIN_DIR . 'assets/js/script.js'),
            true
        );

        // 本地化脚本
        wp_localize_script('hap-script', 'hap_ajax', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('hap-nonce'),
            'rest_url'   => rest_url('hap/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'i18n'       => [
                'loading'      => __('加载中...', 'horror-amusement-park'),
                'error'        => __('发生错误', 'horror-amusement-park'),
                'confirm_buy'  => __('确定要购买此商品吗？', 'horror-amusement-park')
            ]
        ]);

        // 条件加载
        if (is_page('shock-box')) {
            wp_enqueue_script(
                'hap-shock-box',
                HAP_PLUGIN_URL . 'assets/js/scripts.js',
                ['hap-script'],
                filemtime(HAP_PLUGIN_DIR . 'assets/js/scripts.js'),
                true
            );
        }

        if (is_page('warehouse')) {
            wp_enqueue_script(
                'hap-warehouse',
                HAP_PLUGIN_URL . 'assets/js/scripts.js',
                ['hap-script'],
                filemtime(HAP_PLUGIN_DIR . 'assets/js/scripts.js'),
                true
            );
        }
    }

    public function check_requirements()
    {
        $errors = [];

        // 检查PHP版本
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = __('惊悚乐园插件需要PHP 7.4或更高版本', 'horror-amusement-park');
        }

        // 检查WordPress版本
        if (version_compare(get_bloginfo('version'), '5.6', '<')) {
            $errors[] = __('惊悚乐园插件需要WordPress 5.6或更高版本', 'horror-amusement-park');
        }

        // 如果有错误，显示通知并停用插件
        if (!empty($errors)) {
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }

            add_action('admin_notices', function () use ($errors) {
                foreach ($errors as $error) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
                }
            });
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }

    public function log_performance()
    {
        // 记录性能数据的逻辑
        // 这里可以添加代码来记录执行时间、内存使用等
    }
}

// 启动插件
Horror_Amusement_Park::init();
