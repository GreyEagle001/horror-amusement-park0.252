<?php
if (!defined('ABSPATH')) exit;

class HAP_Admin_Center
{
    private static $instance;
    private $edit_request_meta_key = 'hap_edit_request';

    public static function init()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 只对贡献者角色显示管理员页面
        add_shortcode('admin_center', [$this, 'render_admin_center']);
        add_action('wp_ajax_hap_approve_edit_request', [$this, 'approve_edit_request']);
        add_action('wp_ajax_hap_reject_edit_request', [$this, 'reject_edit_request']);

        // 添加管理员菜单项
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            '惊悚乐园管理',
            '惊悚乐园',
            'edit_posts', // 贡献者权限
            'horror-amusement-park',
            [$this, 'render_admin_page'],
            'dashicons-admin-generic',
            6
        );
    }

    public function render_admin_page()
    {
        if (!current_user_can('edit_posts')) {
            wp_die('您没有权限访问此页面');
        }

        echo '<div class="wrap">';
        echo '<h1>惊悚乐园管理面板</h1>';
        echo do_shortcode('[admin_center]');
        echo '</div>';
    }

    public function render_admin_center()
    {
        if (!current_user_can('edit_posts')) {
            return '<p>只有贡献者可以访问此页面。</p>';
        }

        $pending_requests = $this->get_pending_edit_requests();

        ob_start();
?>
        <div class="hap-admin-center">
            <h2>编辑申请管理</h2>

            <?php if (empty($pending_requests)): ?>
                <p>当前没有待处理的编辑申请。</p>
            <?php else: ?>
                <table class="hap-requests-table">
                    <thead>
                        <tr>
                            <th>用户ID</th>
                            <th>用户名</th>
                            <th>昵称</th>
                            <th>申请时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $request):
                            $profile_data = get_user_meta($request->ID, 'hap_profile_data', true);
                            $nickname = $profile_data['basic_info']['nickname'] ?? '';
                        ?>
                            <tr data-user-id="<?php echo esc_attr($request->ID); ?>">
                                <td><?php echo esc_html($request->ID); ?></td>
                                <td><?php echo esc_html($request->user_login); ?></td>
                                <td><?php echo esc_html($nickname); ?></td>
                                <td><?php echo esc_html(get_date_from_gmt($request->request_time)); ?></td>
                                <td>
                                    <button class="hap-approve-btn" data-user-id="<?php echo esc_attr($request->ID); ?>">批准</button>
                                    <button class="hap-reject-btn" data-user-id="<?php echo esc_attr($request->ID); ?>">拒绝</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    private function get_pending_edit_requests()
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT u.ID, u.user_login, um.meta_value as request_time 
             FROM {$wpdb->users} u 
             INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
             WHERE um.meta_key = %s AND um.meta_value = %s",
            $this->edit_request_meta_key,
            'pending'
        );

        $results = $wpdb->get_results($query);

        // 添加请求时间到结果中
        foreach ($results as $result) {
            $result->request_time = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta} 
                     WHERE user_id = %d AND meta_key = %s",
                    $result->ID,
                    $this->edit_request_meta_key . '_time'
                )
            );
        }

        return $results;
    }

    public function approve_edit_request()
    {
        $this->verify_admin_request();

        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('无效的用户ID');
        }

        // 重置编辑次数
        delete_user_meta($user_id, 'hap_edit_count');
        delete_user_meta($user_id, $this->edit_request_meta_key);
        delete_user_meta($user_id, $this->edit_request_meta_key . '_time');

        // 发送通知
        $this->send_notification($user_id, '您的编辑申请已获批准，现在可以修改您的个人信息了。');

        wp_send_json_success('申请已批准');
    }

    public function reject_edit_request()
    {
        $this->verify_admin_request();

        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('无效的用户ID');
        }

        delete_user_meta($user_id, $this->edit_request_meta_key);
        delete_user_meta($user_id, $this->edit_request_meta_key . '_time');

        $this->send_notification($user_id, '您的编辑申请已被拒绝。');

        wp_send_json_success('申请已拒绝');
    }

    private function verify_admin_request()
    {
        check_ajax_referer('hap-nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('您没有权限执行此操作');
        }
    }

    private function send_notification($user_id, $message)
    {
        // 在实际应用中，您可能想使用更正式的通知系统
        // 这里只是一个简单的实现
        $user = get_user_by('id', $user_id);
        if ($user) {
            wp_mail(
                $user->user_email,
                '惊悚乐园 - 编辑申请状态更新',
                $message
            );
        }
    }
}
