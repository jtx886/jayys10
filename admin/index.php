<?php
/**
 * Jay影视 - 后台仪表盘
 * 最新注册用户 / 最新反馈 / 观看历史模块 / 用户收藏模块（支持筛选指定用户）
 */
require_once __DIR__ . '/includes/auth.php';

$ADMIN_PAGE = 'dash';
$PAGE_TITLE = '仪表盘';

$statUsers    = intval(db_val("SELECT COUNT(*) FROM users"));
$statFeedback = intval(db_val("SELECT COUNT(*) FROM feedbacks"));
$statFav      = intval(db_val("SELECT COUNT(*) FROM favorites"));
$statHist     = intval(db_val("SELECT COUNT(*) FROM watch_history"));
$statViews    = intval(db_val("SELECT IFNULL(SUM(position),0) FROM watch_history"));

$latestUsers = db_all("SELECT * FROM users ORDER BY id DESC LIMIT 6");
$latestFb    = db_all(
    "SELECT f.*, u.username FROM feedbacks f JOIN users u ON u.id=f.user_id ORDER BY f.id DESC LIMIT 6"
);

/* 观看历史 / 用户收藏 模块：支持筛选指定用户 */
$allUsers = db_all("SELECT id,username FROM users ORDER BY id ASC");
$fu = intval(get('fu', 0));
$fv = intval(get('fv', 0));
$histWhere = $fu > 0 ? "WHERE h.user_id=" . $fu : '';
$favWhere  = $fv > 0 ? "WHERE v.user_id=" . $fv : '';
$histRows = db_all(
    "SELECT h.*, u.username FROM watch_history h JOIN users u ON u.id=h.user_id {$histWhere} ORDER BY h.updated_at DESC LIMIT 10"
);
$favRows = db_all(
    "SELECT v.*, u.username FROM favorites v JOIN users u ON u.id=v.user_id {$favWhere} ORDER BY v.id DESC LIMIT 10"
);

require __DIR__ . '/includes/header.php';
?>

<div class="stat-cards">
  <div class="stat-card"><span class="s-ico"><i class="ico i-users"></i></span><div><b><?php echo $statUsers; ?></b><span>注册用户</span></div></div>
  <div class="stat-card"><span class="s-ico"><i class="ico i-heart"></i></span><div><b><?php echo $statFav; ?></b><span>用户收藏</span></div></div>
  <div class="stat-card"><span class="s-ico"><i class="ico i-hist"></i></span><div><b><?php echo $statHist; ?></b><span>观看记录</span></div></div>
  <div class="stat-card"><span class="s-ico"><i class="ico i-clock"></i></span><div><b><?php echo fmt_time($statViews); ?></b><span>累计观看时长</span></div></div>
  <div class="stat-card"><span class="s-ico"><i class="ico i-chat"></i></span><div><b><?php echo $statFeedback; ?></b><span>用户反馈</span></div></div>
</div>

<div class="panel">
  <div class="panel-head"><i class="ico i-user"></i>最新注册用户
    <div class="ph-actions"><a class="mini-btn" href="users.php">用户管理 <i class="ico i-arrow"></i></a></div>
  </div>
  <div class="panel-body no-pad">
    <table class="adm-table">
      <thead><tr><th>用户</th><th>邮箱</th><th>状态</th><th>注册时间</th></tr></thead>
      <tbody>
      <?php if (empty($latestUsers)): ?>
        <tr><td colspan="4" class="empty-tip">暂无用户</td></tr>
      <?php else: foreach ($latestUsers as $u): ?>
        <tr>
          <td style="display:flex;align-items:center;gap:9px">
            <?php if (!empty($u['avatar'])): ?><img class="u-avatar" src="../<?php echo h($u['avatar']); ?>" alt="">
            <?php else: ?><span class="u-avatar u-avatar-txt"><?php echo h(mb_substr($u['username'], 0, 1, 'UTF-8')); ?></span><?php endif; ?>
            <?php echo h($u['username']); ?>
            <?php if ($u['role'] === 'admin'): ?><span class="dev-badge"><i class="ico i-crown"></i>开发者</span><?php endif; ?>
          </td>
          <td><?php echo h($u['email']); ?></td>
          <td><?php echo user_banned($u) ? '<span class="st ban">封禁中</span>' : '<span class="st ok">正常</span>'; ?></td>
          <td><?php echo fmt_date($u['created_at']); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><i class="ico i-chat"></i>最新反馈
    <div class="ph-actions"><a class="mini-btn" href="feedback.php">反馈管理 <i class="ico i-arrow"></i></a></div>
  </div>
  <div class="panel-body no-pad">
    <table class="adm-table">
      <thead><tr><th>标题</th><th>提交者</th><th>可见性</th><th>时间</th></tr></thead>
      <tbody>
      <?php if (empty($latestFb)): ?>
        <tr><td colspan="4" class="empty-tip">暂无反馈</td></tr>
      <?php else: foreach ($latestFb as $f): ?>
        <tr>
          <td><a href="feedback.php" style="color:var(--theme)"><?php echo h(mb_substr($f['title'], 0, 30, 'UTF-8')); ?></a></td>
          <td><?php echo h($f['username']); ?></td>
          <td><?php echo $f['is_public'] ? '<span class="st ok">公开</span>' : '<span class="st mut">私密</span>'; ?></td>
          <td><?php echo fmt_date($f['created_at']); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px" class="dash-two">
  <!-- 观看历史模块（可筛选用户） -->
  <div class="panel">
    <div class="panel-head"><i class="ico i-hist"></i>观看历史
      <div class="ph-actions">
        <form method="get" style="display:flex;gap:8px">
          <select name="fu" onchange="this.form.submit()">
            <option value="0">全部用户</option>
            <?php foreach ($allUsers as $au): ?>
            <option value="<?php echo intval($au['id']); ?>" <?php echo $fu === intval($au['id']) ? 'selected' : ''; ?>><?php echo h($au['username']); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
    <div class="panel-body no-pad">
      <table class="adm-table">
        <thead><tr><th>用户</th><th>影视</th><th>进度</th><th>观看时长</th></tr></thead>
        <tbody>
        <?php if (empty($histRows)): ?>
          <tr><td colspan="4" class="empty-tip">暂无观看记录</td></tr>
        <?php else: foreach ($histRows as $h): ?>
          <tr>
            <td><?php echo h($h['username']); ?></td>
            <td><?php echo h(mb_substr($h['title'], 0, 14, 'UTF-8')); ?><?php echo $h['media_type'] === 'tv' ? ' S' . intval($h['season']) . 'E' . intval($h['episode']) : ''; ?></td>
            <td><div class="progress-bar" style="margin:0;max-width:90px"><i style="width:<?php echo progress_pct($h['position'], $h['media_type'] === 'tv' ? 12 : 1); ?>%"></i></div></td>
            <td><?php echo fmt_time($h['position']); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 用户收藏模块（可筛选用户） -->
  <div class="panel">
    <div class="panel-head"><i class="ico i-heart"></i>用户收藏
      <div class="ph-actions">
        <form method="get" style="display:flex;gap:8px">
          <select name="fv" onchange="this.form.submit()">
            <option value="0">全部用户</option>
            <?php foreach ($allUsers as $au): ?>
            <option value="<?php echo intval($au['id']); ?>" <?php echo $fv === intval($au['id']) ? 'selected' : ''; ?>><?php echo h($au['username']); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
    <div class="panel-body no-pad">
      <table class="adm-table">
        <thead><tr><th>用户</th><th>影视</th><th>类型</th><th>收藏时间</th></tr></thead>
        <tbody>
        <?php if (empty($favRows)): ?>
          <tr><td colspan="4" class="empty-tip">暂无收藏记录</td></tr>
        <?php else: foreach ($favRows as $v): ?>
          <tr>
            <td><?php echo h($v['username']); ?></td>
            <td><?php echo h(mb_substr($v['title'], 0, 14, 'UTF-8')); ?></td>
            <td><?php echo $v['media_type'] === 'tv' ? '<span class="st def">剧集</span>' : '<span class="st warn">电影</span>'; ?></td>
            <td><?php echo fmt_date($v['created_at']); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
@media (max-width:1100px){.dash-two{grid-template-columns:1fr!important}}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
