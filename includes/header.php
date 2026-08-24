<?php
/**
 * Jay影视 - 公共头部
 * 变量：$PAGE_TITLE（页面标题）、$IS_HOME（首页标识，控制公告弹窗）
 */
if (!defined('APP_ROOT')) { require_once dirname(__DIR__) . '/includes/init.php'; }
$U = current_user();
$SITE_NAME = setting('site_name', 'Jay影视');
$THEME = setting('theme_color', '#e50914');
$PAGE_TITLE = isset($PAGE_TITLE) ? $PAGE_TITLE : $SITE_NAME;
$IS_HOME = isset($IS_HOME) && $IS_HOME;
$NAV_ACTIVE = isset($NAV_ACTIVE) ? $NAV_ACTIVE : '';
function nav_cls($k, $cur) { return $cur === $k ? 'nav-link active' : 'nav-link'; }
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h($PAGE_TITLE); ?> - <?php echo h($SITE_NAME); ?></title>
<meta name="description" content="<?php echo h($SITE_NAME); ?> - 在线高清影视，电影、剧集、动漫、综艺一站观看">
<link rel="stylesheet" href="assets/css/style.css?v=1.0">
<style>:root{--theme:<?php echo h($THEME); ?>;--theme-soft:rgba(229,9,20,.16)}</style>
<style>
/* 主题色派生（RGBA 混合，避免后端计算） */
:root{--theme-glow:color-mix(in srgb,var(--theme) 18%,transparent)}
@supports not (color:color-mix(in srgb,red 10%,blue)){:root{--theme-glow:rgba(229,9,20,.16)}}
</style>
</head>
<body>

<!-- ===== 顶部导航 ===== -->
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark"><i class="ico i-play invert"></i></span>
      <span class="brand-name"><?php echo h($SITE_NAME); ?></span>
    </a>

    <input type="checkbox" id="nav-toggle" class="nav-toggle">

    <nav class="main-nav">
      <a class="<?php echo nav_cls('home', $NAV_ACTIVE); ?>" href="index.php"><i class="ico i-home"></i><span>首页</span></a>
      <a class="<?php echo nav_cls('movie', $NAV_ACTIVE); ?>" href="category.php?cat=movie"><i class="ico i-film"></i><span>电影</span></a>
      <a class="<?php echo nav_cls('tv', $NAV_ACTIVE); ?>" href="category.php?cat=tv"><i class="ico i-tv"></i><span>剧集</span></a>
      <a class="<?php echo nav_cls('anime', $NAV_ACTIVE); ?>" href="category.php?cat=anime"><i class="ico i-spark"></i><span>动漫</span></a>
      <a class="<?php echo nav_cls('variety', $NAV_ACTIVE); ?>" href="category.php?cat=variety"><i class="ico i-mic"></i><span>综艺</span></a>
      <a class="<?php echo nav_cls('feedback', $NAV_ACTIVE); ?>" href="feedback.php"><i class="ico i-chat"></i><span>反馈</span></a>
    </nav>

    <div class="header-right">
      <form class="search-box" action="search.php" method="get">
        <i class="ico i-search"></i>
        <input type="text" name="q" placeholder="搜索影视 / 剧集 / 综艺" value="<?php echo h(get('q')); ?>">
      </form>

      <?php if ($U): ?>
      <div class="user-menu-wrap">
        <label class="user-btn" for="user-drop">
          <?php if ($U['avatar']): ?>
            <img class="u-avatar" src="<?php echo h($U['avatar']); ?>" alt="">
          <?php else: ?>
            <span class="u-avatar u-avatar-txt"><?php echo h(mb_substr($U['username'], 0, 1, 'UTF-8')); ?></span>
          <?php endif; ?>
          <span class="u-name"><?php echo h($U['username']); ?></span>
          <?php if ($U['role'] === 'admin'): ?><span class="dev-badge"><i class="ico i-crown"></i>开发者</span><?php endif; ?>
          <i class="ico i-caret"></i>
        </label>
        <input type="checkbox" id="user-drop" class="user-drop-check">
        <div class="user-drop">
          <a href="profile.php"><i class="ico i-user"></i>个人中心</a>
          <?php if ($U['role'] === 'admin'): ?><a href="admin/index.php"><i class="ico i-chart"></i>管理后台</a><?php endif; ?>
          <a href="logout.php"><i class="ico i-exit"></i>退出登录</a>
        </div>
      </div>
      <?php else: ?>
      <div class="auth-btns">
        <a class="btn ghost sm" href="login.php">登录</a>
        <a class="btn primary sm" href="register.php">注册</a>
      </div>
      <?php endif; ?>
    </div>

    <label for="nav-toggle" class="nav-burger"><span></span><span></span><span></span></label>
  </div>
</header>

<main class="page-main">

<?php
/* ===== 公告弹窗：仅首页展示 ===== */
if ($IS_HOME) {
    $notice = db_one("SELECT * FROM notices ORDER BY id DESC LIMIT 1");
    if ($notice && (!isset($_COOKIE['notice_seen']) || intval($_COOKIE['notice_seen']) !== intval($notice['id']))) {
?>
<div class="modal-mask" id="noticeModal" style="display:flex" data-nid="<?php echo intval($notice['id']); ?>">
  <div class="modal notice-modal">
    <div class="modal-head">
      <i class="ico i-bell"></i><span>网站公告</span>
      <button class="modal-close" type="button" onclick="closeNotice(false)"><i class="ico i-close"></i></button>
    </div>
    <div class="modal-body notice-content"><?php echo nl2br(h($notice['content'])); ?></div>
    <div class="modal-foot notice-foot">
      <label class="chk"><input type="checkbox" id="noticeNoMore"><span class="chk-box"><i class="ico i-check"></i></span>不再提示</label>
      <button class="btn primary" type="button" onclick="closeNotice(true)">我知道了</button>
    </div>
  </div>
</div>
<?php
    }
}
?>
