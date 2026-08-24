<?php
/**
 * Jay影视 - 发送注册验证码（163 SMTP · 精美HTML邮件）
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/smtp.php';

if (!is_post()) json_out(array('code' => 405, 'msg' => '请求方式错误'));
if (!csrf_check()) json_out(array('code' => 403, 'msg' => '会话已过期，请刷新页面重试'));

$email = strtolower(post('email'));
if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) json_out(array('code' => 1, 'msg' => '邮箱格式不正确'));
if (db_one("SELECT id FROM users WHERE email=? LIMIT 1", array($email))) json_out(array('code' => 2, 'msg' => '该邮箱已注册，请直接登录'));

/* 60秒发送频率限制 */
$last = db_one("SELECT created_at FROM verify_codes WHERE email=? ORDER BY id DESC LIMIT 1", array($email));
if ($last && (time() - strtotime($last['created_at'])) < 60) {
    json_out(array('code' => 3, 'msg' => '发送太频繁，请 60 秒后再试'));
}

$code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
db_exec("INSERT INTO verify_codes (email,code,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 5 MINUTE))", array($email, $code));

$err = '';
$ok = send_mail($email, '新用户', '【Jay影视】注册验证码', mail_template_code($code), $err);
if (!$ok) {
    /* 发送失败回滚验证码，避免触发 60 秒频率限制 */
    db_exec("DELETE FROM verify_codes WHERE email=? AND code=?", array($email, $code));
    if (strpos($err, '535') !== false || stripos($err, 'authentication') !== false || strpos($err, '授权码') !== false) {
        $msg = '邮件发送失败：SMTP 授权码认证被拒，请联系管理员检查 163 邮箱授权码';
    } elseif (strpos($err, '连接') !== false) {
        $msg = '邮件发送失败：无法连接邮件服务器，请稍后重试';
    } else {
        $msg = '邮件发送失败，请稍后重试';
    }
    json_out(array('code' => 4, 'msg' => $msg));
}
json_out(array('code' => 0, 'msg' => '发送成功'));
