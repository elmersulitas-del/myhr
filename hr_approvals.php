<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$hrId = (int)$_SESSION['user_db_id'];

// Fetch HR user (needed for layout)
$me = $conn->prepare("SELECT id, full_name, email, role, approval_status, profile_completed FROM users WHERE id = ?");
$me->bind_param("i", $hrId);
$me->execute();
$user = $me->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'hr') { header("Location: login.php"); exit; }

$_SESSION['user_role'] = 'hr';

// Pending count for sidebar badge
$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status = 'pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

// Approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
  $uid = (int)$_POST['approve_user_id'];

  $upd = $conn->prepare("UPDATE users
    SET approval_status='approved', approved_by_hr=?, approved_hr_at=NOW()
    WHERE id=? AND approval_status='pending_hr'");
  $upd->bind_param("ii", $hrId, $uid);
  $upd->execute();

  header("Location: hr_approvals.php");
  exit;
}

// Fetch pending list
$list = $conn->prepare("SELECT id, full_name, email, role, department, created_at
  FROM users
  WHERE approval_status='pending_hr'
  ORDER BY created_at DESC");
$list->execute();
$rows = $list->get_result();

// Layout settings
$pageTitle = "HR Approvals";
$active = "approvals";

// Page content
ob_start();
?>
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">HR Pending Approvals</h1>
      <p class="text-sm text-slate-600">Approve accounts sent by Department Heads to activate access.</p>
    </div>
    <span class="<?php echo $pendingHr > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'; ?> text-xs font-bold px-3 py-2 rounded-full">
      <?php echo (int)$pendingHr; ?> pending
    </span>
  </div>

  <div class="mt-6 rounded-2xl border bg-white p-6">
    <?php if ($rows->num_rows === 0) { ?>
      <div class="rounded-xl bg-slate-50 border p-4 text-sm text-slate-600">
        No pending HR approvals.
      </div>
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
                <td class="py-3 text-slate-600">
                  <?php
                    $ts = strtotime($u['created_at'] ?? '');
                    echo $ts ? date("M d, Y h:i A", $ts) : htmlspecialchars($u['created_at']);
                  ?>
                </td>
                <td class="py-3">
                  <form method="POST" onsubmit="return confirm('Approve this user?')">
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
<?php
$content = ob_get_clean();

require_once __DIR__ . "/hr_layout.php";