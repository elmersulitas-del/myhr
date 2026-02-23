<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
  header("Location: login.php");
  exit;
}

$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT email, full_name, role, profile_completed FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  header("Location: login.php");
  exit;
}

function redirect_by_role($role) {
  $role = $role ?: 'employee';
  if ($role === 'hr') { header("Location: dashboard_hr.php"); exit; }
  if ($role === 'head') { header("Location: dashboard_head.php"); exit; }
  header("Location: dashboard_employee.php"); exit;
}

if ((int)$user['profile_completed'] === 1) {
  redirect_by_role($user['role'] ?? 'employee');
}

// Departments list (edit this list to match your school)
$departments = [
  "Human Resources",
  "Information Technology",
  "Accounting",
  "Registrar",
  "Guidance Office",
  "Library",
  "Faculty - College",
  "Faculty - Senior High",
  "Faculty - Junior High",
  "Administration",
  "Security",
  "Maintenance",
  "Other"
];

// Optional error message from save_profile.php
$err = $_SESSION['login_error'] ?? '';
if ($err) {
  unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Complete Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-800">
  <!-- Wrapper: on desktop, height is fixed so only right panel scrolls -->
  <div class="min-h-screen md:h-screen flex items-center justify-center px-4 py-8 md:py-0">

    <!-- Card container -->
    <div class="w-full max-w-5xl overflow-hidden rounded-3xl border bg-white shadow-sm md:h-[92vh]">
      <div class="grid md:grid-cols-2 md:h-full">

        <!-- LEFT PANEL (Sticky on desktop, stays visible) -->
        <div class="p-8 md:p-10 bg-gradient-to-br from-slate-900 to-slate-700 text-white
                    md:sticky md:top-0 md:h-full">
          <div class="flex items-center gap-3">
            <div class="h-11 w-11 rounded-xl bg-white/15 grid place-items-center font-bold">HR</div>
            <div>
              <p class="font-bold leading-tight">Human Resource System</p>
              <p class="text-sm text-white/80 leading-tight">Immaculada Concepcion College</p>
            </div>
          </div>

          <h1 class="mt-8 text-2xl font-extrabold leading-snug">
            First-time login setup
            <span class="block text-white/90">Complete your profile & upload requirements.</span>
          </h1>

          <p class="mt-3 text-sm text-white/80 leading-relaxed">
            Please provide your employee details. Required documents help HR verify your profile.
          </p>

          <div class="mt-8 rounded-2xl border border-white/20 bg-white/10 p-4">
            <p class="text-xs uppercase tracking-wider text-white/70">Signed in email</p>
            <p class="mt-1 font-semibold break-all"><?php echo htmlspecialchars($user['email']); ?></p>
            <p class="mt-2 text-xs text-white/70">
              Allowed domain: <span class="font-semibold">@<?php echo htmlspecialchars(ALLOWED_DOMAIN); ?></span>
            </p>
          </div>

          <div class="mt-8 rounded-2xl border border-white/20 bg-white/10 p-4">
            <p class="text-sm font-semibold">Required uploads</p>
            <ul class="mt-2 text-sm text-white/80 list-disc pl-5 space-y-1">
              <li>Resume (PDF/DOC/DOCX)</li>
              <li>SSS (PDF/JPG/PNG)</li>
            </ul>
            <p class="mt-3 text-xs text-white/70">
              You can also upload other documents on the form (optional).
            </p>
          </div>

          <div class="mt-10 text-xs text-white/60">
            Tip: On desktop, only the form scrolls. This guide stays visible.
          </div>
        </div>

        <!-- RIGHT PANEL (Scrollable only) -->
        <div class="p-8 md:p-10 md:h-full md:overflow-y-auto">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-xl font-bold">Complete Your Information</h2>
              <p class="mt-1 text-sm text-slate-600">Fill in the form to continue to your dashboard.</p>
            </div>
            <a href="logout.php" class="text-sm font-semibold text-slate-700 hover:underline">Logout</a>
          </div>

          <?php if (!empty($err)) { ?>
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
              <?php echo htmlspecialchars($err); ?>
            </div>
          <?php } ?>

          <form action="save_profile.php" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
            <!-- Employee ID -->
            <div>
              <label class="text-sm font-semibold text-slate-700">Employee ID</label>
              <input
                type="text"
                name="emp_id"
                required
                placeholder="e.g., 2026-00123"
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-slate-400"
              />
            </div>

            <!-- Department Dropdown -->
            <div>
              <label class="text-sm font-semibold text-slate-700">Department</label>
              <select
                name="department"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-slate-400"
              >
                <option value="" disabled selected>Select department</option>
                <?php foreach ($departments as $dept) { ?>
                  <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php } ?>
              </select>
            </div>

            <!-- Role -->
            <div>
              <label class="text-sm font-semibold text-slate-700">Role</label>
              <select
                name="role"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-slate-400"
              >
                <option value="" disabled selected>Select role</option>
                <option value="employee">Employee</option>
                <option value="head">Department Head</option>
                <option value="hr">HR Staff</option>
              </select>

              <p class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3">
                For security, HR roles are usually assigned by an administrator. If your project allows self-select, keep this.
              </p>
            </div>

            <!-- Required Docs -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4">
              <p class="font-semibold text-slate-800">Required Documents</p>

              <div>
                <label class="text-sm font-semibold text-slate-700">Resume <span class="text-red-600">*</span></label>
                <input
                  type="file"
                  name="resume_file"
                  required
                  accept=".pdf,.doc,.docx"
                  class="mt-2 block w-full text-sm text-slate-700
                    file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white
                    hover:file:bg-slate-800"
                />
                <div id="resumePreview" class="mt-2 text-xs text-slate-600"></div>
              </div>

              <div>
                <label class="text-sm font-semibold text-slate-700">SSS (Screenshot/Scan) <span class="text-red-600">*</span></label>
                <input
                  type="file"
                  name="sss_file"
                  required
                  accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-2 block w-full text-sm text-slate-700
                    file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white
                    hover:file:bg-slate-800"
                />
                <div id="sssPreview" class="mt-2 text-xs text-slate-600"></div>
              </div>
            </div>

            <!-- Optional Docs -->
            <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
              <p class="font-semibold text-slate-800">Optional Documents</p>

              <div>
                <label class="text-sm font-semibold text-slate-700">PhilHealth</label>
                <input type="file" name="philhealth_file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-2 block w-full text-sm text-slate-700
                  file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800
                  hover:file:bg-slate-200" />
              </div>

              <div>
                <label class="text-sm font-semibold text-slate-700">Pag-IBIG</label>
                <input type="file" name="pagibig_file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-2 block w-full text-sm text-slate-700
                  file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800
                  hover:file:bg-slate-200" />
              </div>

              <div>
                <label class="text-sm font-semibold text-slate-700">TIN</label>
                <input type="file" name="tin_file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-2 block w-full text-sm text-slate-700
                  file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800
                  hover:file:bg-slate-200" />
              </div>

              <div>
                <label class="text-sm font-semibold text-slate-700">ID Photo</label>
                <input type="file" name="id_photo_file" accept=".jpg,.jpeg,.png"
                  class="mt-2 block w-full text-sm text-slate-700
                  file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800
                  hover:file:bg-slate-200" />
              </div>

              <div>
                <label class="text-sm font-semibold text-slate-700">Additional Files (multiple)</label>
                <input
                  type="file"
                  name="documents[]"
                  multiple
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  class="mt-2 block w-full text-sm text-slate-700
                    file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800
                    hover:file:bg-slate-200"
                />
                <div id="docsPreview" class="mt-2 text-xs text-slate-600"></div>
              </div>

              <p class="text-xs text-slate-500">
                Allowed: PDF, DOC, DOCX, JPG, PNG. Recommended max: 10MB per file.
              </p>
            </div>

            <button
              type="submit"
              class="w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800"
            >
              Save & Continue
            </button>

            <p class="text-xs text-slate-500 text-center">
              Please ensure your details and uploaded files are correct.
            </p>
          </form>

          <div class="h-6"></div>
        </div>

      </div>
    </div>
  </div>

  <script>
    function showSingleFile(inputName, previewId) {
      const input = document.querySelector(`input[name="${inputName}"]`);
      const preview = document.getElementById(previewId);
      if (!input || !preview) return;

      input.addEventListener('change', () => {
        preview.textContent = '';
        if (input.files && input.files[0]) {
          preview.textContent = `Selected: ${input.files[0].name}`;
        }
      });
    }

    function showMultipleFiles(inputName, previewId) {
      const input = document.querySelector(`input[name="${inputName}"]`);
      const preview = document.getElementById(previewId);
      if (!input || !preview) return;

      input.addEventListener('change', () => {
        preview.innerHTML = '';
        if (!input.files || input.files.length === 0) return;

        const ul = document.createElement('ul');
        ul.className = "list-disc pl-5 space-y-1";
        for (const f of input.files) {
          const li = document.createElement('li');
          li.textContent = f.name;
          ul.appendChild(li);
        }
        preview.appendChild(ul);
      });
    }

    showSingleFile('resume_file', 'resumePreview');
    showSingleFile('sss_file', 'sssPreview');

    // Note: querySelector needs exact name; documents[] works using this selector:
    const multiInput = document.querySelector('input[name="documents[]"]');
    if (multiInput) {
      multiInput.addEventListener('change', () => {
        const preview = document.getElementById('docsPreview');
        if (!preview) return;
        preview.innerHTML = '';
        if (!multiInput.files || multiInput.files.length === 0) return;

        const ul = document.createElement('ul');
        ul.className = "list-disc pl-5 space-y-1";
        for (const f of multiInput.files) {
          const li = document.createElement('li');
          li.textContent = f.name;
          ul.appendChild(li);
        }
        preview.appendChild(ul);
      });
    }
  </script>
</body>
</html>