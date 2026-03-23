<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

// Fetch HR user
$stmt = $conn->prepare("
  SELECT id, full_name, email, department, emp_id, role, approval_status, profile_completed
  FROM users
  WHERE id = ?
  LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }

// HR only
if (($user['role'] ?? '') !== 'hr') {
  if (($user['role'] ?? '') === 'employee') { header("Location: dashboard_employee.php"); exit; }
  if (($user['role'] ?? '') === 'head') { header("Location: dashboard_head.php"); exit; }
  header("Location: login.php"); exit;
}

$_SESSION['user_role'] = 'hr';

// =====================
// Dashboard stats
// =====================
$totalEmployees = 0;
$totalHeads = 0;
$totalHrUsers = 0;
$approvedUsers = 0;
$pendingHr = 0;
$totalLeaveRequests = 0;
$pendingLeaveRequests = 0;
$approvedLeaveRequests = 0;
$rejectedLeaveRequests = 0;

try {
  $statsUsers = $conn->query("
    SELECT
      SUM(role='employee') AS total_employees,
      SUM(role='head') AS total_heads,
      SUM(role='hr') AS total_hr,
      SUM(approval_status='approved') AS approved_users,
      SUM(approval_status='pending_hr') AS pending_hr
    FROM users
  ");
  if ($statsUsers) {
    $row = $statsUsers->fetch_assoc();
    $totalEmployees = (int)($row['total_employees'] ?? 0);
    $totalHeads = (int)($row['total_heads'] ?? 0);
    $totalHrUsers = (int)($row['total_hr'] ?? 0);
    $approvedUsers = (int)($row['approved_users'] ?? 0);
    $pendingHr = (int)($row['pending_hr'] ?? 0);
  }
} catch (Throwable $e) {}

try {
  $statsLeave = $conn->query("
    SELECT
      COUNT(*) AS total_leaves,
      SUM(status IN ('pending_head','pending_hr_receive')) AS pending_leaves,
      SUM(status IN ('approved_head','received')) AS approved_leaves,
      SUM(status IN ('rejected_head','rejected_hr')) AS rejected_leaves
    FROM leave_requests
  ");
  if ($statsLeave) {
    $row = $statsLeave->fetch_assoc();
    $totalLeaveRequests = (int)($row['total_leaves'] ?? 0);
    $pendingLeaveRequests = (int)($row['pending_leaves'] ?? 0);
    $approvedLeaveRequests = (int)($row['approved_leaves'] ?? 0);
    $rejectedLeaveRequests = (int)($row['rejected_leaves'] ?? 0);
  }
} catch (Throwable $e) {}

// =====================
// Announcements
// =====================
$announcements = [];
try {
  $a = $conn->query("SELECT id, title, message, created_at FROM announcements ORDER BY created_at DESC LIMIT 5");
  if ($a) {
    while ($row = $a->fetch_assoc()) $announcements[] = $row;
  }
} catch (Throwable $e) {}

// =====================
// Current month calendar
// =====================
$year  = (int)date('Y');
$month = (int)date('n');
$firstDayTs = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)date('t', $firstDayTs);
$startWeekday = (int)date('N', $firstDayTs);
$monthName = date('F', $firstDayTs);
$weekdays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

$holidaysByDate = [];
try {
  $monthStart = sprintf('%04d-%02d-01', $year, $month);
  $monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

  $hol = $conn->prepare("
    SELECT id, event_date, title
    FROM calendar_events
    WHERE type='holiday' AND event_date BETWEEN ? AND ?
    ORDER BY event_date ASC
  ");
  $hol->bind_param("ss", $monthStart, $monthEnd);
  $hol->execute();
  $res = $hol->get_result();
  while ($r = $res->fetch_assoc()) {
    $holidaysByDate[$r['event_date']] = $r;
  }
} catch (Throwable $e) {}

// =====================
// Latest leave requests
// =====================
$latestLeaves = [];
try {
  $lq = $conn->query("
    SELECT lr.id, lr.leave_type, lr.date_from, lr.date_to, lr.days, lr.status, lr.created_at, u.full_name
    FROM leave_requests lr
    LEFT JOIN users u ON u.id = lr.user_id
    ORDER BY lr.id DESC
    LIMIT 6
  ");
  if ($lq) {
    while ($row = $lq->fetch_assoc()) $latestLeaves[] = $row;
  }
} catch (Throwable $e) {}

$pageTitle = "Dashboard";
$active = "home";

ob_start();
?>

<style>
.hr-dashboard-page * { box-sizing: border-box; }

.hr-dashboard-page {
  --hr-card: #252636;
  --hr-card-2: #313348;
  --hr-border: #3c3f58;
  --hr-text: #f3f4f6;
  --hr-muted: #9ca3af;
  --hr-accent: #6f6486;
  --hr-accent-2: #8b7fb0;
  --hr-success: #22c55e;
  --hr-warning: #f59e0b;
  --hr-danger: #ef4444;
  --hr-info: #3b82f6;
  color: var(--hr-text);
}

.hr-dashboard-page .hero-card,
.hr-dashboard-page .stat-card,
.hr-dashboard-page .panel-card,
.hr-dashboard-page .announcement-item,
.hr-dashboard-page .calendar-cell,
.hr-dashboard-page .quick-link,
.hr-dashboard-page .leave-item {
  border: 1px solid var(--hr-border);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
}

.hr-dashboard-page .hero-card {
  background: linear-gradient(135deg, var(--hr-accent), #4d4764 55%, #383b55 100%);
  border-radius: 24px;
  padding: 28px;
  position: relative;
  overflow: hidden;
}

.hr-dashboard-page .hero-card::before,
.hr-dashboard-page .hero-card::after {
  content: "";
  position: absolute;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  pointer-events: none;
}

.hr-dashboard-page .hero-card::before {
  width: 240px;
  height: 240px;
  top: -100px;
  right: -40px;
}

.hr-dashboard-page .hero-card::after {
  width: 150px;
  height: 150px;
  bottom: -60px;
  right: 130px;
}

.hr-dashboard-page .hero-grid {
  display: grid;
  grid-template-columns: 1.3fr .7fr;
  gap: 20px;
  position: relative;
  z-index: 1;
}

.hr-dashboard-page .page-badge {
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

.hr-dashboard-page .hero-title {
  margin: 16px 0 8px;
  font-size: 34px;
  line-height: 1.15;
  font-weight: 800;
  color: #fff;
}

.hr-dashboard-page .hero-subtitle {
  margin: 0;
  color: rgba(255,255,255,0.82);
  font-size: 14px;
  line-height: 1.7;
  max-width: 760px;
}

.hr-dashboard-page .hero-side {
  background: rgba(17,24,39,0.24);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 22px;
  padding: 20px;
  align-self: stretch;
}

.hr-dashboard-page .hero-side-label {
  color: rgba(255,255,255,0.72);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: 8px;
}

.hr-dashboard-page .hero-side-value {
  font-size: 40px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.hr-dashboard-page .hero-side-note {
  margin-top: 10px;
  color: rgba(255,255,255,0.78);
  font-size: 13px;
  line-height: 1.6;
}

.hr-dashboard-page .stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  margin-top: 22px;
}

.hr-dashboard-page .stat-card {
  background: var(--hr-card-2);
  border-radius: 22px;
  padding: 22px;
  min-height: 148px;
  position: relative;
  overflow: hidden;
}

.hr-dashboard-page .stat-card::after {
  content: "";
  position: absolute;
  width: 92px;
  height: 92px;
  border-radius: 50%;
  top: -28px;
  right: -24px;
  opacity: .16;
}

.hr-dashboard-page .stat-employees::after { background: var(--hr-info); }
.hr-dashboard-page .stat-pending::after { background: var(--hr-warning); }
.hr-dashboard-page .stat-approved::after { background: var(--hr-success); }
.hr-dashboard-page .stat-leaves::after { background: #a855f7; }

.hr-dashboard-page .stat-label {
  color: var(--hr-muted);
  font-size: 13px;
  margin-bottom: 18px;
}

.hr-dashboard-page .stat-value {
  font-size: 36px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.hr-dashboard-page .stat-foot {
  margin-top: 14px;
  color: #cbd5e1;
  font-size: 13px;
  line-height: 1.55;
}

.hr-dashboard-page .content-grid {
  display: grid;
  grid-template-columns: 1.05fr .95fr;
  gap: 22px;
  margin-top: 24px;
}

.hr-dashboard-page .panel-card {
  background: var(--hr-card);
  border-radius: 24px;
  padding: 24px;
}

.hr-dashboard-page .panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.hr-dashboard-page .panel-title {
  margin: 0;
  font-size: 20px;
  color: #fff;
  font-weight: 700;
}

.hr-dashboard-page .panel-subtitle {
  margin-top: 4px;
  color: var(--hr-muted);
  font-size: 13px;
}

.hr-dashboard-page .panel-tag {
  background: rgba(111,100,134,0.22);
  color: #ddd6fe;
  border: 1px solid rgba(139,127,176,0.35);
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
}

.hr-dashboard-page .quick-links {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.hr-dashboard-page .quick-link {
  display: block;
  text-decoration: none;
  background: var(--hr-card-2);
  border-radius: 18px;
  padding: 18px;
  color: inherit;
  transition: transform .2s ease, border-color .2s ease, background .2s ease;
}

.hr-dashboard-page .quick-link:hover {
  transform: translateY(-2px);
  border-color: #555a7e;
  background: #353752;
}

.hr-dashboard-page .quick-link-title {
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 6px;
}

.hr-dashboard-page .quick-link-desc {
  color: var(--hr-muted);
  font-size: 13px;
  line-height: 1.6;
}

.hr-dashboard-page .announcement-list,
.hr-dashboard-page .leave-list {
  display: grid;
  gap: 14px;
  margin-top: 18px;
}

.hr-dashboard-page .announcement-item,
.hr-dashboard-page .leave-item {
  background: var(--hr-card-2);
  border-radius: 18px;
  padding: 18px;
  transition: transform .2s ease, border-color .2s ease, background .2s ease;
}

.hr-dashboard-page .announcement-item:hover,
.hr-dashboard-page .leave-item:hover {
  transform: translateY(-2px);
  border-color: #555a7e;
  background: #353752;
}

.hr-dashboard-page .announcement-top,
.hr-dashboard-page .leave-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.hr-dashboard-page .announcement-title,
.hr-dashboard-page .leave-name {
  margin: 0;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
}

.hr-dashboard-page .announcement-date,
.hr-dashboard-page .leave-date {
  font-size: 12px;
  color: var(--hr-muted);
  white-space: nowrap;
}

.hr-dashboard-page .announcement-message,
.hr-dashboard-page .leave-meta {
  margin: 10px 0 0;
  color: #d1d5db;
  font-size: 13px;
  line-height: 1.7;
}

.hr-dashboard-page .status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 7px 11px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.hr-dashboard-page .status-approved {
  background: rgba(34,197,94,0.14);
  color: #bbf7d0;
  border: 1px solid rgba(34,197,94,0.32);
}

.hr-dashboard-page .status-pending {
  background: rgba(245,158,11,0.14);
  color: #fde68a;
  border: 1px solid rgba(245,158,11,0.32);
}

.hr-dashboard-page .status-rejected {
  background: rgba(239,68,68,0.14);
  color: #fecaca;
  border: 1px solid rgba(239,68,68,0.32);
}

.hr-dashboard-page .status-default {
  background: rgba(156,163,175,0.14);
  color: #e5e7eb;
  border: 1px solid rgba(156,163,175,0.24);
}

.hr-dashboard-page .summary-mini {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}

.hr-dashboard-page .mini-card {
  background: var(--hr-card-2);
  border: 1px solid var(--hr-border);
  border-radius: 18px;
  padding: 16px;
}

.hr-dashboard-page .mini-value {
  color: #fff;
  font-size: 24px;
  font-weight: 800;
}

.hr-dashboard-page .mini-label {
  margin-top: 4px;
  color: var(--hr-muted);
  font-size: 12px;
}

.hr-dashboard-page .calendar-weekdays,
.hr-dashboard-page .calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 10px;
}

.hr-dashboard-page .calendar-weekday {
  color: var(--hr-muted);
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  padding: 6px 0;
}

.hr-dashboard-page .calendar-grid {
  margin-top: 12px;
}

.hr-dashboard-page .calendar-cell {
  min-height: 88px;
  border-radius: 18px;
  background: var(--hr-card-2);
  padding: 12px;
  position: relative;
}

.hr-dashboard-page .calendar-cell.empty {
  background: rgba(49,51,72,0.48);
  border-style: dashed;
  box-shadow: none;
}

.hr-dashboard-page .calendar-cell.today {
  outline: 2px solid #fff;
  outline-offset: -2px;
}

.hr-dashboard-page .calendar-cell.holiday {
  background: linear-gradient(180deg, rgba(245,158,11,0.18), rgba(49,51,72,1));
  border-color: rgba(245,158,11,0.35);
}

.hr-dashboard-page .calendar-day {
  color: #fff;
  font-size: 14px;
  font-weight: 800;
}

.hr-dashboard-page .calendar-note {
  margin-top: 10px;
  font-size: 11px;
  line-height: 1.45;
  color: #fde68a;
  font-weight: 700;
}

.hr-dashboard-page .empty-state {
  background: #353752;
  border: 1px dashed #606887;
  border-radius: 18px;
  padding: 28px 20px;
  text-align: center;
  color: var(--hr-muted);
  font-size: 14px;
}

@media (max-width: 1100px) {
  .hr-dashboard-page .hero-grid,
  .hr-dashboard-page .content-grid {
    grid-template-columns: 1fr;
  }

  .hr-dashboard-page .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 720px) {
  .hr-dashboard-page .hero-title {
    font-size: 28px;
  }

  .hr-dashboard-page .stats-grid,
  .hr-dashboard-page .quick-links,
  .hr-dashboard-page .summary-mini {
    grid-template-columns: 1fr;
  }

  .hr-dashboard-page .calendar-weekdays,
  .hr-dashboard-page .calendar-grid {
    gap: 8px;
  }

  .hr-dashboard-page .calendar-cell {
    min-height: 76px;
    padding: 10px;
  }
}
</style>

<div class="hr-dashboard-page">
  <section class="hero-card">
    <div class="hero-grid">
      <div>
        <span class="page-badge">HR Dashboard</span>
        <h1 class="hero-title">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h1>
        <p class="hero-subtitle">
          Monitor employee accounts, leave activity, approvals, and organization updates from one central HR dashboard.
          This page gives you a quick overview of the most important school HR records and actions.
        </p>
      </div>

      <div class="hero-side">
        <div class="hero-side-label">Pending HR Approvals</div>
        <div class="hero-side-value"><?php echo $pendingHr; ?></div>
        <div class="hero-side-note">
          User accounts currently waiting for HR review and final approval.
        </div>
      </div>
    </div>
  </section>

  <section class="stats-grid">
    <div class="stat-card stat-employees">
      <div class="stat-label">Employees</div>
      <div class="stat-value"><?php echo $totalEmployees; ?></div>
      <div class="stat-foot">Active employee accounts registered in the system.</div>
    </div>

    <div class="stat-card stat-pending">
      <div class="stat-label">Pending Approvals</div>
      <div class="stat-value"><?php echo $pendingHr; ?></div>
      <div class="stat-foot">Accounts waiting for HR approval.</div>
    </div>

    <div class="stat-card stat-approved">
      <div class="stat-label">Approved Users</div>
      <div class="stat-value"><?php echo $approvedUsers; ?></div>
      <div class="stat-foot">Total approved users across employee, head, and HR roles.</div>
    </div>

    <div class="stat-card stat-leaves">
      <div class="stat-label">Total Leave Requests</div>
      <div class="stat-value"><?php echo $totalLeaveRequests; ?></div>
      <div class="stat-foot">All leave requests recorded in your system.</div>
    </div>
  </section>

  <section class="content-grid">
    <div class="panel-card">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">Quick Actions</h2>
          <div class="panel-subtitle">Shortcuts to the most used HR modules.</div>
        </div>
        <span class="panel-tag">HR Tools</span>
      </div>

      <div class="quick-links">
        <a class="quick-link" href="hr_approvals.php">
          <div class="quick-link-title">Review Approvals</div>
          <div class="quick-link-desc">Check and process pending employee approvals that need HR action.</div>
        </a>

        <a class="quick-link" href="employees.php">
          <div class="quick-link-title">Employee Records</div>
          <div class="quick-link-desc">Manage employee accounts, roles, departments, and leave balances.</div>
        </a>

        <a class="quick-link" href="leave_admin.php">
          <div class="quick-link-title">Leave Management</div>
          <div class="quick-link-desc">Monitor leave requests, statuses, and approval progress.</div>
        </a>

        <a class="quick-link" href="announcements.php">
          <div class="quick-link-title">Announcements</div>
          <div class="quick-link-desc">Post updates and notices for employees and staff members.</div>
        </a>
      </div>

      <div class="summary-mini">
        <div class="mini-card">
          <div class="mini-value"><?php echo $totalHeads; ?></div>
          <div class="mini-label">Department Heads</div>
        </div>

        <div class="mini-card">
          <div class="mini-value"><?php echo $approvedLeaveRequests; ?></div>
          <div class="mini-label">Approved Leaves</div>
        </div>

        <div class="mini-card">
          <div class="mini-value"><?php echo $rejectedLeaveRequests; ?></div>
          <div class="mini-label">Rejected Leaves</div>
        </div>
      </div>

      <div class="announcement-list">
        <div class="panel-head" style="margin-bottom:0;">
          <div>
            <h2 class="panel-title" style="font-size:18px;">Latest Announcements</h2>
            <div class="panel-subtitle">Recent updates from your organization.</div>
          </div>
        </div>

        <?php if (count($announcements) === 0) { ?>
          <div class="empty-state">No announcements yet.</div>
        <?php } else { ?>
          <?php foreach ($announcements as $a) { ?>
            <article class="announcement-item">
              <div class="announcement-top">
                <h3 class="announcement-title"><?php echo htmlspecialchars($a['title'] ?? 'Announcement'); ?></h3>
                <span class="announcement-date">
                  <?php
                    $ts = strtotime($a['created_at'] ?? '');
                    echo $ts ? date("M d, Y", $ts) : '';
                  ?>
                </span>
              </div>
              <p class="announcement-message">
                <?php echo htmlspecialchars(mb_strimwidth((string)($a['message'] ?? ''), 0, 180, '...')); ?>
              </p>
            </article>
          <?php } ?>
        <?php } ?>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <div>
          <h2 class="panel-title"><?php echo htmlspecialchars($monthName . " " . $year); ?></h2>
          <div class="panel-subtitle">Holiday overview for the current month.</div>
        </div>
        <span class="panel-tag"><?php echo count($holidaysByDate); ?> Holiday<?php echo count($holidaysByDate) === 1 ? '' : 's'; ?></span>
      </div>

      <div class="calendar-weekdays">
        <?php foreach ($weekdays as $w) { ?>
          <div class="calendar-weekday"><?php echo htmlspecialchars($w); ?></div>
        <?php } ?>
      </div>

      <div class="calendar-grid">
        <?php
          for ($i = 1; $i < $startWeekday; $i++) {
            echo '<div class="calendar-cell empty"></div>';
          }

          for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = ($dateStr === date('Y-m-d'));
            $holiday = $holidaysByDate[$dateStr] ?? null;

            $classes = 'calendar-cell';
            if ($isToday) $classes .= ' today';
            if ($holiday) $classes .= ' holiday';

            echo '<div class="' . $classes . '">';
            echo '<div class="calendar-day">' . $d . '</div>';

            if ($holiday) {
              echo '<div class="calendar-note">'
                . htmlspecialchars(mb_strimwidth((string)$holiday['title'], 0, 24, '…'))
                . '</div>';
            }

            echo '</div>';
          }

          $totalCells = ($startWeekday - 1) + $daysInMonth;
          $trail = (7 - ($totalCells % 7)) % 7;
          for ($i = 0; $i < $trail; $i++) {
            echo '<div class="calendar-cell empty"></div>';
          }
        ?>
      </div>

      <div class="leave-list">
        <div class="panel-head" style="margin-bottom:0;">
          <div>
            <h2 class="panel-title" style="font-size:18px;">Recent Leave Requests</h2>
            <div class="panel-subtitle">Latest submitted leave activity.</div>
          </div>
        </div>

        <?php if (count($latestLeaves) === 0) { ?>
          <div class="empty-state">No leave requests yet.</div>
        <?php } else { ?>
          <?php foreach ($latestLeaves as $lr) { ?>
            <?php
              $status = strtolower((string)($lr['status'] ?? ''));
              $statusClass = 'status-default';
              if (in_array($status, ['approved_head', 'received'], true)) $statusClass = 'status-approved';
              elseif (in_array($status, ['pending_head', 'pending_hr_receive'], true)) $statusClass = 'status-pending';
              elseif (in_array($status, ['rejected_head', 'rejected_hr'], true)) $statusClass = 'status-rejected';
            ?>
            <article class="leave-item">
              <div class="leave-top">
                <h3 class="leave-name"><?php echo htmlspecialchars($lr['full_name'] ?? 'Employee'); ?></h3>
                <span class="status-badge <?php echo $statusClass; ?>">
                  <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($lr['status'] ?? '')))); ?>
                </span>
              </div>
              <p class="leave-meta">
                <?php echo htmlspecialchars(ucfirst((string)($lr['leave_type'] ?? 'leave'))); ?> •
                <?php echo htmlspecialchars((string)($lr['days'] ?? 0)); ?> day(s) •
                <?php
                  $from = !empty($lr['date_from']) ? date('M d, Y', strtotime($lr['date_from'])) : '';
                  $to = !empty($lr['date_to']) ? date('M d, Y', strtotime($lr['date_to'])) : '';
                  echo htmlspecialchars($from . ($to ? ' - ' . $to : ''));
                ?>
              </p>
            </article>
          <?php } ?>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/hr_layout.php";
