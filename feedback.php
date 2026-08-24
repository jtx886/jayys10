<?php
/**
 * Jay影视 - 反馈中心（公开列表 / 提交 / 点赞）
 * 回复排序与折叠逻辑见 feedback_detail.php
 */
require_once __DIR__ . '/includes/init.php';
$U = current_user();

$notice = '';
if (is_post() && post('action') === 'new_fb' && csrf_check()) {
    if (!$U) {
        redirect('login.php?msg=play&redirect=' . urlencode('feedback.php'));
    }
    $title = post('title');
    $content = post('content');
    $isPublic = post('is_public') === '1' ? 1 : 0;
    if (mb_strlen($title, 'UTF-8') < 2 || mb_strlen($title, 'UTF-8') > 50) {
        $notice = '反馈标题需为 2-50 个字';
    } elseif (mb_strlen($content, 'UTF-8') < 5) {
        $notice = '反馈内容至少 5 个字';
    } else {
        db_exec("INSERT INTO feedbacks (user_id,title,content,is_public) VALUES (?,?,?,?)", array($U['id'], $title, $content, $isPublic));
        redirect('feedback.php');
    }
}

$page = max(1, intval(get('page', 1)));
$pageSize = 10;
$total = intval(db_val("SELECT COUNT(*) FROM feedbacks WHERE is_public=1"));
$pages = max(1, ceil($total / $pageSize));
$page = min($page, $pages);
$list = db_all(
    "SELECT f.*, u.username, u.avatar, u.role,
            (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id=f.id) AS likes,
            (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id=f.id) AS replies
     FROM feedbacks f JOIN users u ON u.id=f.user_id
     WHERE f.is_public=1
     ORDER BY f.id DESC LIMIT " . intval(($page - 1) * $pageSize) . ", " . $pageSize
);
$myLiked = array();
if ($U) {
    foreach (db_all("SELECT feedback_id FROM feedback_likes WHERE user_id=?", array($U['id'])) as $r) $myLiked[] = intval($r['feedback_id']);
}

function initials($u) { return mb_substr($u, 0, 1, 'UTF-8'); }

$NAV_ACTIVE = 'feedback';
$PAGE_TITLE = '反馈中心';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head anim">
  <span class="s-ico" style="width:42px;height:42px;border-radius:12px;background:var(--theme-soft);color:var(--theme);display:flex;align-items:center;justify-content:center"><i class="ico i-chat" style="font-size:19px"></i></span>
  <h1>反馈中心</h1>
  <span class="count">共 <?php echo $total; ?> 条公开反馈</span>
  <div class="grow"></div>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:18px">
  <?php if ($notice): ?><div class="alert err"><?php echo h($notice); ?></div><?php endif; ?>

  <?php if ($U): ?>
  <!-- 提交反馈 -->
  <div class="form-card wide anim" style="margin:0">
    <h1 style="font-size:18px"><i class="ico i-edit" style="color:var(--theme)"></i> 提交反馈</h1>
    <p class="sub">遇到问题或建议欢迎告诉我们，公开反馈所有人可见</p>
    <form method="post">
      <input type="hidden" name="action" value="new_fb">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <div class="field-row">
        <div class="field">
          <label>标题</label>
          <input type="text" name="title" maxlength="50" placeholder="一句话描述你的问题或建议" required>
        </div>
        <div class="field">
          <label>可见性</label>
          <select name="is_public">
            <option value="1">公开反馈</option>
            <option value="0">仅管理员可见</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>详细内容</label>
        <textarea name="content" placeholder="请详细描述问题（至少5个字）" required></textarea>
      </div>
      <button class="btn-send" type="submit"><i class="ico i-send"></i>提交反馈</button>
    </form>
  </div>
  <?php else: ?>
  <div class="alert warn" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <i class="ico i-lock"></i>登录后可提交反馈、点赞与回复。
    <span class="grow" style="flex:1"></span>
    <a class="btn primary sm" href="login.php?redirect=feedback.php">去登录</a>
  </div>
  <?php endif; ?>

  <?php if (empty($list)): ?>
  <div class="empty-box"><i class="ico i-chat"></i><p>还没有公开反馈，来做第一个发言的人吧</p></div>
  <?php else: foreach ($list as $f): ?>
  <div class="fb-card anim">
    <div class="fb-head">
      <?php if (!empty($f['avatar'])): ?>
      <img class="u-avatar" src="<?php echo h($f['avatar']); ?>" alt="">
      <?php else: ?>
      <span class="u-avatar u-avatar-txt"><?php echo h(initials($f['username'])); ?></span>
      <?php endif; ?>
      <div class="fb-main">
        <div class="fb-meta">
          <span class="fb-author"><i class="ico i-user"></i><?php echo h($f['username']); ?><?php if ($f['role'] === 'admin'): ?><span class="dev-badge"><i class="ico i-crown"></i>开发者</span><?php endif; ?></span>
          <span><?php echo fmt_date($f['created_at']); ?></span>
        </div>
        <div class="fb-title"><a href="feedback_detail.php?id=<?php echo intval($f['id']); ?>"><?php echo h($f['title']); ?></a></div>
        <div class="fb-content"><?php echo h(mb_substr($f['content'], 0, 160, 'UTF-8')); ?><?php echo mb_strlen($f['content'], 'UTF-8') > 160 ? '…' : ''; ?></div>
        <div class="fb-foot">
          <button type="button" class="fb-act btn-like <?php echo in_array(intval($f['id']), $myLiked, true) ? 'liked' : ''; ?>" data-id="<?php echo intval($f['id']); ?>"><i class="ico i-thumb"></i><span><?php echo intval($f['likes']); ?></span></button>
          <a class="fb-act" href="feedback_detail.php?id=<?php echo intval($f['id']); ?>"><i class="ico i-chat"></i><?php echo intval($f['replies']); ?> 条回复</a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>"><i class="ico i-chev-l"></i></a><?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
      <?php if ($i === $page): ?><span class="cur"><?php echo $i; ?></span><?php else: ?><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?page=<?php echo $page + 1; ?>"><i class="ico i-chev-r"></i></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
