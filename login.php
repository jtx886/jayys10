<?php
/**
 * Jay影视 - 登录
 */
require_once __DIR__ . '/includes/init.php';
if (is_login()) redirect('index.php');

$error = '';
$msg = get('msg');
$redirect = get('redirect', 'index.php');
if (strpos($redirect, '://') !== false || $redirect === '' || $redirect[0] === '@') $redirect = 'index.php';

if (is_post()) {
    $account = post('account');
    $password = post('password');
    if ($account === '' || $password === '') {
        $error = '请输入账号和密码';
    } else {
        $u = db_one("SELECT * FROM users WHERE username=? OR email=? LIMIT 1", array($account, $account));
        if (!$u || !password_verify($password, $u['password'])) {
            $error = '账号或密码错误';
        } elseif (user_banned($u)) {
            $until = $u['ban_until'] ? date('Y-m-d H:i', strtotime($u['ban_until'])) : '永久';
            $error = '该账号已被封禁（解除时间：' . $until . '），封禁原因：' . ($u['ban_reason'] !== '' ? $u['ban_reason'] : '未填写');
        } else {
            $_SESSION['uid'] = intval($u['id']);
            session_regenerate_id(true);
            redirect($redirect);
        }
    }
}

$PAGE_TITLE = '登录';
require __DIR__ . '/includes/header.php';
?>
<div class="form-card">
  <h1>欢迎回来</h1>
  <p class="sub">登录 <?php echo h($SITE_NAME); ?>，继续观看精彩影视</p>

  <?php if ($error): ?><div class="alert err"><?php echo h($error); ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="redirect" value="<?php echo h($redirect); ?>">
    <div class="field">
      <label>用户名 / 邮箱</label>
      <input type="text" name="account" value="<?php echo h(post('account')); ?>" placeholder="请输入用户名或邮箱" required>
    </div>
    <div class="field">
      <label>密码</label>
      <input type="password" name="password" placeholder="请输入密码" required>
    </div>
    <button class="btn primary lg" type="submit" style="width:100%"><i class="ico i-lock"></i>登 录</button>
  </form>
  <p style="margin-top:20px;text-align:center;font-size:13.5px;color:var(--muted)">还没有账号？<a href="register.php" style="color:var(--theme);font-weight:600">立即注册</a></p>
</div>

<?php if ($msg === 'play'): ?>
<!-- 点击播放跳转登录：弹窗提示 -->
<div class="modal-mask" id="loginTip" style="display:flex">
  <div class="modal">
    <div class="modal-head"><i class="ico i-lock"></i><span>登录提示</span>
      <button class="modal-close" type="button" onclick="closeModal('#loginTip')"><i class="ico i-close"></i></button>
    </div>
    <div class="modal-body" style="text-align:center">
      <div style="width:62px;height:62px;margin:4px auto 16px;border-radius:50%;background:var(--theme-soft);display:flex;align-items:center;justify-content:center">
        <i class="ico i-user" style="font-size:30px;color:var(--theme)"></i>
      </div>
      需要登录才可以观看哦，如没有账号请注册！
    </div>
    <div class="modal-foot" style="justify-content:center">
      <a class="btn ghost" href="register.php">去注册</a>
      <button class="btn primary" type="button" onclick="closeModal('#loginTip')">去登录</button>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
