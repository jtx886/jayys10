<?php
/**
 * Jay影视 - 安装向导
 * 适配 InfinityFree 免费服务器 / PHP 7.4-8.x / MySQL
 * 安装完成后自动销毁本文件（销毁安装入口）
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Shanghai');
header('Content-Type: text/html; charset=utf-8');

define('APP_ROOT', __DIR__);
$lockFile = APP_ROOT . '/install.lock';
$configFile = APP_ROOT . '/includes/config.php';

/* 已安装则直接关闭入口 */
if (file_exists($configFile) || file_exists($lockFile)) {
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><body style="background:#0d1117;color:#e6edf3;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center"><div style="font-size:42px;width:64px;height:64px;margin:0 auto 18px;border:3px solid #e5383b;border-radius:50%;position:relative"><span style="position:absolute;left:50%;top:12px;transform:translateX(-50%) rotate(45deg);width:22px;height:5px;background:#e5383b;border-radius:2px;display:block"></span><span style="position:absolute;left:50%;top:12px;transform:translateX(-50%) rotate(-45deg);width:22px;height:5px;background:#e5383b;border-radius:2px;display:block"></span></div><h2 style="margin:0 0 8px">安装入口已销毁</h2><p style="color:#8b949e">Jay影视已完成安装，如需重新安装请手动删除 includes/config.php 与 install.lock 后再上传 install.php。</p><a href="index.php" style="color:#e5383b;text-decoration:none">返回首页</a></div></body>');
}

$step  = isset($_POST['step']) ? intval($_POST['step']) : 1;
$error = '';
$done  = false;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ============ 执行安装 ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost = trim(isset($_POST['db_host']) ? $_POST['db_host'] : '');
    $dbName = trim(isset($_POST['db_name']) ? $_POST['db_name'] : '');
    $dbUser = trim(isset($_POST['db_user']) ? $_POST['db_user'] : '');
    $dbPass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
    $admName = trim(isset($_POST['adm_name']) ? $_POST['adm_name'] : '');
    $admPass = isset($_POST['adm_pass']) ? $_POST['adm_pass'] : '';

    if ($dbHost === '' || $dbName === '' || $dbUser === '' || $admName === '' || $admPass === '') {
        $error = '请完整填写数据库信息与管理员账号（InfinityFree 数据库主机填 sqlXXX.infinityfree.com，数据库信息见其控制面板 MySQL Databases 页）';
        $step = 1;
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser, $dbPass,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10)
            );
            $pdo->exec("SET NAMES utf8mb4, sql_mode='', time_zone = '+08:00'");

            /* ---------- 建表 ---------- */
            $tables = array();

            $tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(50) NOT NULL,
                `email` VARCHAR(120) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `avatar` VARCHAR(255) NOT NULL DEFAULT '',
                `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
                `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0正常 1封禁',
                `ban_reason` VARCHAR(255) NOT NULL DEFAULT '',
                `banned_at` DATETIME NULL DEFAULT NULL,
                `ban_until` DATETIME NULL DEFAULT NULL COMMENT 'NULL=永久封禁',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_username` (`username`),
                UNIQUE KEY `uk_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['verify_codes'] = "CREATE TABLE IF NOT EXISTS `verify_codes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(120) NOT NULL,
                `code` VARCHAR(10) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `used` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['favorites'] = "CREATE TABLE IF NOT EXISTS `favorites` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `tmdb_id` INT UNSIGNED NOT NULL,
                `media_type` VARCHAR(10) NOT NULL DEFAULT 'movie',
                `title` VARCHAR(255) NOT NULL DEFAULT '',
                `poster` VARCHAR(255) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_user_media` (`user_id`,`tmdb_id`,`media_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['watch_history'] = "CREATE TABLE IF NOT EXISTS `watch_history` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `tmdb_id` INT UNSIGNED NOT NULL,
                `media_type` VARCHAR(10) NOT NULL DEFAULT 'movie',
                `title` VARCHAR(255) NOT NULL DEFAULT '',
                `poster` VARCHAR(255) NOT NULL DEFAULT '',
                `season` INT UNSIGNED NOT NULL DEFAULT 1,
                `episode` INT UNSIGNED NOT NULL DEFAULT 1,
                `position` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计观看秒数',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_user_media` (`user_id`,`tmdb_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['feedbacks'] = "CREATE TABLE IF NOT EXISTS `feedbacks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `content` TEXT NOT NULL,
                `is_public` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['feedback_replies'] = "CREATE TABLE IF NOT EXISTS `feedback_replies` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `feedback_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `content` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_fb` (`feedback_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['feedback_likes'] = "CREATE TABLE IF NOT EXISTS `feedback_likes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `feedback_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_fb_user` (`feedback_id`,`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['notices'] = "CREATE TABLE IF NOT EXISTS `notices` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `content` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['sources'] = "CREATE TABLE IF NOT EXISTS `sources` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `api_url` VARCHAR(255) NOT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['settings'] = "CREATE TABLE IF NOT EXISTS `settings` (
                `skey` VARCHAR(50) NOT NULL,
                `svalue` TEXT NOT NULL,
                PRIMARY KEY (`skey`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $tables['media_cache'] = "CREATE TABLE IF NOT EXISTS `media_cache` (
                `cache_key` VARCHAR(64) NOT NULL,
                `data` LONGTEXT NOT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`cache_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            foreach ($tables as $sql) { $pdo->exec($sql); }

            /* ---------- 初始数据 ---------- */
            $admEmail = 'admin@jaymovie.local';
            $chk = $pdo->prepare("SELECT id FROM users WHERE role='admin' LIMIT 1");
            $chk->execute();
            if (!$chk->fetch()) {
                $ins = $pdo->prepare("INSERT INTO users (username,email,password,role,status) VALUES (?,?,?,'admin',0)");
                $ins->execute(array($admName, $admEmail, password_hash($admPass, PASSWORD_DEFAULT)));
            }
            $cnt = $pdo->query("SELECT COUNT(*) FROM sources")->fetchColumn();
            if (!$cnt) {
                $pdo->prepare("INSERT INTO sources (name,api_url,is_default) VALUES (?,?,1)")
                    ->execute(array('YY资源（默认）', 'https://api.yyzy-tv.vip/inc/apijson.php'));
            }
            $defSettings = array(
                'site_name'   => 'Jay影视',
                'theme_color' => '#e50914',
                'tmdb_key'    => '3fd2be6f0c70a2a598f084ddfb75487c',
            );
            $st = $pdo->prepare("INSERT IGNORE INTO settings (skey,svalue) VALUES (?,?)");
            foreach ($defSettings as $k => $v) { $st->execute(array($k, $v)); }
            $pdo = null;

            /* ---------- 生成配置文件 ---------- */
            if (!is_dir(APP_ROOT . '/includes')) { mkdir(APP_ROOT . '/includes', 0755, true); }
            if (!is_dir(APP_ROOT . '/uploads'))  { mkdir(APP_ROOT . '/uploads', 0755, true); }
            $cfg  = "<?php\n";
            $cfg .= "/** Jay影视 运行配置（安装向导自动生成） */\n";
            $cfg .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $cfg .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $cfg .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $cfg .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $cfg .= "define('APP_INSTALLED', true);\n";
            $ok = file_put_contents($configFile, $cfg, LOCK_EX);
            if ($ok === false) { throw new Exception('无法写入 includes/config.php，请检查目录写权限'); }

            /* ---------- 销毁安装入口 ---------- */
            @file_put_contents($lockFile, 'installed');
            $selfDestroyed = @unlink(__FILE__);
            $done = true;
        } catch (Exception $e) {
            $error = '安装失败：' . $e->getMessage();
            $step = 1;
        }
    }
}

/* ============ 环境检查 ============ */
$envOk = array(
    'php'     => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo'     => extension_loaded('pdo_mysql'),
    'curl'    => function_exists('curl_init') || ini_get('allow_url_fopen'),
    'openssl' => extension_loaded('openssl'),
    'writable'=> is_writable(APP_ROOT . '/includes') || is_writable(APP_ROOT),
);
$allOk = !in_array(false, $envOk, true);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装向导 - Jay影视</title>
<style>
:root{--theme:#e50914;--bg:#0b0f14;--card:#151b23;--line:#232c37;--txt:#e6edf3;--muted:#8b949e}
*{margin:0;padding:0;box-sizing:border-box}
body{background:radial-gradient(1200px 600px at 80% -10%,rgba(229,9,20,.15),transparent),var(--bg);color:var(--txt);font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.box{width:100%;max-width:560px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:36px;box-shadow:0 20px 60px rgba(0,0,0,.5);animation:pop .45s ease}
@keyframes pop{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:none}}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:6px}
.logo .mark{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--theme),#ff5a4e);display:flex;align-items:center;justify-content:center}
.logo .mark i{width:0;height:0;border-left:13px solid #fff;border-top:8px solid transparent;border-bottom:8px solid transparent;margin-left:3px}
h1{font-size:22px}
.step-tag{color:var(--muted);font-size:13px;margin-bottom:22px}
.env{list-style:none;margin:14px 0 22px;display:grid;gap:8px}
.env li{display:flex;justify-content:space-between;align-items:center;background:#10161d;border:1px solid var(--line);border-radius:10px;padding:10px 14px;font-size:14px}
.ok{color:#3fb950}.bad{color:#e5383b}
label{display:block;font-size:13px;color:var(--muted);margin:14px 0 6px}
input{width:100%;background:#0d1319;border:1px solid var(--line);border-radius:10px;color:var(--txt);padding:12px 14px;font-size:14px;outline:none;transition:border-color .25s,box-shadow .25s}
input:focus{border-color:var(--theme);box-shadow:0 0 0 3px rgba(229,9,20,.15)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:26px;padding:13px;border:0;border-radius:10px;background:linear-gradient(135deg,var(--theme),#ff5a4e);color:#fff;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;transition:transform .2s,filter .2s}
.btn:hover{transform:translateY(-2px);filter:brightness(1.1)}
.btn[disabled]{filter:grayscale(1);cursor:not-allowed;transform:none}
.err{background:rgba(229,56,59,.12);border:1px solid rgba(229,56,59,.4);color:#ff8a8d;border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:16px;line-height:1.6}
.tip{font-size:12px;color:var(--muted);margin-top:10px;line-height:1.7}
.done-ico{width:74px;height:74px;margin:6px auto 18px;border:3px solid #3fb950;border-radius:50%;position:relative}
.done-ico::before{content:"";position:absolute;left:22px;top:34px;width:26px;height:6px;background:#3fb950;border-radius:3px;transform:rotate(-45deg)}
.done-ico::after{content:"";position:absolute;left:18px;top:38px;width:14px;height:6px;background:#3fb950;border-radius:3px;transform:rotate(45deg);transform-origin:right}
.center{text-align:center}
.pill{display:inline-block;background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.4);color:#3fb950;font-size:12px;border-radius:99px;padding:4px 12px;margin-top:14px}
</style>
</head>
<body>
<div class="box">
  <div class="logo"><div class="mark"><i></i></div><div><h1>Jay影视 · 安装向导</h1></div></div>

<?php if ($done): ?>
  <div class="center">
    <div class="done-ico"></div>
    <h1 style="margin-bottom:8px">安装完成</h1>
    <p class="step-tag" style="margin-bottom:0">数据库表已创建，配置文件已生成</p>
    <?php if ($selfDestroyed): ?>
      <div class="pill">安装入口已自动销毁（install.php 已删除）</div>
    <?php else: ?>
      <div class="err" style="margin-top:14px">已写入 install.lock 锁定安装，但服务器不允许自动删除 install.php，请手动删除该文件以确保安全。</div>
    <?php endif; ?>
    <p class="tip">管理员账号：<?php echo h(isset($admName) && $admName !== '' ? $admName : '杰同学'); ?><br>后台入口：/admin/login.php</p>
    <a class="btn" href="index.php">进入网站首页</a>
  </div>
<?php elseif ($step === 1): ?>
  <div class="step-tag">第 1 步 / 共 2 步 · 环境检测与信息填写</div>
  <?php if ($error): ?><div class="err"><?php echo h($error); ?></div><?php endif; ?>
  <ul class="env">
    <li><span>PHP 版本 ≥ 7.4（当前 <?php echo h(PHP_VERSION); ?>）</span><span class="<?php echo $envOk['php'] ? 'ok' : 'bad'; ?>"><?php echo $envOk['php'] ? '通过' : '不通过'; ?></span></li>
    <li><span>PDO MySQL 扩展</span><span class="<?php echo $envOk['pdo'] ? 'ok' : 'bad'; ?>"><?php echo $envOk['pdo'] ? '通过' : '不通过'; ?></span></li>
    <li><span>cURL / 远程请求</span><span class="<?php echo $envOk['curl'] ? 'ok' : 'bad'; ?>"><?php echo $envOk['curl'] ? '通过' : '不通过'; ?></span></li>
    <li><span>OpenSSL（SMTP 加密）</span><span class="<?php echo $envOk['openssl'] ? 'ok' : 'bad'; ?>"><?php echo $envOk['openssl'] ? '通过' : '不通过'; ?></span></li>
    <li><span>目录可写（配置/上传）</span><span class="<?php echo $envOk['writable'] ? 'ok' : 'bad'; ?>"><?php echo $envOk['writable'] ? '通过' : '不通过'; ?></span></li>
  </ul>
  <form method="post" autocomplete="off">
    <input type="hidden" name="step" value="2">
    <label>数据库主机（InfinityFree 填 sqlXXX.infinityfree.com，本地填 localhost）</label>
    <input name="db_host" value="<?php echo h(isset($_POST['db_host']) ? $_POST['db_host'] : 'localhost'); ?>" required>
    <label>数据库名</label>
    <input name="db_name" value="<?php echo h(isset($_POST['db_name']) ? $_POST['db_name'] : ''); ?>" required placeholder="if_XXXXXXXX_xxx">
    <label>数据库用户名</label>
    <input name="db_user" value="<?php echo h(isset($_POST['db_user']) ? $_POST['db_user'] : ''); ?>" required>
    <label>数据库密码</label>
    <input name="db_pass" type="password" value="">
    <label>管理员用户名（后台登录）</label>
    <input name="adm_name" value="杰同学" required>
    <label>管理员密码</label>
    <input name="adm_pass" type="password" value="101113" required>
    <button class="btn" type="submit" <?php if (!$allOk) echo 'disabled'; ?>>开始安装</button>
    <p class="tip">安装将自动创建全部业务数据表（用户 / 收藏 / 观看历史 / 反馈 / 公告 / 播放源 / 媒体缓存），生成 includes/config.php，并在完成后销毁安装入口。</p>
  </form>
<?php endif; ?>
</div>
</body>
</html>
