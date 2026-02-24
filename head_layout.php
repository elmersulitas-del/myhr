<?php
// head_layout.php
// Requires: $user, $pageTitle, $active, $pendingCount, $content

if (!isset($pageTitle)) $pageTitle = "Head";
if (!isset($active)) $active = "home";
if (!isset($content)) $content = "";

function nav_class($key, $active) {
  if ($key === $active) {
    return "flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold bg-slate-900 text-white";
  }
  return "flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold hover:bg-slate-100";
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800">
  <div class="min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="hidden md:flex md:w-64 flex-col border-r bg-white">
      <div class="px-6 py-5 border-b">
        <p class="text-lg font-extrabold">Head Panel</p>
        <p class="text-xs text-slate-500">
          <?php echo htmlspecialchars($user['department'] ?? ''); ?>
        </p>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-1">

        <a href="dashboard_head.php" class="<?php echo nav_class('home', $active); ?>">
          <span>🏠</span> Home
        </a>

        <a href="pending_approvals.php"
           class="flex items-center justify-between rounded-xl px-3 py-2 text-sm font-semibold <?php echo $active==='approvals' ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'; ?>">
          <span class="flex items-center gap-3">
            <span>✅</span> Pending Approvals
          </span>
          <span class="<?php echo ($pendingCount ?? 0) > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'; ?> text-xs font-bold px-2 py-1 rounded-full">
            <?php echo (int)($pendingCount ?? 0); ?>
          </span>
        </a>

        <a href="department_employees.php" class="<?php echo nav_class('employees', $active); ?>">
          <span>👥</span> Department Employees
        </a>

        <a href="leave_head.php" class="<?php echo nav_class('leave', $active); ?>">
          <span>🗂️</span> Leave Approvals
        </a>

        

        <a href="announcements_view.php" class="<?php echo nav_class('announcements', $active); ?>">
          <span>📢</span> Announcements
        </a>

        <a href="calendar_view.php" class="<?php echo nav_class('calendar', $active); ?>">
          <span>📅</span> Calendar
        </a>

      </nav>

      <div class="px-3 py-4 border-t">
        <div class="rounded-2xl bg-slate-50 p-4">
          <p class="text-sm font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></p>
          <p class="text-xs text-slate-600 break-all"><?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        <a href="logout.php"
           class="mt-3 block text-center rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-100">
          Logout
        </a>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1">

      <!-- HEADER -->
      <header class="border-b bg-white">
        <div class="mx-auto max-w-6xl px-4 py-4 flex items-center justify-between">
          <div>
            <p class="font-bold">Department Head Dashboard</p>
            <p class="text-sm text-slate-500"><?php echo htmlspecialchars($pageTitle); ?></p>
          </div>
          <div class="md:hidden">
            <a class="px-4 py-2 rounded-lg border hover:bg-slate-100" href="logout.php">Logout</a>
          </div>
        </div>
      </header>

      <!-- CONTENT -->
      <div class="mx-auto max-w-6xl px-4 py-8">
        <?php echo $content; ?>
      </div>

    </main>
  </div>
</body>
</html>