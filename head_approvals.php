<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$headId = (int)$_SESSION['user_db_id'];

$me = $conn->prepare("SELECT role, department, approval_status FROM users WHERE id = ?");
$me->bind_param("i", $headId);
$me->execute();
$head = $me->get_result()->fetch_assoc();

if (!$head || $head['role'] !== 'head' || $head['approval_status'] !== 'approved') {
  header("Location: waiting_approval.php");
  exit;
}

$dept = $head['department'];

// Approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
  $uid = (int)$_POST['approve_user_id'];

  // only approve users in same department and pending_head
  $upd = $conn->prepare("UPDATE users
    SET approval_status='pending_hr', approved_by_head=?, approved_head_at=NOW()
    WHERE id=? AND department=? AND approval_status='pending_head'");
  $upd->bind_param("iis", $headId, $uid, $dept);
  $upd->execute();
}

$list = $conn->prepare("SELECT id, full_name, email, created_at FROM users
  WHERE department=? AND approval_status='pending_head' ORDER BY created_at DESC");
$list->bind_param("s", $dept);
$list->execute();
$rows = $list->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Department Approvals</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="mx-auto max-w-5xl px-4 py-8">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Pending Employee Approvals</h1>
        <p class="text-sm text-slate-600">Department: <?php echo htmlspecialchars($dept); ?></p>
      </div>
      <a class="rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-100" href="dashboard_head.php">Back</a>
    </div>

    <div class="mt-6 rounded-2xl border bg-white p-6">
      <?php if ($rows->num_rows === 0) { ?>
        <p class="text-slate-600">No pending requests.</p>
      <?php } else { ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-slate-500">
              <tr>
                <th class="py-3 text-left">Name</th>
                <th class="py-3 text-left">Email</th>
                <th class="py-3 text-left">Requested</th>
                <th class="py-3 text-left">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php while ($u = $rows->fetch_assoc()) { ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 font-semibold"><?php echo htmlspecialchars($u['full_name']); ?></td>
                  <td class="py-3"><?php echo htmlspecialchars($u['email']); ?></td>
                  <td class="py-3 text-slate-600"><?php echo htmlspecialchars($u['created_at']); ?></td>
                  <td class="py-3">
                    <form method="POST">
                      <input type="hidden" name="approve_user_id" value="<?php echo (int)$u['id']; ?>">
                      <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                        Approve → Send to HR
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