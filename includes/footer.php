</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <span class="brand-mark sm"><i class="ico i-play invert"></i></span>
      <span><?php echo h($SITE_NAME); ?></span>
    </div>
    <p class="footer-links">
      <a href="index.php">首页</a><a href="category.php?cat=movie">电影</a><a href="category.php?cat=tv">剧集</a>
      <a href="category.php?cat=anime">动漫</a><a href="category.php?cat=variety">综艺</a><a href="feedback.php">反馈</a>
    </p>
    <p class="footer-copy">© <?php echo date('Y'); ?> <?php echo h($SITE_NAME); ?> · 影视数据来源 TMDB · 本站不存储任何音视频文件，仅提供技术演示</p>
  </div>
</footer>

<div class="toast" id="toast"><span id="toastMsg"></span></div>

<script>
window.JAY = {
  csrf: <?php echo json_encode(csrf_token()); ?>,
  isLogin: <?php echo $U ? 'true' : 'false'; ?>,
  siteName: <?php echo json_encode($SITE_NAME); ?>
};
</script>
<script src="assets/js/main.js?v=1.2"></script>
</body>
</html>
