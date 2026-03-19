<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT id, full_name, email, department, emp_id, role,
  approval_status, profile_completed,
  sick_leave_balance, incentive_leave_balance, emergency_leave_balance
  FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }

// Employee only
if (($user['role'] ?? '') !== 'employee') {
  if (($user['role'] ?? '') === 'head') { header("Location: dashboard_head.php"); exit; }
  if (($user['role'] ?? '') === 'hr') { header("Location: dashboard_hr.php"); exit; }
  header("Location: login.php"); exit;
}
$_SESSION['user_role'] = 'employee';

// Latest announcements
$announcements = [];
try {
  $a = $conn->query("SELECT id, title, message, created_at FROM announcements ORDER BY created_at DESC LIMIT 5");
  if ($a) while ($row = $a->fetch_assoc()) $announcements[] = $row;
} catch (Throwable $e) {}

// Calendar (current month + holidays)
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

  $hol = $conn->prepare("SELECT event_date, title FROM calendar_events
                         WHERE type='holiday' AND event_date BETWEEN ? AND ?
                         ORDER BY event_date ASC");
  $hol->bind_param("ss", $monthStart, $monthEnd);
  $hol->execute();
  $res = $hol->get_result();
  while ($r = $res->fetch_assoc()) $holidaysByDate[$r['event_date']] = $r;
} catch (Throwable $e) {}

$pageTitle = "Home";
$active = "home";

$displayName = trim((string)($user['full_name'] ?? 'Employee'));
$displayEmail = trim((string)($user['email'] ?? ''));
$displayDepartment = trim((string)($user['department'] ?? 'Not assigned'));
$displayEmpId = trim((string)($user['emp_id'] ?? '—'));

$totalAnnouncements = count($announcements);
$totalHolidays = count($holidaysByDate);
$totalCredits = (int)$user['sick_leave_balance'] + (int)$user['incentive_leave_balance'] + (int)$user['emergency_leave_balance'];

ob_start();
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

:root {
  --emp-bg: #2a2b3d;
  --emp-card: #313348;
  --emp-card-2: #252636;
  --emp-border: #3c3f58;
  --emp-text: #f3f4f6;
  --emp-muted: #9ca3af;
  --emp-accent: #6f6486;
  --emp-accent-2: #8b7fb0;
  --emp-success: #22c55e;
  --emp-warning: #f59e0b;
  --emp-danger: #ef4444;
  --emp-info: #3b82f6;
}

.myhr-employee-dashboard,
.myhr-employee-dashboard * {
  box-sizing: border-box;
  font-family: 'Inter', sans-serif;
}

.myhr-employee-dashboard {
  color: var(--emp-text);
}

.myhr-employee-dashboard .hero-card,
.myhr-employee-dashboard .metric-card,
.myhr-employee-dashboard .panel-card,
.myhr-employee-dashboard .announcement-item,
.myhr-employee-dashboard .calendar-cell,
.myhr-employee-dashboard .quick-link {
  border: 1px solid var(--emp-border);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
}

.myhr-employee-dashboard .hero-card {
  background: linear-gradient(135deg, var(--emp-accent), #4d4764 55%, #383b55 100%);
  border-radius: 24px;
  padding: 28px;
  position: relative;
  overflow: hidden;
}

.myhr-employee-dashboard .hero-card::before,
.myhr-employee-dashboard .hero-card::after {
  content: "";
  position: absolute;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  pointer-events: none;
}

.myhr-employee-dashboard .hero-card::before {
  width: 220px;
  height: 220px;
  top: -90px;
  right: -40px;
}

.myhr-employee-dashboard .hero-card::after {
  width: 140px;
  height: 140px;
  bottom: -50px;
  right: 120px;
}

.myhr-employee-dashboard .hero-grid {
  display: grid;
  grid-template-columns: 1.3fr .7fr;
  gap: 20px;
  position: relative;
  z-index: 1;
}

.myhr-employee-dashboard .employee-badge {
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

.myhr-employee-dashboard .hero-title {
  margin: 16px 0 8px;
  font-size: 34px;
  line-height: 1.15;
  font-weight: 800;
  color: #fff;
}

.myhr-employee-dashboard .hero-subtitle {
  margin: 0;
  color: rgba(255,255,255,0.82);
  font-size: 14px;
  line-height: 1.7;
  max-width: 680px;
}

.myhr-employee-dashboard .hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 20px;
}

.myhr-employee-dashboard .meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 14px;
  background: rgba(17,24,39,0.20);
  color: #fff;
  font-size: 13px;
  border: 1px solid rgba(255,255,255,0.10);
}

.myhr-employee-dashboard .hero-summary {
  background: rgba(17,24,39,0.24);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 22px;
  padding: 20px;
  align-self: stretch;
}

.myhr-employee-dashboard .hero-summary-label {
  color: rgba(255,255,255,0.72);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: 8px;
}

.myhr-employee-dashboard .hero-summary-value {
  font-size: 38px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.myhr-employee-dashboard .hero-summary-note {
  margin-top: 10px;
  color: rgba(255,255,255,0.78);
  font-size: 13px;
  line-height: 1.6;
}

.myhr-employee-dashboard .metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  margin-top: 22px;
}

.myhr-employee-dashboard .metric-card {
  background: var(--emp-card);
  border-radius: 22px;
  padding: 22px;
  min-height: 150px;
  position: relative;
  overflow: hidden;
}

.myhr-employee-dashboard .metric-card::after {
  content: "";
  position: absolute;
  width: 92px;
  height: 92px;
  border-radius: 50%;
  top: -28px;
  right: -24px;
  opacity: .16;
}

.myhr-employee-dashboard .metric-card.metric-sick::after { background: var(--emp-info); }
.myhr-employee-dashboard .metric-card.metric-incentive::after { background: var(--emp-success); }
.myhr-employee-dashboard .metric-card.metric-emergency::after { background: var(--emp-warning); }
.myhr-employee-dashboard .metric-card.metric-total::after { background: #a855f7; }

.myhr-employee-dashboard .metric-label {
  color: var(--emp-muted);
  font-size: 13px;
  margin-bottom: 18px;
}

.myhr-employee-dashboard .metric-value {
  font-size: 36px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.myhr-employee-dashboard .metric-foot {
  margin-top: 14px;
  color: #cbd5e1;
  font-size: 13px;
}

.myhr-employee-dashboard .content-grid {
  display: grid;
  grid-template-columns: 1.15fr .85fr;
  gap: 22px;
  margin-top: 24px;
}

.myhr-employee-dashboard .panel-card {
  background: var(--emp-card-2);
  border-radius: 24px;
  padding: 24px;
}

.myhr-employee-dashboard .panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.myhr-employee-dashboard .panel-title {
  margin: 0;
  font-size: 20px;
  color: #fff;
  font-weight: 700;
}

.myhr-employee-dashboard .panel-subtitle {
  margin-top: 4px;
  color: var(--emp-muted);
  font-size: 13px;
}

.myhr-employee-dashboard .panel-tag {
  background: rgba(111,100,134,0.22);
  color: #ddd6fe;
  border: 1px solid rgba(139,127,176,0.35);
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
}

.myhr-employee-dashboard .announcement-list {
  display: grid;
  gap: 14px;
}

.myhr-employee-dashboard .announcement-item {
  background: var(--emp-card);
  border-radius: 18px;
  padding: 18px;
  transition: transform .2s ease, border-color .2s ease, background .2s ease;
}

.myhr-employee-dashboard .announcement-item:hover {
  transform: translateY(-2px);
  border-color: #555a7e;
  background: #353752;
}

.myhr-employee-dashboard .announcement-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.myhr-employee-dashboard .announcement-title {
  margin: 0;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
}

.myhr-employee-dashboard .announcement-date {
  font-size: 12px;
  color: var(--emp-muted);
  white-space: nowrap;
}

.myhr-employee-dashboard .announcement-message {
  margin: 10px 0 0;
  color: #d1d5db;
  font-size: 13px;
  line-height: 1.7;
}

.myhr-employee-dashboard .empty-state {
  background: #353752;
  border: 1px dashed #606887;
  border-radius: 18px;
  padding: 28px 20px;
  text-align: center;
  color: var(--emp-muted);
  font-size: 14px;
}

.myhr-employee-dashboard .quick-links {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}

.myhr-employee-dashboard .quick-link {
  display: block;
  text-decoration: none;
  background: var(--emp-card);
  border-radius: 18px;
  padding: 18px;
  color: inherit;
  transition: transform .2s ease, border-color .2s ease, background .2s ease;
}

.myhr-employee-dashboard .quick-link:hover {
  transform: translateY(-2px);
  border-color: #555a7e;
  background: #353752;
}

.myhr-employee-dashboard .quick-link-title {
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 6px;
}

.myhr-employee-dashboard .quick-link-desc {
  color: var(--emp-muted);
  font-size: 13px;
  line-height: 1.6;
}

.myhr-employee-dashboard .calendar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.myhr-employee-dashboard .legend-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--emp-muted);
  font-size: 12px;
}

.myhr-employee-dashboard .legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 4px;
  display: inline-block;
}

.myhr-employee-dashboard .calendar-weekdays,
.myhr-employee-dashboard .calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 10px;
}

.myhr-employee-dashboard .calendar-weekday {
  color: var(--emp-muted);
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  padding: 6px 0;
}

.myhr-employee-dashboard .calendar-grid {
  margin-top: 12px;
}

.myhr-employee-dashboard .calendar-cell {
  min-height: 88px;
  border-radius: 18px;
  background: var(--emp-card);
  padding: 12px;
  position: relative;
}

.myhr-employee-dashboard .calendar-cell.empty {
  background: rgba(49,51,72,0.48);
  border-style: dashed;
  box-shadow: none;
}

.myhr-employee-dashboard .calendar-cell.today {
  outline: 2px solid #fff;
  outline-offset: -2px;
}

.myhr-employee-dashboard .calendar-cell.holiday {
  background: linear-gradient(180deg, rgba(245,158,11,0.18), rgba(49,51,72,1));
  border-color: rgba(245,158,11,0.35);
}

.myhr-employee-dashboard .calendar-day {
  color: #fff;
  font-size: 14px;
  font-weight: 800;
}

.myhr-employee-dashboard .calendar-note {
  margin-top: 10px;
  font-size: 11px;
  line-height: 1.45;
  color: #fde68a;
  font-weight: 700;
}

.myhr-employee-dashboard .section-spacer {
  margin-top: 22px;
}

@media (max-width: 1100px) {
  .myhr-employee-dashboard .hero-grid,
  .myhr-employee-dashboard .content-grid {
    grid-template-columns: 1fr;
  }

  .myhr-employee-dashboard .metrics-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 700px) {
  .myhr-employee-dashboard .hero-card,
  .myhr-employee-dashboard .metric-card,
  .myhr-employee-dashboard .panel-card {
    border-radius: 20px;
  }

  .myhr-employee-dashboard .hero-title {
    font-size: 28px;
  }

  .myhr-employee-dashboard .metrics-grid,
  .myhr-employee-dashboard .quick-links {
    grid-template-columns: 1fr;
  }

  .myhr-employee-dashboard .calendar-weekdays,
  .myhr-employee-dashboard .calendar-grid {
    gap: 8px;
  }

  .myhr-employee-dashboard .calendar-cell {
    min-height: 76px;
    padding: 10px;
  }
}
.success-modal {
  position: fixed;
  inset: 0;
  background: rgba(12, 14, 24, 0.72);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.success-box {
  width: 100%;
  max-width: 420px;
  background: #252636;
  border: 1px solid #3c3f58;
  box-shadow: 0 12px 30px rgba(0,0,0,0.18);
  border-radius: 24px;
  padding: 24px;
  text-align: center;
}

.success-icon {
  width: 68px;
  height: 68px;
  border-radius: 22px;
  margin: 0 auto 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(34,197,94,0.18);
  border: 1px solid rgba(34,197,94,0.30);
  color: #86efac;
  font-size: 28px;
  font-weight: 800;
}

.success-title {
  margin: 0;
  color: #fff;
  font-size: 22px;
  font-weight: 800;
}

.success-text {
  margin-top: 10px;
  color: #9ca3af;
  font-size: 14px;
  line-height: 1.7;
}

.success-btn {
  margin-top: 22px;
  width: 100%;
  border: 0;
  border-radius: 16px;
  padding: 14px 16px;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  background: linear-gradient(135deg, #6f6486 0%, #5d5677 100%);
  color: #fff;
}
</style>

<?php
$leaveSuccessMessage = $_SESSION['leave_request_success'] ?? '';
unset($_SESSION['leave_request_success']);
?>
<?php if ($leaveSuccessMessage !== ''): ?>
  <div id="successModal" class="success-modal">
    <div class="success-box">
      <div class="success-icon">✓</div>
      <h3 class="success-title">Success</h3>
      <p class="success-text"><?php echo htmlspecialchars($leaveSuccessMessage); ?></p>
      <button type="button" class="success-btn" onclick="closeSuccessModal()">OK</button>
    </div>
  </div>
<?php endif; ?>
<div class="myhr-employee-dashboard">
  <section class="hero-card">
    <div class="hero-grid">
      <div>
        
        <h1 class="hero-title">Welcome back, <?php echo htmlspecialchars($displayName); ?> 👋</h1>
        

        <div class="hero-meta">
          <span class="meta-pill"><strong>Department:</strong> <?php echo htmlspecialchars($displayDepartment); ?></span>
          <span class="meta-pill"><strong>Employee ID:</strong> <?php echo htmlspecialchars($displayEmpId); ?></span>
          <?php if ($displayEmail !== '') { ?>
            <span class="meta-pill"><strong>Email:</strong> <?php echo htmlspecialchars($displayEmail); ?></span>
          <?php } ?>
        </div>
      </div>

      
    </div>
  </section>

  <section class="metrics-grid">
    <div class="metric-card metric-sick">
      <div class="metric-label">Sick Leave</div>
      <div class="metric-value"><?php echo (int)$user['sick_leave_balance']; ?></div>
      <div class="metric-foot">Available credits for sickness-related leave.</div>
    </div>

    <div class="metric-card metric-incentive">
      <div class="metric-label">Incentive Leave</div>
      <div class="metric-value"><?php echo (int)$user['incentive_leave_balance']; ?></div>
      <div class="metric-foot">Credits you can use for personal time off.</div>
    </div>

    <div class="metric-card metric-emergency">
      <div class="metric-label">Emergency Leave</div>
      <div class="metric-value"><?php echo (int)$user['emergency_leave_balance']; ?></div>
      <div class="metric-foot">For urgent and emergency-related absences.</div>
    </div>

    <div class="metric-card metric-total">
      <div class="metric-label">Current Overview</div>
      <div class="metric-value"><?php echo $totalAnnouncements; ?></div>
      <div class="metric-foot"><?php echo $totalAnnouncements === 1 ? 'Latest announcement' : 'Latest announcements'; ?> available.</div>
    </div>
  </section>

  <section class="content-grid">
    <div class="panel-card">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">Latest Announcements</h2>
          <div class="panel-subtitle">Stay updated with the latest HR and office notices.</div>
        </div>
        <span class="panel-tag"><?php echo $totalAnnouncements; ?> item<?php echo $totalAnnouncements === 1 ? '' : 's'; ?></span>
      </div>

      <div class="announcement-list">
        <?php if (count($announcements) === 0) { ?>
          <div class="empty-state">
            No announcements yet.
          </div>
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
                <?php echo htmlspecialchars(mb_strimwidth((string)$a['message'], 0, 180, '...')); ?>
              </p>
            </article>
          <?php } ?>
        <?php } ?>
      </div>

      <div class="section-spacer">
        <div class="panel-head" style="margin-bottom:14px;">
          <div>
            <h2 class="panel-title" style="font-size:18px;">Quick Actions</h2>
            <div class="panel-subtitle">Shortcuts for common employee tasks.</div>
          </div>
        </div>

        <div class="quick-links">
          <a class="quick-link" href="leave_request.php">
            <div class="quick-link-title">Apply for Leave</div>
            <div class="quick-link-desc">Submit a new leave request directly from your dashboard.</div>
          </a>

          <a class="quick-link" href="my_leaves.php">
            <div class="quick-link-title">My Leave Status</div>
            <div class="quick-link-desc">Check approval progress and view your submitted requests.</div>
          </a>

          <a class="quick-link" href="announcements.php">
            <div class="quick-link-title">All Announcements</div>
            <div class="quick-link-desc">Open the full list of office and HR announcements.</div>
          </a>

          <a class="quick-link" href="calendar.php">
            <div class="quick-link-title">Calendar</div>
            <div class="quick-link-desc">See upcoming events, holidays, and important schedules.</div>
          </a>
        </div>
      </div>
    </div>

    <div class="panel-card">
      <div class="panel-head">
        <div>
          <h2 class="panel-title"><?php echo htmlspecialchars($monthName . " " . $year); ?></h2>
          <div class="panel-subtitle">Holiday and date overview for the current month.</div>
        </div>

        <div class="calendar-legend">
          <span class="legend-chip"><span class="legend-dot" style="background:#f59e0b;"></span>Holiday</span>
          <span class="legend-chip"><span class="legend-dot" style="background:#ffffff;"></span>Today</span>
        </div>
      </div>

      <div class="quick-links" style="grid-template-columns:repeat(2,minmax(0,1fr)); margin-top:0; margin-bottom:18px;">
        <div class="quick-link" style="pointer-events:none;">
          <div class="quick-link-title"><?php echo $totalHolidays; ?></div>
          <div class="quick-link-desc">Holiday<?php echo $totalHolidays === 1 ? '' : 's'; ?> this month</div>
        </div>
        <div class="quick-link" style="pointer-events:none;">
          <div class="quick-link-title"><?php echo date('d'); ?></div>
          <div class="quick-link-desc">Today’s day of the month</div>
        </div>
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
                . htmlspecialchars(mb_strimwidth((string)$holiday['title'], 0, 26, '…'))
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
    </div>
  </section>
</div>
<script>
function closeSuccessModal() {
  var modal = document.getElementById('successModal');
  if (modal) modal.style.display = 'none';
}
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . "/employee_layout.php";
