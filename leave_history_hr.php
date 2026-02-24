<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$hrId = (int)$_SESSION['user_db_id'];

// HR guard
$me = $conn->prepare("SELECT id, full_name, email, role, approval_status, profile_completed FROM users WHERE id=?");
$me->bind_param("i", $hrId);
$me->execute();
$user = $me->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'hr') { header("Location: login.php"); exit; }

// -------- cutoff helpers --------
function cutoff_range(int $year, int $month, string $cutoff): array {
  // cutoff = 'A' (11-26 same month) or 'B' (27-current month to 10 next month)
  if ($cutoff === 'A') {
    $start = sprintf('%04d-%02d-11', $year, $month);
    $end   = sprintf('%04d-%02d-26', $year, $month);
    return [$start, $end];
  }

  // B: 27 -> 10 next month
  $start = sprintf('%04d-%02d-27', $year, $month);

  $next = strtotime(sprintf('%04d-%02d-01', $year, $month) . ' +1 month');
  $ny = (int)date('Y', $next);
  $nm = (int)date('m', $next);
  $end = sprintf('%04d-%02d-10', $ny, $nm);

  return [$start, $end];
}

// Inputs (month/year + cutoff)
$year = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
$cutoff = $_GET['cutoff'] ?? 'A';

if ($month < 1) $month = 1;
if ($month > 12) $month = 12;
if ($year < 2000) $year = 2000;
if ($year > 2100) $year = 2100;
if ($cutoff !== 'A' && $cutoff !== 'B') $cutoff = 'A';

[$startDate, $endDate] = cutoff_range($year, $month, $cutoff);

$label = ($cutoff === 'A')
  ? "Cutoff 11–26"
  : "Cutoff 27–10";

// Fetch leave records within cutoff
// We use date_from inside cutoff range.
// Only show HR "received" (already deducted).
$q = $conn->prepare("
  SELECT lr.*, u.full_name, u.emp_id, u.department
  FROM leave_requests lr
  JOIN users u ON u.id = lr.user_id
  WHERE lr.status = 'received'
    AND lr.date_from BETWEEN ? AND ?
  ORDER BY lr.date_from ASC, lr.created_at ASC
");
$q->bind_param("ss", $startDate, $endDate);
$q->execute();
$rows = $q->get_result();

// Totals per type
$totals = ['sick'=>0, 'incentive'=>0, 'emergency'=>0, 'all'=>0];
$qt = $conn->prepare("
  SELECT leave_type, SUM(days) AS total_days
  FROM leave_requests
  WHERE status='received'
    AND date_from BETWEEN ? AND ?
  GROUP BY leave_type
");
$qt->bind_param("ss", $startDate, $endDate);
$qt->execute();
$tr = $qt->get_result();
while ($r = $tr->fetch_assoc()) {
  $t = $r['leave_type'];
  $d = (int)($r['total_days'] ?? 0);
  if (isset($totals[$t])) $totals[$t] = $d;
  $totals['all'] += $d;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leave History (Cutoff)</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">

  <div class="mx-auto max-w-6xl px-4 py-8">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold">Leave History</h1>
        <p class="text-sm text-slate-600">
          <?php echo htmlspecialchars($label); ?> •
          <span class="font-semibold"><?php echo htmlspecialchars($startDate); ?></span>
          to
          <span class="font-semibold"><?php echo htmlspecialchars($endDate); ?></span>
        </p>
      </div>

      <a class="rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-100" href="dashboard_hr.php">Back</a>
    </div>

    <!-- Filters -->
    <form method="GET" class="mt-6 rounded-2xl border bg-white p-5 grid gap-4 sm:grid-cols-3">
      <div>
        <label class="text-sm font-semibold">Year</label>
        <input type="number" name="y" value="<?php echo (int)$year; ?>" class="mt-2 w-full rounded-xl border px-3 py-2">
      </div>
      <div>
        <label class="text-sm font-semibold">Month</label>
        <input type="number" name="m" min="1" max="12" value="<?php echo (int)$month; ?>" class="mt-2 w-full rounded-xl border px-3 py-2">
      </div>
      <div>
        <label class="text-sm font-semibold">Cutoff</label>
        <select name="cutoff" class="mt-2 w-full rounded-xl border px-3 py-2">
          <option value="A" <?php echo $cutoff==='A'?'selected':''; ?>>11–26</option>
          <option value="B" <?php echo $cutoff==='B'?'selected':''; ?>>27–10</option>
        </select>
      </div>

      <div class="sm:col-span-3">
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
          Apply Filter
        </button>
      </div>
    </form>

    <!-- Totals -->
    <div class="mt-6 grid gap-4 sm:grid-cols-4">
      <div class="rounded-2xl bg-white border p-5">
        <p class="text-xs uppercase text-slate-500 font-semibold">Total Days</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)$totals['all']; ?></p>
      </div>
      <div class="rounded-2xl bg-white border p-5">
        <p class="text-xs uppercase text-slate-500 font-semibold">Sick</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)$totals['sick']; ?></p>
      </div>
      <div class="rounded-2xl bg-white border p-5">
        <p class="text-xs uppercase text-slate-500 font-semibold">Incentive</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)$totals['incentive']; ?></p>
      </div>
      <div class="rounded-2xl bg-white border p-5">
        <p class="text-xs uppercase text-slate-500 font-semibold">Emergency</p>
        <p class="mt-2 text-3xl font-extrabold"><?php echo (int)$totals['emergency']; ?></p>
      </div>
    </div>

    <!-- Table -->
    <div class="mt-6 rounded-2xl border bg-white p-6">
      <?php if ($rows->num_rows === 0): ?>
        <p class="text-slate-600">No received leave records in this cutoff.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-slate-500">
              <tr>
                <th class="py-3 text-left">Employee</th>
                <th class="py-3 text-left">Emp ID</th>
                <th class="py-3 text-left">Dept</th>
                <th class="py-3 text-left">Type</th>
                <th class="py-3 text-left">Dates</th>
                <th class="py-3 text-left">Days</th>
                <th class="py-3 text-left">Received At</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php while ($r = $rows->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 font-semibold"><?php echo htmlspecialchars($r['full_name']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($r['emp_id'] ?? ''); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($r['department'] ?? ''); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($r['leave_type']); ?></td>
                  <td class="py-3">
                    <?php echo htmlspecialchars($r['date_from']); ?> → <?php echo htmlspecialchars($r['date_to']); ?>
                  </td>
                  <td class="py-3 font-semibold"><?php echo (int)$r['days']; ?></td>
                  <td class="py-3 text-slate-600">
                    <?php
                      $ts = strtotime($r['hr_received_at'] ?? '');
                      echo $ts ? date("M d, Y h:i A", $ts) : '';
                    ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</body>
</html>