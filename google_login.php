<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

$client->setScopes([
  'openid',
  'email',
  'profile'
]);

// Optional but recommended
$client->setPrompt('select_account');
$client->setAccessType('online');

// CSRF protection
$_SESSION['oauth2state'] = bin2hex(random_bytes(16));
$client->setState($_SESSION['oauth2state']);

$authUrl = $client->createAuthUrl();
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;