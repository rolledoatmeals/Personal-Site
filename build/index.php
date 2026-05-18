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
					'address' => [
						'@type' => 'PostalAddress',
						'addressLocality' => 'Tampa',
						'addressRegion' => 'FL',
						'addressCountry' => 'US',
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
					'@type' => 'WebPage',
					'@id' => $canonicalUrl . '#webpage',
					'url' => $canonicalUrl,
					'name' => $siteTitle,
					'description' => $siteDescription,
					'isPartOf' => ['@id' => $canonicalUrl . '#website'],
					'about' => ['@id' => $canonicalUrl . '#person'],
					'inLanguage' => 'en-US',
				],
			],
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
	],
	'person' => [
		'name' => $siteTitle,
		'location' => 'Tampa, Florida',
	'bio' => "I work in web development and AI automation, based in Tampa, Florida. I got into it by building real things for real clients and learning as I went. No bootcamp, no formal training, just hands-on work. These days I spend most of my time with PHP, HTML, JavaScript, and AI tools, building automation systems, websites, and anything else that needs to run cleaner and faster. I am drawn to the intersection of AI and web development and I try to stay on top of where that is heading.\n\nI have done freelance automation work using GoHighLevel, built and integrated CRM systems with webhook configurations, set up AI chatbots, and managed social media growth from zero. Every project I take on I treat the same way: figure out what actually needs to happen and build it properly.\n\nWhen I am not working I am at the gym, snowboarding when the season hits, at the beach, or out eating somewhere good in Tampa. I try to stay active and keep my head clear outside of screens.",
		'hobbies' => ['Gym', 'Snowboarding', 'Beach', 'Food'],
	],
	'nav' => [
		['label' => 'About', 'href' => '#about'],
		['label' => 'Skills', 'href' => '#skills'],
		['label' => 'Contact', 'href' => '#contact'],
	],
	'contact' => [
		'email' => $contactEmail,
		'linkedin' => $linkedinUrl,
	],
];
// Render to a string so optional gzip transport can be applied first.
$output = $twig->render('home.html.twig', $data);

if (!headers_sent()) {
	if (!empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && extension_loaded('zlib')) {
		header('Vary: Accept-Encoding');
		ob_start('ob_gzhandler');
		echo $output;
		ob_end_flush();
		exit;
	}
}

echo $output;

