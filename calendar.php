<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$userId = (int)$_SESSION['user_db_id'];

// Fetch HR user (needed for layout)
$stmt = $conn->prepare("SELECT id, full_name, email, role, approval_status, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'hr') { header("Location: login.php"); exit; }

$_SESSION['user_role'] = 'hr';

// Count pending HR approvals (for sidebar badge)
$c = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE approval_status = 'pending_hr'");
$c->execute();
$pendingHr = (int)($c->get_result()->fetch_assoc()['total'] ?? 0);

// Month navigation
$year  = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;
if ($year < 2000) $year = 2000;
if ($year > 2100) $year = 2100;

$firstDayTs = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)date('t', $firstDayTs);
$startWeekday = (int)date('N', $firstDayTs); // 1=Mon..7=Sun

// Handle add holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
  $event_date = $_POST['event_date'] ?? '';
  $title = trim($_POST['title'] ?? '');

  if ($event_date && $title) {
    $ins = $conn->prepare("INSERT INTO calendar_events (event_date, title, type, created_by)
                           VALUES (?, ?, 'holiday', ?)
                           ON DUPLICATE KEY UPDATE title=VALUES(title)");
    $ins->bind_param("ssi", $event_date, $title, $userId);
    $ins->execute();
  }
  header("Location: calendar.php?y={$year}&m={$month}");
  exit;
}

// Handle delete holiday
if (isset($_GET['delete'])) {
  $delId = (int)$_GET['delete'];
  $del = $conn->prepare("DELETE FROM calendar_events WHERE id=? AND type='holiday'");
  $del->bind_param("i", $delId);
  $del->execute();
  header("Location: calendar.php?y={$year}&m={$month}");
  exit;
}

// Fetch holidays for this month
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$hol = $conn->prepare("SELECT id, event_date, title
                       FROM calendar_events
                       WHERE type='holiday' AND event_date BETWEEN ? AND ?
                       ORDER BY event_date ASC");
$hol->bind_param("ss", $monthStart, $monthEnd);
$hol->execute();
$holRes = $hol->get_result();

$holidaysByDate = [];
while ($r = $holRes->fetch_assoc()) {
  $holidaysByDate[$r['event_date']] = $r;
}

$monthName = date('F', $firstDayTs);

// prev/next month links
$prevTs = strtotime('-1 month', $firstDayTs);
$nextTs = strtotime('+1 month', $firstDayTs);
$prevY = (int)date('Y', $prevTs);
$prevM = (int)date('n', $prevTs);
$nextY = (int)date('Y', $nextTs);
$nextM = (int)date('n', $nextTs);

$weekdays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

// Layout settings
$pageTitle = "Calendar";
$active = "calendar";

// Page content
ob_start();
?>
  <div class="grid gap-6 lg:grid-cols-3">

    <!-- CALENDAR -->
    <section class="lg:col-span-2 rounded-2xl bg-white border p-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <a class="rounded-lg border px-3 py-2 hover:bg-slate-100"
             href="calendar.php?y=<?php echo $prevY; ?>&m=<?php echo $prevM; ?>">←</a>
          <div>
            <p class="text-lg font-bold"><?php echo htmlspecialchars($monthName . " " . $year); ?></p>
            <p class="text-xs text-slate-500">Click a date to add holiday</p>
          </div>
          <a class="rounded-lg border px-3 py-2 hover:bg-slate-100"
             href="calendar.php?y=<?php echo $nextY; ?>&m=<?php echo $nextM; ?>">→</a>
        </div>

        <a class="text-sm font-semibold hover:underline" href="calendar.php">Today</a>
      </div>

      <div class="mt-5 grid grid-cols-7 text-xs font-semibold text-slate-500">
        <?php foreach ($weekdays as $w) { ?>
          <div class="px-2 py-2"><?php echo $w; ?></div>
        <?php } ?>
      </div>

      <div class="grid grid-cols-7 gap-2">
        <?php
          for ($i = 1; $i < $startWeekday; $i++) {
            echo '<div class="h-24 rounded-xl bg-slate-50 border border-slate-100"></div>';
          }

          for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday = ($dateStr === date('Y-m-d'));
            $holiday = $holidaysByDate[$dateStr] ?? null;

            $base = "h-24 rounded-xl border p-2 cursor-pointer hover:bg-slate-50";
            $bg = $holiday ? "bg-orange-50 border-orange-200" : "bg-white border-slate-200";
            $ring = $isToday ? "ring-2 ring-slate-900" : "";

            echo '<div class="'.$base.' '.$bg.' '.$ring.'" data-date="'.$dateStr.'" data-title="'.htmlspecialchars($holiday['title'] ?? '').'">';

            echo '<div class="flex items-start justify-between">';
            echo '<span class="text-sm font-bold">'. $d .'</span>';

            if ($holiday) {
              echo '<a class="text-xs font-semibold text-orange-700 hover:underline"
                        href="calendar.php?y='.$year.'&m='.$month.'&delete='.(int)$holiday['id'].'"
                        onclick="return confirm(\'Delete this holiday?\')">Delete</a>';
            }
            echo '</div>';

            if ($holiday) {
              echo '<p class="mt-2 text-xs font-semibold text-orange-800">'.htmlspecialchars($holiday['title']).'</p>';
              echo '<p class="mt-1 text-[11px] text-orange-700">Holiday</p>';
            } else {
              echo '<p class="mt-6 text-[11px] text-slate-400">No event</p>';
            }

            echo '</div>';
          }

          $totalCells = ($startWeekday - 1) + $daysInMonth;
          $trail = (7 - ($totalCells % 7)) % 7;
          for ($i = 0; $i < $trail; $i++) {
            echo '<div class="h-24 rounded-xl bg-slate-50 border border-slate-100"></div>';
          }
        ?>
      </div>
    </section>

    <!-- ADD HOLIDAY FORM -->
    <aside class="rounded-2xl bg-white border p-6">
      <h2 class="text-lg font-bold">Add / Update Holiday</h2>
      <p class="mt-1 text-sm text-slate-600">Choose a date and set the holiday name.</p>

      <form method="POST" class="mt-5 space-y-4" id="holidayForm">
        <input type="hidden" name="add_holiday" value="1">

        <div>
          <label class="text-sm font-semibold">Date</label>
          <input type="date" name="event_date" id="event_date" required
                 value="<?php echo htmlspecialchars(date('Y-m-d', $firstDayTs)); ?>"
                 class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-slate-400">
        </div>

        <div>
          <label class="text-sm font-semibold">Holiday Name</label>
          <input type="text" name="title" id="title" required placeholder="e.g., Independence Day"
                 class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-slate-400">
          <p class="mt-2 text-xs text-slate-500">
            If you select a date that already has a holiday, it will update the name.
          </p>
        </div>

        <button class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">
          Save Holiday
        </button>
      </form>

      <div class="mt-8 border-t pt-5">
        <h3 class="font-semibold">Legend</h3>
        <div class="mt-3 space-y-2 text-sm">
          <div class="flex items-center gap-2">
            <span class="h-4 w-4 rounded bg-orange-200 border border-orange-300"></span> Holiday (Orange)
          </div>
          <div class="flex items-center gap-2">
            <span class="h-4 w-4 rounded bg-white border border-slate-200"></span> Normal day
          </div>
          <div class="flex items-center gap-2">
            <span class="h-4 w-4 rounded bg-white border border-slate-200 ring-2 ring-slate-900"></span> Today
          </div>
        </div>
      </div>
    </aside>

  </div>

  <script>
    document.querySelectorAll('[data-date]').forEach(cell => {
      cell.addEventListener('click', () => {
        const date = cell.getAttribute('data-date');
        const title = cell.getAttribute('data-title') || '';
        document.getElementById('event_date').value = date;
        document.getElementById('title').value = title;
        document.getElementById('title').focus();
      });
    });
  </script>
<?php
$content = ob_get_clean();

require_once __DIR__ . "/hr_layout.php";