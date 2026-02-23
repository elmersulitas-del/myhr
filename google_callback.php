<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . "/db.php";

function redirect_with_error($msg) {
  $_SESSION['login_error'] = $msg;
  header("Location: login.php");
  exit;
}

if (!isset($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
  redirect_with_error("Invalid login session. Please try again.");
}

if (!isset($_GET['code'])) {
  redirect_with_error("Login cancelled or failed. Please try again.");
}

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

try {

  $client->authenticate($_GET['code']);
  $token = $client->getAccessToken();

  if (!$token || (isset($token['error']))) {
    $err = $token['error'] ?? 'Unknown error';
    redirect_with_error("Google login error: " . $err);
  }

  $client->setAccessToken($token);

  $oauth = new Google_Service_Oauth2($client);
  $userInfo = $oauth->userinfo->get();

  $email = strtolower($userInfo->email ?? '');
  $name  = $userInfo->name ?? '';
  $gid   = $userInfo->id ?? '';

  if (!$email) {
    redirect_with_error("No email returned by Google.");
  }

  // ✅ Restrict to institutional domain
  $domain = substr(strrchr($email, "@"), 1);
  if ($domain !== ALLOWED_DOMAIN) {
    session_unset();
    session_destroy();
    session_start();
    redirect_with_error("Only @" . ALLOWED_DOMAIN . " accounts are allowed.");
  }

  // Store basic Google info
  $_SESSION['user'] = [
    'google_id' => $gid,
    'name'      => $name,
    'email'     => $email
  ];

  // ✅ CHECK USER IN DATABASE
  $stmt = $conn->prepare("
      SELECT id, profile_completed, role, approval_status
      FROM users
      WHERE email = ?
  ");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {

    $_SESSION['user_db_id'] = (int)$row['id'];
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role']  = $row['role'] ?? 'employee';

    // 🔹 If profile not completed → go to setup
    if ((int)$row['profile_completed'] !== 1) {
      header("Location: profile_setup.php");
      exit;
    }

    // 🔹 Profile completed but NOT approved → waiting page
    if (($row['approval_status'] ?? '') !== 'approved') {
      header("Location: waiting_approval.php");
      exit;
    }

    // 🔹 Approved → redirect by role
    if ($_SESSION['user_role'] === 'hr') {
      header("Location: dashboard_hr.php");
      exit;
    } elseif ($_SESSION['user_role'] === 'head') {
      header("Location: dashboard_head.php");
      exit;
    } else {
      header("Location: dashboard_employee.php");
      exit;
    }

  } else {

    // ✅ FIRST TIME LOGIN → INSERT USER
    $insert = $conn->prepare("
        INSERT INTO users (google_id, email, full_name, profile_completed, approval_status)
        VALUES (?, ?, ?, 0, 'pending_head')
    ");
    $insert->bind_param("sss", $gid, $email, $name);
    $insert->execute();

    $_SESSION['user_db_id'] = (int)$insert->insert_id;
    $_SESSION['user_email'] = $email;

    header("Location: profile_setup.php");
    exit;
  }

} catch (Exception $e) {
  redirect_with_error("Login failed: " . $e->getMessage());
}