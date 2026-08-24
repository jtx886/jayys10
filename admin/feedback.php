<?php
/**
 * Jay影视 - 反馈管理（查看全部反馈 / 回复反馈 / 删除）
 */
require_once __DIR__ . '/includes/auth.php';

$ADMIN_PAGE = 'feedback';
$PAGE_TITLE = '反馈管理';
$msg = ''; $msgType = 'ok';

/* 管理员回复反馈 */
if (is_post() && post('action') === 'reply' && csrf_check()) {
    $fid = intval(post('fid'));
    $content = post('content');
    if ($content !== '' && db_one("SELECT id FROM feedbacks WHERE id=?", array($fid))) {
        db_exec("INSERT INTO feedback_replies (feedback_id,user_id,content) VALUES (?,?,?)", array($fid, $ADMIN['id'], $content));
        $msg = '回复已发布（前台将优先展示管理员回复）';
    }
}
/* 删除反馈 */
if (is_post() && post('action') === 'del' && csrf_check()) {
    $fid = intval(post('fid'));
    db_exec("DELETE FROM feedbacks WHERE id=?", array($fid));
    db_exec("DELETE FROM feedback_replies WHERE feedback_id=?", array($fid));
    db_exec("DELETE FROM feedback_likes WHERE feedback_id=?", array($fid));
    $msg = '反馈已删除';
}
/* 删除单条回复 */
if (is_post() && post('action') === 'del_reply' && csrf_check()) {
    db_exec("DELETE FROM feedback_replies WHERE id=?", array(intval(post('rid'))));
    $msg = '回复已删除';
}

$page = max(1, intval(get('page', 1)));
$pageSize = 12;
$total = intval(db_val("SELECT COUNT(*) FROM feedbacks"));
$pages = max(1, ceil($total / $pageSize));
$page = min($page, $pages);
$rows = db_all(
    "SELECT f.*, u.username, u.avatar,
            (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id=f.id) AS replies,
            (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id=f.id) AS likes
     FROM feedbacks f JOIN users u ON u.id=f.user_id
     ORDER BY f.id DESC LIMIT " . intval(($page - 1) * $pageSize) . ", {$pageSize}"
);

/* 各反馈的回复 */
$repliesMap = array();
if (!empty($rows)) {
    $ids = array();
    foreach ($rows as $r) $ids[] = intval($r['id']);
    $in = implode(',', $ids);
    $replies = db_all(
        "SELECT r.*, u.username, u.role FROM feedback_replies r JOIN users u ON u.id=r.user_id
         WHERE r.feedback_id IN ($in) ORDER BY (u.role='admin') DESC, r.created_at ASC"
    );
    foreach ($replies as $rp) $repliesMap[intval($rp['feedback_id'])][] = $rp;
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo h($msg); ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head"><i class="ico i-chat"></i>全部用户反馈（<?php echo $total; ?>）</div>
  <div class="panel-body" style="display:flex;flex-direction:column;gap:16px">

  <?php if (empty($rows)): ?>
    <div class="empty-tip">暂无反馈</div>
  <?php else: foreach ($rows as $f):
      $reps = isset($repliesMap[intval($f['id'])]) ? $repliesMap[intval($f['id'])] : array();
  ?>
    <div style="border:1px solid var(--line);border-radius:13px;padding:16px;background:var(--card-2)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
        <span style="font-weight:700"><?php echo h($f['username']); ?></span>
        <?php if ($f['is_public']): ?><span class="st ok">公开</span><?php else: ?><span class="st mut">私密</span><?php endif; ?>
        <span style="font-size:12px;color:var(--muted)"><i class="ico i-clock"></i> <?php echo fmt_date($f['created_at']); ?></span>
        <span style="font-size:12px;color:var(--muted)"><i class="ico i-heart"></i> <?php echo intval($f['likes']); ?> · <i class="ico i-chat"></i> <?php echo intval($f['replies']); ?></span>
        <form method="post" style="margin-left:auto" onsubmit="return confirm('删除该反馈及其全部回复？')">
          <input type="hidden" name="action" value="del"><input type="hidden" name="fid" value="<?php echo intval($f['id']); ?>">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <button class="mini-btn dan" type="submit"><i class="ico i-trash"></i>删除</button>
        </form>
      </div>
      <div style="font-weight:700;margin-bottom:5px"><?php echo h($f['title']); ?></div>
      <div style="font-size:13.5px;color:#aab6c2;white-space:pre-wrap;margin-bottom:12px"><?php echo h($f['content']); ?></div>

      <?php if (!empty($reps)): ?>
      <div style="border-top:1px dashed var(--line);padding-top:10px;margin-bottom:12px;display:flex;flex-direction:column;gap:8px">
        <?php foreach ($reps as $rp): ?>
        <div style="display:flex;gap:9px;align-items:flex-start;<?php echo $rp['role'] === 'admin' ? 'background:linear-gradient(90deg,var(--theme-soft),transparent);border-radius:8px;padding:7px 9px' : ''; ?>">
          <span style="font-size:12.5px;font-weight:700;flex:none"><?php echo h($rp['username']); ?><?php echo $rp['role'] === 'admin' ? '<span class="dev-badge" style="margin-left:6px"><i class="ico i-crown"></i>开发者</span>' : ''; ?></span>
          <span style="font-size:13px;color:#aab6c2;flex:1;white-space:pre-wrap"><?php echo h($rp['content']); ?></span>
          <form method="post" onsubmit="return confirm('删除该回复？')">
            <input type="hidden" name="action" value="del_reply"><input type="hidden" name="rid" value="<?php echo intval($rp['id']); ?>">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <button class="mini-btn dan" type="submit" style="padding:3px 9px;font-size:11px">删</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="post" style="display:flex;gap:10px">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="fid" value="<?php echo intval($f['id']); ?>">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <input type="text" name="content" class="input" placeholder="以管理员身份回复…" style="flex:1" required>
        <button class="btn primary sm" type="submit"><i class="ico i-send"></i>回复</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php
  if ($page > 1) echo '<a href="?page=' . ($page - 1) . '">上一页</a>';
  for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
      echo $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="?page=' . $i . '">' . $i . '</a>';
  }
  if ($page < $pages) echo '<a href="?page=' . ($page + 1) . '">下一页</a>';
  ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
