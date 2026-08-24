<?php
/**
 * Jay影视 - 公共函数库
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require_once APP_ROOT . '/includes/db.php';

/* ---------- 基础 ---------- */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_post() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function post($k, $d = '')
{
    return isset($_POST[$k]) ? (is_array($_POST[$k]) ? $_POST[$k] : trim((string)$_POST[$k])) : $d;
}

function get($k, $d = '')
{
    return isset($_GET[$k]) ? (is_array($_GET[$k]) ? $_GET[$k] : trim((string)$_GET[$k])) : $d;
}

/* ---------- 设置 ---------- */
function setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        try {
            foreach (db_all("SELECT skey,svalue FROM settings") as $r) { $cache[$r['skey']] = $r['svalue']; }
        } catch (Exception $e) {}
    }
    return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
}

function setting_save($key, $value)
{
    db_exec("INSERT INTO settings (skey,svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)", array($key, $value));
}

/* ---------- 用户与会话 ---------- */
function current_user()
{
    static $user = null;
    if ($user === null && !empty($_SESSION['uid'])) {
        $u = db_one("SELECT * FROM users WHERE id=?", array($_SESSION['uid']));
        if ($u) {
            /* 自动解封：封禁到期后自动恢复正常 */
            if ((int)$u['status'] === 1 && $u['ban_until'] !== null && strtotime($u['ban_until']) <= time()) {
                db_exec("UPDATE users SET status=0, ban_reason='', banned_at=NULL, ban_until=NULL WHERE id=?", array($u['id']));
                $u['status'] = 0; $u['ban_reason'] = ''; $u['banned_at'] = null; $u['ban_until'] = null;
            }
            $user = $u;
        }
    }
    return $user;
}

function is_login() { return current_user() !== null; }

function is_admin() { $u = current_user(); return $u !== null && $u['role'] === 'admin'; }

/** 用户是否处于封禁中 */
function user_banned($u)
{
    if (!$u || (int)$u['status'] !== 1) return false;
    if ($u['ban_until'] === null || $u['ban_until'] === '') return true; /* 永久 */
    return strtotime($u['ban_until']) > time();
}

function avatar_url($u)
{
    if (!empty($u['avatar'])) return $u['avatar'];
    /* 无头像：纯CSS首字母头像 */
    return '';
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}

function csrf_check()
{
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    return hash_equals($_SESSION['csrf'], $t);
}

/* ---------- 媒体工具 ---------- */
function tmdb_img($path, $size = 'w500')
{
    if (!$path) return '';
    return 'https://image.tmdb.org/t/p/' . $size . $path;
}

function media_link($type, $id)
{
    return 'detail.php?type=' . urlencode($type) . '&id=' . intval($id);
}

function fmt_time($seconds)
{
    $seconds = max(0, intval($seconds));
    if ($seconds < 60) return $seconds . '秒';
    if ($seconds < 3600) return floor($seconds / 60) . '分钟';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return $h . '小时' . ($m > 0 ? $m . '分' : '');
}

function fmt_date($s)
{
    if (!$s || $s === '0000-00-00') return '';
    $t = strtotime($s);
    return $t ? date('Y-m-d H:i', $t) : '';
}

/** 观看进度百分比 */
function progress_pct($pos, $epCount)
{
    $pos = intval($pos);
    if ($pos <= 0) return 0;
    /* 粗略估算：单集约45分钟 */
    $total = max(1, intval($epCount)) * 2700;
    return min(100, (int)round($pos / $total * 100));
}

/* ---------- HTTP 请求 ---------- */
function http_get($url, $timeout = 12)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ));
        $res = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        if ($err === 0 && $res !== false) return $res;
        return false;
    }
    $ctx = stream_context_create(array('http' => array('timeout' => $timeout, 'header' => "User-Agent: Mozilla/5.0\r\n")));
    $res = @file_get_contents($url, false, $ctx);
    return $res === false ? false : $res;
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}
