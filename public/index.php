<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$data = [
	'site' => [
		'title' => 'Zachary Shepelsky',
		'tagline' => 'AI Automation & Web Developer based in Tampa, FL',
	],
	'person' => [
		'name' => 'Zachary Shepelsky',
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
		'email' => 'zshepelsky@gmail.com',
		'linkedin' => 'https://www.linkedin.com/in/zachary-shepelsky/',
	],
];

echo $twig->render('home.html.twig', $data);

