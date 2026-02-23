<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$hrId = (int)$_SESSION['user_db_id'];

$me = $conn->prepare("SELECT role, approval_status FROM users WHERE id = ?");
$me->bind_param("i", $hrId);
$me->execute();
$hr = $me->get_result()->fetch_assoc();

if (!$hr || $hr['role'] !== 'hr' || $hr['approval_status'] !== 'approved') {
  header("Location: waiting_approval.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
  $uid = (int)$_POST['approve_user_id'];

  $upd = $conn->prepare("UPDATE users
    SET approval_status='approved', approved_by_hr=?, approved_hr_at=NOW()
    WHERE id=? AND approval_status='pending_hr'");
  $upd->bind_param("ii", $hrId, $uid);
  $upd->execute();
}

$list = $conn->prepare("SELECT id, full_name, email, role, department, created_at FROM users
  WHERE approval_status='pending_hr' ORDER BY created_at DESC");
$list->execute();
$rows = $list->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HR Approvals</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="mx-auto max-w-6xl px-4 py-8">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">HR Pending Approvals</h1>
        <p class="text-sm text-slate-600">Approve accounts to activate dashboard access.</p>
      </div>
      <a class="rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-100" href="dashboard_hr.php">Back</a>
    </div>

    <div class="mt-6 rounded-2xl border bg-white p-6">
      <?php if ($rows->num_rows === 0) { ?>
        <p class="text-slate-600">No pending HR approvals.</p>
      <?php } else { ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-slate-500">
              <tr>
                <th class="py-3 text-left">Name</th>
                <th class="py-3 text-left">Email</th>
                <th class="py-3 text-left">Role</th>
                <th class="py-3 text-left">Department</th>
                <th class="py-3 text-left">Requested</th>
                <th class="py-3 text-left">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php while ($u = $rows->fetch_assoc()) { ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 font-semibold"><?php echo htmlspecialchars($u['full_name']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($u['email']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($u['role']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($u['department']); ?></td>
                  <td class="py-3 text-slate-600"><?php echo htmlspecialchars($u['created_at']); ?></td>
                  <td class="py-3">
                    <form method="POST">
                      <input type="hidden" name="approve_user_id" value="<?php echo (int)$u['id']; ?>">
                      <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                        Approve
                      </button>
                    </form>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </div>
  </div>
</body>
</html>