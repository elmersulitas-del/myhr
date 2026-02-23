<?php
session_start();
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('YOUR_CLIENT_ID');
$client->setClientSecret('YOUR_CLIENT_SECRET');
$client->setRedirectUri('http://localhost/hr-system/google_callback.php');

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$client->setAccessToken($token);

$oauth = new Google_Service_Oauth2($client);
$userInfo = $oauth->userinfo->get();

$email = $userInfo->email;
$name  = $userInfo->name;

/* 🔐 DOMAIN VALIDATION */
$allowed_domain = "yourschool.edu.ph";
$email_domain = substr(strrchr($email, "@"), 1);

if ($email_domain !== $allowed_domain) {
    die("Access denied. Only institutional email is allowed.");
}

/* If allowed */
$_SESSION['user_email'] = $email;
$_SESSION['user_name']  = $name;

header("Location: dashboard.php");
exit();
