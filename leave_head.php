<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$headId = (int)$_SESSION['user_db_id'];

$me = $conn->prepare("SELECT id, full_name, email, department, role, approval_status, profile_completed
  FROM users WHERE id=?");
$me->bind_param("i", $headId);
$me->execute();
$head = $me->get_result()->fetch_assoc();

if (!$head) { header("Location: login.php"); exit; }
if ((int)$head['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($head['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($head['role'] ?? '') !== 'head') { header("Location: login.php"); exit; }

$dept = $head['department'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['leave_id'])) {
  $leaveId = (int)$_POST['leave_id'];
  $action = $_POST['action'];
  $note = trim($_POST['note'] ?? '');

  if ($action === 'approve') {
    $upd = $conn->prepare("UPDATE leave_requests
      SET status='pending_hr_receive', head_id=?, head_action_at=NOW(), head_note=?
      WHERE id=? AND status='pending_head' AND department=?");
    $upd->bind_param("isis", $headId, $note, $leaveId, $dept);
    $upd->execute();
  }

  if ($action === 'reject') {
    $upd = $conn->prepare("UPDATE leave_requests
      SET status='rejected_head', head_id=?, head_action_at=NOW(), head_note=?
      WHERE id=? AND status='pending_head' AND department=?");
    $upd->bind_param("isis", $headId, $note, $leaveId, $dept);
    $upd->execute();
  }

  header("Location: leave_head.php");
  exit;
}

$list = $conn->prepare("
  SELECT lr.*, u.full_name, u.email
  FROM leave_requests lr
  JOIN users u ON u.id = lr.user_id
  WHERE lr.department=? AND lr.status='pending_head'
  ORDER BY lr.created_at DESC
");
$list->bind_param("s", $dept);
$list->execute();
$rows = $list->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Head Leave Approvals</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
  <div class="mx-auto max-w-6xl px-4 py-10">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Leave Requests</h1>
        <p class="text-sm text-slate-600">Department: <?php echo htmlspecialchars($dept); ?></p>
      </div>
      <a class="rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-100" href="dashboard_head.php">Back</a>
    </div>

    <div class="mt-6 rounded-2xl bg-white border p-6">
      <?php if ($rows->num_rows === 0): ?>
        <p class="text-slate-600">No pending leave requests.</p>
      <?php else: ?>
        <div class="space-y-4">
          <?php while($r = $rows->fetch_assoc()): ?>
            <div class="rounded-xl border p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="font-bold"><?php echo htmlspecialchars($r['full_name']); ?></p>
                  <p class="text-xs text-slate-500"><?php echo htmlspecialchars($r['email']); ?></p>
                </div>
                <span class="text-xs font-semibold rounded-full bg-slate-100 px-3 py-1">
                  <?php echo htmlspecialchars($r['leave_type']); ?> • <?php echo (int)$r['days']; ?> day(s)
                </span>
              </div>

              <p class="mt-2 text-sm">
                <b>Date:</b> <?php echo htmlspecialchars($r['date_from']); ?> to <?php echo htmlspecialchars($r['date_to']); ?>
              </p>
              <p class="mt-2 text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($r['reason'])); ?></p>

              <?php if (!empty($r['med_cert_file'])): ?>
                <p class="mt-2 text-xs">
                  Med Cert: <span class="font-semibold"><?php echo htmlspecialchars($r['med_cert_file']); ?></span>
                </p>
              <?php endif; ?>

              <form method="POST" class="mt-4 grid gap-3 sm:grid-cols-3">
                <input type="hidden" name="leave_id" value="<?php echo (int)$r['id']; ?>">
                <input type="text" name="note" placeholder="Note (optional)"
                       class="sm:col-span-2 rounded-xl border px-3 py-2 text-sm">
                <div class="flex gap-2">
                  <button name="action" value="approve"
                          class="flex-1 rounded-xl bg-slate-900 text-white px-3 py-2 text-sm font-semibold hover:bg-slate-800">
                    Approve
                  </button>
                  <button name="action" value="reject"
                          class="flex-1 rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-slate-100">
                    Reject
                  </button>
                </div>
              </form>
            </div>
          <?php endwhile; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>