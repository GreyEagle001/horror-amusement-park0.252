<?php
if (!defined('ABSPATH')) exit;

class HAP_Personal_Center
{
    private static $instance;
    private $edit_count_meta_key = 'hap_edit_count';
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
        add_shortcode('personal_center', [$this, 'render_personal_center']);
        add_action('wp_ajax_hap_save_profile', [$this, 'save_profile']);
        add_action('wp_ajax_hap_request_edit', [$this, 'request_edit']);
        add_action('wp_ajax_hap_upload_avatar', [$this, 'handle_avatar_upload']);
    }

    public function render_personal_center()
    {
        if (!is_user_logged_in()) {
            return '<p>请先登录以访问个人中心。</p>';
        }

        $user_id = get_current_user_id();
        $user_data = $this->get_user_data($user_id);
        $can_edit = $this->can_user_edit($user_id);
        $has_requested = get_user_meta($user_id, $this->edit_request_meta_key, true);

        ob_start();
?>
        <div class="hap-personal-center">
            <h2>个人中心</h2>

            <?php if ($can_edit): ?>
                <form id="hap-profile-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('hap_save_profile', 'hap_nonce'); ?>

                    <!-- 基础信息部分 -->
                    <div class="hap-section">
                        <h3>基础信息</h3>
                        <div class="hap-field">
                            <label>昵称</label>
                            <input type="text" name="nickname" value="<?php echo esc_attr($user_data['basic_info']['nickname']); ?>">
                        </div>
                        <div class="hap-field">
                            <label>头像</label>
                            <div class="hap-avatar-preview">
                                <?php
                                $avatar_id = $user_data['basic_info']['avatar'] ?? 0;
                                if ($avatar_id && wp_get_attachment_url($avatar_id)):
                                    $avatar_url = wp_get_attachment_url($avatar_id);
                                    $avatar_alt = esc_attr($user_data['basic_info']['nickname']) . '的头像';
                                ?>
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo $avatar_alt; ?>" style="max-width: 100px;">
                                <?php else: ?>
                                    <div class="hap-avatar-placeholder">暂无头像</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="avatar" id="hap-avatar-upload" accept="image/*">
                            <input type="hidden" name="avatar_id" id="hap-avatar-id" value="<?php echo esc_attr($avatar_id); ?>">
                        </div>
                        <div class="hap-field">
                            <label>个性签名</label>
                            <textarea name="bio"><?php echo esc_textarea($user_data['basic_info']['bio']); ?></textarea>
                        </div>
                        <div class="hap-field">
                            <label>通关副本 (每行一个)</label>
                            <textarea name="completed_scenarios"><?php
                                                                    if (!empty($user_data['basic_info']['completed_scenarios'])) {
                                                                        echo esc_textarea(implode("\n", $user_data['basic_info']['completed_scenarios']));
                                                                    }
                                                                    ?></textarea>
                        </div>
                    </div>

                    <!-- 角色属性部分 -->
                    <div class="hap-section">
                        <h3>角色属性</h3>
                        <div class="hap-attributes-grid">
                            <?php foreach ($user_data['attributes'] as $key => $value): ?>
                                <div class="hap-attribute">
                                    <label><?php echo esc_html($this->get_attribute_label($key)); ?></label>
                                    <input type="number" name="attributes[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 衍生属性部分 -->
                    <div class="hap-section">
                        <h3>衍生属性</h3>
                        <div class="hap-attributes-grid">
                            <?php foreach ($user_data['derived_attributes'] as $key => $value): ?>
                                <div class="hap-attribute">
                                    <label><?php echo esc_html($this->get_derived_attribute_label($key)); ?></label>
                                    <input type="number" name="derived_attributes[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 专精部分 -->
                    <div class="hap-section">
                        <h3>专精</h3>
                        <div class="hap-specializations-grid">
                            <?php foreach ($user_data['specializations'] as $key => $value): ?>
                                <div class="hap-specialization">
                                    <label><?php echo esc_html($this->get_specialization_label($key)); ?></label>
                                    <input type="text" name="specializations[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" class="hap-text-input">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 货币部分 -->
                    <div class="hap-section">
                        <h3>货币</h3>
                        <div class="hap-currency-grid">
                            <div class="hap-currency">
                                <label>游戏币</label>
                                <input type="number" name="currency[game_coin]" value="<?php echo esc_attr($user_data['currency']['game_coin']); ?>">
                            </div>
                            <div class="hap-currency">
                                <label>技巧值</label>
                                <input type="number" name="currency[skill_points]" value="<?php echo esc_attr($user_data['currency']['skill_points']); ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="hap-submit">保存信息</button>
                </form>
            <?php else: ?>
                <!-- 查看模式 -->
                <div class="hap-view-mode">
                    <div class="hap-section">
                        <h3>基础信息</h3>
                        <div class="hap-info-display">
                            <div class="hap-info-row">
                                <span class="hap-info-label">昵称:</span>
                                <span class="hap-info-value"><?php echo esc_html($user_data['basic_info']['nickname']); ?></span>
                            </div>
                            <div class="hap-info-row">
                                <span class="hap-info-label">头像:</span>
                                <span class="hap-info-value">
                                    <?php if (!empty($user_data['basic_info']['avatar'])): ?>
                                        <img src="<?php echo esc_url($user_data['basic_info']['avatar']); ?>" alt="头像" style="max-width: 80px; vertical-align: middle;">
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="hap-info-row">
                                <span class="hap-info-label">个性签名:</span>
                                <span class="hap-info-value"><?php echo esc_html($user_data['basic_info']['bio']); ?></span>
                            </div>
                            <div class="hap-info-row">
                                <span class="hap-info-label">通关副本:</span>
                                <div class="hap-info-value">
                                    <?php if (!empty($user_data['basic_info']['completed_scenarios'])): ?>
                                        <ul>
                                            <?php foreach ($user_data['basic_info']['completed_scenarios'] as $scenario): ?>
                                                <li><?php echo esc_html($scenario); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        暂无通关副本
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 显示角色属性 -->
                    <div class="hap-section">
                        <h3>角色属性</h3>
                        <div class="hap-attributes-display">
                            <?php foreach ($user_data['attributes'] as $key => $value): ?>
                                <div class="hap-attribute-display">
                                    <span class="hap-attribute-label"><?php echo esc_html($this->get_attribute_label($key)); ?>:</span>
                                    <span class="hap-attribute-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 显示衍生属性 -->
                    <div class="hap-section">
                        <h3>衍生属性</h3>
                        <div class="hap-attributes-display">
                            <?php foreach ($user_data['derived_attributes'] as $key => $value): ?>
                                <div class="hap-attribute-display">
                                    <span class="hap-attribute-label"><?php echo esc_html($this->get_derived_attribute_label($key)); ?>:</span>
                                    <span class="hap-attribute-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 显示专精 -->
                    <div class="hap-section">
                        <h3>专精</h3>
                        <div class="hap-specializations-display">
                            <?php foreach ($user_data['specializations'] as $key => $value): ?>
                                <div class="hap-specialization-display">
                                    <span class="hap-specialization-label"><?php echo esc_html($this->get_specialization_label($key)); ?>:</span>
                                    <span class="hap-specialization-value"><?php echo esc_html($value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 显示货币 -->
                    <div class="hap-section">
                        <h3>货币</h3>
                        <div class="hap-currency-display">
                            <div class="hap-currency-item">
                                <span class="hap-currency-label">游戏币:</span>
                                <span class="hap-currency-value"><?php echo esc_html($user_data['currency']['game_coin']); ?></span>
                            </div>
                            <div class="hap-currency-item">
                                <span class="hap-currency-label">技巧值:</span>
                                <span class="hap-currency-value"><?php echo esc_html($user_data['currency']['skill_points']); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!$has_requested): ?>
                        <button id="hap-request-edit" class="hap-button">申请修改</button>
                    <?php else: ?>
                        <p class="hap-notice">您的修改申请已提交，等待管理员审核中...</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    private function get_user_data($user_id)
    {
        $defaults = [
            'basic_info' => [
                'nickname' => '',
                'avatar' => '',
                'bio' => '',
                'completed_scenarios' => []
            ],
            'attributes' => $this->get_default_attributes(),
            'derived_attributes' => $this->get_default_derived_attributes(),
            'specializations' => $this->get_default_specializations(),
            'currency' => $this->get_default_currency()
        ];

        $user_data = get_user_meta($user_id, 'hap_profile_data', true);

        if (empty($user_data)) {
            return $defaults;
        }

        return wp_parse_args($user_data, $defaults);
    }

    private function get_default_attributes()
    {
        return [
            'strength' => 0,       // 力量
            'agility' => 0,        // 敏捷
            'will' => 0,           // 意志
            'intelligence' => 0,   // 智力
            'spirit' => 0,         // 精神
            'luck' => 0,           // 幸运
            'perception' => 0,     // 感知
            'constitution' => 0,   // 体质
            'charm' => 0,          // 魅力
            'health' => 0,         // 生存值
            'physical' => 0,       // 物理
            'shooting' => 0,       // 射击
            'magic' => 0           // 灵术
        ];
    }

    private function get_default_derived_attributes()
    {
        return [
            'ap' => 0,              // AP
            'dodge' => 0,           // 闪避
            'physical_armor' => 0,  // 物理护甲
            'block' => 0,           // 格挡
            'natural_armor' => 0,   // 天生护甲
            'magic_armor' => 0,     // 灵术护甲
            'initiative' => 0,      // 先攻
            'spirit_armor' => 0,    // 精神护甲
            'strength_db' => 0,     // 力量DB
            'intelligence_db' => 0, // 智力DB
            'perception_db' => 0,  // 感知DB
            'physical_hit' => 0,    // 物理命中
            'shooting_hit' => 0,    // 射击命中
            'magic_hit' => 0        // 灵术命中
        ];
    }

    private function get_default_specializations()
    {
        return [
            'general' => 0,    // 通用专精
            'combat' => 0,      // 格斗专精
            'mechanical' => 0,  // 机械专精
            'medical' => 0,     // 医疗专精
            'summoning' => 0,   // 召唤专精
            'shooting' => 0,    // 射击专精
            'scouting' => 0,    // 侦查专精
            'magic' => 0        // 灵术专精
        ];
    }

    private function get_default_currency()
    {
        return [
            'game_coin' => 0,   // 游戏币
            'skill_points' => 0 // 技巧值
        ];
    }

    private function get_attribute_label($key)
    {
        $labels = [
            'strength' => '力量',
            'agility' => '敏捷',
            'will' => '意志',
            'intelligence' => '智力',
            'spirit' => '精神',
            'luck' => '幸运',
            'perception' => '感知',
            'constitution' => '体质',
            'charm' => '魅力',
            'health' => '生存值',
            'physical' => '物理',
            'shooting' => '射击',
            'magic' => '灵术'
        ];

        return $labels[$key] ?? $key;
    }

    private function get_derived_attribute_label($key)
    {
        $labels = [
            'ap' => 'AP',
            'dodge' => '闪避',
            'physical_armor' => '物理护甲',
            'block' => '格挡',
            'natural_armor' => '天生护甲',
            'magic_armor' => '灵术护甲',
            'initiative' => '先攻',
            'spirit_armor' => '精神护甲',
            'strength_db' => '力量DB',
            'intelligence_db' => '智力DB',
            'perception_db' => '感知DB',
            'physical_hit' => '物理命中',
            'shooting_hit' => '射击命中',
            'magic_hit' => '灵术命中'
        ];

        return $labels[$key] ?? $key;
    }

    private function get_specialization_label($key)
    {
        $labels = [
            'general' => '通用专精',
            'combat' => '格斗专精',
            'mechanical' => '机械专精',
            'medical' => '医疗专精',
            'summoning' => '召唤专精',
            'shooting' => '射击专精',
            'scouting' => '侦查专精',
            'magic' => '灵术专精'
        ];

        return $labels[$key] ?? $key;
    }

    public function save_profile()
    {
        check_ajax_referer('hap-nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('用户未登录');
        }

        // 处理头像ID
        $avatar_id = intval($_POST['avatar_id']);

        // 处理基础信息
        $basic_info = [
            'nickname' => sanitize_text_field($_POST['nickname'] ?? ''),
            'avatar' => !empty($_POST['avatar_id']) ? intval($_POST['avatar_id']) : 0,
            'bio' => sanitize_textarea_field($_POST['bio'] ?? ''),
            'completed_scenarios' => array_filter(
                array_map(
                    'sanitize_text_field',
                    explode("\n", $_POST['completed_scenarios'] ?? '')
                )
            )
        ];

        // 处理基础信息
        $basic_info = [
            'nickname' => sanitize_text_field($_POST['nickname']),
            'avatar' => esc_url_raw($_POST['avatar_url']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'completed_scenarios' => array_filter(
                array_map(
                    'sanitize_text_field',
                    explode("\n", $_POST['completed_scenarios'])
                )
            )
        ];

        // 处理其他数据
        $profile_data = [
            'basic_info' => $basic_info,
            'attributes' => array_map('intval', $_POST['attributes']),
            'derived_attributes' => array_map('intval', $_POST['derived_attributes']),
            'specializations' => array_map('sanitize_text_field', $_POST['specializations']), // 改为字符串处理
            'currency' => [
                'game_coin' => intval($_POST['currency']['game_coin']),
                'skill_points' => intval($_POST['currency']['skill_points'])
            ]
        ];

        update_user_meta($user_id, 'hap_profile_data', $profile_data);

        // 更新编辑次数
        $edit_count = get_user_meta($user_id, $this->edit_count_meta_key, true);
        update_user_meta($user_id, $this->edit_count_meta_key, ($edit_count ? $edit_count + 1 : 1));

        wp_send_json_success('个人信息已保存');
        wp_die();
    }

    public function handle_avatar_upload()
    {
        check_ajax_referer('hap-nonce', 'nonce');

        if (empty($_FILES['avatar'])) {
            wp_send_json_error('没有上传文件');
        }

        $file = $_FILES['avatar'];

        // 检查文件类型
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('只允许上传JPEG、PNG或GIF图片');
        }

        // 检查文件大小 (限制为2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error('图片大小不能超过2MB');
        }

        // 处理上传
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        wp_send_json_success([
            'url' => wp_get_attachment_url($attachment_id),
            'id' => $attachment_id
        ]);
    }

    public function request_edit()
    {
        check_ajax_referer('hap-nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('用户未登录');
        }

        if (get_user_meta($user_id, $this->edit_request_meta_key, true)) {
            wp_send_json_error('您已经提交过申请');
        }

        update_user_meta($user_id, $this->edit_request_meta_key, 'pending');
        update_user_meta($user_id, $this->edit_request_meta_key . '_time', current_time('mysql', true));

        wp_send_json_success('修改申请已提交');
        wp_die();
    }

    private function can_user_edit($user_id)
    {
        $edit_count = get_user_meta($user_id, $this->edit_count_meta_key, true);
        return empty($edit_count) || $edit_count < 1;
    }
}
