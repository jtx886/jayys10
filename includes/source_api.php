<?php
/**
 * Jay影视 - 播放源接口封装
 * 默认资源站：https://api.yyzy-tv.vip/inc/apijson.php （苹果CMS JSON 格式，后台可管理）
 *
 * 播放链路：
 *   1. 调用播放源接口按片名搜索，取得真实 m3u8 直链（vod_play_url）
 *   2. rawurlencode(真实m3u8)
 *   3. 拼接到解析播放器外壳 https://svip.ffzyplay.com/?url= 后 iframe 嵌入
 *   —— 严禁直接把源接口地址传入解析播放器
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require_once APP_ROOT . '/includes/functions.php';

define('PLAYER_SHELL', 'https://svip.ffzyplay.com/?url=');

/** 取默认播放源 */
function default_source()
{
    $s = db_one("SELECT * FROM sources ORDER BY is_default DESC, id ASC LIMIT 1");
    return $s ? $s : null;
}

function source_by_id($id)
{
    return db_one("SELECT * FROM sources WHERE id=?", array(intval($id)));
}

/** 调用播放源接口搜索影片（含 10 分钟缓存） */
function source_search($keyword, $source = null)
{
    if ($source === null) { $source = default_source(); }
    if (!$source || trim($keyword) === '') return array();
    $kw = trim($keyword);
    $ck = 'src_' . md5($source['id'] . '|' . $kw);
    $row = db_one("SELECT data, updated_at FROM media_cache WHERE cache_key=?", array($ck));
    if ($row && (time() - strtotime($row['updated_at'])) < 600) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) return $d;
    }
    $url = rtrim($source['api_url'], '/&') . '?ac=videolist&wd=' . rawurlencode($kw);
    $res = http_get($url, 12);
    $list = array();
    if ($res !== false) {
        $json = json_decode($res, true);
        if (is_array($json) && !empty($json['list'])) {
            foreach ($json['list'] as $v) {
                $list[] = array(
                    'vod_id'   => isset($v['vod_id']) ? $v['vod_id'] : 0,
                    'vod_name' => isset($v['vod_name']) ? $v['vod_name'] : '',
                    'vod_pic'  => isset($v['vod_pic']) ? $v['vod_pic'] : '',
                    'vod_play_url' => isset($v['vod_play_url']) ? $v['vod_play_url'] : '',
                );
            }
        }
    }
    db_exec("INSERT INTO media_cache (cache_key,data,updated_at) VALUES (?,?,NOW())
             ON DUPLICATE KEY UPDATE data=VALUES(data), updated_at=NOW()", array($ck, json_encode($list, JSON_UNESCAPED_UNICODE)));
    return $list;
}

/** 解析 vod_play_url 为 [ ['name'=>'第01集','url'=>'http...m3u8'], ... ] */
function parse_play_list($playUrl)
{
    $eps = array();
    if (!$playUrl) return $eps;
    /* 多播放组以 $$$ 分隔，取第一组（或第一可用组） */
    $groups = explode('$$$', $playUrl);
    foreach ($groups as $g) {
        $items = explode('#', $g);
        $eps = array();
        foreach ($items as $it) {
            $kv = explode('$', $it);
            if (count($kv) >= 2) {
                $url = trim(end($kv));
                if ($url !== '') {
                    array_pop($kv);
                    $eps[] = array('name' => trim(implode('$', $kv)), 'url' => $url);
                }
            }
        }
        if (!empty($eps)) break; /* 优先第一组可用数据 */
    }
    return $eps;
}

/** 从搜索结果中挑出与片名最匹配的条目 */
function pick_vod($list, $title)
{
    if (empty($list)) return null;
    $t = str_replace(array(' ', '　', '第1季', '第一季', '第2季', '第二季', '第3季', '第三季'), '', $title);
    $best = null; $bestScore = -1;
    foreach ($list as $v) {
        $n = str_replace(array(' ', '　'), '', $v['vod_name']);
        $score = 0;
        if ($n === $t) $score = 100;
        elseif (strpos($n, $t) !== false || strpos($t, $n) !== false) $score = 60 + min(20, strlen($n));
        else {
            similar_text($n, $t, $pct);
            $score = $pct;
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $v; }
    }
    return $bestScore >= 40 ? $best : (isset($list[0]) ? $list[0] : null);
}

/**
 * 生成最终解析播放地址（真实 m3u8 编码后拼接解析外壳）
 * @return array ['ok'=>bool,'player_url'=>string,'eps'=>array,'msg'=>string,'ep'=>int]
 */
function build_player($title, $season = 1, $ep = 1, $audio = 'ori', $source = null)
{
    $out = array('ok' => false, 'player_url' => '', 'eps' => array(), 'msg' => '', 'ep' => max(1, intval($ep)));
    $kw = trim($title);
    if ($audio === 'cn') $kw .= ' 普通话';
    $list = source_search($kw, $source);
    if (empty($list)) { $out['msg'] = '播放源中未找到《' . $title . '》的相关资源'; return $out; }
    $vod = pick_vod($list, $title);
    if (!$vod || $vod['vod_play_url'] === '') { $out['msg'] = '该资源暂无可播放的地址'; return $out; }
    $eps = parse_play_list($vod['vod_play_url']);
    if (empty($eps)) { $out['msg'] = '播放地址解析失败'; return $out; }
    $idx = $out['ep'] - 1;
    if ($idx < 0 || $idx >= count($eps)) $idx = 0;
    $out['ep'] = $idx + 1;
    $m3u8 = $eps[$idx]['url'];
    /* 核心安全规则：必须使用真实 m3u8 直链编码后拼接，严禁传源接口地址 */
    $out['player_url'] = PLAYER_SHELL . rawurlencode($m3u8);
    $out['eps'] = $eps;
    $out['ok'] = true;
    return $out;
}
