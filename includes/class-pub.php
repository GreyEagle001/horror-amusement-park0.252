<?php
if (!defined('ABSPATH')) exit;

class HAP_Pub
{
    private static $instance;

    //实现单例模式
    public static function init()
 
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {

        // 短代码和AJAX钩子注册
    //     add_shortcode('pub', [$this, 'render_pub']);
    //     add_action('wp_ajax_hap_search_items', [$this, 'ajax_search_items']);
    //     add_action('wp_ajax_nopriv_hap_search_items', [$this, 'ajax_search_items']);
    //     add_action('wp_ajax_hap_purchase_item', [$this, 'ajax_purchase_item']);
    //     add_action('wp_ajax_nopriv_hap_purchase_item', [$this, 'ajax_purchase_item']);

    //     // 修正后的详情钩子（移除init检查）
    //     add_action('wp_ajax_hap_get_full_details', [$this, 'handle_full_details']);
    //     add_action('wp_ajax_nopriv_hap_get_full_details', [$this, 'handle_full_details']);
    // }
}
public function render_pub()
    {
        if (!is_user_logged_in()) {
            return '<div class="hap-notice">请登录后访问酒馆</div>';
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

}