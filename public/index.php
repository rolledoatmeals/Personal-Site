<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$siteTitle = 'Zachary Shepelsky';
$siteTagline = 'AI Operations & Automation based in Tampa, FL';
$pageTitle = $siteTitle . ' | ' . $siteTagline;
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

$data = [
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
		'bio' => "I do AI operations for an industrial staffing company. Most of that is connecting Salesforce to everything else so the numbers people depend on show up on their own instead of being rebuilt by hand every week.\n\nBefore that I built websites and CRM systems for clients, and I still do through my own studio. The work I like best is when something is quietly broken and nobody has noticed. A form that stopped sending. A report that has been wrong for months. Those are usually configuration problems rather than code problems, and finding them takes patience more than cleverness.",
		'bio2' => "Outside work I'm at the gym, snowboarding when there's a season worth catching, or finding somewhere new to eat around Tampa. I'm also taking night classes for an AI degree while working full time.",
		'hobbies' => ['Gym', 'Snowboarding', 'Beach', 'Food'],
	],
	'work' => [
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
			'blurb' => 'A brand knowledge base plus five purpose-built agents covering email, sales copy, prospect research and campaign collateral. Produces publish-ready work rather than drafts, so the marketing output of a team comes out of one person.',
			'tags' => ['Claude API', 'Knowledge base', 'Agents'],
			'url' => '',
		],
		[
			'title' => 'DevSply',
			'stat' => '5 live client properties',
			'context' => 'My web and automation studio',
			'blurb' => 'Client sites designed, built and deployed on Astro, React and WordPress. Five live properties across diesel repair, mens health and e-commerce, plus GoHighLevel CRM systems wired end to end with lead routing, follow-up and conversion tracking.',
			'tags' => ['Astro', 'React', 'WordPress', 'GoHighLevel'],
			'url' => 'https://devsply.com',
		],
		[
			'title' => 'Salon site rebuild',
			'stat' => 'Sales up 300% YTD',
			'context' => 'Wig Design by Flora',
			'blurb' => 'Built the original site, then got brought back two years later to rebuild it end to end. Restructured the architecture and shortened the path to booking an appointment. Sales grew 300 percent year to date after launch.',
			'tags' => ['Booking flow', 'Performance', 'Conversion'],
			'url' => '',
		],
		[
			'title' => 'Lender systems audit',
			'stat' => '3 critical finds, day one',
			'context' => 'Commercial real estate lender',
			'blurb' => 'Went through the website and CRM before quoting anything. Found borrower financial documents sitting publicly readable, form submissions silently failing to reach the CRM, and an ad account that had never once recorded a conversion. Scoped the rebuild in phases from there.',
			'tags' => ['Technical audit', 'Security review', 'CRM'],
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
		['label' => 'Contact', 'scroll' => 'contact'],
	],
	'contact' => [
		'email'   => $contactEmail,
		'linkedin' => $linkedinUrl,
		'blurb'   => 'If you are hiring for operations, automation, or Salesforce work, I would like to talk. Email is fastest.',
	],
	'nonce' => $nonce,
];

// Render to a string so headers and optional gzip transport can be applied first.
$output = $twig->render('home.html.twig', $data);

if (!headers_sent()) {
	$csp = implode('; ', [
		"default-src 'self'",
		"script-src 'self' 'nonce-{$nonce}'",
		"style-src 'self'",
		"font-src 'self'",
		"img-src 'self' data:",
		"object-src 'none'",
		"base-uri 'self'",
		"frame-ancestors 'none'",
		"form-action 'self'",
	]);
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

