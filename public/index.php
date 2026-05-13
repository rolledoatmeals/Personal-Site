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
		'bio' => "I am a 21 year old living in Tampa, Florida. I work in Social Media Management, AI automation and web development. Outside of work I am very active. You will find me at the gym, snowboarding, at the beach, or out trying out a new food spot somewhere in Tampa.",
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

echo $twig->render('home.html.twig', $data);

