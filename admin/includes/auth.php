<?php
/**
 * Jay影视 - 后台鉴权（所有 admin 页面引用）
 */
require_once dirname(dirname(__DIR__)) . '/includes/init.php';
$ADMIN = current_user();
if (!$ADMIN || $ADMIN['role'] !== 'admin') {
    redirect('login.php');
}
