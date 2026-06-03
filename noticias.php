<?php
/**
 * Guru Marketing - Blog dinámico con server-side rendering
 *
 * Rutas:
 *   /noticias                  → listado de artículos
 *   /noticias/{slug}           → artículo individual
 *   /sitemap-noticias.xml      → sitemap dinámico (ver sitemap-noticias.php)
 *
 * Fuente: API de seo.app.gurumkt.com.mx (cache 5 min en /tmp)
 */

// ====== Configuración ======
$API_URL    = 'https://seo.app.gurumkt.com.mx/api/v1/widget/4227daf1-2c67-4541-a537-00cdb79cb6af';
$CACHE_FILE = sys_get_temp_dir() . '/gurumkt_news_cache.json';
$CACHE_TTL  = 300; // 5 minutos
$SITE_URL   = 'https://gurumkt.com.mx';

// ====== Helpers ======
function fetch_news($url, $cache_file, $cache_ttl) {
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
        $cached = json_decode(@file_get_contents($cache_file), true);
        if (is_array($cached)) return $cached;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'gurumkt.com.mx',
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $response) {
        @file_put_contents($cache_file, $response);
        return json_decode($response, true) ?: ['articles' => []];
    }
    // Fallback: cache obsoleto si la API falla
    if (file_exists($cache_file)) {
        $stale = json_decode(@file_get_contents($cache_file), true);
        if (is_array($stale)) return $stale;
    }
    return ['articles' => []];
}

function slugify($text) {
    // Mapeo explícito para caracteres del español (más confiable que iconv)
    $map = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'sin-titulo';
}

function excerpt($html, $len = 160) {
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', trim($text));
    if (function_exists('mb_strlen') && mb_strlen($text) > $len) {
        $text = mb_substr($text, 0, $len) . '...';
    } elseif (strlen($text) > $len) {
        $text = substr($text, 0, $len) . '...';
    }
    return $text;
}

function normalize_thumb($thumb) {
    if (empty($thumb)) return null;
    if (preg_match('~^https?://~', $thumb)) return $thumb;
    return 'https://seo.app.gurumkt.com.mx/storage/' . ltrim($thumb, '/');
}

function format_date_es($raw) {
    if (empty($raw)) return null;
    $ts = strtotime($raw);
    if (!$ts) return null;
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return date('j', $ts) . ' ' . $meses[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function iso_date($raw) {
    if (empty($raw)) return date('c');
    $ts = strtotime($raw);
    return $ts ? date('c', $ts) : date('c');
}

// ====== Datos ======
$data     = fetch_news($API_URL, $CACHE_FILE, $CACHE_TTL);
$articles = $data['articles'] ?? [];

// Enriquecer cada artículo con slug y datos normalizados
$slug_used = [];
foreach ($articles as $i => &$a) {
    $a['index']         = $i;
    $base_slug          = slugify($a['title'] ?? '');
    $slug               = $base_slug;
    $n                  = 2;
    while (isset($slug_used[$slug])) {
        $slug = $base_slug . '-' . $n++;
    }
    $slug_used[$slug]   = true;
    $a['slug']          = $slug;
    $a['thumbnail_url'] = normalize_thumb($a['thumbnail_url'] ?? null);
    $a['date_iso']      = iso_date($a['scheduled_date'] ?? $a['created_at'] ?? null);
    $a['date_human']    = format_date_es($a['scheduled_date'] ?? $a['created_at'] ?? null) ?: 'Reciente';
}
unset($a);

// ====== Routing ======
$requested_slug = $_GET['slug'] ?? '';
$current        = null;
if ($requested_slug !== '') {
    foreach ($articles as $a) {
        if ($a['slug'] === $requested_slug) {
            $current = $a;
            break;
        }
    }
    if (!$current) {
        http_response_code(404);
    }
}

// ====== Metadata por página ======
if ($current) {
    $page_title    = htmlspecialchars($current['title']) . ' | Guru Marketing';
    $page_desc     = htmlspecialchars(excerpt($current['content'] ?? '', 160));
    $canonical     = $SITE_URL . '/noticias/' . $current['slug'];
    $og_image      = $current['thumbnail_url'] ?: ($SITE_URL . '/logo.webp');
    $og_type       = 'article';
} else {
    $page_title    = 'Noticias de Marketing Digital en Manzanillo | Guru Marketing';
    $page_desc     = 'Las últimas noticias, tendencias y estrategias de marketing digital, diseño web y publicidad en Manzanillo, Colima.';
    $canonical     = $SITE_URL . '/noticias';
    $og_image      = $SITE_URL . '/logo.webp';
    $og_type       = 'website';
}
?><!DOCTYPE html>
<html lang="es" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= $page_desc ?>">

    <link rel="canonical" href="<?= $canonical ?>">
    <link rel="alternate" hreflang="es-MX" href="<?= $canonical ?>">
    <link rel="alternate" hreflang="x-default" href="<?= $canonical ?>">

    <!-- Geo / Local SEO -->
    <meta name="geo.region" content="MX-COL">
    <meta name="geo.placename" content="Manzanillo">
    <meta name="geo.position" content="19.0522;-104.3158">
    <meta name="ICBM" content="19.0522, -104.3158">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= $og_type ?>">
    <meta property="og:url" content="<?= $canonical ?>">
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= $page_desc ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
    <meta property="og:locale" content="es_MX">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title ?>">
    <meta name="twitter:description" content="<?= $page_desc ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>">

    <link rel="stylesheet" href="/final.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
    <link rel="icon" href="/img/logo-normAsset%202.png">

    <style>
        .article-content h1 { font-size: 2.25rem; font-weight: 700; margin-bottom: 1.5rem; line-height: 1.2;
            background: linear-gradient(to right, #60a5fa, #a855f7); -webkit-background-clip: text;
            background-clip: text; -webkit-text-fill-color: transparent; }
        .article-content h2 { font-size: 1.5rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; color: #e5e7eb; }
        .article-content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #9ca3af; }
        .article-content p { margin-bottom: 1.25rem; line-height: 1.75; color: #d1d5db; }
        .article-content ul, .article-content ol { margin-bottom: 1.25rem; padding-left: 1.5rem; list-style-type: disc; color: #d1d5db; }
        .article-content li { margin-bottom: 0.5rem; }
        .article-content strong { color: white; font-weight: 600; }
        .article-content a { color: #60a5fa; text-decoration: underline; }
        .article-content img { max-width: 100%; height: auto; border-radius: 0.75rem; margin: 1.5rem 0; }
    </style>

    <?php if ($current): ?>
    <!-- Schema Article -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context'         => 'https://schema.org',
        '@type'            => 'Article',
        'headline'         => $current['title'],
        'description'      => excerpt($current['content'] ?? '', 160),
        'image'            => $current['thumbnail_url'] ?: ($SITE_URL . '/logo.webp'),
        'datePublished'    => $current['date_iso'],
        'dateModified'     => $current['date_iso'],
        'author'           => [
            '@type' => 'Organization',
            'name'  => 'Guru Marketing',
            'url'   => $SITE_URL,
        ],
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => 'Guru Marketing',
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => $SITE_URL . '/logo.webp',
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id'   => $canonical,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <!-- BreadcrumbList -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio',   'item' => $SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Noticias', 'item' => $SITE_URL . '/noticias'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $current['title'], 'item' => $canonical],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php else: ?>
    <!-- Schema Blog + BreadcrumbList -->
    <script type="application/ld+json">
    <?= json_encode([
        '@context'        => 'https://schema.org',
        '@type'           => 'Blog',
        'name'            => 'Noticias Guru Marketing',
        'description'     => $page_desc,
        'url'             => $canonical,
        'publisher'       => [
            '@type' => 'Organization',
            'name'  => 'Guru Marketing',
            'logo'  => ['@type' => 'ImageObject', 'url' => $SITE_URL . '/logo.webp'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <script type="application/ld+json">
    <?= json_encode([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio',   'item' => $SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Noticias', 'item' => $canonical],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php endif; ?>

    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init','1169052968437432'); fbq('track','PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=1169052968437432&ev=PageView&noscript=1" /></noscript>
</head>

<body class="bg-gray-900 text-white overflow-x-hidden font-sans">

    <!-- Navbar -->
    <nav class="fixed w-full z-50 transition-all duration-300 glass-nav" id="navbar">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/" class="text-2xl font-bold tracking-tighter">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">GURU</span>
                MARKETING
            </a>
            <div class="hidden md:flex space-x-8">
                <a href="/#hero" class="hover:text-blue-400 transition-colors">Inicio</a>
                <a href="/#services" class="hover:text-blue-400 transition-colors">Servicios</a>
                <a href="/#portfolio" class="hover:text-blue-400 transition-colors">Portafolio</a>
                <a href="/#contact" class="hover:text-blue-400 transition-colors">Contacto</a>
                <a href="/noticias" class="hover:text-blue-400 transition-colors">Noticias</a>
            </div>
            <a href="/#contact"
                class="hidden md:block px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full font-medium hover:scale-105 transition-transform shadow-lg shadow-purple-500/20">
                Inicia tu Proyecto
            </a>
            <button class="md:hidden text-2xl focus:outline-none p-4 min-w-[48px] min-h-[48px] flex items-center justify-center"
                id="mobile-menu-btn" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
        </div>
        <div class="md:hidden hidden bg-gray-900/95 backdrop-blur-md absolute w-full left-0 top-full border-t border-gray-800"
            id="mobile-menu">
            <div class="flex flex-col p-6 space-y-4">
                <a href="/#hero" class="block hover:text-blue-400">Inicio</a>
                <a href="/#services" class="block hover:text-blue-400">Servicios</a>
                <a href="/#portfolio" class="block hover:text-blue-400">Portafolio</a>
                <a href="/#contact" class="block hover:text-blue-400">Contacto</a>
                <a href="/noticias" class="block hover:text-blue-400">Noticias</a>
            </div>
        </div>
    </nav>

    <?php if ($current): ?>

    <!-- ===== Vista de artículo individual ===== -->
    <main class="container mx-auto px-6 pt-32 pb-12 relative z-10 min-h-screen">
        <div class="max-w-4xl mx-auto">

            <!-- Breadcrumb visible -->
            <nav class="mb-8 text-sm text-gray-400" aria-label="Breadcrumb">
                <ol class="flex flex-wrap gap-2 items-center">
                    <li><a href="/" class="hover:text-blue-400">Inicio</a></li>
                    <li><i class="fas fa-chevron-right text-xs text-gray-600"></i></li>
                    <li><a href="/noticias" class="hover:text-blue-400">Noticias</a></li>
                    <li><i class="fas fa-chevron-right text-xs text-gray-600"></i></li>
                    <li class="text-gray-300 line-clamp-1"><?= htmlspecialchars($current['title']) ?></li>
                </ol>
            </nav>

            <article class="glass-card p-8 md:p-12 rounded-3xl border border-white/5 bg-gray-900/50 backdrop-blur-xl">
                <?php if (!empty($current['thumbnail_url'])): ?>
                <div class="w-full h-64 md:h-96 rounded-2xl overflow-hidden mb-8">
                    <img src="<?= htmlspecialchars($current['thumbnail_url']) ?>"
                         alt="<?= htmlspecialchars($current['title']) ?>"
                         class="w-full h-full object-cover" loading="eager" decoding="async">
                </div>
                <?php endif; ?>

                <div class="text-xs text-gray-500 uppercase tracking-wider mb-4">
                    <i class="far fa-calendar-alt mr-1"></i> <?= htmlspecialchars($current['date_human']) ?>
                </div>

                <div class="article-content text-lg text-gray-300 leading-relaxed">
                    <?= $current['content'] /* HTML del CMS — confiamos en la fuente */ ?>
                </div>
            </article>

            <!-- Otros artículos -->
            <?php
            $others = array_filter($articles, fn($x) => $x['slug'] !== $current['slug']);
            $others = array_slice($others, 0, 3);
            ?>
            <?php if (!empty($others)): ?>
            <section class="mt-16">
                <h2 class="text-2xl font-bold mb-6">Otros artículos</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($others as $o): ?>
                    <a href="/noticias/<?= htmlspecialchars($o['slug']) ?>"
                        class="bg-gray-800/40 backdrop-blur-sm rounded-2xl hover:bg-gray-800/80 transition-all border border-white/5 hover:border-blue-500/50 hover:shadow-lg hover:shadow-blue-500/20 hover:-translate-y-1 overflow-hidden block">
                        <?php if (!empty($o['thumbnail_url'])): ?>
                        <div class="h-32 overflow-hidden"><img src="<?= htmlspecialchars($o['thumbnail_url']) ?>"
                             alt="<?= htmlspecialchars($o['title']) ?>" class="w-full h-full object-cover"
                             loading="lazy" decoding="async"></div>
                        <?php endif; ?>
                        <div class="p-4">
                            <h3 class="text-sm font-bold leading-snug line-clamp-2"><?= htmlspecialchars($o['title']) ?></h3>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <?php else: ?>

    <!-- ===== Hero del listing ===== -->
    <header class="relative pb-24 overflow-hidden" style="padding-top: 125px;">
        <div class="absolute inset-0 bg-gradient-to-b from-blue-900/20 to-gray-900 z-0"></div>
        <div class="container mx-auto px-6 relative z-10 text-center">
            <img src="/img/logo.webp" alt="Guru Marketing - Logo agencia de publicidad en Manzanillo"
                class="h-24 md:h-32 mx-auto mb-12 animate-float drop-shadow-[0_0_15px_rgba(59,130,246,0.5)]"
                loading="eager" decoding="async">
            <h1 class="text-7xl md:text-9xl font-black mb-8 tracking-tighter leading-none">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-purple-500 to-pink-500 drop-shadow-2xl">
                    GURU NOTICIAS
                </span>
            </h1>
            <div class="w-48 h-2 bg-gradient-to-r from-blue-500 to-pink-500 mx-auto rounded-full mb-12"></div>
            <p class="text-xl md:text-3xl text-gray-300 max-w-4xl mx-auto leading-relaxed font-light">
                Estrategias digitales, tendencias innovadoras y todo lo que necesitas saber para
                <span class="text-blue-400 font-bold">dominar tu mercado</span>.
            </p>
        </div>
    </header>

    <!-- ===== Grid de artículos ===== -->
    <main class="container mx-auto px-6 py-12 relative z-10 min-h-screen">

        <nav class="mb-8 text-sm text-gray-400 max-w-7xl mx-auto" aria-label="Breadcrumb">
            <ol class="flex flex-wrap gap-2 items-center">
                <li><a href="/" class="hover:text-blue-400">Inicio</a></li>
                <li><i class="fas fa-chevron-right text-xs text-gray-600"></i></li>
                <li class="text-gray-300">Noticias</li>
            </ol>
        </nav>

        <?php if (empty($articles)): ?>
            <p class="text-center text-gray-400 py-20">No hay noticias disponibles en este momento.</p>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">
            <?php foreach ($articles as $a):
                $thumb = $a['thumbnail_url'] ?: '/img/guru_blog.jpg';
                $ex    = excerpt($a['content'] ?? '', 120);
            ?>
            <a href="/noticias/<?= htmlspecialchars($a['slug']) ?>"
                class="bg-gray-800/40 backdrop-blur-sm p-0 rounded-2xl hover:bg-gray-800/80 transition-all duration-300 group flex flex-col overflow-hidden border border-white/5 hover:border-blue-500/50 hover:shadow-lg hover:shadow-blue-500/20 hover:-translate-y-1 h-full">
                <div class="h-40 overflow-hidden relative">
                    <img src="<?= htmlspecialchars($thumb) ?>"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-in-out"
                        alt="<?= htmlspecialchars($a['title']) ?>" loading="lazy" decoding="async">
                    <div class="absolute top-3 left-3">
                        <span class="px-2 py-1 text-[10px] font-bold text-white bg-blue-600/90 backdrop-blur-md rounded-md uppercase tracking-wider shadow-sm">News</span>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-90"></div>
                </div>
                <div class="p-5 flex-1 flex flex-col">
                    <h2 class="text-lg font-bold mb-2 group-hover:text-blue-400 transition-colors leading-snug line-clamp-2 text-white">
                        <?= htmlspecialchars($a['title']) ?>
                    </h2>
                    <p class="text-gray-400 text-sm mb-4 flex-1 line-clamp-3 leading-relaxed"><?= htmlspecialchars($ex) ?></p>
                    <div class="flex items-center justify-between mt-auto pt-4 border-t border-white/5">
                        <span class="text-xs text-gray-500 font-medium"><i class="far fa-calendar-alt mr-1"></i> <?= htmlspecialchars($a['date_human']) ?></span>
                        <span class="text-blue-400 text-sm font-semibold flex items-center group-hover:text-blue-300 transition-colors">
                            Leer <i class="fas fa-arrow-right ml-1 text-xs group-hover:translate-x-1 transition-transform"></i>
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>

    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-black border-t border-white/10 py-12">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-8">
                <div class="flex items-center gap-2 mb-4 md:mb-0">
                    <img src="/img/logo.webp" alt="Guru Marketing" class="h-8 grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all">
                    <span class="text-xl font-bold text-gray-500">Guru Mkt</span>
                </div>
                <div class="flex gap-6">
                    <a href="https://www.facebook.com" target="_blank" class="text-gray-400 hover:text-blue-500 transition-colors text-xl"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com" target="_blank" class="text-gray-400 hover:text-pink-500 transition-colors text-xl"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.youtube.com/channel/UC_ilu6yAwntLTIANy8DFjeQ" target="_blank" class="text-gray-400 hover:text-red-600 transition-colors text-xl"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div class="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
                <p>&copy; 2026 Guru Marketing. Todos los derechos reservados.</p>
                <div class="flex flex-wrap gap-4 mt-4 md:mt-0 justify-center md:justify-end">
                    <a href="/marketing.html" class="hover:text-white transition-colors">Marketing</a>
                    <a href="/branding.html" class="hover:text-white transition-colors">Branding</a>
                    <a href="/web-design.html" class="hover:text-white transition-colors">Diseño Web</a>
                    <a href="/editorial.html" class="hover:text-white transition-colors">Diseño Editorial</a>
                    <a href="/audiovisual.html" class="hover:text-white transition-colors">Producción Audiovisual</a>
                    <a href="/seo.html" class="hover:text-white transition-colors">SEO</a>
                    <a href="/invitaciones.html" class="hover:text-white transition-colors">Invitaciones Digitales</a>
                    <a href="/noticias" class="hover:text-white transition-colors">Noticias</a>
                    <a href="/aviso-privacidad.html" class="hover:text-white transition-colors">Aviso de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        if (btn && menu) btn.addEventListener('click', () => menu.classList.toggle('hidden'));

        // Navbar scroll
        const nav = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) nav.classList.add('bg-gray-900/95', 'backdrop-blur-md', 'shadow-lg');
            else nav.classList.remove('bg-gray-900/95', 'backdrop-blur-md', 'shadow-lg');
        });
    </script>
</body>
</html>
