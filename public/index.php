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
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
$scheme = $isHttps ? 'https' : 'http';
$siteUrl = sprintf('%s://%s', $scheme, $requestHost);
$canonicalUrl = rtrim($siteUrl, '/') . '/';

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
	'person' => [
		'name' => $siteTitle,
		'location' => 'Tampa, Florida',
		'bio' => "I work in web development and AI automation, based in Tampa, Florida. I got into it by building real things for real clients and learning as I went. I spend most of my time with PHP, HTML, JavaScript, and AI tools, building automation systems, websites, and anything else that needs to run cleaner and faster. I am drawn to the intersection of AI and web development and I try to stay on top of where that is heading.\n\nI have done freelance automation work using GoHighLevel, built and integrated CRM systems with webhook configurations, set up AI chatbots, and managed social media growth from zero. Every project I take on I treat the same way: figure out what actually needs to happen and build it properly.",
		'bio2' => "When I am not working I am at the gym, snowboarding when the season hits, at the beach, or out eating somewhere good in Tampa. I try to stay active and keep my head clear outside of screens.",
		'hobbies' => ['Gym', 'Snowboarding', 'Beach', 'Food'],
	],
	'nav' => [
		['label' => 'About', 'scroll' => 'about'],
		['label' => 'Skills', 'scroll' => 'skills'],
		['label' => 'Contact', 'scroll' => 'contact'],
	],
	'contact' => [
		'email' => $contactEmail,
		'linkedin' => $linkedinUrl,
	],
	'nonce' => $nonce,
];

// render to string so we can minify and compress
$output = $twig->render('home.html.twig', $data);

// conservative minification: skip if preformatted content exists
if (stripos($output, '<pre') === false && stripos($output, '<code') === false && stripos($output, '<textarea') === false) {
	$output = preg_replace('/<!--(?!\s*\[if).*?-->/s', '', $output);
	$output = preg_replace('/>\s+</', '><', $output);
	$output = preg_replace('/\s{2,}/', ' ', $output);
}

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

