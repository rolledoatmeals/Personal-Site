<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$siteTitle = 'Zachary Shepelsky';
$siteTagline = 'AI Operations & Automation based in Tampa, FL';
$pageTitle = $siteTitle . ' | AI Operations & Automation';
$siteDescription = 'Zachary Shepelsky builds AI operations and automation systems in Tampa, Florida. Salesforce integrations, CRM builds, and the web work in between.';
$contactEmail = 'zshepelsky@gmail.com';
$linkedinUrl = 'https://www.linkedin.com/in/zachary-shepelsky/';

$requestHost = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
$normalizedHost = strtolower(preg_replace('/:\d+$/', '', $requestHost));
$canonicalHost = 'zacharyshep.com';
$canonicalUrl = sprintf('https://%s/', $canonicalHost);
$hostsRequiringCanonicalRedirect = [
	'www.zacharyshep.com',
	'zach-peronal-site-5187db19fc90.herokuapp.com',
];

if (!headers_sent()) {
	$shouldRedirectToCanonical = in_array($normalizedHost, $hostsRequiringCanonicalRedirect, true)
		|| ($normalizedHost === $canonicalHost && !$isHttps);

	if ($shouldRedirectToCanonical) {
		header('Location: https://' . $canonicalHost . $requestUri, true, 301);
		exit;
	}
}

$nonce = base64_encode(random_bytes(16));

// Cache-bust assets on content change rather than a hand-edited number.
$assetVersion = (string) max(
	(int) @filemtime(__DIR__ . '/assets/css/style.css'),
	(int) @filemtime(__DIR__ . '/assets/js/main.js')
);

/**
 * Same headers on every route. Styles carry the nonce because the audit
 * page writes a few dynamic rules (progress widths, the score ring) that
 * cannot be known ahead of time.
 */
function audit_security_headers(string $nonce): void
{
	if (headers_sent()) {
		return;
	}
	$csp = implode('; ', [
		"default-src 'self'",
		"script-src 'self' 'nonce-{$nonce}'",
		"style-src 'self' 'nonce-{$nonce}'",
		"font-src 'self'",
		"img-src 'self' data:",
		"connect-src 'self'",
		"object-src 'none'",
		"base-uri 'self'",
		"frame-ancestors 'none'",
		"form-action 'self'",
	]);
	header("Content-Security-Policy: {$csp}");
	header('X-Frame-Options: DENY');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}


$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

if ($requestPath === '/api/audit') {
	header('Content-Type: application/json');
	require __DIR__ . '/../lib/audit.php';
	$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown')[0]);
	if (!audit_rate_limit($ip)) {
		http_response_code(429);
		echo json_encode(['error' => 'Easy there. A few audits an hour is plenty; try again in a bit.']);
		exit;
	}
	$target = (string) ($_GET['url'] ?? '');
	if ($target === '' || strlen($target) > 300) {
		http_response_code(400);
		echo json_encode(['error' => 'Give me a website address to look at.']);
		exit;
	}
	echo json_encode(audit_run($target));
	exit;
}


$data = [
	'asset_version' => $assetVersion,
	'site' => [
		'title' => $siteTitle,
		'tagline' => $siteTagline,
	],
	'seo' => [
		'title' => $pageTitle,
		'description' => $siteDescription,
		'keywords' => 'Zachary Shepelsky, AI operations, automation engineer, Salesforce, systems integration, Tampa Florida, web developer',
		'robots' => 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
		'canonical_url' => $canonicalUrl,
		'og_type' => 'website',
		'locale' => 'en_US',
		'json_ld' => json_encode([
			'@context' => 'https://schema.org',
			'@graph' => [
				[
					'@type' => 'Person',
					'@id' => $canonicalUrl . '#person',
					'name' => $siteTitle,
					'url' => $canonicalUrl,
					'jobTitle' => 'AI Operations & Automation',
					'description' => $siteDescription,
					'email' => 'mailto:' . $contactEmail,
					'image' => [
						'@type' => 'ImageObject',
						'url' => $canonicalUrl . 'assets/images/profile.jpg',
						'width' => 800,
						'height' => 800,
					],
					'address' => [
						'@type' => 'PostalAddress',
						'addressLocality' => 'Tampa',
						'addressRegion' => 'FL',
						'addressCountry' => 'US',
					],
					'knowsAbout' => [
						'PHP', 'HTML', 'CSS', 'JavaScript',
						'AI Automation', 'GoHighLevel', 'CRM Integration',
						'Webhook Configuration', 'Web Development',
						'Social Media Management', 'AI Chatbots',
					],
					'sameAs' => [$linkedinUrl],
				],
				[
					'@type' => 'WebSite',
					'@id' => $canonicalUrl . '#website',
					'url' => $canonicalUrl,
					'name' => $siteTitle,
					'description' => $siteDescription,
					'inLanguage' => 'en-US',
					'publisher' => ['@id' => $canonicalUrl . '#person'],
				],
				[
					'@type' => ['WebPage', 'ProfilePage'],
					'@id' => $canonicalUrl . '#webpage',
					'url' => $canonicalUrl,
					'name' => $pageTitle,
					'description' => $siteDescription,
					'isPartOf' => ['@id' => $canonicalUrl . '#website'],
					'about' => ['@id' => $canonicalUrl . '#person'],
					'mainEntity' => ['@id' => $canonicalUrl . '#person'],
					'inLanguage' => 'en-US',
					'dateModified' => date('Y-m-d'),
				],
			],
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
	],
	'hero' => [
		'descriptor' => 'I wire business systems together so the work runs on its own. Salesforce, CRMs, websites, and the automation in between.',
	],
	'person' => [
		'name' => $siteTitle,
		'location' => 'Tampa, Florida',
		'bio' => "I connect business systems so information moves on its own. CRMs, websites, meeting tools, the spreadsheet somebody rebuilds every Monday. If a person has to copy numbers from one screen to another each week, I can usually make that job disappear.\n\nI got here by building websites and CRM systems for clients, and I still run my own studio doing that today. The work I like best is finding what is quietly broken. A form that stopped sending. A report that has been wrong for months. Nobody notices those until someone goes looking, and going looking is most of what I do.",
		'bio2' => "Outside work I'm at the gym, snowboarding when there's a season worth catching, or finding somewhere new to eat around Tampa. I'm also taking night classes for an AI degree while working full time.",
		'hobbies' => ['Gym', 'Snowboarding', 'Beach', 'Food'],
	],
	'work' => [
		[
			'title' => 'Website Audit MCP server',
			'stat' => 'Install it in one command',
			'context' => 'Open source on GitHub',
			'blurb' => 'The same audit engine, handed to AI assistants through the Model Context Protocol. Anyone running Claude Code or Cursor installs it and just says "audit example.com". One capability, shipped two ways: a web page for people, a protocol server for agents.',
			'tags' => ['MCP', 'Node', 'Open source'],
			'url' => 'https://github.com/rolledoatmeals/website-audit-mcp',
		],
		[
			'title' => 'Website audit tool',
			'stat' => 'Try it live, right now',
			'context' => 'Running on this site',
			'blurb' => 'Paste any website address and get a scored report in about ten seconds: page weight, oversized images, tap-to-call, and whether search engines can read the page. The same checks I run by hand before quoting a client, automated.',
			'tags' => ['PHP', 'Live tool', 'Performance'],
			'url' => '/audit',
		],
		[
			'title' => 'Salesforce to Align integration',
			'stat' => 'Daily numbers, zero hands',
			'context' => 'Industrial staffing company',
			'blurb' => 'Wired the company system of record into their meeting software so daily operating numbers feed themselves every morning instead of being rebuilt by hand each week. Traced a metric-definition defect that was inflating reported figures and reconciled the feed exactly to the official weekly report.',
			'tags' => ['Salesforce', 'Apex', 'Scheduled feeds', 'Data integrity'],
			'url' => '',
		],
		[
			'title' => 'Vendor-to-Salesforce bridge',
			'stat' => 'No API? Built one anyway',
			'context' => 'Recruiting pipeline automation',
			'blurb' => 'The vendor had no export and no API worth the name. Built a scheduled cloud bot that signs in, pulls new applicants and writes them straight into Salesforce, working alongside the vendor engineering team. Also fixed the duplicate-record flow so one person applying to three jobs stays one person.',
			'tags' => ['GitHub Actions', 'Integration', 'Automation'],
			'url' => '',
		],
		[
			'title' => 'AI marketing system',
			'stat' => '5 agents, one-person output',
			'context' => 'B2B certification company',
			'blurb' => 'A brand knowledge base plus five purpose-built agents covering email, sales copy, prospect research and campaign collateral. Produces publish-ready work rather than drafts, so one person now covers what used to take a small marketing team.',
			'tags' => ['Claude API', 'Knowledge base', 'Agents'],
			'url' => '',
		],
		[
			'title' => 'DevSply',
			'stat' => '5 live client properties',
			'context' => 'My web and automation studio',
			'blurb' => 'Client sites built on Astro, React and WordPress. Five live properties across diesel repair, men\'s health and e-commerce, plus GoHighLevel CRM systems wired end to end with lead routing, follow-up and conversion tracking.',
			'tags' => ['Astro', 'React', 'WordPress', 'GoHighLevel'],
			'url' => 'https://devsply.com',
		],
		[
			'title' => 'Salon site rebuild',
			'stat' => 'Sales up 300% YTD',
			'context' => 'Wig Design by Flora',
			'blurb' => 'Built the original site, then got brought back two years later to rebuild it end to end. Restructured the architecture and shortened the path to booking an appointment.',
			'tags' => ['Booking flow', 'Performance', 'Conversion'],
			'url' => '',
		],
	],
	'skills' => [
		['name' => 'Salesforce',   'desc' => 'Flows, Apex, CLI deploys, reports and dashboards'],
		['name' => 'Integrations', 'desc' => 'REST APIs, webhooks and scheduled jobs'],
		['name' => 'AI Systems',   'desc' => 'Claude API, MCP servers, agents and knowledge bases'],
		['name' => 'Automation',   'desc' => 'Replacing manual work with jobs that run themselves'],
		['name' => 'Web',          'desc' => 'React, Astro, PHP and WordPress'],
		['name' => 'GoHighLevel',  'desc' => 'CRM pipelines, lead routing and follow-up'],
		['name' => 'Diagnosis',    'desc' => 'Root-cause tracing and data-quality audits'],
		['name' => 'AI-Assisted Delivery', 'desc' => 'Shipping production systems with AI tooling'],
	],
	'nav' => [
		['label' => 'About', 'scroll' => 'about'],
		['label' => 'Work', 'scroll' => 'work'],
		['label' => 'Skills', 'scroll' => 'skills'],
		['label' => 'Audit tool', 'href' => '/audit'],
		['label' => 'Contact', 'scroll' => 'contact'],
	],
	'contact' => [
		'email'   => $contactEmail,
		'linkedin' => $linkedinUrl,
		'blurb'   => 'Have a system that should run itself, or a team spending hours on work a machine could do? Tell me what\'s broken. I read everything.',
	],
	'nonce' => $nonce,
];

// Render to a string so headers and optional gzip transport can be applied first.
if ($requestPath === '/audit/report') {
	require_once __DIR__ . '/../lib/audit.php';
	$target = (string) ($_GET['url'] ?? '');
	$report = $target !== '' && strlen($target) <= 300 ? audit_run($target) : ['error' => 'No website address given.'];
	if (isset($report['error'])) {
		http_response_code(400);
		audit_security_headers($nonce);
		echo '<!doctype html><meta charset="utf-8"><title>Audit</title><p style="font:14px system-ui;padding:2rem">'
			. htmlspecialchars($report['error'], ENT_QUOTES) . '</p>';
		exit;
	}
	$data['report'] = $report;
	$data['nonce'] = $nonce;
	$data['seo']['robots'] = 'noindex';
	audit_security_headers($nonce);
	echo $twig->render('audit-report.html.twig', $data);
	exit;
}

if (($requestPath ?? parse_url($requestUri, PHP_URL_PATH)) === '/audit') {
	$data['seo']['title'] = 'Free Website Audit | ' . $siteTitle;
	$data['seo']['description'] = 'Paste a website address and get a plain-English report on speed, mobile usability, and search visibility in about ten seconds.';
	$data['seo']['canonical_url'] = 'https://zacharyshep.com/audit';
	unset($data['seo']['json_ld']);
	$data['nonce'] = $nonce;
	audit_security_headers($nonce);
	echo $twig->render('audit.html.twig', $data);
	exit;
}

$output = $twig->render('home.html.twig', $data);

audit_security_headers($nonce);
if (false) {
	$csp = '';
	header("Content-Security-Policy: {$csp}");
	header('X-Frame-Options: DENY');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: strict-origin-when-cross-origin');

	if (!empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && extension_loaded('zlib')) {
		header('Vary: Accept-Encoding');
		ob_start('ob_gzhandler');
		echo $output;
		ob_end_flush();
		exit;
	}
}

echo $output;

