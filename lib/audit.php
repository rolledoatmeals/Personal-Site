<?php
declare(strict_types=1);

/**
 * Server-side website audit. Fetches a public URL the way a phone would
 * and reports the findings DevSply audits check by hand: page weight,
 * heavy images, tap-to-call, platform, and the basic SEO signals.
 *
 * Security: only public hosts on ports 80/443, redirects re-validated
 * per hop, response bodies capped, and a small per-IP rate limit.
 */

function audit_rate_limit(string $ip, int $max = 40, int $windowSec = 3600): bool
{
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
		$ips = array_values(array_filter(array_map(
			fn($r) => $r['ip'] ?? $r['ipv6'] ?? null,
			$records
		)));
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

function audit_fetch(string $url, int $maxBytes = 3_000_000, int $timeout = 12, bool $headOnly = false): ?array
{
	for ($hop = 0; $hop < 5; $hop++) {
		$parts = parse_url($url);
		if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
			return null;
		}
		if (isset($parts['port']) && !in_array($parts['port'], [80, 443], true)) {
			return null;
		}
		$host = strtolower($parts['host'] ?? '');
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || !audit_host_is_public($host)) {
			return null;
		}

		$ch = curl_init($url);
		$body = '';
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => !$headOnly,
			CURLOPT_NOBODY => $headOnly,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
			CURLOPT_ENCODING => '',
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

		if ($code >= 300 && $code < 400 && $redirect) {
			$url = $redirect;
			continue;
		}
		return [
			'url' => $url,
			'status' => $code,
			'body' => $body,
			'bytes' => $headOnly ? (int) $sizeDown : strlen($body),
			'seconds' => round($elapsed, 2),
		];
	}
	return null;
}

function audit_run(string $inputUrl): array
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
		return ['error' => "The site answered with an error (HTTP {$res['status']}). That itself is worth fixing."];
	}

	$html = $res['body'];
	$kb = (int) round($res['bytes'] / 1024);
	$findings = [];
	$score = 100;
	$add = function (string $level, string $title, string $detail) use (&$findings) {
		$findings[] = ['level' => $level, 'title' => $title, 'detail' => $detail];
	};

	// Page weight
	if ($kb > 900) {
		$score -= 15;
		$add('bad', "The page itself is {$kb} KB", "Most of your visitors are on phones. A page this heavy makes them wait, and many won't.");
	} elseif ($kb > 400) {
		$score -= 7;
		$add('warn', "The page is {$kb} KB", "Not terrible, but leaner pages rank and convert better on mobile.");
	} else {
		$add('good', "Lean page: {$kb} KB", "The HTML itself is a reasonable size.");
	}

	// Heaviest image
	preg_match_all('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m);
	$imgs = array_slice(array_unique($m[1]), 0, 8);
	$heaviest = 0;
	$base = $res['url'];
	foreach ($imgs as $src) {
		if (str_starts_with($src, 'data:')) continue;
		$abs = audit_resolve_url($base, $src);
		if (!$abs) continue;
		$head = audit_fetch($abs, 1, 6, true);
		if ($head && $head['status'] === 200) {
			$heaviest = max($heaviest, (int) round($head['bytes'] / 1024));
		}
	}
	if ($heaviest > 800) {
		$score -= 15;
		$add('bad', "An image on this page is " . round($heaviest / 1024, 1) . " MB", "Images this large are the single most common reason a site feels slow on a phone. Resizing it is usually a one-hour fix.");
	} elseif ($heaviest > 300) {
		$score -= 7;
		$add('warn', "Largest image is {$heaviest} KB", "Compressing your images would make the page noticeably faster.");
	} elseif ($heaviest > 0) {
		$add('good', "Images look reasonably sized", "Largest one found was {$heaviest} KB.");
	}

	// Tap to call
	$tel = preg_match_all('~href=["\']tel:~i', $html);
	if ($tel === 0) {
		$score -= 15;
		$add('bad', "Nobody can tap to call you", "There's no tap-to-call link on this page. On a phone, every extra step between a visitor and your number costs you calls.");
	} else {
		$add('good', "Tap-to-call works", "$tel phone link" . ($tel === 1 ? '' : 's') . " found.");
	}

	// Title & description
	$hasTitle = (bool) preg_match('~<title[^>]*>\s*[^<\s]~i', $html);
	$hasDesc = (bool) preg_match('~<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']{20,}~i', $html);
	if (!$hasTitle || !$hasDesc) {
		$score -= 10;
		$add('bad', "Google can't describe this page", "The page is missing a proper " . (!$hasTitle ? "title" : "description") . ". That text is what shows up in search results.");
	} else {
		$add('good', "Title and description present", "Search engines have something to show.");
	}

	// Viewport
	if (!preg_match('~<meta[^>]+name=["\']viewport~i', $html)) {
		$score -= 10;
		$add('bad', "Not built for phones", "No viewport tag means the page renders as a shrunken desktop site on mobile.");
	}

	// H1
	$h1 = preg_match_all('~<h1[\s>]~i', $html);
	if ($h1 === 0) {
		$score -= 5;
		$add('warn', "No main heading", "The page has no H1. Search engines use it to understand what the page is about.");
	}

	// Schema
	if (!preg_match('~application/ld\+json~i', $html)) {
		$score -= 5;
		$add('warn', "No structured data", "Schema markup tells Google and AI search tools what your business is. Without it you're harder to surface.");
	} else {
		$add('good', "Structured data present", "Google and AI tools can read what this business is.");
	}

	// Response time
	if ($res['seconds'] > 3) {
		$score -= 10;
		$add('warn', "Slow server response: {$res['seconds']}s", "The server took a while before sending anything at all.");
	}

	// Platform
	// Match asset URLs and runtime markers only, never page copy that
	// merely mentions a platform by name.
	$platforms = [
		'Wix' => '~wixstatic\.com|_wixCIDX|parastorage\.com~i',
		'Squarespace' => '~static1\.squarespace\.com|squarespace-cdn\.com|SQUARESPACE_CONTEXT~',
		'GoDaddy Builder' => '~img1\.wsimg\.com~i',
		'Duda' => '~irp\.cdn-website\.com|dudamobile~i',
		'WordPress' => '~/wp-content/|/wp-includes/~i',
		'Webflow' => '~assets\.website-files\.com|data-wf-page~i',
		'Shopify' => '~cdn\.shopify\.com|Shopify\.theme~i',
	];
	$platform = 'Custom / unknown';
	foreach ($platforms as $name => $re) {
		if (preg_match($re, $html)) { $platform = $name; break; }
	}

	usort($findings, function ($a, $b) {
		$rank = ['bad' => 0, 'warn' => 1, 'good' => 2];
		return $rank[$a['level']] <=> $rank[$b['level']];
	});

	return [
		'url' => $res['url'],
		'score' => max(0, min(100, $score)),
		'platform' => $platform,
		'page_kb' => $kb,
		'seconds' => $res['seconds'],
		'findings' => $findings,
	];
}

function audit_resolve_url(string $base, string $rel): ?string
{
	if (preg_match('~^https?://~i', $rel)) return $rel;
	if (str_starts_with($rel, '//')) return 'https:' . $rel;
	$p = parse_url($base);
	if (!$p) return null;
	$origin = $p['scheme'] . '://' . $p['host'];
	if (str_starts_with($rel, '/')) return $origin . $rel;
	$dir = rtrim(dirname($p['path'] ?? '/'), '/');
	return $origin . $dir . '/' . $rel;
}
