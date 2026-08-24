<?php
/**
 * Jay影视 - 后台公共头部
 * 变量：$ADMIN_PAGE（当前页标识）、$PAGE_TITLE
 */
require_once __DIR__ . '/auth.php';
$ADMIN_PAGE = isset($ADMIN_PAGE) ? $ADMIN_PAGE : '';
$PAGE_TITLE = isset($PAGE_TITLE) ? $PAGE_TITLE : '管理后台';
$THEME = setting('theme_color', '#e50914');

$menus = array(
    'dash'     => array('link' => 'index.php',    'icon' => 'i-chart',    'name' => '仪表盘'),
    'users'    => array('link' => 'users.php',    'icon' => 'i-users',    'name' => '用户管理'),
    'sources'  => array('link' => 'sources.php',  'icon' => 'i-film',     'name' => '播放源管理'),
    'mail'     => array('link' => 'mail.php',     'icon' => 'i-mail',     'name' => '邮件推送'),
    'notice'   => array('link' => 'notice.php',   'icon' => 'i-bell',     'name' => '网站公告'),
    'settings' => array('link' => 'settings.php', 'icon' => 'i-sliders',  'name' => '网站设置'),
    'feedback' => array('link' => 'feedback.php', 'icon' => 'i-chat',     'name' => '反馈管理'),
);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h($PAGE_TITLE); ?> - <?php echo h(setting('site_name', 'Jay影视')); ?> 后台</title>
<link rel="stylesheet" href="../assets/css/style.css?v=1.0">
<link rel="stylesheet" href="../assets/css/admin.css?v=1.0">
<style>:root{--theme:<?php echo h($THEME); ?>;--theme-soft:rgba(229,9,20,.16)}</style>
</head>
<body class="admin-body">

<aside class="adm-side">
  <a class="adm-brand" href="index.php">
    <span class="brand-mark"><i class="ico i-play invert"></i></span>
    <span class="adm-brand-txt"><?php echo h(setting('site_name', 'Jay影视')); ?><small>管理后台</small></span>
  </a>
  <nav class="adm-nav">
    <?php foreach ($menus as $k => $m): ?>
    <a class="<?php echo $ADMIN_PAGE === $k ? 'on' : ''; ?>" href="<?php echo $m['link']; ?>">
      <i class="ico <?php echo $m['icon']; ?>"></i><span><?php echo $m['name']; ?></span>
      <?php if ($k === 'feedback'): ?>
        <?php $fbn = intval(db_val("SELECT COUNT(*) FROM feedbacks f WHERE NOT EXISTS (SELECT 1 FROM feedback_replies r WHERE r.feedback_id=f.id)")); ?>
        <?php if ($fbn > 0): ?><em class="adm-badge"><?php echo $fbn; ?></em><?php endif; ?>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="adm-side-foot">
    <a href="../index.php"><i class="ico i-home"></i>返回前台</a>
    <a href="../logout.php"><i class="ico i-exit"></i>退出登录</a>
  </div>
</aside>

<div class="adm-main">
  <header class="adm-topbar">
    <label class="adm-toggle" for="adm-side-toggle" onclick="document.querySelector('.adm-side').classList.toggle('open')"><span></span><span></span><span></span></label>
    <h1><?php echo h($PAGE_TITLE); ?></h1>
    <div class="adm-user">
      <span class="u-avatar u-avatar-txt"><?php echo h(mb_substr($ADMIN['username'], 0, 1, 'UTF-8')); ?></span>
      <span class="u-name"><?php echo h($ADMIN['username']); ?></span>
      <span class="dev-badge"><i class="ico i-crown"></i>开发者</span>
    </div>
  </header>
  <div class="adm-content">
