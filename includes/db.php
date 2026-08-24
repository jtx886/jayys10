<?php
/**
 * Jay影视 - 数据库连接（PDO 单例）
 * PHP 7.4 - 8.x 兼容
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require_once APP_ROOT . '/includes/config.php';

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ));
            /* 统一数据库会话时区为北京时间，保证 NOW()/DATE_ADD 与 PHP time() 一致 */
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (Exception $e) {
            http_response_code(500);
            exit('数据库连接失败，请检查 includes/config.php 中的配置信息。');
        }
    }
    return $pdo;
}

/** 快捷查询 */
function db_query($sql, $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_all($sql, $params = array())
{
    return db_query($sql, $params)->fetchAll();
}

function db_one($sql, $params = array())
{
    return db_query($sql, $params)->fetch();
}

function db_val($sql, $params = array())
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec($sql, $params = array())
{
    return db_query($sql, $params)->rowCount();
}
