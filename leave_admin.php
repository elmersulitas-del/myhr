<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$hrId = (int)$_SESSION['user_db_id'];

// HR guard + layout needs $user
$me = $conn->prepare("SELECT id, full_name, email, role, approval_status, profile_completed FROM users WHERE id=?");
$me->bind_param("i", $hrId);
$me->execute();
$user = $me->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'hr') { header("Location: login.php"); exit; }

$_SESSION['user_role'] = 'hr';

// Pending HR approvals badge for sidebar
$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status = 'pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

// ---------- Tabs ----------
$tab = $_GET['tab'] ?? 'requests';
if ($tab !== 'requests' && $tab !== 'history') $tab = 'requests';

// ---------- RECEIVE & DEDUCT (Requests tab action) ----------
$flash = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_leave_id'])) {
  $leaveId = (int)$_POST['receive_leave_id'];

  $lrq = $conn->prepare("SELECT id, user_id, leave_type, days, status FROM leave_requests WHERE id=?");
  $lrq->bind_param("i", $leaveId);
  $lrq->execute();
  $lr = $lrq->get_result()->fetch_assoc();

  if ($lr && $lr['status'] === 'pending_hr_receive') {
    $uid  = (int)$lr['user_id'];
    $days = (int)$lr['days'];
    $type = $lr['leave_type'];

    $conn->begin_transaction();
    try {
      if ($type === 'sick') {
        $u = $conn->prepare("UPDATE users
          SET sick_leave_balance = sick_leave_balance - ?
          WHERE id=? AND sick_leave_balance >= ?");
        $u->bind_param("iii", $days, $uid, $days);
      } elseif ($type === 'incentive') {
        $u = $conn->prepare("UPDATE users
          SET incentive_leave_balance = incentive_leave_balance - ?
          WHERE id=? AND incentive_leave_balance >= ?");
        $u->bind_param("iii", $days, $uid, $days);
      } else { // emergency
        $u = $conn->prepare("UPDATE users
          SET emergency_leave_balance = emergency_leave_balance - ?
          WHERE id=? AND emergency_leave_balance >= ?");
        $u->bind_param("iii", $days, $uid, $days);
      }

      $u->execute();
      if ($u->affected_rows !== 1) {
        throw new Exception("Not enough balance to deduct or already deducted.");
      }

      $upd = $conn->prepare("UPDATE leave_requests
        SET status='received', hr_id=?, hr_received_at=NOW()
        WHERE id=? AND status='pending_hr_receive'");
      $upd->bind_param("ii", $hrId, $leaveId);
      $upd->execute();

      $conn->commit();
      $flash = "Leave received and credits deducted.";
    } catch (Exception $e) {
      $conn->rollback();
      $flash = "Failed: " . $e->getMessage();
    }
  } else {
    $flash = "This request is not ready to receive.";
  }

  header("Location: leave_admin.php?tab=requests");
  exit;
}

// ---------- Requests list ----------
$reqRows = $conn->query("
  SELECT lr.*, u.full_name, u.email, u.emp_id, u.department
  FROM leave_requests lr
  JOIN users u ON u.id = lr.user_id
  WHERE lr.status='pending_hr_receive'
  ORDER BY lr.created_at DESC
");

// ---------- History (Cutoff) ----------
function cutoff_range(int $year, int $month, string $cutoff): array {
  if ($cutoff === 'A') {
    return [sprintf('%04d-%02d-11', $year, $month), sprintf('%04d-%02d-26', $year, $month)];
  }
  $start = sprintf('%04d-%02d-27', $year, $month);
  $next = strtotime(sprintf('%04d-%02d-01', $year, $month) . ' +1 month');
  $ny = (int)date('Y', $next);
  $nm = (int)date('m', $next);
  $end = sprintf('%04d-%02d-10', $ny, $nm);
  return [$start, $end];
}

$hy = (int)($_GET['y'] ?? date('Y'));
$hm = (int)($_GET['m'] ?? date('n'));
$cutoff = $_GET['cutoff'] ?? 'A';
if ($hm < 1) $hm = 1;
if ($hm > 12) $hm = 12;
if ($hy < 2000) $hy = 2000;
if ($hy > 2100) $hy = 2100;
if ($cutoff !== 'A' && $cutoff !== 'B') $cutoff = 'A';

[$startDate, $endDate] = cutoff_range($hy, $hm, $cutoff);
$cutoffLabel = ($cutoff === 'A') ? "11–26" : "27–10";

$hist = $conn->prepare("
  SELECT lr.*, u.full_name, u.emp_id, u.department
  FROM leave_requests lr
  JOIN users u ON u.id = lr.user_id
  WHERE lr.status='received'
    AND lr.date_from BETWEEN ? AND ?
  ORDER BY lr.date_from ASC, lr.created_at ASC
");
$hist->bind_param("ss", $startDate, $endDate);
$hist->execute();
$histRows = $hist->get_result();

$totals = ['sick'=>0,'incentive'=>0,'emergency'=>0,'all'=>0];
$qt = $conn->prepare("
  SELECT leave_type, SUM(days) AS total_days
  FROM leave_requests
  WHERE status='received' AND date_from BETWEEN ? AND ?
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

// -------- Layout settings --------
$pageTitle = "Leave Management";
$active = "leave";

// -------- Content --------
ob_start();
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold">Leave Management</h1>
    <p class="text-sm text-slate-600">Receive approved leave requests and view cutoff history.</p>
  </div>
</div>

<!-- Tabs -->
<div class="mt-6 border-b flex gap-2">
  <a href="leave_admin.php?tab=requests"
     class="px-4 py-2 text-sm font-semibold rounded-t-xl <?php echo $tab==='requests' ? 'bg-white border border-b-0' : 'text-slate-600 hover:text-slate-900'; ?>">
    Leave Requests
  </a>
  <a href="leave_admin.php?tab=history"
     class="px-4 py-2 text-sm font-semibold rounded-t-xl <?php echo $tab==='history' ? 'bg-white border border-b-0' : 'text-slate-600 hover:text-slate-900'; ?>">
    Leave History (Cutoff)
  </a>
</div>

<?php if ($tab === 'requests'): ?>

  <div class="rounded-b-2xl border bg-white p-6">
    <?php if ($reqRows && $reqRows->num_rows > 0): ?>
      <div class="space-y-4">
        <?php while($r = $reqRows->fetch_assoc()): ?>
          <div class="rounded-xl border p-4">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="font-bold"><?php echo htmlspecialchars($r['full_name']); ?></p>
                <p class="text-xs text-slate-500">
                  <?php echo htmlspecialchars($r['department'] ?? ''); ?> • <?php echo htmlspecialchars($r['emp_id'] ?? ''); ?>
                </p>
              </div>
              <span class="text-xs font-semibold rounded-full bg-slate-100 px-3 py-1">
                <?php echo htmlspecialchars($r['leave_type']); ?> • <?php echo (int)$r['days']; ?> day(s)
              </span>
            </div>

            <p class="mt-2 text-sm"><b>Date:</b> <?php echo htmlspecialchars($r['date_from']); ?> → <?php echo htmlspecialchars($r['date_to']); ?></p>
            <p class="mt-2 text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($r['reason'])); ?></p>

            <form method="POST" class="mt-4">
              <input type="hidden" name="receive_leave_id" value="<?php echo (int)$r['id']; ?>">
              <button class="rounded-xl bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800"
                      onclick="return confirm('Receive this leave and deduct credits?')">
                Receive & Deduct
              </button>
            </form>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="rounded-xl bg-slate-50 border p-4 text-sm text-slate-600">
        No leave requests ready to receive.
      </div>
    <?php endif; ?>
  </div>

<?php else: ?>

  <div class="rounded-b-2xl border bg-white p-6">
    <form method="GET" class="grid gap-4 sm:grid-cols-4">
      <input type="hidden" name="tab" value="history">
      <div>
        <label class="text-sm font-semibold">Year</label>
        <input type="number" name="y" value="<?php echo (int)$hy; ?>" class="mt-2 w-full rounded-xl border px-3 py-2">
      </div>
      <div>
        <label class="text-sm font-semibold">Month</label>
        <input type="number" name="m" min="1" max="12" value="<?php echo (int)$hm; ?>" class="mt-2 w-full rounded-xl border px-3 py-2">
      </div>
      <div>
        <label class="text-sm font-semibold">Cutoff</label>
        <select name="cutoff" class="mt-2 w-full rounded-xl border px-3 py-2">
          <option value="A" <?php echo $cutoff==='A'?'selected':''; ?>>11–26</option>
          <option value="B" <?php echo $cutoff==='B'?'selected':''; ?>>27–10</option>
        </select>
      </div>
      <div class="flex items-end">
        <button class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
          Apply
        </button>
      </div>
    </form>

    <div class="mt-4 text-sm text-slate-600">
      Showing: <span class="font-semibold"><?php echo htmlspecialchars($startDate); ?></span> to
      <span class="font-semibold"><?php echo htmlspecialchars($endDate); ?></span>
      (Cutoff <?php echo htmlspecialchars($cutoffLabel); ?>)
    </div>

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

    <div class="mt-6 rounded-2xl border p-4 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="text-xs uppercase text-slate-500">
          <tr>
            <th class="py-3 text-left">Employee</th>
            <th class="py-3 text-left">Emp ID</th>
            <th class="py-3 text-left">Dept</th>
            <th class="py-3 text-left">Type</th>
            <th class="py-3 text-left">Dates</th>
            <th class="py-3 text-left">Days</th>
            <th class="py-3 text-left">Received</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php if ($histRows->num_rows === 0): ?>
            <tr><td colspan="7" class="py-4 text-slate-600">No records for this cutoff.</td></tr>
          <?php else: ?>
            <?php while($r = $histRows->fetch_assoc()): ?>
              <tr class="hover:bg-slate-50">
                <td class="py-3 font-semibold"><?php echo htmlspecialchars($r['full_name']); ?></td>
                <td class="py-3"><?php echo htmlspecialchars($r['emp_id'] ?? ''); ?></td>
                <td class="py-3"><?php echo htmlspecialchars($r['department'] ?? ''); ?></td>
                <td class="py-3"><?php echo htmlspecialchars($r['leave_type']); ?></td>
                <td class="py-3"><?php echo htmlspecialchars($r['date_from']); ?> → <?php echo htmlspecialchars($r['date_to']); ?></td>
                <td class="py-3 font-semibold"><?php echo (int)$r['days']; ?></td>
                <td class="py-3 text-slate-600">
                  <?php
                    $ts = strtotime($r['hr_received_at'] ?? '');
                    echo $ts ? date("M d, Y h:i A", $ts) : '';
                  ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/hr_layout.php";