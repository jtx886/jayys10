<?php
/**
 * Jay影视 - 个人中心
 * 我的收藏（可删除）/ 观看历史（播放秒记录，可删除）/ 上传自定义头像
 */
require_once __DIR__ . '/includes/init.php';
$U = current_user();
if (!$U) redirect('login.php');

$msg = ''; $msgType = 'ok';
$tab = get('tab', 'fav');

/* ---------- 删除收藏 ---------- */
if (is_post() && post('action') === 'del_fav' && csrf_check()) {
    db_exec("DELETE FROM favorites WHERE id=? AND user_id=?", array(intval(post('id')), $U['id']));
    redirect('profile.php?tab=fav');
}
/* ---------- 删除观看记录 ---------- */
if (is_post() && post('action') === 'del_hist' && csrf_check()) {
    db_exec("DELETE FROM watch_history WHERE id=? AND user_id=?", array(intval(post('id')), $U['id']));
    redirect('profile.php?tab=hist');
}
/* ---------- 上传头像 ---------- */
if (is_post() && post('action') === 'avatar' && csrf_check()) {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $msg = '请选择要上传的头像图片'; $msgType = 'err';
    } else {
        $f = $_FILES['avatar'];
        $allow = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
        $info = @getimagesize($f['tmp_name']);
        $mime = $info ? $info['mime'] : '';
        if (!isset($allow[$mime])) { $msg = '仅支持 JPG / PNG / GIF / WEBP 图片'; $msgType = 'err'; }
        elseif ($f['size'] > 2 * 1024 * 1024) { $msg = '头像大小不能超过 2MB'; $msgType = 'err'; }
        else {
            $name = 'avatar_' . $U['id'] . '_' . time() . '.' . $allow[$mime];
            if (move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $name)) {
                db_exec("UPDATE users SET avatar=? WHERE id=?", array('uploads/' . $name, $U['id']));
                redirect('profile.php');
            } else { $msg = '头像保存失败，请检查 uploads 目录权限'; $msgType = 'err'; }
        }
    }
}

$favs = db_all("SELECT * FROM favorites WHERE user_id=? ORDER BY id DESC", array($U['id']));
$hists = db_all("SELECT * FROM watch_history WHERE user_id=? ORDER BY updated_at DESC", array($U['id']));

$PAGE_TITLE = '个人中心';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head anim">
  <h1>个人中心</h1>
</div>

<?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo h($msg); ?></div><?php endif; ?>

<div class="profile-grid">
  <aside class="profile-side anim">
    <div class="big-avatar">
      <?php if ($U['avatar']): ?>
      <img id="avatarPreview" src="<?php echo h($U['avatar']); ?>?v=<?php echo time(); ?>" alt="">
      <?php else: ?>
      <span class="u-avatar-txt" id="avatarPreview"><?php echo h(mb_substr($U['username'], 0, 1, 'UTF-8')); ?></span>
      <?php endif; ?>
    </div>
    <h2><?php echo h($U['username']); ?><?php if ($U['role'] === 'admin'): ?><span class="dev-badge"><i class="ico i-crown"></i>开发者</span><?php endif; ?></h2>
    <p class="p-email"><?php echo h($U['email']); ?></p>
    <form method="post" enctype="multipart/form-data" id="avatarForm">
      <input type="hidden" name="action" value="avatar">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
      <div class="avatar-upload" style="display:block;width:100%">
        <button class="btn ghost sm" type="button" style="width:100%" onclick="pickAvatar()"><i class="ico i-cam"></i>上传自定义头像</button>
        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="document.getElementById('avatarForm').submit()">
      </div>
    </form>
    <div class="p-stats">
      <div><b><?php echo count($favs); ?></b><span>收藏</span></div>
      <div><b><?php echo count($hists); ?></b><span>观看记录</span></div>
      <div><b><?php echo fmt_time(array_sum(array_map(function ($x) { return intval($x['position']); }, $hists))); ?></b><span>累计观看</span></div>
    </div>
  </aside>

  <div>
    <div class="tab-bar anim">
      <a href="profile.php?tab=fav" class="btn ghost sm" style="flex:1;justify-content:center;<?php echo $tab !== 'hist' ? 'background:linear-gradient(135deg,var(--theme),#ff5f54);border-color:transparent;color:#fff' : ''; ?>"><i class="ico i-heart"></i>我的收藏</a>
      <a href="profile.php?tab=hist" class="btn ghost sm" style="flex:1;justify-content:center;<?php echo $tab === 'hist' ? 'background:linear-gradient(135deg,var(--theme),#ff5f54);border-color:transparent;color:#fff' : ''; ?>"><i class="ico i-hist"></i>观看历史</a>
    </div>

  <?php if ($tab !== 'hist'): ?>
    <!-- ===== 我的收藏 ===== -->
    <?php if (empty($favs)): ?>
    <div class="empty-box"><i class="ico i-heart"></i><p>还没有收藏影视，去发现喜欢的作品吧</p><a class="btn primary" href="index.php">去逛逛</a></div>
    <?php else: foreach ($favs as $f): ?>
    <div class="list-item anim">
      <a class="li-poster" href="<?php echo media_link($f['media_type'], $f['tmdb_id']); ?>">
        <?php if ($f['poster']): ?><img data-src="<?php echo h($f['poster']); ?>" alt="">
        <?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#33404f"><i class="ico i-film"></i></div><?php endif; ?>
      </a>
      <div class="li-main">
        <div class="li-title"><?php echo h($f['title']); ?></div>
        <div class="li-sub">
          <span class="tag theme"><?php echo $f['media_type'] === 'tv' ? '剧集' : '电影'; ?></span>
          <span>收藏于 <?php echo fmt_date($f['created_at']); ?></span>
        </div>
      </div>
      <a class="btn primary sm" href="play.php?type=<?php echo h($f['media_type']); ?>&id=<?php echo intval($f['tmdb_id']); ?>&title=<?php echo urlencode($f['title']); ?>&ep=1"><i class="ico i-play invert"></i>播放</a>
      <form method="post" onsubmit="return confirm('确定删除该收藏吗？')">
        <input type="hidden" name="action" value="del_fav">
        <input type="hidden" name="id" value="<?php echo intval($f['id']); ?>">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <button class="icon-btn danger" type="submit" title="删除收藏"><i class="ico i-trash"></i></button>
      </form>
    </div>
    <?php endforeach; endif; ?>

  <?php else: ?>
    <!-- ===== 观看历史（保存播放秒记录） ===== -->
    <?php if (empty($hists)): ?>
    <div class="empty-box"><i class="ico i-hist"></i><p>暂无观看记录，快去看一部吧</p><a class="btn primary" href="index.php">去逛逛</a></div>
    <?php else: foreach ($hists as $h): ?>
    <div class="list-item anim">
      <a class="li-poster" href="<?php echo media_link($h['media_type'], $h['tmdb_id']); ?>">
        <?php if ($h['poster']): ?><img data-src="<?php echo h($h['poster']); ?>" alt="">
        <?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#33404f"><i class="ico i-film"></i></div><?php endif; ?>
      </a>
      <div class="li-main">
        <div class="li-title"><?php echo h($h['title']); ?></div>
        <div class="li-sub">
          <?php if ($h['media_type'] === 'tv'): ?><span class="tag theme">看到 S<?php echo intval($h['season']); ?>E<?php echo intval($h['episode']); ?></span><?php else: ?><span class="tag theme">电影</span><?php endif; ?>
          <span><i class="ico i-clock"></i> 已观看 <?php echo fmt_time($h['position']); ?></span>
          <span><?php echo fmt_date($h['updated_at']); ?></span>
        </div>
        <div class="progress-bar"><i style="width:<?php echo progress_pct($h['position'], $h['media_type'] === 'tv' ? 12 : 1); ?>%"></i></div>
      </div>
      <a class="btn primary sm" href="play.php?type=<?php echo h($h['media_type']); ?>&id=<?php echo intval($h['tmdb_id']); ?>&title=<?php echo urlencode($h['title']); ?>&season=<?php echo intval($h['season']); ?>&ep=<?php echo intval($h['episode']); ?>"><i class="ico i-play invert"></i>继续观看</a>
      <form method="post" onsubmit="return confirm('确定删除该观看记录吗？')">
        <input type="hidden" name="action" value="del_hist">
        <input type="hidden" name="id" value="<?php echo intval($h['id']); ?>">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <button class="icon-btn danger" type="submit" title="删除记录"><i class="ico i-trash"></i></button>
      </form>
    </div>
    <?php endforeach; endif; ?>
  <?php endif; ?>
  </div>
</div>

<script>
function pickAvatar(){document.getElementById('avatarInput').click();}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
