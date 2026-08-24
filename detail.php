<?php
/**
 * Jay影视 - 影视详情页
 * - TMDB 元数据 + 演职员
 * - 剧集/动漫支持季切换（自动拉取本季简介/评分/年份/演员/单集封面）
 * - 海外影视显示音轨切换（普通话/原版），国产隐藏
 * - 收藏（登录后）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/tmdb.php';
require_once __DIR__ . '/includes/source_api.php';

$type = get('type') === 'tv' ? 'tv' : 'movie';
$id = intval(get('id'));
if ($id <= 0) redirect('index.php');

$detail = tmdb_detail($type, $id);
if (!$detail) {
    $PAGE_TITLE = '未找到';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-box"><i class="ico i-info"></i><p>影视信息加载失败（TMDB 数据暂不可用）</p><a class="btn ghost" href="index.php">返回首页</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isTV   = ($type === 'tv');
$title  = $isTV && isset($detail['name']) ? $detail['name'] : (isset($detail['title']) ? $detail['title'] : '');
$orgLang = isset($detail['original_language']) ? $detail['original_language'] : 'en';
$isZh   = in_array($orgLang, array('zh', 'cn'), true); /* 国产影视 */
$overview = isset($detail['overview']) && $detail['overview'] !== '' ? $detail['overview'] : '暂无简介';

if ($isTV) {
    $dateField = isset($detail['first_air_date']) ? $detail['first_air_date'] : '';
    $seasons = isset($detail['seasons']) ? $detail['seasons'] : array();
    $validSeasons = array();
    foreach ($seasons as $s) {
        if (isset($s['season_number']) && intval($s['season_number']) >= 1 && intval($s['episode_count']) > 0) $validSeasons[] = $s;
    }
    $season = max(1, intval(get('season', 1)));
    $seasonData = tmdb_season($id, $season);
    $seasonInfo = array(
        'overview' => $overview,
        'rate'     => isset($detail['vote_average']) ? $detail['vote_average'] : 0,
        'year'     => $dateField ? substr($dateField, 0, 4) : '',
        'cast'     => isset($detail['credits']['cast']) ? $detail['credits']['cast'] : array(),
        'episodes' => array(),
    );
    if ($seasonData) {
        if (!empty($seasonData['overview'])) $seasonInfo['overview'] = $seasonData['overview'];
        if (!empty($seasonData['vote_average'])) $seasonInfo['rate'] = $seasonData['vote_average'];
        if (!empty($seasonData['air_date'])) $seasonInfo['year'] = substr($seasonData['air_date'], 0, 4);
        if (!empty($seasonData['credits']['cast'])) $seasonInfo['cast'] = $seasonData['credits']['cast'];
        $seasonInfo['episodes'] = isset($seasonData['episodes']) ? $seasonData['episodes'] : array();
    }
    $epCount = count($seasonInfo['episodes']);
    if ($epCount === 0) {
        foreach ($validSeasons as $vs) { if (intval($vs['season_number']) === $season) { $epCount = intval($vs['episode_count']); break; } }
    }
} else {
    $dateField = isset($detail['release_date']) ? $detail['release_date'] : '';
    $runtime = isset($detail['runtime']) ? $detail['runtime'] : 0;
    $epCount = 1;
}

$backdrop = !empty($detail['backdrop_path']) ? tmdb_img($detail['backdrop_path'], 'w1280') : '';
$poster   = !empty($detail['poster_path']) ? tmdb_img($detail['poster_path'], 'w500') : '';
$genres   = isset($detail['genres']) ? $detail['genres'] : array();
$rate     = isset($detail['vote_average']) ? floatval($detail['vote_average']) : 0;

/* 收藏状态 */
$faved = false;
if (is_login()) {
    $faved = (bool)db_one("SELECT id FROM favorites WHERE user_id=? AND tmdb_id=? AND media_type=?", array(current_user()['id'], $id, $type));
}

$audio = get('audio', 'ori');
if (!in_array($audio, array('ori', 'cn'), true)) $audio = 'ori';

/* 播放源：后台可管理，详情页选择（默认取后台设定的默认源） */
$sources = db_all("SELECT * FROM sources ORDER BY is_default DESC, id ASC");
$defSource = default_source();
$srcId = intval(get('source', $defSource ? $defSource['id'] : 0));
if ($srcId > 0 && !source_by_id($srcId)) $srcId = $defSource ? intval($defSource['id']) : 0;

$playUrl = 'play.php?type=' . $type . '&id=' . $id . '&title=' . urlencode($title) . '&season=' . ($isTV ? $season : 1) . '&ep=1&audio=' . $audio . '&source=' . $srcId;
$PAGE_TITLE = $title;
require __DIR__ . '/includes/header.php';
?>

<section class="detail-hero">
  <?php if ($backdrop): ?><div class="dh-bg" style="background-image:url('<?php echo h($backdrop); ?>')"></div><?php endif; ?>
  <div class="dh-mask"></div>
  <div class="dh-body">
    <?php if ($poster): ?><div class="dh-poster"><img src="<?php echo h($poster); ?>" alt="<?php echo h($title); ?>"></div><?php endif; ?>
    <div class="dh-info">
      <h1><?php echo h($title); ?></h1>
      <?php $enName = isset($detail['original_name']) ? $detail['original_name'] : (isset($detail['original_title']) ? $detail['original_title'] : ''); ?>
      <?php if ($enName && $enName !== $title): ?><div class="en-name"><?php echo h($enName); ?></div><?php endif; ?>
      <div class="dh-meta">
        <?php if ($rate > 0): ?><span class="rate-badge"><i class="ico i-star"></i><?php echo number_format($rate, 1); ?></span><?php endif; ?>
        <?php if (!$isTV && $runtime): ?><span><i class="ico i-clock"></i> <?php echo intval($runtime / 60); ?>小时<?php echo $runtime % 60; ?>分钟</span><?php endif; ?>
        <?php if ($isTV): ?><span><i class="ico i-tv"></i> 共 <?php echo count($validSeasons); ?> 季</span><?php endif; ?>
        <span class="tag theme"><?php echo $isTV ? '剧集' : '电影'; ?></span>
        <span class="tag"><?php echo $isZh ? '国产' : '海外'; ?></span>
        <?php foreach (array_slice($genres, 0, 3) as $g): ?><span class="tag"><?php echo h(isset($g['name']) ? $g['name'] : ''); ?></span><?php endforeach; ?>
      </div>
      <p class="dh-desc"><?php echo h($isTV ? $seasonInfo['overview'] : $overview); ?></p>
      <div class="dh-actions">
        <a class="btn primary lg" href="<?php echo h($playUrl); ?>"><i class="ico i-play invert"></i>立即播放</a>
        <button type="button" class="btn ghost lg btn-fav <?php echo $faved ? 'on' : ''; ?>"
          data-id="<?php echo $id; ?>" data-type="<?php echo $type; ?>"
          data-title="<?php echo h($title); ?>" data-poster="<?php echo h($poster); ?>"
          style="<?php echo $faved ? 'border-color:var(--theme);color:var(--theme)' : ''; ?>">
          <i class="ico i-heart"></i><span><?php echo $faved ? '已收藏' : '收藏'; ?></span>
        </button>
        <?php if (!$isZh): ?>
        <!-- 海外影视：音轨切换（普通话 / 原版） -->
        <div class="audio-switch" title="切换配音版本">
          <button type="button" class="<?php echo $audio === 'cn' ? 'on' : ''; ?>" onclick="switchAudio('cn')"><i class="ico i-audio"></i>普通话</button>
          <button type="button" class="<?php echo $audio === 'ori' ? 'on' : ''; ?>" onclick="switchAudio('ori')"><i class="ico i-globe"></i>原版</button>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($sources)): ?>
      <!-- 播放源选择（后台播放源管理模块维护） -->
      <div class="source-bar">
        <span class="src-label">播放源</span>
        <?php foreach ($sources as $s): ?>
        <a class="src-btn <?php echo intval($s['id']) === $srcId ? 'active' : ''; ?>"
           href="detail.php?type=<?php echo $type; ?>&id=<?php echo $id; ?><?php echo $isTV ? '&season=' . $season : ''; ?>&audio=<?php echo h($audio); ?>&source=<?php echo intval($s['id']); ?>">
          <?php echo h($s['name']); ?><?php if (intval($s['is_default']) === 1): ?><span class="src-def">默认</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
function switchAudio(a) {
  var u = new URL(location.href);
  u.searchParams.set('audio', a);
  location.href = u.toString();
}
</script>

<?php if ($isTV && $validSeasons): ?>
<section class="section anim">
  <div class="section-head">
    <span class="s-ico"><i class="ico i-calendar"></i></span>
    <h2>选季</h2>
  </div>
  <div class="season-bar">
    <?php foreach ($validSeasons as $s): $sn = intval($s['season_number']); ?>
    <a class="season-btn <?php echo $sn === $season ? 'active' : ''; ?>" href="detail.php?type=tv&id=<?php echo $id; ?>&season=<?php echo $sn; ?>&audio=<?php echo h($audio); ?>&source=<?php echo $srcId; ?>">
      第 <?php echo $sn; ?> 季<?php echo !empty($s['air_date']) ? ' · ' . substr($s['air_date'], 0, 4) : ''; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="season-stats">
    <div class="stat-pill"><span class="s-ico"><i class="ico i-star"></i></span><div><b><?php echo $seasonInfo['rate'] > 0 ? number_format($seasonInfo['rate'], 1) : '--'; ?></b><span>本季评分</span></div></div>
    <div class="stat-pill"><span class="s-ico"><i class="ico i-calendar"></i></span><div><b><?php echo $seasonInfo['year'] !== '' ? $seasonInfo['year'] : '--'; ?></b><span>本季年份</span></div></div>
    <div class="stat-pill"><span class="s-ico"><i class="ico i-tv"></i></span><div><b><?php echo $epCount; ?> 集</b><span>本季集数</span></div></div>
    <div class="stat-pill"><span class="s-ico"><i class="ico i-user"></i></span><div><b><?php echo count($seasonInfo['cast']); ?> 位</b><span>本季演员</span></div></div>
  </div>
</section>

<section class="section anim">
  <div class="section-head">
    <span class="s-ico"><i class="ico i-play"></i></span>
    <h2>本季剧集 <span style="color:var(--muted);font-size:14px;font-weight:400">（TMDB 单集信息）</span></h2>
  </div>
  <?php if (empty($seasonInfo['episodes'])): ?>
  <div class="empty-box"><i class="ico i-info"></i><p>本季单集信息暂未收录</p></div>
  <?php else: ?>
  <div class="ep-grid">
    <?php foreach ($seasonInfo['episodes'] as $ep):
        $epNum = intval($ep['episode_number']);
        $epPlay = 'play.php?type=tv&id=' . $id . '&title=' . urlencode($title) . '&season=' . $season . '&ep=' . $epNum . '&audio=' . $audio . '&source=' . $srcId;
        $still = !empty($ep['still_path']) ? tmdb_img($ep['still_path'], 'w300') : '';
    ?>
    <a class="ep-card" href="<?php echo h($epPlay); ?>">
      <div class="ep-thumb">
        <?php if ($still): ?><img data-src="<?php echo h($still); ?>" alt="">
        <?php else: ?><span class="no-img"><i class="ico i-play"></i></span><?php endif; ?>
        <span class="ep-num-badge">第<?php echo $epNum; ?>集</span>
      </div>
      <div class="ep-txt">
        <div class="ep-title"><?php echo h(isset($ep['name']) && $ep['name'] !== '' ? $ep['name'] : '第' . $epNum . '集'); ?></div>
        <div class="ep-desc"><?php echo h(isset($ep['overview']) && $ep['overview'] !== '' ? mb_substr($ep['overview'], 0, 60, 'UTF-8') : '暂无单集简介'); ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php
$casts = $isTV ? $seasonInfo['cast'] : (isset($detail['credits']['cast']) ? $detail['credits']['cast'] : array());
?>
<?php if ($casts): ?>
<section class="section anim">
  <div class="section-head">
    <span class="s-ico"><i class="ico i-users"></i></span>
    <h2><?php echo $isTV ? '本季演员' : '演职员'; ?></h2>
  </div>
  <div class="cast-grid">
    <?php foreach (array_slice($casts, 0, 14) as $c): ?>
    <div class="cast-card">
      <div class="cc-avatar">
        <?php if (!empty($c['profile_path'])): ?>
        <img data-src="<?php echo h(tmdb_img($c['profile_path'], 'w185')); ?>" alt="">
        <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d2836,#121a24);color:#3a4756"><i class="ico i-user" style="font-size:26px"></i></div>
        <?php endif; ?>
      </div>
      <div class="cc-name"><?php echo h(isset($c['name']) ? $c['name'] : ''); ?></div>
      <div class="cc-role"><?php echo h(isset($c['character']) && $c['character'] !== '' ? $c['character'] : '饰未知角色'); ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
