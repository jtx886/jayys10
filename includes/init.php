<?php
/**
 * Jay影视 - 统一引导文件
 * 未安装（无 includes/config.php）时自动引导至安装向导
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Shanghai');

/* 当前脚本（用于识别安装向导自身） */
$current = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';

/* 尚未安装：跳转安装向导（install.php 自身独立运行，不经过本文件） */
$configFile = APP_ROOT . '/includes/config.php';
if (!is_file($configFile) && $current !== 'install.php') {
    /* 由脚本相对 APP_ROOT 的位置反推站点根 Web 路径（兼容子目录部署） */
    $base = '';
    $relScript = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], strlen(APP_ROOT) + 1));
    if ($relScript !== '' && substr($_SERVER['SCRIPT_NAME'], -strlen($relScript)) === $relScript) {
        $base = rtrim(substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - strlen($relScript)), '/');
    }
    header('Location: ' . ($base === '' ? '' : $base) . '/install.php');
    exit;
}

require_once $configFile;
require_once APP_ROOT . '/includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('JAYSESS');
    session_set_cookie_params(0, '/', '', false, true);
    session_start();
}
