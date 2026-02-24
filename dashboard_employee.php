<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

// Fetch user once (include balances)
$stmt = $conn->prepare("SELECT id, full_name, email, department, emp_id, role,
  approval_status, profile_completed,
  sick_leave_balance, incentive_leave_balance, emergency_leave_balance
  FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }

if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }

// Employee only
if (($user['role'] ?? '') !== 'employee') {
  if (($user['role'] ?? '') === 'head') { header("Location: dashboard_head.php"); exit; }
  if (($user['role'] ?? '') === 'hr') { header("Location: dashboard_hr.php"); exit; }
  header("Location: login.php"); exit;
}

$_SESSION['user_role'] = 'employee';

// Leave status counters (for this employee)
$counts = [
  'pending_head' => 0,
  'pending_hr_receive' => 0,
  'received' => 0,
  'rejected' => 0
];

try {
  $q = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM leave_requests
    WHERE user_id = ?
    GROUP BY status
  ");
  $q->bind_param("i", $userId);
  $q->execute();
  $res = $q->get_result();
  while ($r = $res->fetch_assoc()) {
    $st = $r['status'] ?? '';
    $total = (int)($r['total'] ?? 0);
    if ($st === 'pending_head') $counts['pending_head'] = $total;
    if ($st === 'pending_hr_receive') $counts['pending_hr_receive'] = $total;
    if ($st === 'received') $counts['received'] = $total;
    if ($st === 'rejected_head' || $st === 'rejected_hr') $counts['rejected'] += $total;
  }
} catch (Throwable $e) {
  // if leave_requests table not yet created, ignore
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800">
  <header class="border-b bg-white">
    <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between">
      <div>
        <p class="font-bold">Employee Dashboard</p>
        <p class="text-sm text-slate-500"><?php echo htmlspecialchars($user['email']); ?></p>
      </div>
      <a class="px-4 py-2 rounded-lg border hover:bg-slate-100" href="logout.php">Logout</a>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-8">
    <!-- Welcome -->
    <div class="rounded-2xl bg-white border p-6">
      <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h1>
      <p class="mt-1 text-slate-600">
        Department: <span class="font-semibold"><?php echo htmlspecialchars($user['department'] ?? '—'); ?></span>
        • Employee ID: <span class="font-semibold"><?php echo htmlspecialchars($user['emp_id'] ?? '—'); ?></span>
      </p>
    </div>

    <!-- Leave balances -->
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
      <div class="rounded-2xl bg-white border p-6">
        <p class="text-sm text-slate-600">Sick Leave</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)($user['sick_leave_balance'] ?? 0); ?></p>
        <p class="mt-1 text-xs text-slate-500">Credits available</p>
      </div>

      <div class="rounded-2xl bg-white border p-6">
        <p class="text-sm text-slate-600">Incentive Leave</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)($user['incentive_leave_balance'] ?? 0); ?></p>
        <p class="mt-1 text-xs text-slate-500">Credits available</p>
      </div>

      <div class="rounded-2xl bg-white border p-6">
        <p class="text-sm text-slate-600">Emergency Leave</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)($user['emergency_leave_balance'] ?? 0); ?></p>
        <p class="mt-1 text-xs text-slate-500">Credits available</p>
      </div>
    </div>

    <!-- Status overview -->
    <div class="mt-6 grid gap-4 sm:grid-cols-4">
      <div class="rounded-2xl bg-white border p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase">Pending Head</p>
        <p class="mt-2 text-2xl font-extrabold"><?php echo (int)$counts['pending_head']; ?></p>
      </div>
      <div class="rounded-2xl bg-white border p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase">Pending HR</p>
        <p class="mt-2 text-2xl font-extrabold"><?php echo (int)$counts['pending_hr_receive']; ?></p>
      </div>
      <div class="rounded-2xl bg-white border p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase">Received</p>
        <p class="mt-2 text-2xl font-extrabold"><?php echo (int)$counts['received']; ?></p>
      </div>
      <div class="rounded-2xl bg-white border p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase">Rejected</p>
        <p class="mt-2 text-2xl font-extrabold"><?php echo (int)$counts['rejected']; ?></p>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
      <a href="leave_request.php" class="rounded-2xl bg-white border p-6 hover:bg-slate-50">
        <p class="font-semibold">Request Leave</p>
        <p class="text-sm text-slate-600 mt-1">
          Submit a new leave request (Sick / Incentive / Emergency)
        </p>
        <p class="mt-3 text-xs text-slate-500">
          Sick leave 4+ days requires medical certificate.
        </p>
      </a>

      <a href="my_leaves.php" class="rounded-2xl bg-white border p-6 hover:bg-slate-50">
        <p class="font-semibold">My Leave Records</p>
        <p class="text-sm text-slate-600 mt-1">View pending / approved / received / rejected leave history</p>
      </a>
    </div>
  </main>
</body>
</html>