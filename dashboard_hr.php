<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

// Fetch HR user
$stmt = $conn->prepare("SELECT full_name, email, department, emp_id, role, approval_status, profile_completed FROM users WHERE id = ?");
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

// Count pending HR approvals
$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status = 'pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

// -------- HOME: ANNOUNCEMENTS (latest 5) --------
$announcements = [];
try {
  $a = $conn->query("SELECT id, title, message, created_at FROM announcements ORDER BY created_at DESC LIMIT 5");
  if ($a) {
    while ($row = $a->fetch_assoc()) $announcements[] = $row;
  }
} catch (Throwable $e) { /* ignore */ }

// -------- HOME: MONTH CALENDAR (current month) --------
$year  = (int)date('Y');
$month = (int)date('n');

$firstDayTs = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)date('t', $firstDayTs);
$startWeekday = (int)date('N', $firstDayTs); // 1=Mon..7=Sun
$monthName = date('F', $firstDayTs);

// Holidays for this month
$holidaysByDate = [];
try {
  $monthStart = sprintf('%04d-%02d-01', $year, $month);
  $monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

  $hol = $conn->prepare("SELECT id, event_date, title
                         FROM calendar_events
                         WHERE type='holiday' AND event_date BETWEEN ? AND ?
                         ORDER BY event_date ASC");
  $hol->bind_param("ss", $monthStart, $monthEnd);
  $hol->execute();
  $res = $hol->get_result();
  while ($r = $res->fetch_assoc()) {
    $holidaysByDate[$r['event_date']] = $r;
  }
} catch (Throwable $e) { /* ignore */ }

$weekdays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

// -------- PAGE SETTINGS FOR LAYOUT --------
$pageTitle = "Home";
$active = "home";

// Build page content
ob_start();
?>
  <div class="rounded-2xl bg-white border p-6">
    <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h1>
    <p class="mt-1 text-slate-600">Here are the latest announcements and this month’s calendar.</p>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-2">

    <!-- Announcements -->
    <section class="rounded-2xl bg-white border p-6">
      <h2 class="text-lg font-bold">Latest Announcements</h2>

      <div class="mt-4 space-y-4">
        <?php if (count($announcements) === 0) { ?>
          <div class="rounded-xl border bg-slate-50 p-4 text-sm text-slate-600">
            No announcements yet.
          </div>
        <?php } else { ?>
          <?php foreach ($announcements as $a) { ?>
            <div class="rounded-xl border p-4 hover:bg-slate-50">
              <div class="flex items-start justify-between gap-4">
                <p class="font-semibold"><?php echo htmlspecialchars($a['title'] ?? 'Announcement'); ?></p>
                <p class="text-xs text-slate-500">
                  <?php
                    $ts = strtotime($a['created_at'] ?? '');
                    echo $ts ? date("M d, Y", $ts) : '';
                  ?>
                </p>
              </div>
              <p class="mt-2 text-sm text-slate-700">
                <?php
                  $msg = (string)($a['message'] ?? '');
                  echo htmlspecialchars(mb_strimwidth($msg, 0, 180, '...'));
                ?>
              </p>
            </div>
          <?php } ?>
        <?php } ?>
      </div>
    </section>

    <!-- Calendar -->
    <section class="rounded-2xl bg-white border p-6">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold"><?php echo htmlspecialchars($monthName . " " . $year); ?></h2>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span class="inline-flex items-center gap-2">
            <span class="h-3 w-3 rounded bg-orange-200 border border-orange-300"></span> Holiday
          </span>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-7 text-xs font-semibold text-slate-500">
        <?php foreach ($weekdays as $w) { ?>
          <div class="px-2 py-2"><?php echo $w; ?></div>
        <?php } ?>
      </div>

      <div class="grid grid-cols-7 gap-2">
        <?php
          for ($i = 1; $i < $startWeekday; $i++) {
            echo '<div class="h-16 rounded-xl bg-slate-50 border border-slate-100"></div>';
          }

          for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = ($dateStr === date('Y-m-d'));
            $holiday = $holidaysByDate[$dateStr] ?? null;

            $bg = $holiday ? "bg-orange-50 border-orange-200" : "bg-white border-slate-200";
            $ring = $isToday ? "ring-2 ring-slate-900" : "";

            echo '<div class="h-16 rounded-xl border p-2 '.$bg.' '.$ring.'">';
            echo '<div class="flex items-start justify-between">';
            echo '<span class="text-sm font-bold">'.$d.'</span>';
            echo '</div>';

            if ($holiday) {
              echo '<p class="mt-1 text-[11px] font-semibold text-orange-800 leading-tight">'
                . htmlspecialchars(mb_strimwidth($holiday['title'], 0, 18, '…'))
                . '</p>';
            }
            echo '</div>';
          }

          $totalCells = ($startWeekday - 1) + $daysInMonth;
          $trail = (7 - ($totalCells % 7)) % 7;
          for ($i = 0; $i < $trail; $i++) {
            echo '<div class="h-16 rounded-xl bg-slate-50 border border-slate-100"></div>';
          }
        ?>
      </div>
    </section>

  </div>
<?php
$content = ob_get_clean();

// Render with shared layout (sidebar+header stays across pages)
require_once __DIR__ . "/hr_layout.php";