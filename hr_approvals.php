<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$hrId = (int)$_SESSION['user_db_id'];

$me = $conn->prepare("
  SELECT id, full_name, email, role, approval_status, profile_completed
  FROM users
  WHERE id = ?
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

$flash = $_SESSION['flash'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

function set_flash($message, $type = 'success') {
  $_SESSION['flash'] = $message;
  $_SESSION['flash_type'] = $type;
}

// Pending count for sidebar badge
$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status = 'pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

// Approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_id'])) {
  $uid = (int)($_POST['approve_user_id'] ?? 0);

  if ($uid > 0) {
    $upd = $conn->prepare("
      UPDATE users
      SET approval_status='approved', approved_by_hr=?, approved_hr_at=NOW()
      WHERE id=? AND approval_status='pending_hr'
    ");
    $upd->bind_param("ii", $hrId, $uid);
    $upd->execute();

    set_flash("User approved successfully.");
  } else {
    set_flash("Invalid approval request.", "error");
  }

  header("Location: hr_approvals.php");
  exit;
}

// Reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user_id'])) {
  $uid = (int)($_POST['reject_user_id'] ?? 0);

  if ($uid > 0) {
    $upd = $conn->prepare("
      UPDATE users
      SET approval_status='rejected', approved_by_hr=?, approved_hr_at=NOW()
      WHERE id=? AND approval_status='pending_hr'
    ");
    $upd->bind_param("ii", $hrId, $uid);
    $upd->execute();

    set_flash("User rejected successfully.");
  } else {
    set_flash("Invalid rejection request.", "error");
  }

  header("Location: hr_approvals.php");
  exit;
}

// Search/filter
$q = trim($_GET['q'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fDepartment = trim($_GET['department'] ?? '');

$where = ["approval_status='pending_hr'"];
$params = [];
$types = "";

if ($q !== '') {
  $where[] = "(full_name LIKE ? OR email LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}

if ($fRole !== '') {
  $where[] = "role = ?";
  $params[] = $fRole;
  $types .= "s";
}

if ($fDepartment !== '') {
  $where[] = "department = ?";
  $params[] = $fDepartment;
  $types .= "s";
}

$sqlWhere = "WHERE " . implode(" AND ", $where);

// Stats
$pendingEmployees = 0;
$pendingHeads = 0;
$pendingToday = 0;

try {
  $stats = $conn->query("
    SELECT
      SUM(role='employee') AS pending_employees,
      SUM(role='head') AS pending_heads,
      SUM(DATE(created_at)=CURDATE()) AS pending_today
    FROM users
    WHERE approval_status='pending_hr'
  ");
  if ($stats) {
    $s = $stats->fetch_assoc();
    $pendingEmployees = (int)($s['pending_employees'] ?? 0);
    $pendingHeads = (int)($s['pending_heads'] ?? 0);
    $pendingToday = (int)($s['pending_today'] ?? 0);
  }
} catch (Throwable $e) {}

// Departments for filter
$departments = [];
try {
  $dep = $conn->query("
    SELECT DISTINCT department
    FROM users
    WHERE approval_status='pending_hr' AND department IS NOT NULL AND department <> ''
    ORDER BY department ASC
  ");
  if ($dep) {
    while ($d = $dep->fetch_assoc()) $departments[] = $d['department'];
  }
} catch (Throwable $e) {}

// Fetch pending list
$sql = "
  SELECT id, full_name, email, role, department, created_at
  FROM users
  $sqlWhere
  ORDER BY created_at DESC, id DESC
";
$list = $conn->prepare($sql);
if ($types !== '') $list->bind_param($types, ...$params);
$list->execute();
$rows = $list->get_result();

$pageTitle = "HR Approvals";
$active = "approvals";

ob_start();
?>

<style>
.hr-approvals-page * { box-sizing: border-box; }

.hr-approvals-page {
  --hr-card: #252636;
  --hr-card-2: #313348;
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

.hr-approvals-page .hero-card,
.hr-approvals-page .stat-card,
.hr-approvals-page .filter-card,
.hr-approvals-page .table-card,
.hr-approvals-page .flash-box {
  border: 1px solid var(--hr-border);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
}

.hr-approvals-page .hero-card {
  background: linear-gradient(135deg, var(--hr-accent), #4d4764 55%, #383b55 100%);
  border-radius: 24px;
  padding: 28px;
  position: relative;
  overflow: hidden;
}

.hr-approvals-page .hero-card::before,
.hr-approvals-page .hero-card::after {
  content: "";
  position: absolute;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
}

.hr-approvals-page .hero-card::before {
  width: 220px;
  height: 220px;
  top: -90px;
  right: -40px;
}

.hr-approvals-page .hero-card::after {
  width: 140px;
  height: 140px;
  bottom: -50px;
  right: 120px;
}

.hr-approvals-page .hero-grid {
  display: grid;
  grid-template-columns: 1.25fr .75fr;
  gap: 20px;
  position: relative;
  z-index: 1;
}

.hr-approvals-page .page-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 999px;
  background: rgba(255,255,255,0.08);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.hr-approvals-page .hero-title {
  margin: 16px 0 8px;
  font-size: 34px;
  line-height: 1.15;
  font-weight: 800;
  color: #fff;
}

.hr-approvals-page .hero-subtitle {
  margin: 0;
  color: rgba(255,255,255,0.82);
  font-size: 14px;
  line-height: 1.75;
  max-width: 760px;
}

.hr-approvals-page .hero-side {
  background: rgba(17,24,39,0.24);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 22px;
  padding: 20px;
  align-self: stretch;
}

.hr-approvals-page .hero-side-label {
  color: rgba(255,255,255,0.72);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: 8px;
}

.hr-approvals-page .hero-side-value {
  font-size: 40px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.hr-approvals-page .hero-side-note {
  margin-top: 10px;
  color: rgba(255,255,255,0.78);
  font-size: 13px;
  line-height: 1.6;
}

.hr-approvals-page .flash-box {
  margin-top: 22px;
  border-radius: 18px;
  padding: 14px 18px;
  font-size: 14px;
  font-weight: 700;
}

.hr-approvals-page .flash-success {
  background: rgba(34,197,94,0.16);
  color: #bbf7d0;
  border-color: rgba(34,197,94,0.35);
}

.hr-approvals-page .flash-error {
  background: rgba(239,68,68,0.15);
  color: #fecaca;
  border-color: rgba(239,68,68,0.35);
}

.hr-approvals-page .stats-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin-top: 22px;
}

.hr-approvals-page .stat-card {
  background: var(--hr-card-2);
  border-radius: 22px;
  padding: 22px;
  min-height: 148px;
  position: relative;
  overflow: hidden;
}

.hr-approvals-page .stat-card::after {
  content: "";
  position: absolute;
  width: 92px;
  height: 92px;
  border-radius: 50%;
  top: -28px;
  right: -24px;
  opacity: .16;
}

.hr-approvals-page .stat-total::after { background: var(--hr-warning); }
.hr-approvals-page .stat-employees::after { background: var(--hr-info); }
.hr-approvals-page .stat-heads::after { background: #a855f7; }

.hr-approvals-page .stat-label {
  color: var(--hr-muted);
  font-size: 13px;
  margin-bottom: 18px;
}

.hr-approvals-page .stat-value {
  font-size: 36px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.hr-approvals-page .stat-foot {
  margin-top: 14px;
  color: #cbd5e1;
  font-size: 13px;
}

.hr-approvals-page .filter-card,
.hr-approvals-page .table-card {
  margin-top: 24px;
  background: var(--hr-card);
  border-radius: 24px;
  padding: 24px;
}

.hr-approvals-page .section-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: #fff;
}

.hr-approvals-page .section-subtitle {
  margin-top: 6px;
  color: var(--hr-muted);
  font-size: 13px;
}

.hr-approvals-page .filter-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 16px;
  margin-top: 18px;
}

.hr-approvals-page .field-label {
  display: block;
  margin-bottom: 8px;
  color: #fff;
  font-size: 13px;
  font-weight: 700;
}

.hr-approvals-page .input,
.hr-approvals-page .select {
  width: 100%;
  background: #2f3146;
  border: 1px solid #424663;
  color: #fff;
  border-radius: 16px;
  padding: 14px 15px;
  outline: none;
}

.hr-approvals-page .input:focus,
.hr-approvals-page .select:focus {
  border-color: #8b7fb0;
  box-shadow: 0 0 0 4px rgba(139, 127, 176, 0.15);
}

.hr-approvals-page .select option {
  color: #111827;
}

.hr-approvals-page .filter-actions,
.hr-approvals-page .table-top {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-top: 18px;
}

.hr-approvals-page .btn-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.hr-approvals-page .btn,
.hr-approvals-page .action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 0;
  border-radius: 16px;
  padding: 13px 16px;
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  text-decoration: none;
  transition: transform .2s ease, opacity .2s ease;
}

.hr-approvals-page .btn:hover,
.hr-approvals-page .action-btn:hover {
  transform: translateY(-1px);
  opacity: .97;
}

.hr-approvals-page .btn-primary {
  background: linear-gradient(135deg, #6f6486 0%, #5d5677 100%);
  color: #fff;
}

.hr-approvals-page .btn-secondary {
  background: #3a3d56;
  color: #fff;
  border: 1px solid #4a4f6e;
}

.hr-approvals-page .table-wrap {
  width: 100%;
  overflow-x: auto;
  margin-top: 18px;
}

.hr-approvals-page .approval-table {
  width: 100%;
  min-width: 1080px;
  border-collapse: separate;
  border-spacing: 0 12px;
}

.hr-approvals-page .approval-table thead th {
  text-align: left;
  color: var(--hr-muted);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .08em;
  padding: 0 14px 6px;
  font-weight: 700;
}

.hr-approvals-page .approval-table tbody tr {
  background: var(--hr-card-2);
}

.hr-approvals-page .approval-table tbody td {
  padding: 16px 14px;
  font-size: 13px;
  color: #e5e7eb;
  vertical-align: top;
  border-top: 1px solid var(--hr-border);
  border-bottom: 1px solid var(--hr-border);
}

.hr-approvals-page .approval-table tbody td:first-child {
  border-left: 1px solid var(--hr-border);
  border-top-left-radius: 16px;
  border-bottom-left-radius: 16px;
}

.hr-approvals-page .approval-table tbody td:last-child {
  border-right: 1px solid var(--hr-border);
  border-top-right-radius: 16px;
  border-bottom-right-radius: 16px;
}

.hr-approvals-page .name-main {
  color: #fff;
  font-size: 14px;
  font-weight: 800;
}

.hr-approvals-page .name-sub {
  color: var(--hr-muted);
  font-size: 12px;
  margin-top: 5px;
}

.hr-approvals-page .badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 8px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.hr-approvals-page .role-employee {
  background: rgba(59,130,246,0.14);
  color: #bfdbfe;
  border: 1px solid rgba(59,130,246,0.32);
}

.hr-approvals-page .role-head {
  background: rgba(245,158,11,0.14);
  color: #fde68a;
  border: 1px solid rgba(245,158,11,0.32);
}

.hr-approvals-page .action-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.hr-approvals-page .approve-btn {
  background: rgba(34,197,94,0.16);
  color: #bbf7d0;
  border: 1px solid rgba(34,197,94,0.30);
}

.hr-approvals-page .reject-btn {
  background: rgba(239,68,68,0.15);
  color: #fecaca;
  border: 1px solid rgba(239,68,68,0.30);
}

.hr-approvals-page .empty-state {
  text-align: center;
  padding: 36px 20px 12px;
  color: var(--hr-muted);
  font-size: 14px;
}

@media (max-width: 1024px) {
  .hr-approvals-page .hero-grid,
  .hr-approvals-page .filter-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 720px) {
  .hr-approvals-page .hero-title {
    font-size: 28px;
  }

  .hr-approvals-page .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="hr-approvals-page">
  <section class="hero-card">
    <div class="hero-grid">
      <div>
        <span class="page-badge">HR Review Queue</span>
        <h1 class="hero-title">HR Pending Approvals</h1>
        <p class="hero-subtitle">
          Review employee accounts endorsed by department heads and decide whether to approve or reject access
          before they can fully use the school HR system.
        </p>
      </div>

      <div class="hero-side">
        <div class="hero-side-label">Pending Requests</div>
        <div class="hero-side-value"><?php echo $pendingHr; ?></div>
        <div class="hero-side-note">
          Accounts currently waiting for HR action.
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
      <div class="stat-label">Pending Accounts</div>
      <div class="stat-value"><?php echo $pendingHr; ?></div>
      <div class="stat-foot">Total users waiting for HR approval.</div>
    </div>

    <div class="stat-card stat-employees">
      <div class="stat-label">Employees</div>
      <div class="stat-value"><?php echo $pendingEmployees; ?></div>
      <div class="stat-foot">Pending employee accounts in queue.</div>
    </div>

    <div class="stat-card stat-heads">
      <div class="stat-label">Department Heads</div>
      <div class="stat-value"><?php echo $pendingHeads; ?></div>
      <div class="stat-foot"><?php echo $pendingToday; ?> submitted today.</div>
    </div>
  </section>

  <form method="GET" class="filter-card">
    <h2 class="section-title">Search and Filter</h2>
    <div class="section-subtitle">Find pending approvals by employee name, email, role, or department.</div>

    <div class="filter-grid">
      <div>
        <label class="field-label">Search</label>
        <input
          type="text"
          name="q"
          value="<?php echo htmlspecialchars($q); ?>"
          placeholder="Name or email"
          class="input"
        />
      </div>

      <div>
        <label class="field-label">Role</label>
        <select name="role" class="select">
          <option value="">All Roles</option>
          <option value="employee" <?php echo $fRole === 'employee' ? 'selected' : ''; ?>>Employee</option>
          <option value="head" <?php echo $fRole === 'head' ? 'selected' : ''; ?>>Department Head</option>
        </select>
      </div>

      <div>
        <label class="field-label">Department</label>
        <select name="department" class="select">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $fDepartment === $d ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="filter-actions">
      <div class="btn-row">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <a href="hr_approvals.php" class="btn btn-secondary">Reset</a>
      </div>

      <div class="btn-row">
        <span class="btn btn-secondary" style="cursor:default;">
          <?php echo (int)$rows->num_rows; ?> result(s)
        </span>
      </div>
    </div>
  </form>

  <section class="table-card">
    <div class="table-top">
      <div>
        <h2 class="section-title">Approval Queue</h2>
        <div class="section-subtitle">Approve or reject accounts endorsed for HR review.</div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="approval-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Department</th>
            <th>Requested</th>
            <th>Action</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($rows->num_rows === 0): ?>
            <tr>
              <td colspan="6">
                <div class="empty-state">No pending HR approvals found.</div>
              </td>
            </tr>
          <?php else: ?>
            <?php while ($u = $rows->fetch_assoc()) { ?>
              <?php $roleClass = ($u['role'] ?? '') === 'head' ? 'role-head' : 'role-employee'; ?>
              <tr>
                <td>
                  <div class="name-main"><?php echo htmlspecialchars($u['full_name']); ?></div>
                </td>

                <td>
                  <div class="name-sub" style="margin-top:0;"><?php echo htmlspecialchars($u['email']); ?></div>
                </td>

                <td>
                  <span class="badge <?php echo $roleClass; ?>">
                    <?php echo htmlspecialchars(ucfirst((string)$u['role'])); ?>
                  </span>
                </td>

                <td><?php echo htmlspecialchars($u['department'] ?: '—'); ?></td>

                <td>
                  <?php
                    $ts = strtotime($u['created_at'] ?? '');
                    echo $ts ? date("M d, Y h:i A", $ts) : htmlspecialchars((string)$u['created_at']);
                  ?>
                </td>

                <td>
                  <div class="action-wrap">
                    <form method="POST" onsubmit="return confirm('Approve this user?');" style="display:inline;">
                      <input type="hidden" name="approve_user_id" value="<?php echo (int)$u['id']; ?>">
                      <button type="submit" class="action-btn approve-btn">Approve</button>
                    </form>

                    <form method="POST" onsubmit="return confirm('Reject this user?');" style="display:inline;">
                      <input type="hidden" name="reject_user_id" value="<?php echo (int)$u['id']; ?>">
                      <button type="submit" class="action-btn reject-btn">Reject</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php } ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/hr_layout.php";
