<?php
/**
 * Jay影视 - 后台登录（默认账号：杰同学 / 101113）
 */
require_once dirname(__DIR__) . '/includes/init.php';
$U = current_user();
if ($U && $U['role'] === 'admin') redirect('index.php');

$error = '';
if (is_post()) {
    $account = post('account');
    $password = post('password');
    $u = ($account !== '') ? db_one("SELECT * FROM users WHERE (username=? OR email=?) AND role='admin' LIMIT 1", array($account, $account)) : null;
    if (!$u || !password_verify($password, $u['password'])) {
        $error = '管理员账号或密码错误';
    } elseif (user_banned($u)) {
        $error = '该管理员账号已被封禁';
    } else {
        $_SESSION['uid'] = intval($u['id']);
        session_regenerate_id(true);
        redirect('index.php');
    }
}
$PAGE_TITLE = '后台登录';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录 - <?php echo h(setting('site_name', 'Jay影视')); ?></title>
<link rel="stylesheet" href="../assets/css/style.css?v=1.0">
<style>:root{--theme:<?php echo h(setting('theme_color', '#e50914')); ?>}</style>
</head>
<body>
<div class="form-card">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
    <span class="brand-mark"><i class="ico i-play invert"></i></span>
    <h1 style="margin:0">管理后台</h1>
  </div>
  <p class="sub">仅限管理员（默认账号：杰同学）登录</p>
  <?php if ($error): ?><div class="alert err"><?php echo h($error); ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <div class="field">
      <label>管理员账号</label>
      <input type="text" name="account" value="<?php echo h(post('account')); ?>" required>
    </div>
    <div class="field">
      <label>密码</label>
      <input type="password" name="password" required>
    </div>
    <button class="btn primary lg" type="submit" style="width:100%"><i class="ico i-lock"></i>登录后台</button>
  </form>
  <p style="margin-top:18px;text-align:center;font-size:13px"><a href="../index.php" style="color:var(--muted)">← 返回前台首页</a></p>
</div>
</body>
</html>
