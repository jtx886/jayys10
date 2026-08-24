<?php
/**
 * Jay影视 - 网站公告
 * 自定义弹窗公告内容；公告仅首页弹窗展示；
 * 用户勾选「不再提示」写入 Cookie；发布新公告后 Cookie 失效重新弹窗；其他页面完全不展示。
 */
require_once __DIR__ . '/includes/auth.php';

$ADMIN_PAGE = 'notice';
$PAGE_TITLE = '网站公告';
$msg = ''; $msgType = 'ok';

if (is_post() && post('action') === 'add' && csrf_check()) {
    $content = post('content');
    if (mb_strlen($content, 'UTF-8') < 2) { $msg = '公告内容至少 2 个字'; $msgType = 'err'; }
    else {
        db_exec("INSERT INTO notices (content) VALUES (?)", array($content));
        $msg = '公告已发布，新公告将在首页重新弹窗（旧「不再提示」Cookie 自动失效）';
    }
}
if (is_post() && post('action') === 'del' && csrf_check()) {
    db_exec("DELETE FROM notices WHERE id=?", array(intval(post('id'))));
    $msg = '公告已删除';
}

$rows = db_all("SELECT * FROM notices ORDER BY id DESC");
require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo h($msg); ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head"><i class="ico i-bell"></i>发布公告</div>
  <div class="panel-body adm-form">
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <div class="field">
        <label>公告内容（支持换行，仅在网站首页弹窗显示）</label>
        <textarea name="content" style="min-height:150px" placeholder="例如：欢迎来到 Jay影视，本周新增海量4K片源！" required></textarea>
      </div>
      <button class="btn primary" type="submit"><i class="ico i-send"></i>发布公告</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><i class="ico i-clock"></i>公告历史（<?php echo count($rows); ?>）· 置顶为当前生效公告</div>
  <div class="panel-body no-pad">
    <table class="adm-table">
      <thead><tr><th style="width:60px">#</th><th>内容</th><th>发布时间</th><th>状态</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="empty-tip">暂无公告</td></tr>
      <?php else: foreach ($rows as $i => $n): ?>
        <tr>
          <td><?php echo intval($n['id']); ?></td>
          <td style="max-width:520px;white-space:pre-wrap"><?php echo h(mb_substr($n['content'], 0, 120, 'UTF-8')); ?></td>
          <td><?php echo fmt_date($n['created_at']); ?></td>
          <td><?php echo $i === 0 ? '<span class="st def">生效中</span>' : '<span class="st mut">历史</span>'; ?></td>
          <td>
            <form method="post" onsubmit="return confirm('确定删除该公告吗？')">
              <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?php echo intval($n['id']); ?>">
              <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
              <button class="mini-btn dan" type="submit"><i class="ico i-trash"></i>删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
