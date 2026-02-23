<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$headId = (int)$_SESSION['user_db_id'];

// Load head info
$stmt = $conn->prepare("SELECT full_name, department, role, approval_status, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $headId);
$stmt->execute();
$head = $stmt->get_result()->fetch_assoc();

if (!$head) { header("Location: login.php"); exit; }
if ((int)$head['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($head['role'] ?? '') !== 'head') { header("Location: login.php"); exit; }
if (($head['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }

$dept = $head['department'] ?? '';

// Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $uid = (int)($_POST['user_id'] ?? 0);

  if ($uid > 0) {
    if ($action === 'approve') {
      $upd = $conn->prepare("UPDATE users
        SET approval_status='pending_hr',
            approved_by_head=?,
            approved_head_at=NOW(),
            rejection_reason=NULL
        WHERE id=? AND department=? AND approval_status='pending_head'");
      $upd->bind_param("iis", $headId, $uid, $dept);
      $upd->execute();
    }

    if ($action === 'reject') {
      $reason = trim($_POST['reason'] ?? '');
      if ($reason === '') $reason = 'Rejected by Department Head';

      $upd = $conn->prepare("UPDATE users
        SET approval_status='rejected',
            approved_by_head=?,
            approved_head_at=NOW(),
            rejection_reason=?
        WHERE id=? AND department=? AND approval_status='pending_head'");
      $upd->bind_param("isis", $headId, $reason, $uid, $dept);
      $upd->execute();
    }
  }

  header("Location: pending_approvals.php");
  exit;
}

// Fetch pending employees in the same department
$list = $conn->prepare("SELECT id, full_name, email, emp_id, created_at
  FROM users
  WHERE department=? AND approval_status='pending_head'
  ORDER BY created_at DESC");
$list->bind_param("s", $dept);
$list->execute();
$pending = $list->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pending Employee Approvals</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">
  <header class="border-b bg-white">
    <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between">
      <div>
        <p class="font-bold">Pending Employee Approvals</p>
        <p class="text-sm text-slate-500">Department: <?php echo htmlspecialchars($dept); ?></p>
      </div>
      <a class="px-4 py-2 rounded-lg border hover:bg-slate-100" href="dashboard_head.php">Back</a>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-8">
    <div class="rounded-2xl bg-white border p-6">
      <?php if ($pending->num_rows === 0) { ?>
        <p class="text-slate-600">No pending employee accounts for approval.</p>
      <?php } else { ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-slate-500">
              <tr>
                <th class="py-3 text-left">Name</th>
                <th class="py-3 text-left">Email</th>
                <th class="py-3 text-left">Employee ID</th>
                <th class="py-3 text-left">Requested</th>
                <th class="py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php while ($u = $pending->fetch_assoc()) { ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 font-semibold"><?php echo htmlspecialchars($u['full_name']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($u['email']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($u['emp_id'] ?? ''); ?></td>
                  <td class="py-3 text-slate-600"><?php echo htmlspecialchars($u['created_at']); ?></td>
                  <td class="py-3">
                    <div class="flex flex-wrap gap-2">
                      <!-- Approve -->
                      <form method="POST">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                          Approve → Send to HR
                        </button>
                      </form>

                      <!-- Reject (with reason) -->
                      <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        <input
                          type="text"
                          name="reason"
                          placeholder="Reason (optional)"
                          class="w-44 rounded-lg border px-2 py-2 text-xs outline-none focus:border-slate-400"
                        >
                        <button class="rounded-lg border px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                          Reject
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </div>

    <p class="mt-4 text-xs text-slate-500">
      Approving an employee changes their status to <b>pending_hr</b>. HR will complete the final approval.
    </p>
  </main>
</body>
</html>