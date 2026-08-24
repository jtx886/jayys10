<?php
/**
 * Jay影视 - 注册（邮箱验证码）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/smtp.php';
if (is_login()) redirect('index.php');

$error = '';
$vEmail = post('email');
$vUser = post('username');

if (is_post()) {
    $email = strtolower(post('email'));
    $username = post('username');
    $password = post('password');
    $password2 = post('password2');
    $code = post('code');

    if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
        $error = '邮箱格式不正确';
    } elseif (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]{2,20}$/u', $username)) {
        $error = '用户名需为 2-20 位中文、字母、数字或下划线';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少 6 位';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = '请输入 6 位邮箱验证码';
    } else {
        if (db_one("SELECT id FROM users WHERE email=? LIMIT 1", array($email))) {
            $error = '该邮箱已被注册';
        } elseif (db_one("SELECT id FROM users WHERE username=? LIMIT 1", array($username))) {
            $error = '该用户名已被使用';
        } else {
            $vc = db_one("SELECT * FROM verify_codes WHERE email=? AND code=? AND used=0 ORDER BY id DESC LIMIT 1", array($email, $code));
            $okCode = $vc && strtotime($vc['expires_at']) > time();
            if (!$okCode) {
                $error = '验证码错误或已过期，请重新获取';
            } else {
                db_exec("UPDATE verify_codes SET used=1 WHERE id=?", array($vc['id']));
                db_exec("INSERT INTO users (username,email,password,role,status) VALUES (?,?,?,'user',0)",
                    array($username, $email, password_hash($password, PASSWORD_DEFAULT)));
                $uid = db_val("SELECT id FROM users WHERE email=?", array($email));
                $_SESSION['uid'] = intval($uid);
                session_regenerate_id(true);
                redirect('index.php');
            }
        }
    }
}

$PAGE_TITLE = '注册';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card">
  <h1>创建账号</h1>
  <p class="sub">注册 <?php echo h($SITE_NAME); ?> 账号，海量影视免费观看</p>

  <?php if ($error): ?><div class="alert err"><?php echo h($error); ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="field">
      <label>邮箱（用于接收验证码）</label>
      <input type="email" id="regEmail" name="email" value="<?php echo h($vEmail); ?>" placeholder="example@mail.com" required>
    </div>
    <div class="field">
      <label>用户名</label>
      <input type="text" name="username" value="<?php echo h($vUser); ?>" placeholder="2-20位中文/字母/数字/下划线" required>
    </div>
    <div class="field">
      <label>密码</label>
      <input type="password" name="password" placeholder="至少 6 位" required>
    </div>
    <div class="field">
      <label>确认密码</label>
      <input type="password" name="password2" placeholder="再次输入密码" required>
    </div>
    <div class="field input-group">
      <input type="text" name="code" placeholder="邮箱验证码" maxlength="6" style="flex:1" class="input" required>
      <button type="button" class="btn ghost btn-code" id="sendCode">获取验证码</button>
    </div>
    <button class="btn primary lg" type="submit" style="width:100%"><i class="ico i-check"></i>注 册</button>
  </form>
  <p style="margin-top:20px;text-align:center;font-size:13.5px;color:var(--muted)">已有账号？<a href="login.php" style="color:var(--theme);font-weight:600">去登录</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
