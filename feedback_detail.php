<?php
/**
 * Jay影视 - 反馈详情
 * 回复排序：提问者内容 → 管理员回复（优先）→ 普通用户回复
 * 单条反馈回复总数大于 3 条自动折叠，显示展开按钮
 */
require_once __DIR__ . '/includes/init.php';
$U = current_user();

$fidRaw = get('id');
if ($fidRaw === '') { $fidRaw = post('id'); }
$fid = intval($fidRaw);
$fb = db_one(
    "SELECT f.*, u.username, u.avatar, u.role,
            (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id=f.id) AS likes
     FROM feedbacks f JOIN users u ON u.id=f.user_id
     WHERE f.id=?", array($fid)
);
if (!$fb) redirect('feedback.php');

/* 非公开反馈：仅提问者本人与管理员可见 */
if (!$fb['is_public'] && (!$U || (intval($U['id']) !== intval($fb['user_id']) && $U['role'] !== 'admin'))) {
    redirect('feedback.php');
}

$notice = '';
if (is_post() && post('action') === 'reply' && csrf_check()) {
    if (!$U) redirect('login.php?redirect=' . urlencode('feedback_detail.php?id=' . $fid));
    $content = post('content');
    if (mb_strlen($content, 'UTF-8') < 1) {
        $notice = '回复内容不能为空';
    } else {
        db_exec("INSERT INTO feedback_replies (feedback_id,user_id,content) VALUES (?,?,?)", array($fid, $U['id'], $content));
        redirect('feedback_detail.php?id=' . $fid);
    }
}

/* 回复排序：管理员回复在前（各自按时间正序），普通用户回复在后 */
$replies = db_all(
    "SELECT r.*, u.username, u.avatar, u.role
     FROM feedback_replies r JOIN users u ON u.id=r.user_id
     WHERE r.feedback_id=?
     ORDER BY (u.role='admin') DESC, r.created_at ASC",
    array($fid)
);
$liked = $U && db_one("SELECT id FROM feedback_likes WHERE feedback_id=? AND user_id=?", array($fid, $U['id']));

$PAGE_TITLE = '反馈详情';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head anim">
  <a class="btn ghost sm" href="feedback.php"><i class="ico i-chev-l"></i>返回列表</a>
  <h1 style="font-size:20px">反馈详情</h1>
</div>

<?php if ($notice): ?><div class="alert err"><?php echo h($notice); ?></div><?php endif; ?>

<div class="fb-card anim">
  <div class="fb-head">
    <?php if (!empty($fb['avatar'])): ?>
    <img class="u-avatar" src="<?php echo h($fb['avatar']); ?>" alt="">
    <?php else: ?>
    <span class="u-avatar u-avatar-txt"><?php echo h(mb_substr($fb['username'], 0, 1, 'UTF-8')); ?></span>
    <?php endif; ?>
    <div class="fb-main">
      <div class="fb-meta">
        <span class="fb-author"><i class="ico i-user"></i><?php echo h($fb['username']); ?><?php if ($fb['role'] === 'admin'): ?><span class="dev-badge"><i class="ico i-crown"></i>开发者</span><?php endif; ?></span>
        <span><i class="ico i-clock"></i><?php echo fmt_date($fb['created_at']); ?></span>
        <?php if (!$fb['is_public']): ?><span class="tag theme">仅管理员可见</span><?php endif; ?>
      </div>
      <div class="fb-title"><?php echo h($fb['title']); ?></div>
      <div class="fb-content"><?php echo h($fb['content']); ?></div>
      <div class="fb-foot">
        <button type="button" class="fb-act btn-like <?php echo $liked ? 'liked' : ''; ?>" data-id="<?php echo intval($fb['id']); ?>"><i class="ico i-heart"></i><span><?php echo intval($fb['likes']); ?></span></button>
        <span class="fb-act" style="cursor:default"><i class="ico i-chat"></i><?php echo count($replies); ?> 条回复</span>
      </div>

      <?php if (!empty($replies)): ?>
      <div class="replies-box">
        <?php foreach ($replies as $r): ?>
        <div class="reply-item <?php echo $r['role'] === 'admin' ? 'admin-reply' : ''; ?>">
          <?php if (!empty($r['avatar'])): ?>
          <img class="u-avatar" src="<?php echo h($r['avatar']); ?>" alt="">
          <?php else: ?>
          <span class="u-avatar u-avatar-txt"><?php echo h(mb_substr($r['username'], 0, 1, 'UTF-8')); ?></span>
          <?php endif; ?>
          <div class="r-main">
            <div class="r-head">
              <span class="r-name"><?php echo h($r['username']); ?></span>
              <?php if ($r['role'] === 'admin'): ?><span class="dev-badge"><i class="ico i-crown"></i>开发者</span><?php endif; ?>
              <span><?php echo fmt_date($r['created_at']); ?></span>
            </div>
            <div class="r-content"><?php echo h($r['content']); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($replies) > 3): ?>
        <button type="button" class="replies-toggle"><i class="ico i-chev-d" style="transform:rotate(45deg)"></i><span>展开全部<?php echo count($replies); ?>条回复</span></button>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($U): ?>
      <form method="post" class="reply-form">
        <input type="hidden" name="id" value="<?php echo $fid; ?>">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <textarea name="content" class="input" placeholder="友善回复，文明发言…" required></textarea>
        <button class="btn primary" type="submit" style="align-self:flex-end"><i class="ico i-send"></i>回复</button>
      </form>
      <?php else: ?>
      <div class="alert warn" style="margin-top:14px;margin-bottom:0">登录后即可参与回复与点赞 <a href="login.php?redirect=<?php echo urlencode('feedback_detail.php?id=' . $fid); ?>" style="color:var(--theme);font-weight:700">去登录</a></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
