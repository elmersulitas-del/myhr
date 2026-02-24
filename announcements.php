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

// EDIT MODE
$editAnnouncement = null;
if (isset($_GET['edit'])) {
  $editId = (int)$_GET['edit'];
  $e = $conn->prepare("SELECT id, title, message FROM announcements WHERE id = ?");
  $e->bind_param("i", $editId);
  $e->execute();
  $editAnnouncement = $e->get_result()->fetch_assoc();
}

/* ----------------------
   HANDLE CREATE
-----------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
  $title = trim($_POST['title'] ?? '');
  $message = trim($_POST['message'] ?? '');

  if ($title !== '' && $message !== '') {
    $ins = $conn->prepare("INSERT INTO announcements (title, message, posted_by) VALUES (?, ?, ?)");
    $ins->bind_param("ssi", $title, $message, $userId);
    $ins->execute();
  }
  header("Location: announcements.php");
  exit;
}

/* ----------------------
   HANDLE UPDATE
-----------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  $id = (int)($_POST['id'] ?? 0);
  $title = trim($_POST['title'] ?? '');
  $message = trim($_POST['message'] ?? '');

  if ($id > 0 && $title !== '' && $message !== '') {
    $upd = $conn->prepare("UPDATE announcements SET title = ?, message = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("ssi", $title, $message, $id);
    $upd->execute();
  }
  header("Location: announcements.php");
  exit;
}

/* ----------------------
   HANDLE DELETE
-----------------------*/
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $del = $conn->prepare("DELETE FROM announcements WHERE id = ?");
  $del->bind_param("i", $id);
  $del->execute();

  header("Location: announcements.php");
  exit;
}

/* ----------------------
   FETCH ALL
-----------------------*/
$result = $conn->query("
  SELECT a.*, u.full_name
  FROM announcements a
  LEFT JOIN users u ON a.posted_by = u.id
  ORDER BY a.created_at DESC
");

// ---- Layout settings ----
$pageTitle = "Announcements";
$active = "announcements";

// ---- Page content ----
ob_start();
?>
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">Announcements</h1>
      <p class="text-sm text-slate-600">Create, edit, and delete HR announcements.</p>
    </div>
  </div>

  <!-- CREATE OR EDIT FORM -->
  <div class="mt-6 bg-white border rounded-2xl p-6">
    <?php if ($editAnnouncement): ?>
      <h2 class="font-semibold mb-4">Edit Announcement</h2>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="update" value="1">
        <input type="hidden" name="id" value="<?php echo (int)$editAnnouncement['id']; ?>">

        <div>
          <label class="text-sm font-semibold">Title</label>
          <input type="text" name="title" required
                 value="<?php echo htmlspecialchars($editAnnouncement['title']); ?>"
                 class="mt-2 w-full border rounded-xl px-4 py-2 focus:outline-none focus:border-slate-500">
        </div>

        <div>
          <label class="text-sm font-semibold">Message</label>
          <textarea name="message" rows="4" required
                    class="mt-2 w-full border rounded-xl px-4 py-2 focus:outline-none focus:border-slate-500"><?php
            echo htmlspecialchars($editAnnouncement['message']);
          ?></textarea>
        </div>

        <div class="flex gap-3">
          <button class="bg-slate-900 text-white px-5 py-2 rounded-xl hover:bg-slate-800">
            Update Announcement
          </button>
          <a href="announcements.php" class="px-5 py-2 rounded-xl border hover:bg-slate-100">
            Cancel
          </a>
        </div>
      </form>

    <?php else: ?>
      <h2 class="font-semibold mb-4">Create New Announcement</h2>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="create" value="1">

        <div>
          <label class="text-sm font-semibold">Title</label>
          <input type="text" name="title" required
                 class="mt-2 w-full border rounded-xl px-4 py-2 focus:outline-none focus:border-slate-500">
        </div>

        <div>
          <label class="text-sm font-semibold">Message</label>
          <textarea name="message" rows="4" required
                    class="mt-2 w-full border rounded-xl px-4 py-2 focus:outline-none focus:border-slate-500"></textarea>
        </div>

        <button class="bg-slate-900 text-white px-5 py-2 rounded-xl hover:bg-slate-800">
          Post Announcement
        </button>
      </form>
    <?php endif; ?>
  </div>

  <!-- LIST -->
  <div class="mt-8 bg-white border rounded-2xl p-6">
    <h2 class="font-semibold mb-4">All Announcements</h2>

    <?php if ($result && $result->num_rows === 0): ?>
      <p class="text-slate-500 text-sm">No announcements yet.</p>
    <?php else: ?>
      <div class="space-y-4">
        <?php while ($result && $row = $result->fetch_assoc()): ?>
          <div class="border rounded-xl p-4 hover:bg-slate-50">
            <div class="flex justify-between items-start gap-4">
              <div>
                <p class="font-semibold"><?php echo htmlspecialchars($row['title']); ?></p>
                <p class="text-xs text-slate-500">
                  Posted by <?php echo htmlspecialchars($row['full_name'] ?? 'Unknown'); ?>
                  • <?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?>
                  <?php if (!empty($row['updated_at'])): ?>
                    • <span class="italic">Edited</span>
                  <?php endif; ?>
                </p>
              </div>

              <div class="flex gap-3">
                <a href="?edit=<?php echo (int)$row['id']; ?>"
                   class="text-xs text-slate-700 font-semibold hover:underline">
                  Edit
                </a>

                <a href="?delete=<?php echo (int)$row['id']; ?>"
                   onclick="return confirm('Delete this announcement?')"
                   class="text-xs text-red-600 font-semibold hover:underline">
                  Delete
                </a>
              </div>
            </div>

            <p class="mt-3 text-sm text-slate-700">
              <?php echo nl2br(htmlspecialchars($row['message'])); ?>
            </p>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();

require_once __DIR__ . "/hr_layout.php";