<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT id, full_name, email, department, role, approval_status, profile_completed,
  sick_leave_balance, incentive_leave_balance, emergency_leave_balance
  FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'employee') { header("Location: login.php"); exit; }

$err = "";

function calc_days_inclusive($from, $to) {
  $a = strtotime($from);
  $b = strtotime($to);
  if (!$a || !$b) return 0;
  if ($b < $a) return 0;
  return (int)(floor(($b - $a) / 86400) + 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_type = $_POST['leave_type'] ?? '';
  $date_from  = $_POST['date_from'] ?? '';
  $date_to    = $_POST['date_to'] ?? '';
  $reason     = trim($_POST['reason'] ?? '');

  $allowed = ['sick','incentive','emergency'];
  if (!in_array($leave_type, $allowed, true)) $err = "Invalid leave type.";
  if (!$date_from || !$date_to) $err = "Please choose date range.";
  if ($reason === '') $err = "Reason is required.";

  $days = calc_days_inclusive($date_from, $date_to);
  if (!$err && $days <= 0) $err = "Invalid dates.";

  // Check available balance
  if (!$err) {
    $bal = 0;
    if ($leave_type === 'sick') $bal = (int)$user['sick_leave_balance'];
    if ($leave_type === 'incentive') $bal = (int)$user['incentive_leave_balance'];
    if ($leave_type === 'emergency') $bal = (int)$user['emergency_leave_balance'];

    if ($days > $bal) $err = "Not enough leave balance. Available: {$bal}, Requested: {$days}.";
  }

  // Sick leave >=4 requires med cert
  $medStored = null;
  if (!$err && $leave_type === 'sick' && $days >= 4) {
    if (!isset($_FILES['med_cert']) || $_FILES['med_cert']['error'] === UPLOAD_ERR_NO_FILE) {
      $err = "Medical certificate is required for Sick Leave 4 days or more.";
    } else {
      $uploadDir = __DIR__ . "/uploads/leave_docs/";
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

      $f = $_FILES['med_cert'];
      if ($f['error'] !== UPLOAD_ERR_OK) {
        $err = "Upload failed. Please try again.";
      } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf','jpg','jpeg','png'];
        if (!in_array($ext, $allowedExt, true)) {
          $err = "Med cert must be PDF/JPG/PNG.";
        } else if ((int)$f['size'] > 10 * 1024 * 1024) {
          $err = "File too large (max 10MB).";
        } else {
          $medStored = $userId . "_" . bin2hex(random_bytes(10)) . "." . $ext;
          if (!move_uploaded_file($f['tmp_name'], $uploadDir . $medStored)) {
            $err = "Could not save file.";
          }
        }
      }
    }
  }

  if (!$err) {
    $dept = $user['department'] ?? '';
    $ins = $conn->prepare("INSERT INTO leave_requests
      (user_id, department, leave_type, date_from, date_to, days, reason, med_cert_file, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_head')");
    $ins->bind_param("issssiss", $userId, $dept, $leave_type, $date_from, $date_to, $days, $reason, $medStored);
    $ins->execute();

    header("Location: dashboard_employee.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Request Leave</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
  <div class="mx-auto max-w-3xl px-4 py-10">
    <div class="rounded-2xl bg-white border p-6">
      <h1 class="text-2xl font-bold">Leave Request</h1>
      <p class="text-sm text-slate-600 mt-1">Sick/Incentive/Emergency (5 credits each)</p>

      <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
        <div class="rounded-xl border p-3"><b>Sick:</b> <?php echo (int)$user['sick_leave_balance']; ?></div>
        <div class="rounded-xl border p-3"><b>Incentive:</b> <?php echo (int)$user['incentive_leave_balance']; ?></div>
        <div class="rounded-xl border p-3"><b>Emergency:</b> <?php echo (int)$user['emergency_leave_balance']; ?></div>
      </div>

      <?php if ($err): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
          <?php echo htmlspecialchars($err); ?>
        </div>
      <?php endif; ?>

      <form class="mt-6 space-y-4" method="POST" enctype="multipart/form-data">
        <div>
          <label class="text-sm font-semibold">Leave Type</label>
          <select name="leave_type" class="mt-2 w-full rounded-xl border px-3 py-2" required>
            <option value="" disabled selected>Select</option>
            <option value="sick">Sick Leave</option>
            <option value="incentive">Incentive Leave</option>
            <option value="emergency">Emergency Leave</option>
          </select>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-semibold">Date From</label>
            <input type="date" name="date_from" class="mt-2 w-full rounded-xl border px-3 py-2" required>
          </div>
          <div>
            <label class="text-sm font-semibold">Date To</label>
            <input type="date" name="date_to" class="mt-2 w-full rounded-xl border px-3 py-2" required>
          </div>
        </div>

        <div>
          <label class="text-sm font-semibold">Reason</label>
          <textarea name="reason" rows="4" class="mt-2 w-full rounded-xl border px-3 py-2" required></textarea>
          <p class="mt-2 text-xs text-slate-500">
            Sick Leave 4+ days requires Medical Certificate upload.
          </p>
        </div>

        <div>
          <label class="text-sm font-semibold">Medical Certificate (required if Sick 4+ days)</label>
          <input type="file" name="med_cert" accept=    ".pdf,.jpg,.jpeg,.png"
            class="mt-2 block w-full text-sm text-slate-700
            file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
        </div>

        <button class="w-full rounded-xl bg-slate-900 text-white px-4 py-3 font-semibold hover:bg-slate-800">
          Submit to Department Head
        </button>
      </form>
    </div>
  </div>
</body>
</html>