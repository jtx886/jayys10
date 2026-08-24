<?php
/**
 * Jay影视 - TMDB API 封装（含 MySQL 缓存）
 */
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require_once APP_ROOT . '/includes/functions.php';

define('TMDB_API', 'https://api.themoviedb.org/3/');
define('TMDB_CACHE_TTL', 21600); /* 6小时 */

/**
 * 请求 TMDB 接口（自动缓存）
 * @return array|null
 */
function tmdb_get($path, $params = array())
{
    $key = setting('tmdb_key', '');
    if ($key === '') return null;
    $params['api_key'] = $key;
    $params['language'] = isset($params['language']) ? $params['language'] : 'zh-CN';
    $url = TMDB_API . ltrim($path, '/') . '?' . http_build_query($params);
    $ck = 'tmdb_' . md5($url);

    /* 命中缓存 */
    $row = db_one("SELECT data, updated_at FROM media_cache WHERE cache_key=?", array($ck));
    if ($row && (time() - strtotime($row['updated_at'])) < TMDB_CACHE_TTL) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) return $d;
    }

    $res = http_get($url, 12);
    if ($res === false) return null;
    $data = json_decode($res, true);
    if (!is_array($data) || isset($data['success'])) return null; /* TMDB错误响应含 success:false */

    db_exec("INSERT INTO media_cache (cache_key,data,updated_at) VALUES (?,?,NOW())
             ON DUPLICATE KEY UPDATE data=VALUES(data), updated_at=NOW()", array($ck, $res));
    return $data;
}

/** 首页：流行趋势 */
function tmdb_trending()
{
    $d = tmdb_get('/trending/all/week');
    return $d && isset($d['results']) ? $d['results'] : array();
}

function tmdb_popular($type) /* movie | tv */
{
    $d = tmdb_get('/' . $type . '/popular', array('page' => 1));
    return $d && isset($d['results']) ? $d['results'] : array();
}

/** 分类发现: cat = movie|tv|anime|variety */
function tmdb_discover($cat, $page = 1)
{
    $params = array('sort_by' => 'popularity.desc', 'include_adult' => 'false', 'page' => max(1, min(500, intval($page))));
    if ($cat === 'movie') { $path = '/discover/movie'; }
    elseif ($cat === 'tv') { $path = '/discover/tv'; }
    elseif ($cat === 'anime') { $path = '/discover/tv'; $params['with_genres'] = '16'; }
    elseif ($cat === 'variety') { $path = '/discover/tv'; $params['with_genres'] = '10764'; }
    else { $path = '/discover/movie'; }
    $d = tmdb_get($path, $params);
    return $d ? $d : array('results' => array(), 'total_pages' => 0, 'page' => 1);
}

function tmdb_search($query, $page = 1)
{
    $d = tmdb_get('/search/multi', array('query' => $query, 'page' => max(1, intval($page)), 'include_adult' => 'false'));
    return $d ? $d : array('results' => array(), 'total_pages' => 0, 'page' => 1);
}

/** 详情（含演职员） */
function tmdb_detail($type, $id)
{
    if ($type !== 'movie' && $type !== 'tv') $type = 'movie';
    return tmdb_get('/' . $type . '/' . intval($id), array('append_to_response' => 'credits'));
}

/** 季详情（含本季演职员与每集信息） */
function tmdb_season($tvId, $season)
{
    return tmdb_get('/tv/' . intval($tvId) . '/season/' . intval($season), array('append_to_response' => 'credits'));
}

/** 标准化媒体条目（列表卡片用） */
function tmdb_item($r)
{
    $type = isset($r['media_type']) && $r['media_type'] ? $r['media_type'] : (isset($r['first_air_date']) ? 'tv' : 'movie');
    $title = $type === 'tv' ? (isset($r['name']) ? $r['name'] : '') : (isset($r['title']) ? $r['title'] : '');
    $date = $type === 'tv' ? (isset($r['first_air_date']) ? $r['first_air_date'] : '') : (isset($r['release_date']) ? $r['release_date'] : '');
    return array(
        'id'     => isset($r['id']) ? $r['id'] : 0,
        'type'   => $type,
        'title'  => $title,
        'poster' => isset($r['poster_path']) ? tmdb_img($r['poster_path'], 'w342') : '',
        'date'   => $date,
        'year'   => $date ? substr($date, 0, 4) : '',
        'rate'   => isset($r['vote_average']) ? $r['vote_average'] : 0,
    );
}
