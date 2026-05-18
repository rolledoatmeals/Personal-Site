<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$siteTitle = 'Zachary Shepelsky';
$siteTagline = 'AI Automation & Web Developer based in Tampa, FL';
$pageTitle = $siteTitle . ' | ' . $siteTagline;
$siteDescription = 'Portfolio site for Zachary Shepelsky, an AI automation and web developer based in Tampa, Florida, featuring background, skills, and contact information.';
$contactEmail = 'zshepelsky@gmail.com';
$linkedinUrl = 'https://www.linkedin.com/in/zachary-shepelsky/';

$requestHost = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
$normalizedHost = strtolower(preg_replace('/:\d+$/', '', $requestHost));
$canonicalHost = 'www.zacharyshep.com';
$canonicalUrl = sprintf('https://%s/', $canonicalHost);
$hostsRequiringCanonicalRedirect = [
	'zacharyshep.com',
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
		'keywords' => 'Zachary Shepelsky, AI automation, web developer, Tampa Florida, PHP developer, social media management',
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
					'jobTitle' => 'AI Automation & Web Developer',
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
		'descriptor' => 'I build websites, automation systems, and AI integrations for real clients — focused on what actually delivers results.',
	],
	'person' => [
		'name' => $siteTitle,
		'location' => 'Tampa, Florida',
		'bio' => "I work in web development and AI automation out of Tampa, Florida. I started by building real things for real clients — websites, automation flows, CRM integrations — and learned what I needed along the way.\n\nMost of my time is spent in PHP, HTML, CSS, JavaScript, and AI tooling. I've done freelance automation work with GoHighLevel, set up webhook-driven CRM systems, built AI chatbots, and managed social media growth from zero. Every project gets the same approach: figure out what actually needs to happen, then build it properly.",
		'bio2' => "Outside of work I'm at the gym, snowboarding when the season hits, at the beach, or trying somewhere new to eat in Tampa. I try to stay active and keep my head clear away from screens.",
		'hobbies' => ['Gym', 'Snowboarding', 'Beach', 'Food'],
	],
	'skills' => [
		['name' => 'PHP',            'desc' => 'Backend logic & server-side scripting'],
		['name' => 'HTML',           'desc' => 'Semantic, accessible markup'],
		['name' => 'CSS',            'desc' => 'Responsive layouts & polished UI'],
		['name' => 'JavaScript',     'desc' => 'Dynamic interactions & browser logic'],
		['name' => 'AI Automation',  'desc' => 'Chatbots, workflows & AI integrations'],
		['name' => 'GoHighLevel',    'desc' => 'CRM pipelines & marketing automation'],
		['name' => 'CRM',            'desc' => 'Webhook configs & system integrations'],
		['name' => 'Vibe Coding',    'desc' => 'Building fast with AI-assisted tools'],
	],
	'nav' => [
		['label' => 'About', 'scroll' => 'about'],
		['label' => 'Skills', 'scroll' => 'skills'],
		['label' => 'Contact', 'scroll' => 'contact'],
	],
	'contact' => [
		'email'   => $contactEmail,
		'linkedin' => $linkedinUrl,
		'blurb'   => 'Building something and need a developer? Looking to automate your workflow? Want to talk AI? Reach out — I\'d like to hear about it.',
	],
	'nonce' => $nonce,
];

// Render to a string so headers and optional gzip transport can be applied first.
$output = $twig->render('home.html.twig', $data);

if (!headers_sent()) {
	$csp = implode('; ', [
		"default-src 'self'",
		"script-src 'self' 'nonce-{$nonce}'",
		"style-src 'self' https://fonts.googleapis.com",
		"font-src 'self' https://fonts.gstatic.com",
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

