<?php
/**
 * @name init.php
 * @desc 文件初始化设置,包含此目录包需要的文件及变量声明
 */

header('Content-Type: text/html;charset=utf-8');

# 配置文件
include '../conf/_local.inc.php';
include ROOT_PATH . '/function/global.inc.php';
include ROOT_PATH . '/function/function.inc.php';

// 调试探针，初始化完成，页面开始执行
\common\DebugLog::_time('_init.php, start page');
// 定义一个变量，引入的文件会用到
$TEMPLATE = array();
// 获取用户信息
$login_userinfo = get_login_userinfo();
$TEMPLATE['login_userinfo'] = $login_userinfo;
$now = time();
