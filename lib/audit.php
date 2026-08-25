<?php
declare(strict_types=1);

/**
 * Website audit. Fetches a public URL the way a phone would and reports
 * what actually costs a local business customers: whether the page loads
 * fast, works on a phone, can be found in search, and lets someone get
 * in touch.
 *
 * Security: public hosts only, ports 80/443, redirects re-validated per
 * hop, response bodies capped, per-IP rate limit.
 */

const AUDIT_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

function audit_rate_limit(string $ip, int $max = 40, int $windowSec = 3600): bool
{
	if ($ip === '127.0.0.1' || $ip === '::1') {
		return true;
	}
	$file = sys_get_temp_dir() . '/audit-rl-' . md5($ip);
	$now = time();
	$hits = [];
	if (is_file($file)) {
		$hits = array_filter(
			array_map('intval', file($file, FILE_IGNORE_NEW_LINES) ?: []),
			fn($t) => $now - $t < $windowSec
		);
	}
	if (count($hits) >= $max) {
		return false;
	}
	$hits[] = $now;
	file_put_contents($file, implode("\n", $hits));
	return true;
}

function audit_host_is_public(string $host): bool
{
	if (filter_var($host, FILTER_VALIDATE_IP)) {
		$ips = [$host];
	} else {
		$records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
		$ips = array_values(array_filter(array_map(fn($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
		if ($ips === []) {
			return false;
		}
	}
	foreach ($ips as $ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			return false;
		}
	}
	return true;
}

function audit_url_allowed(string $url): bool
{
	$p = parse_url($url);
	if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true)) return false;
	if (isset($p['port']) && !in_array($p['port'], [80, 443], true)) return false;
	$host = strtolower($p['host'] ?? '');
	if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) return false;
	return audit_host_is_public($host);
}

function audit_fetch(string $url, int $maxBytes = 3_000_000, int $timeout = 12, bool $headOnly = false): ?array
{
	for ($hop = 0; $hop < 5; $hop++) {
		if (!audit_url_allowed($url)) return null;

		$ch = curl_init($url);
		$body = '';
		$headers = [];
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => !$headOnly,
			CURLOPT_NOBODY => $headOnly,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_USERAGENT => AUDIT_UA,
			CURLOPT_ENCODING => '',
			CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) {
					$headers[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
				return strlen($line);
			},
		]);
		if (!$headOnly) {
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($c, $chunk) use (&$body, $maxBytes) {
				$body .= $chunk;
				return strlen($body) > $maxBytes ? 0 : strlen($chunk);
			});
		}
		$start = microtime(true);
		curl_exec($ch);
		$elapsed = microtime(true) - $start;
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
		$sizeDown = (float) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
		$finalScheme = parse_url($url, PHP_URL_SCHEME);

		if ($code >= 300 && $code < 400 && $redirect) {
			$url = $redirect;
			continue;
		}
		return [
			'url' => $url,
			'scheme' => $finalScheme,
			'status' => $code,
			'body' => $body,
			'headers' => $headers,
			'bytes' => $headOnly ? (int) $sizeDown : strlen($body),
			'seconds' => round($elapsed, 2),
		];
	}
	return null;
}

function audit_resolve_url(string $base, string $rel): ?string
{
	$rel = trim($rel);
	if ($rel === '' || str_starts_with($rel, 'data:')) return null;
	if (preg_match('~^https?://~i', $rel)) return $rel;
	if (str_starts_with($rel, '//')) return 'https:' . $rel;
	$p = parse_url($base);
	if (!$p || !isset($p['host'])) return null;
	$origin = ($p['scheme'] ?? 'https') . '://' . $p['host'];
	if (str_starts_with($rel, '/')) return $origin . $rel;
	$dir = rtrim(dirname($p['path'] ?? '/'), '/');
	return $origin . $dir . '/' . $rel;
}

function audit_weigh(array $urls, int $limit): array
{
	$totalKb = 0;
	$heaviest = ['kb' => 0, 'url' => ''];
	foreach (array_slice($urls, 0, $limit) as $u) {
		$h = audit_fetch($u, 1, 6, true);
		if (!$h || $h['status'] !== 200) continue;

		// A HEAD never downloads a body, so size_download is 0. The real
		// number lives in content-length; if the server omits it, fall back
		// to a small GET and measure what comes back.
		$bytes = (int) ($h['headers']['content-length'] ?? 0);
		if ($bytes === 0) {
			$g = audit_fetch($u, 1_500_000, 8, false);
			$bytes = $g ? $g['bytes'] : 0;
		}
		if ($bytes <= 0) continue;

		$kb = (int) round($bytes / 1024);
		$totalKb += $kb;
		if ($kb > $heaviest['kb']) $heaviest = ['kb' => $kb, 'url' => $u];
	}
	return ['total' => $totalKb, 'heaviest' => $heaviest];
}


/**
 * Work out what kind of site this is, because the same finding means
 * different things depending. A missing phone number is fatal for a body
 * shop and irrelevant for a portfolio.
 *
 * Returns the type, a confidence, and the evidence behind the call so the
 * visitor can see why and correct it.
 */
function audit_detect_type(string $html, string $visible): array
{
	$scores = ['local' => 0, 'ecommerce' => 0, 'portfolio' => 0, 'saas' => 0];
	$why = [];

	// schema.org is the strongest signal when it is present
	if (preg_match('~"@type"\s*:\s*"(Product|Offer|AggregateOffer)"~i', $html)) {
		$scores['ecommerce'] += 5; $why[] = 'product schema markup';
	}
	if (preg_match('~"@type"\s*:\s*"(LocalBusiness|Restaurant|Store|AutoRepair|Dentist|MedicalBusiness|HealthAndBeautyBusiness|ProfessionalService|HomeAndConstructionBusiness)"~i', $html, $m)) {
		$scores['local'] += 5; $why[] = $m[1] . ' schema markup';
	}
	if (preg_match('~"@type"\s*:\s*"SoftwareApplication"~i', $html)) {
		$scores['saas'] += 5; $why[] = 'software schema markup';
	}
	if (preg_match('~"@type"\s*:\s*"Person"~i', $html) && !preg_match('~"@type"\s*:\s*"(Product|LocalBusiness)"~i', $html)) {
		$scores['portfolio'] += 3; $why[] = 'person schema markup';
	}

	// storefront platforms and cart mechanics
	if (preg_match('~cdn\.shopify\.com|Shopify\.theme|woocommerce|bigcommerce|snipcart|/cart/add~i', $html)) {
		$scores['ecommerce'] += 4; $why[] = 'a storefront platform';
	}
	if (preg_match('~\b(add to cart|add to bag|shopping cart|proceed to checkout|view cart)\b~i', $visible)) {
		$scores['ecommerce'] += 3; $why[] = 'cart and checkout wording';
	}
	if (preg_match('~\$\s?\d{1,3}(?:[.,]\d{2})\b~', $visible) && preg_match('~\b(buy|shop|order)\b~i', $visible)) {
		$scores['ecommerce'] += 2; $why[] = 'listed prices with buy wording';
	}

	// signals of a place you visit or call
	$hasTel = (bool) preg_match('~href=["\']tel:~i', $html);
	$hasAddr = (bool) preg_match('~\b[A-Z]{2}\s*\d{5}\b~', $html);
	if ($hasTel && $hasAddr) { $scores['local'] += 3; $why[] = 'a phone number and street address'; }
	if (preg_match('~google\.com/maps|maps\.google~i', $html)) { $scores['local'] += 2; $why[] = 'an embedded map'; }
	if (preg_match('~\b(hours|open today|mon(day)?\s*[-–]\s*fri|book (an )?appointment|schedule (a |an )?(visit|appointment|consultation)|walk[- ]ins)\b~i', $visible)) {
		$scores['local'] += 3; $why[] = 'opening hours or appointment booking';
	}
	if (preg_match('~\b(serving|proudly serving|areas we serve|service area)\b~i', $visible)) {
		$scores['local'] += 2; $why[] = 'a stated service area';
	}

	// software product signals
	if (preg_match('~\b(start (your )?free trial|sign up free|get started free|book a demo|request a demo|no credit card required)\b~i', $visible)) {
		$scores['saas'] += 4; $why[] = 'trial or demo calls to action';
	}
	if (preg_match('~\b(per month|/mo\b|per user|pricing plans|billed annually)\b~i', $visible)) {
		$scores['saas'] += 2; $why[] = 'subscription pricing language';
	}
	if (preg_match('~href=["\'][^"\']*\b(login|sign-?in|app\.|dashboard)~i', $html)) {
		$scores['saas'] += 2; $why[] = 'a login or app link';
	}

	// personal or portfolio signals
	if (preg_match('~\b(my portfolio|selected work|my work|case studies|about me|i\'m a |i am a |hire me|resume|curriculum vitae)\b~i', $visible)) {
		$scores['portfolio'] += 3; $why[] = 'portfolio or personal wording';
	}
	if (preg_match('~\b(freelance|available for work|get in touch)\b~i', $visible) && !$hasAddr) {
		$scores['portfolio'] += 2; $why[] = 'freelance or personal framing';
	}

	arsort($scores);
	$top = array_key_first($scores);
	$topScore = $scores[$top];
	$second = array_values($scores)[1] ?? 0;

	// Not enough to call it. Default to local business, which is the most
	// common case for this tool and the strictest ruleset.
	if ($topScore < 3) {
		return ['type' => 'local', 'confidence' => 'low', 'why' => [], 'auto' => true];
	}

	return [
		'type' => $top,
		'confidence' => ($topScore >= 5 && $topScore - $second >= 3) ? 'high' : 'medium',
		'why' => array_slice(array_unique($why), 0, 3),
		'auto' => true,
	];
}

function audit_type_label(string $t): string
{
	return [
		'local' => 'Local business',
		'ecommerce' => 'Online store',
		'portfolio' => 'Portfolio or personal site',
		'saas' => 'Software or app',
	][$t] ?? 'Local business';
}

function audit_run(string $inputUrl, string $typeOverride = ''): array
{
	$url = trim($inputUrl);
	if (!preg_match('~^https?://~i', $url)) {
		$url = 'https://' . $url;
	}

	$res = audit_fetch($url);
	if (!$res || $res['status'] === 0) {
		return ['error' => "Couldn't reach that site. Check the address and try again."];
	}
	if ($res['status'] >= 400) {
		return ['error' => "The site answered with an error (HTTP {$res['status']}). That alone is worth fixing."];
	}

	$html = $res['body'];
	$base = $res['url'];
	$htmlKb = (int) round($res['bytes'] / 1024);
	$origin = (parse_url($base, PHP_URL_SCHEME) ?: 'https') . '://' . parse_url($base, PHP_URL_HOST);

	// Categories carry their own weight so one bad area can't sink the score alone.
	$cats = [
		'speed'   => ['label' => 'Speed',            'weight' => 25, 'lost' => 0, 'findings' => []],
		'mobile'  => ['label' => 'Phone experience', 'weight' => 20, 'lost' => 0, 'findings' => []],
		'search'  => ['label' => 'Getting found',    'weight' => 15, 'lost' => 0, 'findings' => []],
		'contact' => ['label' => 'Getting in touch', 'weight' => 16, 'lost' => 0, 'findings' => []],
		'copy'    => ['label' => 'How the writing reads', 'weight' => 12, 'lost' => 0, 'findings' => []],
		'design'  => ['label' => 'How current it looks',  'weight' => 12, 'lost' => 0, 'findings' => []],
	];
	$add = function (string $cat, string $level, int $cost, string $title, string $detail) use (&$cats) {
		$cats[$cat]['findings'][] = ['level' => $level, 'title' => $title, 'detail' => $detail];
		$cats[$cat]['lost'] += $cost;
	};

	// ---- Is the page rendered by JavaScript? Everything else depends on this.
	$visible = trim(preg_replace('~\s+~', ' ', strip_tags(
		preg_replace('~<(script|style|noscript|template)[^>]*>.*?</\1>~is', ' ', $html)
	)));
	$scriptSrcs = preg_match_all('~<script[^>]*src=~i', $html);
	$isShell = mb_strlen($visible) < 600 && $scriptSrcs > 0;

	// ---- What kind of site is this?
	$valid = ['local', 'ecommerce', 'portfolio', 'saas'];
	$detected = audit_detect_type($html, $visible);
	if (in_array($typeOverride, $valid, true)) {
		$siteType = $typeOverride;
		$detected['auto'] = false;
	} else {
		$siteType = $detected['type'];
	}

	// A missing phone number is fatal for a body shop and irrelevant for a
	// portfolio, so category weights move with the type.
	if ($siteType === 'portfolio') {
		$cats['contact']['weight'] = 8;
		$cats['search']['weight'] = 12;
		$cats['design']['weight'] = 18;
	} elseif ($siteType === 'ecommerce') {
		$cats['speed']['weight'] = 30;
		$cats['contact']['weight'] = 12;
		$cats['search']['weight'] = 18;
	} elseif ($siteType === 'saas') {
		$cats['contact']['weight'] = 10;
		$cats['copy']['weight'] = 16;
		$cats['design']['weight'] = 16;
	}

	// ---- SPEED
	preg_match_all('~<(?:script[^>]+src|link[^>]+href)=["\']([^"\']+\.(?:js|css)[^"\']*)["\']~i', $html, $am);
	$assetUrls = array_values(array_filter(array_map(
		fn($a) => audit_resolve_url($base, $a),
		array_unique($am[1])
	)));
	$assets = audit_weigh($assetUrls, 10);
	$totalKb = $htmlKb + $assets['total'];

	if ($htmlKb > 700) {
		$add('speed', 'bad', 12, "The HTML alone is {$htmlKb} KB",
			"Before a single image or script, the page document itself is this big. That is usually page-builder bloat, and it delays everything that comes after.");
	} elseif ($htmlKb > 300) {
		$add('speed', 'warn', 5, "Bulky HTML: {$htmlKb} KB",
			"The document is heavier than it needs to be, which slows down first paint.");
	}

	if ($totalKb > 2500) {
		$add('speed', 'bad', 18, "This page downloads " . round($totalKb / 1024, 1) . " MB",
			"That's the HTML plus its scripts and stylesheets. On a phone that's a long wait, and a lot of people won't stay for it.");
	} elseif ($totalKb > 1200) {
		$add('speed', 'warn', 9, "This page downloads {$totalKb} KB",
			"Heavier than it needs to be once scripts and stylesheets are counted.");
	} elseif ($htmlKb <= 300) {
		$add('speed', 'good', 0, "Page weight is fine: {$totalKb} KB",
			"HTML plus scripts and stylesheets. Nothing bloated.");
	}

	// An <img> inside a <picture> with a modern <source> is a fallback that
	// current browsers never download. Measuring it reports a problem the
	// visitor does not actually have, so drop those from the image scan.
	$fallbackOnly = [];
	if (preg_match_all('~<picture\b[^>]*>(.*?)</picture>~is', $html, $pics)) {
		foreach ($pics[1] as $inner) {
			if (preg_match('~<source[^>]+type=["\']image/(webp|avif)~i', $inner)
				&& preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $inner, $fb)) {
				$fallbackOnly[] = $fb[1];
			}
		}
	}

	preg_match_all('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $i1);
	preg_match_all('~<source[^>]+srcset=["\']([^"\', ]+)~i', $html, $i2);
	preg_match_all('~url\((["\']?)([^"\')]+\.(?:jpg|jpeg|png|webp|avif))\1\)~i', $html, $i3);
	preg_match_all('~(?:data-src|data-image|content)=["\']([^"\']+\.(?:jpg|jpeg|png|webp|avif)[^"\']*)["\']~i', $html, $i4);
	preg_match_all('~https?://[^"\'\s\\)]+\.(?:jpg|jpeg|png|webp|avif)~i', $html, $i5);
	$candidates = array_diff(
		array_unique(array_merge($i1[1], $i2[1], $i3[2], $i4[1], $i5[0])),
		$fallbackOnly
	);
	$imgUrls = array_values(array_filter(array_map(
		fn($a) => audit_resolve_url($base, $a),
		$candidates
	)));
	$imgs = audit_weigh($imgUrls, 8);
	$heavyKb = $imgs['heaviest']['kb'];

	if ($heavyKb > 800) {
		$add('speed', 'bad', 12, "One image on this page is " . round($heavyKb / 1024, 1) . " MB",
			"Oversized images are the most common reason a site feels slow on a phone. Resizing this is usually an hour of work.");
	} elseif ($heavyKb > 300) {
		$add('speed', 'warn', 6, "Largest image is {$heavyKb} KB",
			"Compressing your images would make this page noticeably faster.");
	} elseif ($heavyKb > 0) {
		$add('speed', 'good', 0, "Images are sized sensibly", "Largest one found was {$heavyKb} KB.");
	}

	$enc = strtolower($res['headers']['content-encoding'] ?? '');
	if ($enc === '') {
		$add('speed', 'warn', 5, "Text isn't compressed",
			"The server sends the page uncompressed. Turning on gzip or brotli usually cuts transfer size by two thirds and costs nothing.");
	}

	if ($res['seconds'] > 3) {
		$add('speed', 'bad', 10, "Slow server: {$res['seconds']}s to first byte",
			"The server took that long before sending anything at all. Visitors stare at a blank screen for that whole time.");
	} elseif ($res['seconds'] > 1.2) {
		$add('speed', 'warn', 4, "Server took {$res['seconds']}s to respond", "Not alarming, but faster hosting would help.");
	}

	// ---- PHONE EXPERIENCE
	if ($isShell) {
		$add('mobile', 'bad', 18, "The page is blank until JavaScript runs",
			"The HTML holds almost no readable text, so everything is drawn by JavaScript after loading. Search engines and AI tools often see an empty page, and anyone on a weak connection waits in front of nothing.");
	}
	if (!preg_match('~<meta[^>]+name=["\']viewport~i', $html)) {
		$add('mobile', 'bad', 14, "Not built for phones",
			"There's no viewport tag, so this renders as a shrunk-down desktop site on mobile. Visitors have to pinch and zoom to read anything.");
	} else {
		$add('mobile', 'good', 0, "Adapts to phone screens", "The viewport tag is set correctly.");
	}

	$imgTags = preg_match_all('~<img[^>]*>~i', $html, $allImgs);
	$noAlt = 0;
	foreach ($allImgs[0] ?? [] as $tag) {
		if (!preg_match('~\salt=~i', $tag)) $noAlt++;
	}
	if ($imgTags > 0 && $noAlt / $imgTags > 0.5) {
		$add('mobile', 'warn', 6, "$noAlt of $imgTags images have no alt text",
			"Alt text is what screen readers announce and what Google reads. Missing it hurts both accessibility and search.");
	}

	// ---- GETTING FOUND
	preg_match('~<title[^>]*>(.*?)</title>~is', $html, $tm);
	$title = trim(strip_tags($tm[1] ?? ''));
	preg_match('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)~i', $html, $dm);
	$desc = trim($dm[1] ?? '');

	if ($title === '') {
		$add('search', 'bad', 12, "No page title", "The title is the blue headline in Google results. Without one you're relying on Google to guess.");
	} elseif (mb_strlen($title) < 15 || mb_strlen($title) > 65) {
		$add('search', 'warn', 4, "Title is " . mb_strlen($title) . " characters", "Google shows roughly 50 to 60. Yours will read oddly or get cut off.");
	} else {
		$add('search', 'good', 0, "Page title looks right", "Reads well at the length Google displays.");
	}

	if ($desc === '') {
		$add('search', 'bad', 8, "No description for search results", "Google will pull a random sentence off the page instead of the pitch you'd choose.");
	} elseif (mb_strlen($desc) < 50) {
		$add('search', 'warn', 3, "Description is very short", "You have about 155 characters to sell the click. Use them.");
	} else {
		$add('search', 'good', 0, "Search description is set", "Google has something written to show.");
	}

	if (preg_match_all('~<h1[\s>]~i', $html) === 0) {
		$add('search', 'warn', 5, "No main heading on the page", "The H1 tells search engines what this page is about. Nothing here claims that role.");
	}

	if (!preg_match('~application/ld\+json~i', $html)) {
		$add('search', 'warn', 6, "No structured data", "Schema markup is how you tell Google and AI search tools your hours, address, and what you sell. Without it you're harder to surface and easier to describe wrongly.");
	} else {
		$add('search', 'good', 0, "Structured data present", "Google and AI tools can read what this business is.");
	}

	if (!preg_match('~property=["\']og:(title|image)~i', $html)) {
		$add('search', 'warn', 4, "Links to this page look plain when shared",
			"With no Open Graph tags, sharing this on Facebook or in a text message shows a bare link instead of a title and picture.");
	}

	$robots = audit_fetch($origin . '/robots.txt', 20000, 6);
	$sitemapOk = false;
	if ($robots && $robots['status'] === 200 && stripos($robots['body'], 'sitemap') !== false) {
		$sitemapOk = true;
	} else {
		$sm = audit_fetch($origin . '/sitemap.xml', 1, 6, true);
		$sitemapOk = $sm && $sm['status'] === 200;
	}
	if (!$sitemapOk) {
		$add('search', 'warn', 4, "No sitemap Google can find", "A sitemap lists your pages so search engines index all of them rather than whatever they stumble on.");
	} else {
		$add('search', 'good', 0, "Sitemap is in place", "Search engines have a map of the site.");
	}

	// ---- GETTING IN TOUCH
	$tel = preg_match_all('~href=["\']tel:~i', $html);
	if ($isShell) {
		$add('contact', 'warn', 5, "Couldn't check the phone link",
			"Because this page renders with JavaScript, an automated pass can't confirm whether tap-to-call exists.");
	} elseif ($tel === 0 && $siteType === 'portfolio') {
		$add('contact', 'good', 0, "No phone number, which is fine here",
			"On a personal site a public phone number mostly invites spam. Email and a form are the right channels.");
	} elseif ($tel === 0 && $siteType === 'saas') {
		$add('contact', 'warn', 4, "No phone number",
			"Optional for software, though buyers evaluating a paid tool often want to reach a human first.");
	} elseif ($tel === 0) {
		$add('contact', 'bad', 14, "Nobody can tap to call you",
			"There's no tap-to-call link on this page. On a phone, every extra step between someone and your number costs you calls.");
	} else {
		$add('contact', 'good', 0, "Tap-to-call works", "$tel phone link" . ($tel === 1 ? '' : 's') . " found.");
	}

	$hasEmail = preg_match('~href=["\']mailto:~i', $html);
	$hasForm = preg_match('~<form~i', $html);
	if (!$isShell && !$hasEmail && !$hasForm) {
		$add('contact', 'bad', 10, "No form and no email address",
			"Someone who'd rather not call has no way to reach you from this page.");
	} elseif ($hasForm) {
		$add('contact', 'good', 0, "There's a contact form", "Visitors who won't call still have a way through.");
	}

	$hasAddress = preg_match('~\b[A-Z]{2}\s*\d{5}\b~', $html) || preg_match('~\d{2,6}\s+[A-Z][A-Za-z.\' ]{2,30}\s+(St|Street|Ave|Avenue|Blvd|Rd|Road|Dr|Drive|Way|Ln|Hwy|Pkwy)\b~', $html);
	if (!$isShell && !$hasAddress && !in_array($siteType, ['portfolio', 'saas'], true)) {
		$add('contact', 'warn', 6, "No address written on the page",
			"If your address only lives inside a map image, Google and AI tools can't read it. That matters for showing up in local searches.");
	}

	// ---- HOW THE WRITING READS
	// Machine-written marketing copy has a fingerprint. None of these are
	// proof on their own, so only a cluster costs points.
	$copyText = $visible;
	$words = max(1, str_word_count($copyText));
	$aiVocab = ['leverage', 'seamless', 'robust', 'cutting-edge', 'elevate', 'delve', 'unlock the power',
		'take it to the next level', 'in today\'s fast-paced', 'game-changer', 'tailored solutions',
		'holistic approach', 'unparalleled', 'bespoke solutions', 'empower your', 'revolutionize',
		'transformative', 'synergy', 'best-in-class', 'world-class'];
	$vocabHits = [];
	foreach ($aiVocab as $w) {
		if (stripos($copyText, $w) !== false) $vocabHits[] = $w;
	}
	$emDashes = substr_count($copyText, "\u{2014}");
	$notJust = preg_match_all('~\bnot (just|only)\b[^.]{0,60}\bbut\b~i', $copyText);
	$emDashRate = $words > 0 ? $emDashes / ($words / 100) : 0;

	$copySignals = count($vocabHits) + ($emDashRate > 1.2 ? 2 : 0) + ($notJust > 0 ? 1 : 0);

	if ($words < 80) {
		$add('copy', 'warn', 4, "Barely any words on the page",
			"There is almost nothing here for a visitor or a search engine to read. Copy is what convinces people, and there is not enough of it.");
	} elseif ($copySignals >= 4) {
		$add('copy', 'bad', 10, "This reads like it was written by AI",
			"Found " . count($vocabHits) . " stock marketing phrases" . ($vocabHits ? " (" . implode(', ', array_slice($vocabHits, 0, 3)) . ")" : "") .
			($emDashes > 2 ? ", plus $emDashes em dashes" : "") . ". Buyers have started noticing this voice, and it makes a real business sound generic.");
	} elseif ($copySignals >= 2) {
		$add('copy', 'warn', 5, "The writing leans generic",
			"A few stock marketing phrases showed up" . ($vocabHits ? ": " . implode(', ', array_slice($vocabHits, 0, 3)) : "") . ". Swapping them for how you actually talk would set you apart from every competitor using the same words.");
	} else {
		$add('copy', 'good', 0, "The writing sounds human", "No stock AI-marketing vocabulary and no telltale punctuation patterns.");
	}

	if ($words > 120) {
		$exclam = substr_count($copyText, '!');
		if ($exclam > 6) {
			$add('copy', 'warn', 3, "$exclam exclamation marks",
				"Heavy exclamation reads as shouting rather than confidence.");
		}
		if (preg_match('~\b(lorem ipsum|dolor sit amet|your text here|insert text|coming soon)\b~i', $copyText, $lm)) {
			$add('copy', 'bad', 8, "Placeholder text is still on the page",
				"Found \"" . trim($lm[0]) . "\" in the live copy. Visitors see it too.");
		}
	}

	// ---- HOW CURRENT IT LOOKS
	$dated = [];
	if (preg_match('~<(center|font|marquee|blink)\b~i', $html, $dm2)) $dated[] = "1990s tags like <" . strtolower($dm2[1]) . ">";
	if (preg_match('~\bbgcolor=|\bcellpadding=|\bcellspacing=~i', $html)) $dated[] = "table-based layout attributes";
	if (preg_match('~\.swf\b|application/x-shockwave-flash~i', $html)) $dated[] = "Flash, which no browser has run since 2020";
	if (preg_match('~jquery[.-](1\.[0-9]|2\.[0-9])~i', $html)) $dated[] = "a jQuery version over a decade old";
	if (preg_match('~bootstrap[/-]?3~i', $html)) $dated[] = "Bootstrap 3, retired in 2019";

	$modernImg = (bool) preg_match('~\.(webp|avif)\b~i', $html);
	$usesPicture = (bool) preg_match('~<picture~i', $html);
	$lazy = (bool) preg_match('~loading=["\']lazy~i', $html);
	$customProps = (bool) preg_match('~--[a-z-]+\s*:~', $html);
	$flexGrid = (bool) preg_match('~display\s*:\s*(flex|grid)~i', $html);

	if ($dated) {
		$add('design', 'bad', 10, "Built with techniques that are years out of date",
			"Found " . implode(' and ', array_slice($dated, 0, 2)) . ". Visitors read this as a business that stopped paying attention.");
	}
	if (!$modernImg && !$usesPicture) {
		$add('design', 'warn', 4, "Images use older formats",
			"No WebP or AVIF anywhere. Modern formats cut image size by roughly a third at the same quality, for free.");
	} else {
		$add('design', 'good', 0, "Modern image formats in use", "WebP or AVIF is being served.");
	}
	if (!$lazy && $imgTags > 6) {
		$add('design', 'warn', 3, "Every image loads at once",
			"With $imgTags images and no lazy loading, the browser downloads pictures nobody has scrolled to yet.");
	}
	if (!$dated && ($customProps || $flexGrid)) {
		$add('design', 'good', 0, "Modern layout techniques", "Built with current CSS rather than legacy workarounds.");
	}

	// ---- Checks that only make sense for this kind of site
	if ($siteType === 'ecommerce') {
		if (!preg_match('~"@type"\s*:\s*"(Product|Offer)"~i', $html)) {
			$add('search', 'bad', 8, "Products have no structured data",
				"Without product schema, Google cannot show your price, availability or rating in results. Competitors who have it take up more of the page.");
		} else {
			$add('search', 'good', 0, "Product data is marked up", "Google can show price and availability in search results.");
		}
		if (!preg_match('~\b(free shipping|returns|refund|money[- ]back|secure checkout|satisfaction guarantee)\b~i', $visible)) {
			$add('copy', 'warn', 4, "No shipping or returns reassurance",
				"Shoppers look for returns, shipping and guarantee wording before entering a card. Not finding it is a common reason carts get abandoned.");
		}
	}

	if ($siteType === 'saas') {
		if (!preg_match('~\b(sign up|get started|start free|book a demo|request a demo|try it free)\b~i', $visible)) {
			$add('copy', 'bad', 8, "No obvious next step",
				"There is no clear sign-up or demo call to action. Interested visitors have nothing to click.");
		}
		if (!preg_match('~\b(pricing|per month|/mo\b|free plan)\b~i', $visible)) {
			$add('copy', 'warn', 4, "Pricing is not mentioned",
				"Buyers who cannot find pricing often assume it is expensive and leave rather than ask.");
		}
	}

	if ($siteType === 'portfolio') {
		if (!preg_match('~\b(work|project|case stud|portfolio|built|shipped)\b~i', $visible)) {
			$add('copy', 'bad', 8, "No work shown",
				"A portfolio without visible projects asks someone to take your word for it. Show what you built.");
		}
		if (!preg_match('~github\.com|gitlab\.com|linkedin\.com|dribbble\.com|behance\.net~i', $html)) {
			$add('contact', 'warn', 4, "No links to profiles elsewhere",
				"Hiring managers look for GitHub or LinkedIn to verify what they are reading.");
		}
	}

	if ($siteType === 'local' && !preg_match('~\b(hours|open|closed|mon(day)?|appointment|walk[- ]in)\b~i', $visible)) {
		$add('contact', 'warn', 5, "No opening hours on the page",
			"Hours are one of the most looked-for things on a local business site, and a common reason someone calls instead of just showing up.");
	}

	// ---- Trust and platform, reported but not scored
	$https = ($res['scheme'] ?? '') === 'https';
	if (!$https) {
		$add('contact', 'bad', 12, "The site isn't secure",
			"No HTTPS. Browsers mark the site 'Not secure' in the address bar, and visitors notice.");
	}

	$platforms = [
		'Wix' => '~wixstatic\.com|_wixCIDX|parastorage\.com~i',
		'Squarespace' => '~static1\.squarespace\.com|squarespace-cdn\.com|SQUARESPACE_CONTEXT~',
		'GoDaddy Builder' => '~img1\.wsimg\.com~i',
		'Duda' => '~irp\.cdn-website\.com|dudamobile~i',
		'Shopify' => '~cdn\.shopify\.com~i',
		'Webflow' => '~assets\.website-files\.com|data-wf-page~i',
		'WordPress' => '~/wp-content/|/wp-includes/~i',
	];
	$platform = 'Custom or hand-built';
	foreach ($platforms as $name => $re) {
		if (preg_match($re, $html)) { $platform = $name; break; }
	}

	$hasAnalytics = (bool) preg_match('~googletagmanager|google-analytics|gtag\(|fbq\(|plausible|fathom|posthog~i', $html);

	// ---- Score
	$categories = [];
	$score = 0;
	foreach ($cats as $key => $c) {
		$pct = max(0, 100 - min(100, $c['lost'] * 100 / max(1, $c['weight'])));
		$earned = $c['weight'] * $pct / 100;
		$score += $earned;
		usort($c['findings'], function ($a, $b) {
			$rank = ['bad' => 0, 'warn' => 1, 'good' => 2];
			return $rank[$a['level']] <=> $rank[$b['level']];
		});
		$categories[] = [
			'key' => $key,
			'label' => $c['label'],
			'pct' => (int) round($pct),
			'findings' => $c['findings'],
		];
	}

	$all = array_merge(...array_column($categories, 'findings'));
	$bad = count(array_filter($all, fn($f) => $f['level'] === 'bad'));

	return [
		'url' => $base,
		'score' => (int) round($score),
		'headline' => $bad === 0
			? "Nothing broken here. This is a well-built site."
			: ($bad === 1 ? "One thing on this page is costing you customers." : "$bad things on this page are costing you customers."),
		'platform' => $platform,
		'site_type' => $siteType,
		'site_type_label' => audit_type_label($siteType),
		'detected' => $detected,
		'page_kb' => $totalKb,
		'html_kb' => $htmlKb,
		'seconds' => $res['seconds'],
		'https' => $https,
		'analytics' => $hasAnalytics,
		'js_rendered' => $isShell,
		'categories' => $categories,
	];
}
