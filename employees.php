<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$hrId = (int)$_SESSION['user_db_id'];

$me = $conn->prepare("
  SELECT id, full_name, email, role, approval_status, profile_completed
  FROM users
  WHERE id=?
  LIMIT 1
");
$me->bind_param("i", $hrId);
$me->execute();
$user = $me->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'hr') { header("Location: login.php"); exit; }

$_SESSION['user_role'] = 'hr';

$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status='pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

$flash = $_SESSION['flash'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

$allowedRoles = ['employee','head','hr'];
$allowedApproval = ['pending_head','pending_hr','approved','rejected'];

function set_flash($message, $type = 'success') {
  $_SESSION['flash'] = $message;
  $_SESSION['flash_type'] = $type;
}

function normalize_csv($value) {
  $value = (string)$value;
  return str_replace(["\r", "\n"], ' ', $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
  $id        = (int)($_POST['id'] ?? 0);
  $full_name = trim($_POST['full_name'] ?? '');
  $emp_id    = trim($_POST['emp_id'] ?? '');
  $dept      = trim($_POST['department'] ?? '');
  $role      = $_POST['role'] ?? 'employee';
  $approval  = $_POST['approval_status'] ?? 'pending_head';
  $sick      = max(0, (int)($_POST['sick_leave_balance'] ?? 0));
  $incentive = max(0, (int)($_POST['incentive_leave_balance'] ?? 0));
  $emergency = max(0, (int)($_POST['emergency_leave_balance'] ?? 0));

  if ($id <= 0 || $full_name === '') {
    set_flash("Invalid update request.", "error");
    header("Location: employees.php"); exit;
  }

  if (!in_array($role, $allowedRoles, true)) $role = 'employee';
  if (!in_array($approval, $allowedApproval, true)) $approval = 'pending_head';

  if ($id === $hrId && $role !== 'hr') {
    set_flash("You cannot change your own role.", "error");
    header("Location: employees.php"); exit;
  }

  $upd = $conn->prepare("
    UPDATE users
    SET full_name=?, emp_id=?, department=?, role=?, approval_status=?,
        sick_leave_balance=?, incentive_leave_balance=?, emergency_leave_balance=?
    WHERE id=?
  ");
  $upd->bind_param("sssssiiii", $full_name, $emp_id, $dept, $role, $approval, $sick, $incentive, $emergency, $id);
  $upd->execute();

  set_flash("Employee record updated successfully.");
  header("Location: employees.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_balance'])) {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $rb = $conn->prepare("
      UPDATE users
      SET sick_leave_balance=5, incentive_leave_balance=5, emergency_leave_balance=5
      WHERE id=?
    ");
    $rb->bind_param("i", $id);
    $rb->execute();
    set_flash("Leave balances reset to 5 / 5 / 5.");
  } else {
    set_flash("Invalid employee selected.", "error");
  }
  header("Location: employees.php"); exit;
}

$q = trim($_GET['q'] ?? '');
$fDept = trim($_GET['department'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fApproval = trim($_GET['approval_status'] ?? '');
$export = trim($_GET['export'] ?? '');

$where = [];
$params = [];
$types = "";

if ($q !== '') {
  $where[] = "(full_name LIKE ? OR email LIKE ? OR emp_id LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= "sss";
}

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

$departments = [];
$dd = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> '' ORDER BY department ASC");
if ($dd) {
  while ($r = $dd->fetch_assoc()) {
    $departments[] = $r['department'];
  }
}

$stats = [
  'total' => 0,
  'employees' => 0,
  'heads' => 0,
  'hr' => 0,
  'approved' => 0,
  'pending' => 0,
];

$statsQ = $conn->query("
  SELECT
    COUNT(*) AS total,
    SUM(role='employee') AS employees,
    SUM(role='head') AS heads,
    SUM(role='hr') AS hr,
    SUM(approval_status='approved') AS approved,
    SUM(approval_status IN ('pending_head','pending_hr')) AS pending
  FROM users
");
if ($statsQ) {
  $stats = array_merge($stats, $statsQ->fetch_assoc() ?: []);
}

$sql = "
  SELECT id, full_name, email, emp_id, department, role, approval_status,
         sick_leave_balance, incentive_leave_balance, emergency_leave_balance, google_id, created_at
  FROM users
  $sqlWhere
  ORDER BY created_at DESC, id DESC
  LIMIT 500
";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($r = $result->fetch_assoc()) {
  $employees[] = $r;
}

if ($export === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=employee-records.csv');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID', 'Full Name', 'Email', 'Employee ID', 'Department', 'Role', 'Approval Status', 'Sick Leave', 'Incentive Leave', 'Emergency Leave', 'Google Linked', 'Created At']);

  foreach ($employees as $row) {
    fputcsv($out, [
      normalize_csv($row['id']),
      normalize_csv($row['full_name']),
      normalize_csv($row['email']),
      normalize_csv($row['emp_id']),
      normalize_csv($row['department']),
      normalize_csv($row['role']),
      normalize_csv($row['approval_status']),
      normalize_csv($row['sick_leave_balance']),
      normalize_csv($row['incentive_leave_balance']),
      normalize_csv($row['emergency_leave_balance']),
      !empty($row['google_id']) ? 'Yes' : 'No',
      normalize_csv($row['created_at']),
    ]);
  }
  fclose($out);
  exit;
}

$pageTitle = "Employee Records";
$active = "employees";

ob_start();
?>

<style>
.hr-employees-page * { box-sizing: border-box; }
.hr-employees-page {
  --hr-card: #313348;
  --hr-card-2: #252636;
  --hr-border: #3c3f58;
  --hr-text: #f3f4f6;
  --hr-muted: #9ca3af;
  --hr-accent: #6f6486;
  --hr-success: #22c55e;
  --hr-warning: #f59e0b;
  --hr-danger: #ef4444;
  --hr-info: #3b82f6;
  color: var(--hr-text);
}
.hr-employees-page .hero-card,
.hr-employees-page .stat-card,
.hr-employees-page .filter-card,
.hr-employees-page .table-card,
.hr-employees-page .modal-card,
.hr-employees-page .flash-box {
  border: 1px solid var(--hr-border);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
}
.hr-employees-page .hero-card {
  background: linear-gradient(135deg, var(--hr-accent), #4d4764 55%, #383b55 100%);
  border-radius: 24px;
  padding: 28px;
  position: relative;
  overflow: hidden;
}
.hr-employees-page .hero-card::before,
.hr-employees-page .hero-card::after {
  content: "";
  position: absolute;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
}
.hr-employees-page .hero-card::before { width: 220px; height: 220px; top: -90px; right: -40px; }
.hr-employees-page .hero-card::after { width: 140px; height: 140px; bottom: -50px; right: 120px; }
.hr-employees-page .hero-grid { display: grid; grid-template-columns: 1.3fr .7fr; gap: 20px; position: relative; z-index: 1; }
.hr-employees-page .page-badge {
  display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px;
  background: rgba(255,255,255,0.08); color: #fff; font-size: 12px; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase;
}
.hr-employees-page .hero-title { margin: 16px 0 8px; font-size: 34px; line-height: 1.15; font-weight: 800; color: #fff; }
.hr-employees-page .hero-subtitle { margin: 0; color: rgba(255,255,255,0.82); font-size: 14px; line-height: 1.75; max-width: 760px; }
.hr-employees-page .hero-side {
  background: rgba(17,24,39,0.24); border: 1px solid rgba(255,255,255,0.10); border-radius: 22px; padding: 20px;
}
.hr-employees-page .hero-side-label { color: rgba(255,255,255,0.72); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 8px; }
.hr-employees-page .hero-side-value { font-size: 38px; line-height: 1; font-weight: 800; color: #fff; }
.hr-employees-page .hero-side-note { margin-top: 10px; color: rgba(255,255,255,0.78); font-size: 13px; line-height: 1.6; }
.hr-employees-page .flash-box { margin-top: 22px; border-radius: 18px; padding: 14px 18px; font-size: 14px; font-weight: 700; }
.hr-employees-page .flash-success { background: rgba(34,197,94,0.16); color: #bbf7d0; border-color: rgba(34,197,94,0.35); }
.hr-employees-page .flash-error { background: rgba(239,68,68,0.15); color: #fecaca; border-color: rgba(239,68,68,0.35); }
.hr-employees-page .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; margin-top: 22px; }
.hr-employees-page .stat-card {
  background: var(--hr-card); border-radius: 22px; padding: 22px; min-height: 148px; position: relative; overflow: hidden;
}
.hr-employees-page .stat-card::after {
  content: ""; position: absolute; width: 92px; height: 92px; border-radius: 50%; top: -28px; right: -24px; opacity: .16;
}
.hr-employees-page .stat-total::after { background: #a855f7; }
.hr-employees-page .stat-approved::after { background: var(--hr-success); }
.hr-employees-page .stat-pending::after { background: var(--hr-warning); }
.hr-employees-page .stat-heads::after { background: var(--hr-info); }
.hr-employees-page .stat-label { color: var(--hr-muted); font-size: 13px; margin-bottom: 18px; }
.hr-employees-page .stat-value { font-size: 36px; line-height: 1; font-weight: 800; color: #fff; }
.hr-employees-page .stat-foot { margin-top: 14px; color: #cbd5e1; font-size: 13px; }
.hr-employees-page .filter-card,
.hr-employees-page .table-card { margin-top: 24px; background: var(--hr-card-2); border-radius: 24px; padding: 24px; }
.hr-employees-page .section-title { margin: 0; font-size: 22px; font-weight: 800; color: #fff; }
.hr-employees-page .section-subtitle { margin-top: 6px; color: var(--hr-muted); font-size: 13px; }
.hr-employees-page .filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 16px; margin-top: 18px; }
.hr-employees-page .field-label { display: block; margin-bottom: 8px; color: #fff; font-size: 13px; font-weight: 700; }
.hr-employees-page .input,
.hr-employees-page .select {
  width: 100%; background: #2f3146; border: 1px solid #424663; color: #fff; border-radius: 16px; padding: 14px 15px; outline: none;
}
.hr-employees-page .input:focus,
.hr-employees-page .select:focus { border-color: #8b7fb0; box-shadow: 0 0 0 4px rgba(139, 127, 176, 0.15); }
.hr-employees-page .select option { color: #111827; }
.hr-employees-page .filter-actions,
.hr-employees-page .table-top { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-top: 18px; }
.hr-employees-page .btn-row { display: flex; flex-wrap: wrap; gap: 10px; }
.hr-employees-page .btn,
.hr-employees-page .table-action,
.hr-employees-page .modal-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 16px; padding: 13px 16px;
  font-size: 13px; font-weight: 800; cursor: pointer; text-decoration: none; transition: transform .2s ease, opacity .2s ease;
}
.hr-employees-page .btn:hover,
.hr-employees-page .table-action:hover,
.hr-employees-page .modal-btn:hover { transform: translateY(-1px); opacity: .97; }
.hr-employees-page .btn-primary,
.hr-employees-page .modal-btn-primary { background: linear-gradient(135deg, #6f6486 0%, #5d5677 100%); color: #fff; }
.hr-employees-page .btn-secondary,
.hr-employees-page .modal-btn-secondary { background: #3a3d56; color: #fff; border: 1px solid #4a4f6e; }
.hr-employees-page .btn-outline { background: transparent; color: #fff; border: 1px solid #4a4f6e; }
.hr-employees-page .table-wrap { width: 100%; overflow-x: auto; margin-top: 18px; }
.hr-employees-page .employee-table { width: 100%; min-width: 1200px; border-collapse: separate; border-spacing: 0 12px; }
.hr-employees-page .employee-table thead th {
  text-align: left; color: var(--hr-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; padding: 0 14px 6px; font-weight: 700;
}
.hr-employees-page .employee-table tbody tr { background: var(--hr-card); }
.hr-employees-page .employee-table tbody td {
  padding: 16px 14px; font-size: 13px; color: #e5e7eb; vertical-align: top; border-top: 1px solid var(--hr-border); border-bottom: 1px solid var(--hr-border);
}
.hr-employees-page .employee-table tbody td:first-child {
  border-left: 1px solid var(--hr-border); border-top-left-radius: 16px; border-bottom-left-radius: 16px;
}
.hr-employees-page .employee-table tbody td:last-child {
  border-right: 1px solid var(--hr-border); border-top-right-radius: 16px; border-bottom-right-radius: 16px;
}
.hr-employees-page .name-wrap { display: grid; gap: 5px; }
.hr-employees-page .name-main { color: #fff; font-size: 14px; font-weight: 800; }
.hr-employees-page .name-sub { color: var(--hr-muted); font-size: 12px; }
.hr-employees-page .badge,
.hr-employees-page .small-badge {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 8px 12px; border-radius: 999px; font-size: 12px; font-weight: 700;
}
.hr-employees-page .small-badge { padding: 6px 10px; font-size: 11px; }
.hr-employees-page .role-employee { background: rgba(59,130,246,0.14); color: #bfdbfe; border: 1px solid rgba(59,130,246,0.32); }
.hr-employees-page .role-head { background: rgba(245,158,11,0.14); color: #fde68a; border: 1px solid rgba(245,158,11,0.32); }
.hr-employees-page .role-hr { background: rgba(168,85,247,0.14); color: #e9d5ff; border: 1px solid rgba(168,85,247,0.32); }
.hr-employees-page .status-approved { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.32); }
.hr-employees-page .status-pending { background: rgba(245,158,11,0.14); color: #fde68a; border: 1px solid rgba(245,158,11,0.32); }
.hr-employees-page .status-rejected { background: rgba(239,68,68,0.14); color: #fecaca; border: 1px solid rgba(239,68,68,0.32); }
.hr-employees-page .balance-list { display: grid; gap: 6px; color: #d1d5db; font-size: 12px; }
.hr-employees-page .balance-list span b { color: #fff; }
.hr-employees-page .table-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.hr-employees-page .table-action { border-radius: 12px; padding: 10px 12px; font-size: 12px; }
.hr-employees-page .action-edit { background: rgba(59,130,246,0.14); color: #bfdbfe; border: 1px solid rgba(59,130,246,0.28); }
.hr-employees-page .action-reset { background: rgba(245,158,11,0.14); color: #fde68a; border: 1px solid rgba(245,158,11,0.28); }
.hr-employees-page .empty-state { text-align: center; padding: 36px 20px 12px; color: var(--hr-muted); font-size: 14px; }
.hr-employees-page .modal-overlay {
  position: fixed; inset: 0; background: rgba(12, 14, 24, 0.72); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center;
  z-index: 9999; padding: 20px;
}
.hr-employees-page .modal-overlay.active { display: flex; }
.hr-employees-page .modal-card { width: 100%; max-width: 860px; background: var(--hr-card-2); border-radius: 24px; padding: 24px; }
.hr-employees-page .modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.hr-employees-page .modal-title { margin: 0; color: #fff; font-size: 24px; font-weight: 800; }
.hr-employees-page .modal-subtitle { margin-top: 6px; color: var(--hr-muted); font-size: 13px; }
.hr-employees-page .modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.hr-employees-page .field-full { grid-column: 1 / -1; }
.hr-employees-page .balance-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.hr-employees-page .modal-actions { display: flex; gap: 12px; margin-top: 22px; }
.hr-employees-page .modal-actions .modal-btn { flex: 1; }

@media (max-width: 1100px) {
  .hr-employees-page .hero-grid,
  .hr-employees-page .filter-grid,
  .hr-employees-page .modal-grid { grid-template-columns: 1fr 1fr; }
  .hr-employees-page .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 780px) {
  .hr-employees-page .hero-grid,
  .hr-employees-page .filter-grid,
  .hr-employees-page .modal-grid,
  .hr-employees-page .balance-grid,
  .hr-employees-page .modal-actions { grid-template-columns: 1fr; display: grid; }
  .hr-employees-page .hero-title { font-size: 28px; }
  .hr-employees-page .stats-grid { grid-template-columns: 1fr; }
}
</style>

<div class="hr-employees-page">
  <section class="hero-card">
    <div class="hero-grid">
      <div>
        <span class="page-badge">HR Management</span>
        <h1 class="hero-title">Employee Records</h1>
        <p class="hero-subtitle">
          Manage employee profiles, update roles, review approval status, and maintain leave balances
          in one clean HR workspace designed for your school human resources system.
        </p>
      </div>

      <div class="hero-side">
        <div class="hero-side-label">Pending HR Approval</div>
        <div class="hero-side-value"><?php echo (int)$pendingHr; ?></div>
        <div class="hero-side-note">
          Employees waiting for HR-level approval are counted here for quick review.
        </div>
      </div>
    </div>
  </section>

  <?php if ($flash): ?>
    <div class="flash-box <?php echo $flashType === 'error' ? 'flash-error' : 'flash-success'; ?>">
      <?php echo htmlspecialchars($flash); ?>
    </div>
  <?php endif; ?>

  <section class="stats-grid">
    <div class="stat-card stat-total">
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
      <div class="stat-foot">All employee, head, and HR accounts in the system.</div>
    </div>
    <div class="stat-card stat-approved">
      <div class="stat-label">Approved Accounts</div>
      <div class="stat-value"><?php echo (int)$stats['approved']; ?></div>
      <div class="stat-foot">Users already approved and active in the HR workflow.</div>
    </div>
    <div class="stat-card stat-pending">
      <div class="stat-label">Pending Accounts</div>
      <div class="stat-value"><?php echo (int)$stats['pending']; ?></div>
      <div class="stat-foot">Accounts still waiting for head or HR approval.</div>
    </div>
    <div class="stat-card stat-heads">
      <div class="stat-label">Department Heads</div>
      <div class="stat-value"><?php echo (int)$stats['heads']; ?></div>
      <div class="stat-foot">Head accounts: <?php echo (int)$stats['heads']; ?> • HR accounts: <?php echo (int)$stats['hr']; ?></div>
    </div>
  </section>

  <form method="GET" class="filter-card">
    <h2 class="section-title">Search and Filter</h2>
    <div class="section-subtitle">Quickly find employee records by name, email, role, department, or approval state.</div>

    <div class="filter-grid">
      <div>
        <label class="field-label">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name / Email / Employee ID" class="input">
      </div>
      <div>
        <label class="field-label">Department</label>
        <select name="department" class="select">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $fDept === $d ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="field-label">Role</label>
        <select name="role" class="select">
          <option value="">All Roles</option>
          <option value="employee" <?php echo $fRole === 'employee' ? 'selected' : ''; ?>>Employee</option>
          <option value="head" <?php echo $fRole === 'head' ? 'selected' : ''; ?>>Department Head</option>
          <option value="hr" <?php echo $fRole === 'hr' ? 'selected' : ''; ?>>HR</option>
        </select>
      </div>
      <div>
        <label class="field-label">Approval Status</label>
        <select name="approval_status" class="select">
          <option value="">All Status</option>
          <option value="pending_head" <?php echo $fApproval === 'pending_head' ? 'selected' : ''; ?>>Pending Head</option>
          <option value="pending_hr" <?php echo $fApproval === 'pending_hr' ? 'selected' : ''; ?>>Pending HR</option>
          <option value="approved" <?php echo $fApproval === 'approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="rejected" <?php echo $fApproval === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
      </div>
    </div>

    <div class="filter-actions">
      <div class="btn-row">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="employees.php" class="btn btn-secondary">Reset</a>
      </div>
      <div class="btn-row">
        <a href="employees.php?<?php echo htmlspecialchars(http_build_query(['q'=>$q,'department'=>$fDept,'role'=>$fRole,'approval_status'=>$fApproval,'export'=>'csv'])); ?>" class="btn btn-outline">
          Export CSV
        </a>
      </div>
    </div>
  </form>

  <section class="table-card">
    <div class="table-top">
      <div>
        <h2 class="section-title">Employee List</h2>
        <div class="section-subtitle">Showing <?php echo count($employees); ?> record(s) based on your current filter.</div>
      </div>
      <div class="small-badge" style="background:rgba(111,100,134,0.22); color:#ddd6fe; border:1px solid rgba(139,127,176,0.35);">
        Latest 500 users
      </div>
    </div>

    <div class="table-wrap">
      <table class="employee-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Employee ID</th>
            <th>Department</th>
            <th>Role</th>
            <th>Approval</th>
            <th>Leave Balances</th>
            <th>Account</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($employees) === 0): ?>
            <tr>
              <td colspan="9"><div class="empty-state">No employee records found for the selected filters.</div></td>
            </tr>
          <?php else: ?>
            <?php foreach ($employees as $r): ?>
              <?php
                $roleClass = 'role-employee';
                if (($r['role'] ?? '') === 'head') $roleClass = 'role-head';
                if (($r['role'] ?? '') === 'hr') $roleClass = 'role-hr';

                $statusClass = 'status-pending';
                if (($r['approval_status'] ?? '') === 'approved') $statusClass = 'status-approved';
                if (($r['approval_status'] ?? '') === 'rejected') $statusClass = 'status-rejected';
              ?>
              <tr>
                <td>
                  <div class="name-wrap">
                    <div class="name-main"><?php echo htmlspecialchars($r['full_name']); ?></div>
                    <div class="name-sub"><?php echo htmlspecialchars($r['email']); ?></div>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($r['emp_id'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($r['department'] ?: '—'); ?></td>
                <td><span class="badge <?php echo $roleClass; ?>"><?php echo htmlspecialchars(ucfirst($r['role'])); ?></span></td>
                <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $r['approval_status']))); ?></span></td>
                <td>
                  <div class="balance-list">
                    <span>SL: <b><?php echo (int)$r['sick_leave_balance']; ?></b></span>
                    <span>IL: <b><?php echo (int)$r['incentive_leave_balance']; ?></b></span>
                    <span>EL: <b><?php echo (int)$r['emergency_leave_balance']; ?></b></span>
                  </div>
                </td>
                <td>
                  <span class="small-badge" style="background:rgba(59,130,246,0.14); color:#bfdbfe; border:1px solid rgba(59,130,246,0.28);">
                    <?php echo !empty($r['google_id']) ? 'Google Linked' : 'Local Record'; ?>
                  </span>
                </td>
                <td><?php echo !empty($r['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($r['created_at']))) : '—'; ?></td>
                <td>
                  <div class="table-actions">
                    <button type="button" class="table-action action-edit"
                      onclick='openEditModal(<?php echo json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>
                      Edit
                    </button>

                    <form method="POST" onsubmit="return confirm('Reset leave balances to 5 / 5 / 5 for this employee?');" style="display:inline;">
                      <input type="hidden" name="reset_balance" value="1">
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <button type="submit" class="table-action action-reset">Reset Credits</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div id="editModal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-head">
        <div>
          <h3 class="modal-title">Edit Employee</h3>
          <div class="modal-subtitle" id="modalEmail"></div>
        </div>
        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeEditModal()">Close</button>
      </div>

      <form method="POST">
        <input type="hidden" name="update_employee" value="1">
        <input type="hidden" name="id" id="m_id">

        <div class="modal-grid">
          <div class="field-full">
            <label class="field-label">Full Name</label>
            <input type="text" name="full_name" id="m_full_name" class="input" required>
          </div>

          <div>
            <label class="field-label">Employee ID</label>
            <input type="text" name="emp_id" id="m_emp_id" class="input">
          </div>

          <div>
            <label class="field-label">Department</label>
            <input type="text" name="department" id="m_department" class="input">
          </div>

          <div>
            <label class="field-label">Role</label>
            <select name="role" id="m_role" class="select">
              <option value="employee">Employee</option>
              <option value="head">Department Head</option>
              <option value="hr">HR</option>
            </select>
          </div>

          <div>
            <label class="field-label">Approval Status</label>
            <select name="approval_status" id="m_approval" class="select">
              <option value="pending_head">Pending Head</option>
              <option value="pending_hr">Pending HR</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div class="field-full">
            <label class="field-label">Leave Balances</label>
            <div class="balance-grid">
              <div>
                <label class="field-label">Sick Leave</label>
                <input type="number" min="0" name="sick_leave_balance" id="m_sick_leave_balance" class="input">
              </div>
              <div>
                <label class="field-label">Incentive Leave</label>
                <input type="number" min="0" name="incentive_leave_balance" id="m_incentive_leave_balance" class="input">
              </div>
              <div>
                <label class="field-label">Emergency Leave</label>
                <input type="number" min="0" name="emergency_leave_balance" id="m_emergency_leave_balance" class="input">
              </div>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button type="submit" class="modal-btn modal-btn-primary">Save Changes</button>
          <button type="button" class="modal-btn modal-btn-secondary" onclick="closeEditModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const editModal = document.getElementById('editModal');

function openEditModal(emp) {
  document.getElementById('m_id').value = emp.id || '';
  document.getElementById('m_full_name').value = emp.full_name || '';
  document.getElementById('m_emp_id').value = emp.emp_id || '';
  document.getElementById('m_department').value = emp.department || '';
  document.getElementById('m_role').value = emp.role || 'employee';
  document.getElementById('m_approval').value = emp.approval_status || 'pending_head';
  document.getElementById('m_sick_leave_balance').value = emp.sick_leave_balance || 0;
  document.getElementById('m_incentive_leave_balance').value = emp.incentive_leave_balance || 0;
  document.getElementById('m_emergency_leave_balance').value = emp.emergency_leave_balance || 0;
  document.getElementById('modalEmail').textContent = emp.email ? ('Email: ' + emp.email) : '';
  editModal.classList.add('active');
}

function closeEditModal() {
  editModal.classList.remove('active');
}

if (editModal) {
  editModal.addEventListener('click', function (e) {
    if (e.target === editModal) closeEditModal();
  });
}
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeEditModal();
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/hr_layout.php";
