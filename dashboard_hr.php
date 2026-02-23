<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT full_name, email, department, role, approval_status, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  header("Location: login.php");
  exit;
}

if ((int)$user['profile_completed'] !== 1) {
  header("Location: profile_setup.php");
  exit;
}

if (($user['approval_status'] ?? '') !== 'approved') {
  header("Location: waiting_approval.php");
  exit;
}

// HR only
if (($user['role'] ?? '') !== 'hr') {
  if (($user['role'] ?? '') === 'employee') { header("Location: dashboard_employee.php"); exit; }
  if (($user['role'] ?? '') === 'head') { header("Location: dashboard_head.php"); exit; }
  header("Location: login.php"); exit;
}

$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT full_name, email, department, emp_id, role, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }

$_SESSION['user_role'] = $user['role'] ?? 'employee';
// paste guard snippet here

if (($_SESSION['user_role'] ?? '') !== 'hr') {
  header("Location: login.php");
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HR Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
  <header class="border-b bg-white">
    <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between">
      <div>
        <p class="font-bold">HR Staff Dashboard</p>
        <p class="text-sm text-slate-500">System Administration</p>
      </div>
      <a class="px-4 py-2 rounded-lg border hover:bg-slate-100" href="logout.php">Logout</a>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-8">
    <div class="rounded-2xl bg-white border p-6">
      <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h1>
      <p class="mt-1 text-slate-600">Manage employees, attendance, announcements, and system calendar.</p>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <a href="employees.php" class="rounded-2xl bg-white border p-6 hover:bg-slate-50">
        <p class="font-semibold">Employee Records</p>
        <p class="text-sm text-slate-600 mt-1">Add/edit employees by department</p>
      </a>
      <a href="leave_admin.php" class="rounded-2xl bg-white border p-6 hover:bg-slate-50">
        <p class="font-semibold">Leave Management</p>
        <p class="text-sm text-slate-600 mt-1">Monitor approvals and logs</p>
      </a>
      <a href="announcements.php" class="rounded-2xl bg-white border p-6 hover:bg-slate-50">
        <p class="font-semibold">Announcements</p>
        <p class="text-sm text-slate-600 mt-1">Post system-wide announcements</p>
      </a>
    </div>
  </main>
</body>
</html>