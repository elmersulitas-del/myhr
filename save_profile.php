<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

$emp_id = trim($_POST['emp_id'] ?? '');
$department = trim($_POST['department'] ?? '');
$role = $_POST['role'] ?? '';

$allowedRoles = ['employee', 'head', 'hr'];
if ($emp_id === '' || $department === '' || !in_array($role, $allowedRoles, true)) {
  header("Location: profile_setup.php");
  exit;
}

// Decide approval status
// Employee -> pending_head
// Department Head -> pending_hr
// HR -> approved
$approval_status = 'pending_head';
if ($role === 'head') $approval_status = 'pending_hr';
if ($role === 'hr') $approval_status = 'approved';

// Upload config
$uploadDir = __DIR__ . "/uploads/user_docs/";
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

// Allowed file types
$allowedExt = ['pdf','doc','docx','jpg','jpeg','png'];
$allowedMime = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'image/jpeg',
  'image/png'
];

// Helper to upload one file input
function upload_one($conn, $userId, $uploadDir, $inputName, $docType, $allowedExt, $allowedMime, $required = false) {
  if (!isset($_FILES[$inputName])) {
    return $required ? false : true;
  }

  $f = $_FILES[$inputName];

  if ($f['error'] === UPLOAD_ERR_NO_FILE) {
    return $required ? false : true;
  }
  if ($f['error'] !== UPLOAD_ERR_OK) {
    return false;
  }

  $tmpName  = $f['tmp_name'];
  $origName = $f['name'];
  $size     = (int)$f['size'];

  // Limit size (10MB)
  if ($size > 10 * 1024 * 1024) return false;

  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) return false;

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($tmpName);
  if (!$mime || !in_array($mime, $allowedMime, true)) return false;

  $storedName = $userId . "_" . bin2hex(random_bytes(12)) . "." . $ext;
  $destPath = $uploadDir . $storedName;

  if (!move_uploaded_file($tmpName, $destPath)) return false;

  $ins = $conn->prepare("INSERT INTO user_files (user_id, doc_type, original_name, stored_name, mime_type, file_size)
                         VALUES (?, ?, ?, ?, ?, ?)");
  $ins->bind_param("issssi", $userId, $docType, $origName, $storedName, $mime, $size);
  $ins->execute();

  return true;
}

// ✅ REQUIRED uploads
$resumeOk = upload_one($conn, $userId, $uploadDir, 'resume_file', 'resume', $allowedExt, $allowedMime, true);
$sssOk    = upload_one($conn, $userId, $uploadDir, 'sss_file', 'sss', $allowedExt, $allowedMime, true);

if (!$resumeOk || !$sssOk) {
  $_SESSION['login_error'] = "Please upload valid required documents (Resume and SSS).";
  header("Location: profile_setup.php");
  exit;
}

// Optional uploads
upload_one($conn, $userId, $uploadDir, 'philhealth_file', 'philhealth', $allowedExt, $allowedMime, false);
upload_one($conn, $userId, $uploadDir, 'pagibig_file', 'pagibig', $allowedExt, $allowedMime, false);
upload_one($conn, $userId, $uploadDir, 'tin_file', 'tin', $allowedExt, $allowedMime, false);
upload_one($conn, $userId, $uploadDir, 'id_photo_file', 'id_photo', $allowedExt, $allowedMime, false);

// Additional multiple files (optional)
if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
  $count = count($_FILES['documents']['name']);

  for ($i = 0; $i < $count; $i++) {
    $error = $_FILES['documents']['error'][$i];

    if ($error === UPLOAD_ERR_NO_FILE) continue;
    if ($error !== UPLOAD_ERR_OK) continue;

    $tmpName  = $_FILES['documents']['tmp_name'][$i];
    $origName = $_FILES['documents']['name'][$i];
    $size     = (int)$_FILES['documents']['size'][$i];

    if ($size > 10 * 1024 * 1024) continue;

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) continue;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!$mime || !in_array($mime, $allowedMime, true)) continue;

    $storedName = $userId . "_" . bin2hex(random_bytes(12)) . "." . $ext;
    $destPath = $uploadDir . $storedName;

    if (!move_uploaded_file($tmpName, $destPath)) continue;

    $docType = 'other';
    $ins = $conn->prepare("INSERT INTO user_files (user_id, doc_type, original_name, stored_name, mime_type, file_size)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $ins->bind_param("issssi", $userId, $docType, $origName, $storedName, $mime, $size);
    $ins->execute();
  }
}

// ✅ Save profile + role + mark completed + approval status
$stmt = $conn->prepare("
  UPDATE users 
  SET emp_id = ?, department = ?, role = ?, profile_completed = 1, approval_status = ?, rejection_reason = NULL
  WHERE id = ?
");
$stmt->bind_param("ssssi", $emp_id, $department, $role, $approval_status, $userId);
$stmt->execute();

$_SESSION['user_role'] = $role;

// ✅ Redirect after first-time submit
// Employee/Head -> waiting page
if ($approval_status !== 'approved') {
  header("Location: waiting_approval.php");
  exit;
}

// HR is approved immediately (optional)
header("Location: dashboard_hr.php");
exit;