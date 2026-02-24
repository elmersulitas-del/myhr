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

$_SESSION['user_role'] = 'hr';

// Sidebar badge
$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status='pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$allowedRoles = ['employee','head','hr'];
$allowedApproval = ['pending_head','pending_hr','approved','rejected'];

/* --------------------
   ACTIONS
---------------------*/
// UPDATE (modal submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
  $id        = (int)($_POST['id'] ?? 0);
  $full_name = trim($_POST['full_name'] ?? '');
  $emp_id    = trim($_POST['emp_id'] ?? '');
  $dept      = trim($_POST['department'] ?? '');
  $role      = $_POST['role'] ?? 'employee';
  $approval  = $_POST['approval_status'] ?? 'pending_head';

  if ($id <= 0 || $full_name === '') {
    $_SESSION['flash'] = "Invalid update.";
    header("Location: employees.php"); exit;
  }

  if (!in_array($role, $allowedRoles, true)) $role = 'employee';
  if (!in_array($approval, $allowedApproval, true)) $approval = 'pending_head';

  // Safety: prevent HR from demoting self
  if ($id === $hrId && $role !== 'hr') {
    $_SESSION['flash'] = "You cannot change your own role.";
    header("Location: employees.php"); exit;
  }

  $upd = $conn->prepare("UPDATE users SET full_name=?, emp_id=?, department=?, role=?, approval_status=? WHERE id=?");
  $upd->bind_param("sssssi", $full_name, $emp_id, $dept, $role, $approval, $id);
  $upd->execute();

  $_SESSION['flash'] = "Employee updated.";
  header("Location: employees.php"); exit;
}

// RESET BALANCES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_balance'])) {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $rb = $conn->prepare("UPDATE users
      SET sick_leave_balance=5, incentive_leave_balance=5, emergency_leave_balance=5
      WHERE id=?");
    $rb->bind_param("i", $id);
    $rb->execute();
    $_SESSION['flash'] = "Leave balances reset to 5/5/5.";
  }
  header("Location: employees.php"); exit;
}

/* --------------------
   LIST + FILTERS
---------------------*/
$q = trim($_GET['q'] ?? '');
$fDept = trim($_GET['department'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fApproval = trim($_GET['approval_status'] ?? '');

$where = [];
$params = [];
$types = "";

// search
if ($q !== '') {
  $where[] = "(full_name LIKE ? OR email LIKE ? OR emp_id LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= "sss";
}

// filters
if ($fDept !== '') {
  $where[] = "department = ?";
  $params[] = $fDept;
  $types .= "s";
}
if ($fRole !== '' && in_array($fRole, $allowedRoles, true)) {
  $where[] = "role = ?";
  $params[] = $fRole;
  $types .= "s";
}
if ($fApproval !== '' && in_array($fApproval, $allowedApproval, true)) {
  $where[] = "approval_status = ?";
  $params[] = $fApproval;
  $types .= "s";
}

$sqlWhere = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

// departments dropdown
$departments = [];
$dd = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> '' ORDER BY department ASC");
if ($dd) while ($r = $dd->fetch_assoc()) $departments[] = $r['department'];

$sql = "SELECT id, full_name, email, emp_id, department, role, approval_status,
        sick_leave_balance, incentive_leave_balance, emergency_leave_balance, created_at
        FROM users
        $sqlWhere
        ORDER BY created_at DESC
        LIMIT 500";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result();

/* Fetch all rows into array for modal JS */
$employees = [];
while ($r = $rows->fetch_assoc()) $employees[] = $r;

// Layout vars for hr_layout.php
$pageTitle = "Employee Records";
$active = "employees";

ob_start();
?>

<?php if ($flash): ?>
  <div class="mb-4 rounded-xl border bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
    <?php echo htmlspecialchars($flash); ?>
  </div>
<?php endif; ?>

<div class="flex items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold">Employee Records</h1>
    <p class="text-sm text-slate-600">Search, filter, edit, reset leave credits.</p>
  </div>
</div>

<!-- Filters -->
<form method="GET" class="mt-6 rounded-2xl border bg-white p-5 grid gap-4 md:grid-cols-4">
  <div class="md:col-span-2">
    <label class="text-sm font-semibold">Search</label>
    <input name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name / Email / Employee ID"
           class="mt-2 w-full rounded-xl border px-3 py-2 text-sm" />
  </div>

  <div>
    <label class="text-sm font-semibold">Department</label>
    <select name="department" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm">
      <option value="">All</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $fDept===$d?'selected':''; ?>>
          <?php echo htmlspecialchars($d); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label class="text-sm font-semibold">Role</label>
    <select name="role" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm">
      <option value="">All</option>
      <option value="employee" <?php echo $fRole==='employee'?'selected':''; ?>>Employee</option>
      <option value="head" <?php echo $fRole==='head'?'selected':''; ?>>Department Head</option>
      <option value="hr" <?php echo $fRole==='hr'?'selected':''; ?>>HR</option>
    </select>
  </div>

  <div>
    <label class="text-sm font-semibold">Approval</label>
    <select name="approval_status" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm">
      <option value="">All</option>
      <option value="pending_head" <?php echo $fApproval==='pending_head'?'selected':''; ?>>Pending Head</option>
      <option value="pending_hr" <?php echo $fApproval==='pending_hr'?'selected':''; ?>>Pending HR</option>
      <option value="approved" <?php echo $fApproval==='approved'?'selected':''; ?>>Approved</option>
      <option value="rejected" <?php echo $fApproval==='rejected'?'selected':''; ?>>Rejected</option>
    </select>
  </div>

  <div class="md:col-span-4 flex gap-2">
    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
      Apply
    </button>
    <a href="employees.php" class="rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-100">
      Reset
    </a>
  </div>
</form>

<!-- Employee List -->
<div class="mt-6 rounded-2xl border bg-white p-6">
  <div class="flex items-center justify-between">
    <h2 class="text-lg font-bold">Employees</h2>
    <p class="text-xs text-slate-500"><?php echo count($employees); ?> result(s)</p>
  </div>

  <div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-xs uppercase text-slate-500">
        <tr>
          <th class="py-3 text-left">Name</th>
          <th class="py-3 text-left">Email</th>
          <th class="py-3 text-left">Emp ID</th>
          <th class="py-3 text-left">Dept</th>
          <th class="py-3 text-left">Role</th>
          <th class="py-3 text-left">Approval</th>
          <th class="py-3 text-left">Balances</th>
          <th class="py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php foreach ($employees as $r): ?>
          <tr class="hover:bg-slate-50">
            <td class="py-3 font-semibold"><?php echo htmlspecialchars($r['full_name']); ?></td>
            <td class="py-3"><?php echo htmlspecialchars($r['email']); ?></td>
            <td class="py-3"><?php echo htmlspecialchars($r['emp_id'] ?? ''); ?></td>
            <td class="py-3"><?php echo htmlspecialchars($r['department'] ?? ''); ?></td>
            <td class="py-3"><?php echo htmlspecialchars($r['role']); ?></td>
            <td class="py-3"><?php echo htmlspecialchars($r['approval_status']); ?></td>
            <td class="py-3 text-xs text-slate-600">
              SL: <b><?php echo (int)$r['sick_leave_balance']; ?></b> •
              IL: <b><?php echo (int)$r['incentive_leave_balance']; ?></b> •
              EL: <b><?php echo (int)$r['emergency_leave_balance']; ?></b>
            </td>
            <td class="py-3">
              <div class="flex flex-wrap gap-3">
                <button
                  type="button"
                  class="text-xs font-semibold hover:underline"
                  onclick='openEditModal(<?php echo json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>
                  Edit
                </button>

                <form method="POST" onsubmit="return confirm('Reset leave balances to 5/5/5?');">
                  <input type="hidden" name="reset_balance" value="1">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="text-xs font-semibold text-amber-700 hover:underline">
                    Reset Credits
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (count($employees) === 0): ?>
          <tr>
            <td colspan="8" class="py-6 text-center text-slate-600">No employees found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 hidden items-center justify-center bg-black/40 p-4 z-50">
  <div class="w-full max-w-xl rounded-2xl bg-white border shadow-lg">
    <div class="flex items-start justify-between p-5 border-b">
      <div>
        <h3 class="text-lg font-bold">Edit Employee</h3>
        <p class="text-sm text-slate-600" id="modalEmail"></p>
      </div>
      <button class="rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-slate-100" onclick="closeEditModal()">
        ✕
      </button>
    </div>

    <form method="POST" class="p-5 space-y-4">
      <input type="hidden" name="update_employee" value="1">
      <input type="hidden" name="id" id="m_id">

      <div>
        <label class="text-sm font-semibold">Full Name</label>
        <input name="full_name" id="m_full_name" required
               class="mt-2 w-full rounded-xl border px-3 py-2 text-sm" />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="text-sm font-semibold">Employee ID</label>
          <input name="emp_id" id="m_emp_id"
                 class="mt-2 w-full rounded-xl border px-3 py-2 text-sm" />
        </div>

        <div>
          <label class="text-sm font-semibold">Department</label>
          <input name="department" id="m_department"
                 class="mt-2 w-full rounded-xl border px-3 py-2 text-sm" />
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="text-sm font-semibold">Role</label>
          <select name="role" id="m_role" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm">
            <option value="employee">Employee</option>
            <option value="head">Department Head</option>
            <option value="hr">HR</option>
          </select>
        </div>

        <div>
          <label class="text-sm font-semibold">Approval Status</label>
          <select name="approval_status" id="m_approval" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm">
            <option value="pending_head">Pending Head</option>
            <option value="pending_hr">Pending HR</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button class="flex-1 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">
          Save Changes
        </button>
        <button type="button" class="flex-1 rounded-xl border px-4 py-3 text-sm font-semibold hover:bg-slate-100"
                onclick="closeEditModal()">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  const modal = document.getElementById('editModal');

  function openEditModal(emp) {
    document.getElementById('m_id').value = emp.id || '';
    document.getElementById('m_full_name').value = emp.full_name || '';
    document.getElementById('m_emp_id').value = emp.emp_id || '';
    document.getElementById('m_department').value = emp.department || '';
    document.getElementById('m_role').value = emp.role || 'employee';
    document.getElementById('m_approval').value = emp.approval_status || 'pending_head';
    document.getElementById('modalEmail').textContent = emp.email ? ("Email: " + emp.email) : "";
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeEditModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  // close when clicking outside
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeEditModal();
  });

  // close with ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeEditModal();
  });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/hr_layout.php";