<?php

class Warehouse {
    public function ajax_get_custom_items() {
        check_ajax_referer('hap-ajax', 'nonce');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $result = $this->get_custom_items($page);

        wp_send_json_success($result);
    }

    private function get_custom_items($page = 1) {
        // 这里是获取自定义道具的逻辑，您需要根据实际情况进行修改
        // 下面是一个示例代码
        $items = []; // 从数据库中获取自定义道具
        $total = 0; // 获取自定义道具的总数

        $pages = ceil($total / 10);
        $page = max(1, min($pages, $page));

        return [
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
        ];
    }
} 