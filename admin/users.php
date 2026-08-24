<?php
/**
 * Jay影视 - 用户管理
 * 封禁用户（自定义封禁开始/解除时间 + 原因），封禁时自动 SMTP 邮件通知
 */
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/smtp.php';

$ADMIN_PAGE = 'users';
$PAGE_TITLE = '用户管理';
$msg = ''; $msgType = 'ok';

/* ---------- 封禁 ---------- */
if (is_post() && post('action') === 'ban' && csrf_check()) {
    $uid = intval(post('uid'));
    $reason = post('reason');
    $start = post('start_at');
    $until = post('until_at');
    $target = db_one("SELECT * FROM users WHERE id=?", array($uid));
    if (!$target) {
        $msg = '用户不存在'; $msgType = 'err';
    } elseif ($target['role'] === 'admin') {
        $msg = '不能封禁管理员账号'; $msgType = 'err';
    } elseif ($reason === '') {
        $msg = '请填写封禁原因'; $msgType = 'err';
    } elseif ($until !== '' && $start !== '' && strtotime($until) <= strtotime($start)) {
        $msg = '解除时间必须晚于封禁开始时间'; $msgType = 'err';
    } else {
        $startAt = $start !== '' ? date('Y-m-d H:i:s', strtotime($start)) : date('Y-m-d H:i:s');
        $untilAt = $until !== '' ? date('Y-m-d H:i:s', strtotime($until)) : null;
        db_exec("UPDATE users SET status=1, ban_reason=?, banned_at=?, ban_until=? WHERE id=?",
            array($reason, $startAt, $untilAt, $uid));
        /* 自动发送封禁通知邮件 */
        $sent = send_mail($target['email'], $target['username'], '【Jay影视】账号封禁通知',
            mail_template_ban($target['username'], $reason, $startAt, $untilAt));
        $msg = '已封禁用户 ' . $target['username'] . ($sent ? '，封禁通知邮件已发送' : '（邮件发送失败，已记录日志）');
        $msgType = $sent ? 'ok' : 'warn';
    }
}

/* ---------- 解封 ---------- */
if (is_post() && post('action') === 'unban' && csrf_check()) {
    $uid = intval(post('uid'));
    $target = db_one("SELECT * FROM users WHERE id=?", array($uid));
    if ($target) {
        db_exec("UPDATE users SET status=0, ban_reason='', banned_at=NULL, ban_until=NULL WHERE id=?", array($uid));
        $msg = '已解除用户 ' . $target['username'] . ' 的封禁';
    }
}

/* ---------- 列表 ---------- */
$kw = get('kw');
$page = max(1, intval(get('page', 1)));
$pageSize = 15;
$where = '';
$params = array();
if ($kw !== '') {
    $where = "WHERE username LIKE ? OR email LIKE ?";
    $params = array("%{$kw}%", "%{$kw}%");
}
$total = intval(db_val("SELECT COUNT(*) FROM users {$where}", $params));
$pages = max(1, ceil($total / $pageSize));
$page = min($page, $pages);
$rows = db_all("SELECT * FROM users {$where} ORDER BY id DESC LIMIT " . intval(($page - 1) * $pageSize) . ", {$pageSize}", $params);

$banTarget = null;
if (get('ban') !== '') {
    $banTarget = db_one("SELECT * FROM users WHERE id=?", array(intval(get('ban'))));
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo h($msg); ?></div><?php endif; ?>

<?php if ($banTarget): ?>
<!-- 封禁表单 -->
<div class="panel">
  <div class="panel-head"><i class="ico i-ban"></i>封禁用户：<?php echo h($banTarget['username']); ?>（<?php echo h($banTarget['email']); ?>）</div>
  <div class="panel-body adm-form">
    <form method="post">
      <input type="hidden" name="action" value="ban">
      <input type="hidden" name="uid" value="<?php echo intval($banTarget['id']); ?>">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <div class="grid-2">
        <div class="field">
          <label>封禁开始时间（默认当前时间）</label>
          <input type="datetime-local" name="start_at" value="<?php echo date('Y-m-d\TH:i'); ?>">
        </div>
        <div class="field">
          <label>解除时间（留空 = 永久封禁）</label>
          <input type="datetime-local" name="until_at">
        </div>
      </div>
      <div class="field">
        <label>封禁原因（将写入邮件通知用户）</label>
        <textarea name="reason" placeholder="请输入封禁原因…" required></textarea>
      </div>
      <div style="display:flex;gap:12px">
        <button class="btn danger" type="submit"><i class="ico i-ban"></i>确认封禁并发送通知邮件</button>
        <a class="btn ghost" href="users.php">取消</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><i class="ico i-users"></i>用户列表（<?php echo $total; ?>）
    <div class="ph-actions">
      <form method="get" class="filter-bar" style="margin:0">
        <input type="text" name="kw" value="<?php echo h($kw); ?>" placeholder="搜索用户名/邮箱">
        <button class="mini-btn pri" type="submit"><i class="ico i-search"></i>搜索</button>
      </form>
    </div>
  </div>
  <div class="panel-body no-pad">
    <table class="adm-table">
      <thead><tr><th>用户</th><th>邮箱</th><th>角色</th><th>状态</th><th>封禁信息</th><th>注册时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="empty-tip">未找到用户</td></tr>
      <?php else: foreach ($rows as $u): $banned = user_banned($u); ?>
        <tr>
          <td style="display:flex;align-items:center;gap:9px">
            <?php if (!empty($u['avatar'])): ?><img class="u-avatar" src="../<?php echo h($u['avatar']); ?>" alt="">
            <?php else: ?><span class="u-avatar u-avatar-txt"><?php echo h(mb_substr($u['username'], 0, 1, 'UTF-8')); ?></span><?php endif; ?>
            <?php echo h($u['username']); ?>
          </td>
          <td><?php echo h($u['email']); ?></td>
          <td><?php echo $u['role'] === 'admin' ? '<span class="dev-badge"><i class="ico i-crown"></i>开发者</span>' : '<span class="st mut">用户</span>'; ?></td>
          <td><?php echo $banned ? '<span class="st ban">封禁中</span>' : '<span class="st ok">正常</span>'; ?></td>
          <td style="max-width:260px;font-size:12px;color:var(--muted)">
            <?php if ($banned): ?>
              <?php echo h($u['ban_reason'] ?: '未填写原因'); ?><br>
              <?php echo fmt_date($u['banned_at']); ?> ~ <?php echo $u['ban_until'] ? fmt_date($u['ban_until']) : '永久'; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><?php echo fmt_date($u['created_at']); ?></td>
          <td>
            <div class="td-actions">
              <?php if ($u['role'] !== 'admin'): ?>
                <?php if ($banned): ?>
                <form method="post" onsubmit="return confirm('确定解除封禁该用户吗？')">
                  <input type="hidden" name="action" value="unban"><input type="hidden" name="uid" value="<?php echo intval($u['id']); ?>">
                  <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                  <button class="mini-btn suc" type="submit"><i class="ico i-check"></i>解封</button>
                </form>
                <?php else: ?>
                <a class="mini-btn dan" href="users.php?ban=<?php echo intval($u['id']); ?>&kw=<?php echo h($kw); ?>&page=<?php echo $page; ?>"><i class="ico i-ban"></i>封禁</a>
                <?php endif; ?>
              <?php else: ?>
                <span class="st mut" style="cursor:default">管理员</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php
  $qs = $kw !== '' ? '&kw=' . urlencode($kw) : '';
  if ($page > 1) echo '<a href="?page=' . ($page - 1) . $qs . '">上一页</a>';
  for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
      echo $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="?page=' . $i . $qs . '">' . $i . '</a>';
  }
  if ($page < $pages) echo '<a href="?page=' . ($page + 1) . $qs . '">下一页</a>';
  ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
