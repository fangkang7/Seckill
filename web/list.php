<?php

// web/init.php
include 'init.php';

$TEMPLATE['type'] = 'list';
$TEMPLATE['pageTitle'] = '秒杀商品列表';
# 活动模型
$active_model = new \model\Active();
# 商品列表模型
$goods_model = new \model\Goods();

# 获取上线的活动列表
$list_active = $active_model->getListInuse();
# 声明一个活动商品的变量
$list_active_goods = array();
foreach ($list_active as $data) {
    # 取出活动id
    $aid = $data['id'];
    $list_goods = $goods_model->getListByActive($aid, -1);
    // 取出活动中的商品
    $list_active_goods[$aid] = $list_goods;
}
$TEMPLATE['list_active'] = $list_active;
$TEMPLATE['list_active_goods'] = $list_active_goods;

# '/views'
include TEMPLATE_PATH . '/list.php';
