<?php
/**
 * Jay影视 - 首页（流行趋势 + 分类板块，公告弹窗仅在此页展示）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/tmdb.php';

$IS_HOME  = true;
$NAV_ACTIVE = 'home';
$PAGE_TITLE = '首页';

$trending = tmdb_trending();
$sections = array(
    array('key' => 'hot-movie', 'icon' => 'i-film',  'title' => '热门电影', 'link' => 'category.php?cat=movie', 'items' => tmdb_popular('movie')),
    array('key' => 'hot-tv',    'icon' => 'i-tv',    'title' => '热门剧集', 'link' => 'category.php?cat=tv',    'items' => tmdb_popular('tv')),
    array('key' => 'hot-anime', 'icon' => 'i-spark', 'title' => '人气动漫', 'link' => 'category.php?cat=anime', 'items' => array_slice(tmdb_discover('anime')['results'], 0, 18)),
    array('key' => 'hot-vari',  'icon' => 'i-mic',   'title' => '综艺精选', 'link' => 'category.php?cat=variety', 'items' => array_slice(tmdb_discover('variety')['results'], 0, 18)),
);

/** 输出媒体卡片 */
function card_html($r, $i = 0)
{
    $m = tmdb_item($r);
    if (!$m['id'] || $m['poster'] === '') return '';
    $cls = 'anim anim-d' . min(4, $i % 5);
    $typeTxt = $m['type'] === 'tv' ? '剧集' : '电影';
    return '<a class="media-card ' . $cls . '" style="animation-delay:' . ($i * 0.03) . 's" href="' . media_link($m['type'], $m['id']) . '">'
        . '<div class="mc-poster"><img data-src="' . h($m['poster']) . '" alt="' . h($m['title']) . '">'
        . ($m['rate'] > 0 ? '<span class="mc-rate"><i class="ico i-star"></i>' . number_format($m['rate'], 1) . '</span>' : '')
        . '<span class="mc-play"><span><i class="ico i-play invert"></i></span></span></div>'
        . '<div class="mc-info"><div class="mc-title">' . h($m['title']) . '</div>'
        . '<div class="mc-sub"><span class="mc-type">' . $typeTxt . '</span><span class="dot"></span><span>' . h($m['year']) . '</span></div></div></a>';
}

require __DIR__ . '/includes/header.php';

/* Hero：趋势第一的影视 */
$hero = null;
foreach ($trending as $t) {
    if (!empty($t['backdrop_path'])) { $hero = $t; break; }
}
if ($hero):
    $hm = tmdb_item($hero);
    $bg = tmdb_img(isset($hero['backdrop_path']) ? $hero['backdrop_path'] : '', 'w1280');
?>
<section class="hero anim">
  <div class="hero-bg" style="background-image:url('<?php echo h($bg); ?>')"></div>
  <div class="hero-mask"></div>
  <div class="hero-info">
    <span class="hero-tag"><i class="ico i-spark"></i>本周热门 NO.1</span>
    <h1><?php echo h($hm['title']); ?></h1>
    <div class="hero-meta">
      <span class="rate-badge"><i class="ico i-star"></i><?php echo number_format($hm['rate'], 1); ?></span>
      <span><?php echo h($hm['year']); ?></span>
      <span class="tag theme"><?php echo $hm['type'] === 'tv' ? '剧集' : '电影'; ?></span>
      <?php if (isset($hero['genre_ids']) && $hero['genre_ids']): ?><span class="tag">TMDB 热榜</span><?php endif; ?>
    </div>
    <?php if (!empty($hero['overview'])): ?>
    <p class="hero-desc"><?php echo h($hero['overview']); ?></p>
    <?php endif; ?>
    <div class="hero-actions">
      <a class="btn primary lg" href="play.php?type=<?php echo h($hm['type']); ?>&id=<?php echo intval($hm['id']); ?>&title=<?php echo urlencode($hm['title']); ?>&ep=1"><i class="ico i-play invert"></i>立即播放</a>
      <a class="btn ghost lg" href="<?php echo media_link($hm['type'], $hm['id']); ?>"><i class="ico i-info"></i>查看详情</a>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section anim">
  <div class="section-head">
    <span class="s-ico"><i class="ico i-spark"></i></span>
    <h2>流行趋势</h2>
    <a class="more" href="category.php?cat=movie">更多 <i class="ico i-arrow"></i></a>
  </div>
  <div class="scroll-row">
    <?php
    $i = 0;
    foreach ($trending as $t) {
        echo card_html($t, $i);
        if (++$i >= 18) break;
    }
    ?>
  </div>
</section>

<?php foreach ($sections as $sec): if (empty($sec['items'])) continue; ?>
<section class="section anim">
  <div class="section-head">
    <span class="s-ico"><i class="ico <?php echo $sec['icon']; ?>"></i></span>
    <h2><?php echo h($sec['title']); ?></h2>
    <a class="more" href="<?php echo $sec['link']; ?>">更多 <i class="ico i-arrow"></i></a>
  </div>
  <div class="scroll-row">
    <?php
    $i = 0;
    foreach ($sec['items'] as $r) {
        echo card_html($r, $i);
        if (++$i >= 18) break;
    }
    ?>
  </div>
</section>
<?php endforeach; ?>

<?php if (empty($trending) && empty($sections[0]['items'])): ?>
<div class="empty-box">
  <i class="ico i-info"></i>
  <p>影视数据加载失败，请稍后刷新重试（数据来源 TMDB，可能需要配置有效的 API Key）</p>
  <a class="btn primary" href="index.php">刷新页面</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
