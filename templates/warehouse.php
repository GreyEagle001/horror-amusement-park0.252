<div class="hap-warehouse-container">
    <div class="hap-warehouse-tabs">
        <button class="hap-tab-btn active" data-tab="inventory">我的仓库</button>
        <button class="hap-tab-btn" data-tab="custom">自定义道具</button>
    </div>

    <div class="hap-tab-content active" id="hap-inventory-tab">
        <h3>我的仓库</h3>
        <div class="hap-inventory-filters">
            <select id="hap-inventory-type">
                <option value="*">所有类型</option>
                <option value="consumable">消耗道具</option>
                <option value="permanent">永久道具</option>
                <option value="arrow">箭矢</option>
                <option value="bullet">子弹</option>
                <option value="equipment">装备</option>
                <option value="skill">法术</option>
            </select>
        </div>
        <button id="hap-warehouse-search-btn" class="hap-button">搜索</button>
        <div class="hap-inventory-grid" id="hap-inventory-container">
        </div>
    </div>

    <div class="hap-tab-content" id="hap-custom-tab">
        <h3>自定义道具</h3>
        <div class="hap-custom-item-container">
            <h4>添加自定义道具</h4>
            <div class="hap-custom-item-filters">
                <!-- 基础信息 -->
                <input type="text" id="hap-custom-item-name" placeholder="名称*" required>
                <input type="text" id="hap-custom-item-attributes" placeholder="属性">
                
                <!-- 类型选择 -->
                <select id="hap-custom-item-type" required>
                    <option value="">类型*</option>
                    <option value="consumable">消耗道具</option>
                    <option value="permanent">永久道具</option>
                    <option value="arrow">箭矢</option>
                    <option value="bullet">子弹</option>
                    <option value="equipment">装备</option>
                    <option value="skill">法术</option>
                </select>
                
                <!-- 品质选择 -->
                <select id="hap-custom-item-quality">
                    <option value="">品质</option>
                    <option value="common">普通</option>
                    <option value="uncommon">精良</option>
                    <option value="rare">稀有</option>
                    <option value="epic">史诗</option>
                    <option value="legendary">传说</option>
                </select>
                
                <!-- 数值类字段 -->
                <input type="text" id="hap-custom-item-level" placeholder="可使用等级">
                <input type="number" id="hap-custom-item-restrictions" placeholder="单格携带数量" min="1">
                <textarea id="hap-custom-item-effects" placeholder="特效" required></textarea>
                <textarea id="hap-custom-item-comment" placeholder="备注"></textarea>
                <input type="text" id="hap-custom-item-duration" placeholder="持续时间">
                
                <!-- 价格系统 -->
                <div class="hap-price-section">
                    <input type="number" id="hap-custom-item-price" placeholder="价格*" min="0" required>
                    <select id="hap-custom-item-currency" required>
                        <option value="game_coin" selected>游戏币*</option>
                        <option value="skill_points">技巧值*</option>
                    </select>
                </div>
                
                <!-- 消耗/学习要求 -->
                <input type="text" id="hap-custom-item-consumption" placeholder="单次使用消耗">
                <input type="text" id="hap-custom-item-learning-req" placeholder="学习条件">
                
                <!-- 作者信息 -->
                <input type="text" id="hap-custom-item-author" placeholder="作者*" required>
                
                <button id="hap-custom-item-save-btn" class="hapcustom-item-save-button">保存道具</button>
            </div>
        </div>
        
        <div class="hap-custom-item-grid" id="hap-custom-item-container">
            <!-- 自定义道具列表将在此显示 -->
        </div>

        <div class="hap-custom-item-pagination" id="hap-custom-items-pagination"></div>
    </div>
</div> 