<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (($user['approval_status'] ?? '') !== 'approved') {
  header("Location: waiting_approval.php");
  exit;
}
if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT full_name, email, department, emp_id, role, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }

$_SESSION['user_role'] = $user['role'] ?? 'employee';

if (($_SESSION['user_role'] ?? '') !== 'hr') {
  header("Location: login.php");
  exit;
}

// Optional: fetch uploaded docs (if you created user_files table)
$files = null;
$filesStmt = $conn->prepare("SELECT original_name, stored_name, uploaded_at FROM user_files WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 10");
if ($filesStmt) {
  $filesStmt->bind_param("i", $userId);
  $filesStmt->execute();
  $files = $filesStmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800">
  <!-- Top Nav -->
  <header class="sticky top-0 z-20 border-b bg-white/80 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
      <div class="flex items-center gap-3">
        <!-- You can replace with your logo -->
        <div class="h-10 w-10 rounded-xl bg-slate-900 text-white grid place-items-center font-bold">HR</div>
        <div>
          <p class="text-sm font-semibold leading-tight">Leave Management System</p>
          <p class="text-xs text-slate-500 leading-tight">Immaculada Concepcion College</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="hidden sm:block text-right">
          <p class="text-sm font-semibold"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></p>
          <p class="text-xs text-slate-500"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
        </div>
        <a href="logout.php"
           class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-100">
          Logout
        </a>
      </div>
    </div>
  </header>

  <!-- Main -->
  <main class="mx-auto max-w-6xl px-4 py-8">
    <!-- Welcome -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? ''); ?> 👋</h1>
      <p class="mt-1 text-sm text-slate-600">
        Manage your leave requests, view your records, and upload documents for HR verification.
      </p>
    </div>

    <!-- Cards -->
    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500">Employee ID</p>
        <p class="mt-2 text-lg font-bold"><?php echo htmlspecialchars($user['emp_id'] ?? '—'); ?></p>
        <p class="mt-1 text-xs text-slate-500">Your assigned employee number</p>
      </div>

      <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500">Department</p>
        <p class="mt-2 text-lg font-bold"><?php echo htmlspecialchars($user['department'] ?? '—'); ?></p>
        <p class="mt-1 text-xs text-slate-500">Current department</p>
      </div>

      <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold text-slate-500">Role</p>
        <p class="mt-2 text-lg font-bold"><?php echo htmlspecialchars($user['role'] ?? 'employee'); ?></p>
        <p class="mt-1 text-xs text-slate-500">System permission level</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 grid gap-4 md:grid-cols-2">
      <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Quick Actions</h2>
        <p class="mt-1 text-sm text-slate-600">Start here to manage your HR tasks.</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <a href="#"
             class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
            <p class="font-semibold">Request Leave</p>
            <p class="text-xs text-slate-500 mt-1">Create a new leave request</p>
          </a>

          <a href="#"
             class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
            <p class="font-semibold">View Attendance</p>
            <p class="text-xs text-slate-500 mt-1">See logs & records</p>
          </a>

          <a href="profile_setup.php"
             class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
            <p class="font-semibold">Edit Profile</p>
            <p class="text-xs text-slate-500 mt-1">Update your information</p>
          </a>

          <a href="#"
             class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
            <p class="font-semibold">Announcements</p>
            <p class="text-xs text-slate-500 mt-1">Read school HR updates</p>
          </a>
        </div>
      </div>

      <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Account Status</h2>
        <p class="mt-1 text-sm text-slate-600">Your profile is completed and ready.</p>

        <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4">
          <p class="font-semibold text-emerald-800">Verified access</p>
          <p class="text-sm text-emerald-700 mt-1">
            Only institutional email accounts can access this system.
          </p>
          <p class="text-xs text-emerald-700 mt-2">
            Allowed domain: <span class="font-semibold">@<?php echo htmlspecialchars(ALLOWED_DOMAIN); ?></span>
          </p>
        </div>

        <div class="mt-4 text-sm text-slate-600">
          <p><span class="font-semibold">Email:</span> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
        </div>
      </div>
    </div>

    <!-- Uploaded Documents -->
    <div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold">Uploaded Documents</h2>
          <p class="text-sm text-slate-600">Your submitted files for HR verification.</p>
        </div>
        <a href="profile_setup.php"
           class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
          Upload More
        </a>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-xs uppercase text-slate-500">
            <tr>
              <th class="py-3 pr-4">File Name</th>
              <th class="py-3 pr-4">Uploaded</th>
              <th class="py-3">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php if ($files && $files->num_rows > 0) { ?>
              <?php while ($f = $files->fetch_assoc()) { ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 pr-4 font-medium">
                    <?php echo htmlspecialchars($f['original_name']); ?>
                  </td>
                  <td class="py-3 pr-4 text-slate-600">
                    <?php echo htmlspecialchars($f['uploaded_at']); ?>
                  </td>
                  <td class="py-3">
                    <a class="text-slate-900 font-semibold hover:underline"
                       target="_blank"
                       href="<?php echo 'uploads/user_docs/' . rawurlencode($f['stored_name']); ?>">
                      View
                    </a>
                  </td>
                </tr>
              <?php } ?>
            <?php } else { ?>
              <tr>
                <td class="py-4 text-slate-600" colspan="3">
                  No documents uploaded yet.
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <footer class="mt-10 text-center text-xs text-slate-500">
      © <?php echo date('Y'); ?> HR System — Immaculada Concepcion College
    </footer>
  </main>
</body>
</html>