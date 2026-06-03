<?php
/**
 * Sitemap dinámico de noticias.
 * Disponible en /sitemap-noticias.xml (vía rewrite en .htaccess).
 */

header('Content-Type: application/xml; charset=utf-8');

$API_URL    = 'https://seo.app.gurumkt.com.mx/api/v1/widget/4227daf1-2c67-4541-a537-00cdb79cb6af';
$CACHE_FILE = sys_get_temp_dir() . '/gurumkt_news_cache.json';
$CACHE_TTL  = 300;
$SITE_URL   = 'https://gurumkt.com.mx';

function fetch_news_sitemap($url, $cache_file, $cache_ttl) {
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
        $cached = json_decode(@file_get_contents($cache_file), true);
        if (is_array($cached)) return $cached;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $response) {
        @file_put_contents($cache_file, $response);
        return json_decode($response, true) ?: ['articles' => []];
    }
    if (file_exists($cache_file)) {
        $stale = json_decode(@file_get_contents($cache_file), true);
        if (is_array($stale)) return $stale;
    }
    return ['articles' => []];
}

function slugify_sm($text) {
    $map = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    return trim($text, '-') ?: 'sin-titulo';
}

$data     = fetch_news_sitemap($API_URL, $CACHE_FILE, $CACHE_TTL);
$articles = $data['articles'] ?? [];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= $SITE_URL ?>/noticias</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <priority>0.7</priority>
    </url>
<?php
$used = [];
foreach ($articles as $a) {
    $title = $a['title'] ?? '';
    $base  = slugify_sm($title);
    $slug  = $base;
    $n     = 2;
    while (isset($used[$slug])) { $slug = $base . '-' . $n++; }
    $used[$slug] = true;
    $raw_date = $a['scheduled_date'] ?? $a['created_at'] ?? null;
    $ts       = $raw_date ? strtotime($raw_date) : false;
    $lastmod  = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
?>
    <url>
        <loc><?= htmlspecialchars($SITE_URL . '/noticias/' . $slug) ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <priority>0.6</priority>
    </url>
<?php } ?>
</urlset>
