<?php
/**
 * Jay影视 - SMTP 邮件发送（原生 Socket SSL，适配 InfinityFree 等禁用 mail() 的免费空间）
 * 固定 163 邮箱配置
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }

define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

class Mailer
{
    private $socket = null;

    public function __construct()
    {
        $addr = 'ssl://' . SMTP_HOST . ':' . SMTP_PORT;
        $ctx = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
        $this->socket = @stream_socket_client($addr, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->socket) {
            throw new Exception("SMTP 连接失败 ({$errno}): {$errstr}");
        }
        stream_set_timeout($this->socket, 15);
    }

    private function read()
    {
        $data = '';
        while (($line = fgets($this->socket, 512)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return $data;
    }

    private function cmd($cmd)
    {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->read();
    }

    public function send($toEmail, $toName, $subject, $htmlBody)
    {
        /* 问候 */
        $r = $this->read();
        if (strpos($r, '220') !== 0) throw new Exception('SMTP 服务器无响应: ' . $r);

        $r = $this->cmd('EHLO jaymovie');
        if (strpos($r, '250') !== 0) throw new Exception('EHLO 失败: ' . $r);

        $r = $this->cmd('AUTH LOGIN');
        if (strpos($r, '334') !== 0) throw new Exception('请求认证失败: ' . $r);
        $r = $this->cmd(base64_encode(SMTP_USER));
        if (strpos($r, '334') !== 0) throw new Exception('用户名错误: ' . $r);
        $r = $this->cmd(base64_encode(SMTP_PASS));
        if (strpos($r, '235') !== 0) throw new Exception('密码/授权码错误: ' . $r);

        $r = $this->cmd('MAIL FROM:<' . SMTP_FROM . '>');
        if (strpos($r, '250') !== 0) throw new Exception('MAIL FROM 失败: ' . $r);
        $r = $this->cmd('RCPT TO:<' . $toEmail . '>');
        if (strpos($r, '250') !== 0 && strpos($r, '251') !== 0) throw new Exception('收件人被拒绝: ' . $r);
        $r = $this->cmd('DATA');
        if (strpos($r, '354') !== 0) throw new Exception('DATA 失败: ' . $r);

        $headers  = 'From: =?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . "?= <" . SMTP_FROM . ">\r\n";
        $headers .= 'To: =?UTF-8?B?' . base64_encode($toName) . "?= <" . $toEmail . ">\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . md5(uniqid()) . "@jaymovie>\r\n";

        $body = chunk_split(base64_encode($htmlBody));
        $msg  = $headers . "\r\n" . $body . "\r\n.";
        $r = $this->cmd($msg);
        if (strpos($r, '250') !== 0) throw new Exception('邮件发送失败: ' . $r);

        $this->cmd('QUIT');
        return true;
    }

    public function close()
    {
        if ($this->socket) { fclose($this->socket); $this->socket = null; }
    }

    public function __destruct() { $this->close(); }
}

/** 发送邮件便捷函数（失败返回 false，异常信息记录到静默日志） */
function send_mail($toEmail, $toName, $subject, $htmlBody)
{
    try {
        $m = new Mailer();
        $ok = $m->send($toEmail, $toName, $subject, $htmlBody);
        $m->close();
        return $ok;
    } catch (Exception $e) {
        @file_put_contents(APP_ROOT . '/uploads/smtp_error.log', date('Y-m-d H:i:s') . ' ' . $toEmail . ' ' . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/* ============ HTML 邮件模板（精致暗色风） ============ */
function mail_wrap($title, $innerHtml)
{
    $site = 'Jay影视';
    return '<!doctype html><html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#0b0f14;">
<div style="display:none;max-height:0;overflow:hidden;">' . h($title) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f14;padding:32px 12px;font-family:\'Segoe UI\',\'PingFang SC\',\'Microsoft YaHei\',Arial,sans-serif;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#151b23;border:1px solid #232c37;border-radius:18px;overflow:hidden;">
  <tr><td style="background:linear-gradient(135deg,#e50914,#ff5a4e);padding:26px 32px;">
    <table role="presentation" cellpadding="0" cellspacing="0"><tr>
      <td style="width:38px;height:38px;background:rgba(255,255,255,.18);border-radius:11px;text-align:center;vertical-align:middle;">
        <div style="width:0;height:0;border-left:13px solid #fff;border-top:8px solid transparent;border-bottom:8px solid transparent;display:inline-block;margin-left:4px;"></div>
      </td>
      <td style="padding-left:12px;color:#fff;font-size:19px;font-weight:700;letter-spacing:1px;">' . h($site) . '</td>
    </tr></table>
  </td></tr>
  <tr><td style="padding:32px;color:#e6edf3;font-size:14px;line-height:1.8;">
    ' . $innerHtml . '
  </td></tr>
  <tr><td style="padding:18px 32px;border-top:1px solid #232c37;color:#8b949e;font-size:12px;text-align:center;">
    这封邮件由 ' . h($site) . ' 系统自动发送，请勿直接回复。
  </td></tr>
</table>
</td></tr></table></body></html>';
}

/** 验证码邮件 */
function mail_template_code($code)
{
    $inner = '
    <p style="margin:0 0 14px;color:#8b949e;">您好！欢迎注册 <b style="color:#e6edf3;">Jay影视</b>，您的邮箱验证码为：</p>
    <div style="text-align:center;margin:26px 0;">
      <span style="display:inline-block;background:#0d1319;border:1px solid #2a3442;border-left:4px solid #e50914;border-radius:12px;padding:16px 34px;font-size:34px;font-weight:800;letter-spacing:10px;color:#ff6b62;font-family:Consolas,monospace;">' . h($code) . '</span>
    </div>
    <p style="margin:0 0 6px;color:#8b949e;">验证码 <b style="color:#e6edf3;">5 分钟</b> 内有效，请尽快完成注册。</p>
    <p style="margin:0;color:#8b949e;">如果这不是您本人的操作，请忽略本邮件。</p>';
    return mail_wrap('注册验证码', $inner);
}

/** 封禁通知邮件 */
function mail_template_ban($username, $reason, $startAt, $untilAt)
{
    $row = function ($label, $val) {
        return '<tr>
          <td style="padding:10px 14px;background:#0d1319;border:1px solid #232c37;color:#8b949e;font-size:13px;white-space:nowrap;">' . $label . '</td>
          <td style="padding:10px 14px;background:#0d1319;border:1px solid #232c37;color:#e6edf3;font-size:13px;">' . $val . '</td>
        </tr>';
    };
    $inner = '
    <p style="margin:0 0 6px;">尊敬的用户 <b style="color:#e6edf3;">' . h($username) . '</b>：</p>
    <p style="margin:0 0 18px;color:#ff8a8d;">您的账号已被管理员封禁，具体信息如下：</p>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border-spacing:0 6px;">
      ' . $row('封禁原因', h($reason) ?: '未填写') . '
      ' . $row('封禁时间', h($startAt)) . '
      ' . $row('解除时间', $untilAt ? h($untilAt) : '<b style="color:#ff8a8d;">永久封禁</b>') . '
    </table>
    <p style="margin:18px 0 0;color:#8b949e;">如有疑问，请通过站内反馈系统联系管理员。</p>';
    return mail_wrap('账号封禁通知', $inner);
}

/** 通用通知邮件（后台邮件推送） */
function mail_template_notice($title, $content)
{
    $inner = '
    <p style="margin:0 0 16px;font-size:17px;font-weight:700;color:#e6edf3;">' . h($title) . '</p>
    <div style="background:#0d1319;border:1px solid #232c37;border-left:4px solid #e50914;border-radius:12px;padding:18px 20px;color:#c9d1d9;white-space:pre-wrap;">' . h($content) . '</div>
    <p style="margin:16px 0 0;color:#8b949e;">—— 来自 Jay影视 管理团队</p>';
    return mail_wrap($title, $inner);
}
