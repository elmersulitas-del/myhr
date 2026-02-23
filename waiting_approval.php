<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT full_name, email, department, role, approval_status, rejection_reason FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }

if ($user['approval_status'] === 'approved') {
  // redirect based on role
  if ($user['role'] === 'hr') { header("Location: dashboard_hr.php"); exit; }
  if ($user['role'] === 'head') { header("Location: dashboard_head.php"); exit; }
  header("Location: dashboard_employee.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Waiting for Approval</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-xl rounded-3xl border bg-white p-8 shadow-sm">
      <h1 class="text-2xl font-extrabold">Approval Pending</h1>
      <p class="mt-2 text-sm text-slate-600">
        Hi <span class="font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></span>,
        your account is not yet activated.
      </p>

      <?php if ($user['approval_status'] === 'pending_head') { ?>
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <p class="font-semibold text-amber-900">Waiting for Department Head Approval</p>
          <p class="mt-1 text-sm text-amber-800">
            Your department head will verify that you belong to
            <span class="font-semibold"><?php echo htmlspecialchars($user['department']); ?></span>.
          </p>
        </div>
      <?php } elseif ($user['approval_status'] === 'pending_hr') { ?>
        <div class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-4">
          <p class="font-semibold text-blue-900">Waiting for HR Approval</p>
          <p class="mt-1 text-sm text-blue-800">
            HR will validate your profile and documents before activation.
          </p>
        </div>
      <?php } elseif ($user['approval_status'] === 'rejected') { ?>
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4">
          <p class="font-semibold text-red-900">Request Rejected</p>
          <p class="mt-1 text-sm text-red-800">
            Reason: <?php echo htmlspecialchars($user['rejection_reason'] ?? 'No reason provided'); ?>
          </p>
          <p class="mt-2 text-sm text-red-800">
            Please contact your Department Head / HR.
          </p>
        </div>
      <?php } ?>

      <div class="mt-6 flex items-center justify-between">
        <a href="logout.php" class="text-sm font-semibold text-slate-700 hover:underline">Logout</a>
        <button onclick="location.reload()" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
          Refresh Status
        </button>
      </div>
    </div>
  </div>
</body>
</html>