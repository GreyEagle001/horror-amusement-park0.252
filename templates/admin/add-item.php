<div class="wrap hap-admin">
    <h1>添加新商品</h1>
    
    <form id="hap-item-form" method="post">
        <div class="hap-form-section">
            <h2>基本信息</h2>
            
            <div class="hap-form-row">
                <label for="hap-item-type">商品类型</label>
                <select id="hap-item-type" name="item_type" required>
                    <option value="">选择类型</option>
                    <option value="consumable">消耗道具</option>
                    <option value="permanent">永久道具</option>
                    <option value="arrow">箭矢</option>
                    <option value="bullet">子弹</option>
                    <option value="equipment">装备</option>
                    <option value="skill">法术</option>
                </select>
            </div>
            
            <div class="hap-form-row">
                <label for="hap-item-name">名称</label>
                <input type="text" id="hap-item-name" name="name" required>
            </div>
            
            <div class="hap-form-row">
                <label for="hap-item-quality">品质</label>
                <select id="hap-item-quality" name="quality" required>
                    <option value="common">普通</option>
                    <option value="uncommon">精良</option>
                    <option value="rare">稀有</option>
                    <option value="epic">史诗</option>
                    <option value="legendary">传说</option>
                </select>
            </div>
            
            <div class="hap-form-row">
                <label for="hap-item-price">价格</label>
                <input type="number" id="hap-item-price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="hap-form-row">
                <label for="hap-item-currency">货币类型</label>
                <select id="hap-item-currency" name="currency" required>
                    <option value="game_coin">游戏币</option>
                    <option value="skill_points">技巧值</option>
                </select>
            </div>
            
            <div class="hap-form-row">
                <label for="hap-item-duration">持续时间(秒)</label>
                <input type="number" id="hap-item-duration" name="duration" min="0">
                <p class="description">0表示永久有效</p>
            </div>
            
            <div class="hap-form-row">
                <label for="hap-item-status">状态</label>
                <select id="hap-item-status" name="status" required>
                    <option value="publish">发布</option>
                    <option value="draft">草稿</option>
                </select>
            </div>
        </div>
        
        <div class="hap-form-section" id="hap-attributes-section">
            <h2>属性设置</h2>
            <div id="hap-common-attributes">
                <div class="hap-form-row">
                    <label for="hap-item-effect">特效描述</label>
                    <textarea id="hap-item-effect" name="attributes[effect]"></textarea>
                </div>
                
                <div class="hap-form-row">
                    <label for="hap-item-restriction">使用限制</label>
                    <textarea id="hap-item-restriction" name="attributes[restriction]"></textarea>
                </div>
            </div>
            
            <div id="hap-type-specific-fields">
                <!-- 动态加载不同类型特有字段 -->
            </div>
        </div>
        
        <div class="hap-form-actions">
            <button type="submit" class="button button-primary">保存商品</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // 动态加载不同类型字段
    $('#hap-item-type').change(function() {
        const type = $(this).val();
        $('#hap-type-specific-fields').html('<div class="hap-loading">加载字段...</div>');
        
        $.post(ajaxurl, {
            action: 'hap_load_item_fields',
            type: type,
            nonce: hap_admin.nonce
        }, function(response) {
            if (response.success) {
                $('#hap-type-specific-fields').html(response.data);
            } else {
                $('#hap-type-specific-fields').html('<div class="hap-error">加载字段失败</div>');
            }
        });
    });
    
    // 表单提交
    $('#hap-item-form