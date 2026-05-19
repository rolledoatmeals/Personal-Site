<?php

declare(strict_types=1);

$docroot = __DIR__;
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = ltrim($requestPath, '/');
$target = realpath($docroot . '/' . $normalizedPath);

if ($target !== false && str_starts_with($target, $docroot . DIRECTORY_SEPARATOR) && is_file($target)) {
	$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
	$longLivedExtensions = ['css', 'js', 'jpg', 'jpeg', 'webp', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf'];
	$mimeTypes = [
		'css' => 'text/css; charset=UTF-8',
		'gif' => 'image/gif',
		'ico' => 'image/x-icon',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'js' => 'application/javascript',
		'otf' => 'font/otf',
		'png' => 'image/png',
		'svg' => 'image/svg+xml',
		'ttf' => 'font/ttf',
		'webp' => 'image/webp',
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
	];
	$mimeType = $mimeTypes[$extension] ?? (mime_content_type($target) ?: 'application/octet-stream');

	header('Content-Type: ' . $mimeType);
	header('Content-Length: ' . (string) filesize($target));
	if (in_array($extension, $longLivedExtensions, true)) {
		header('Cache-Control: public, max-age=31536000, immutable');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
	}
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
		readfile($target);
	}
	return true;
}

require $docroot . '/index.php';