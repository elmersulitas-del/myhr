<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }

$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT full_name, email, department, emp_id, role, approval_status, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['role'] ?? '') !== 'head') { header("Location: login.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }

$_SESSION['user_role'] = 'head';

$dept = $user['department'] ?? '';

// Count pending employee approvals
$pendingCount = 0;
if ($dept !== '') {
  $pc = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE department = ? AND approval_status = 'pending_head'");
  $pc->bind_param("s", $dept);
  $pc->execute();
  $pendingCount = (int)($pc->get_result()->fetch_assoc()['total'] ?? 0);
}

// Announcements
$announcements = [];
$a = $conn->query("SELECT id, title, message, created_at FROM announcements ORDER BY created_at DESC LIMIT 5");
if ($a) while ($row = $a->fetch_assoc()) $announcements[] = $row;

// Calendar
$year  = (int)date('Y');
$month = (int)date('n');
$firstDayTs = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)date('t', $firstDayTs);
$startWeekday = (int)date('N', $firstDayTs);
$monthName = date('F', $firstDayTs);
$weekdays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

$holidaysByDate = [];
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$hol = $conn->prepare("SELECT event_date, title FROM calendar_events
                       WHERE type='holiday' AND event_date BETWEEN ? AND ?");
$hol->bind_param("ss", $monthStart, $monthEnd);
$hol->execute();
$res = $hol->get_result();
while ($r = $res->fetch_assoc()) $holidaysByDate[$r['event_date']] = $r;

// Layout settings
$pageTitle = "Home";
$active = "home";

// Build content
ob_start();
?>

<div class="rounded-2xl bg-white border p-6">
  <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h1>
  <p class="mt-1 text-slate-600">See HR announcements and this month’s calendar.</p>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">

  <!-- Announcements -->
  <section class="rounded-2xl bg-white border p-6">
    <h2 class="text-lg font-bold">Latest Announcements</h2>

    <div class="mt-4 space-y-4">
      <?php if (count($announcements) === 0): ?>
        <div class="rounded-xl border bg-slate-50 p-4 text-sm text-slate-600">
          No announcements yet.
        </div>
      <?php else: ?>
        <?php foreach ($announcements as $a): ?>
          <div class="rounded-xl border p-4 hover:bg-slate-50">
            <div class="flex justify-between">
              <p class="font-semibold"><?php echo htmlspecialchars($a['title']); ?></p>
              <p class="text-xs text-slate-500">
                <?php echo date("M d, Y", strtotime($a['created_at'])); ?>
              </p>
            </div>
            <p class="mt-2 text-sm text-slate-700">
              <?php echo htmlspecialchars(mb_strimwidth($a['message'], 0, 180, '...')); ?>
            </p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- Calendar -->
  <section class="rounded-2xl bg-white border p-6">
    <h2 class="text-lg font-bold"><?php echo $monthName . " " . $year; ?></h2>

    <div class="mt-4 grid grid-cols-7 text-xs font-semibold text-slate-500">
      <?php foreach ($weekdays as $w): ?>
        <div class="px-2 py-2"><?php echo $w; ?></div>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-7 gap-2">
      <?php
      for ($i = 1; $i < $startWeekday; $i++) {
        echo '<div class="h-16 rounded-xl bg-slate-50 border"></div>';
      }

      for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $holiday = $holidaysByDate[$dateStr] ?? null;
        $bg = $holiday ? "bg-orange-50 border-orange-200" : "bg-white border-slate-200";

        echo '<div class="h-16 rounded-xl border p-2 '.$bg.'">';
        echo '<span class="text-sm font-bold">'.$d.'</span>';
        if ($holiday) {
          echo '<p class="mt-1 text-[11px] font-semibold text-orange-800">'
               . htmlspecialchars(mb_strimwidth($holiday['title'], 0, 15, '…'))
               . '</p>';
        }
        echo '</div>';
      }
      ?>
    </div>
  </section>

</div>

<?php
$content = ob_get_clean();

require_once __DIR__ . "/head_layout.php";