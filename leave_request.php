<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) { header("Location: login.php"); exit; }
$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("SELECT id, full_name, email, department, role, approval_status, profile_completed,
  sick_leave_balance, incentive_leave_balance, emergency_leave_balance
  FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: login.php"); exit; }
if ((int)$user['profile_completed'] !== 1) { header("Location: profile_setup.php"); exit; }
if (($user['approval_status'] ?? '') !== 'approved') { header("Location: waiting_approval.php"); exit; }
if (($user['role'] ?? '') !== 'employee') { header("Location: login.php"); exit; }

$pageTitle = "Request Leave";
$active = "request";
$err = "";

function calc_days_inclusive($from, $to) {
  $a = strtotime($from);
  $b = strtotime($to);
  if (!$a || !$b) return 0;
  if ($b < $a) return 0;
  return (int)(floor(($b - $a) / 86400) + 1);
}

$old_leave_type = $_POST['leave_type'] ?? '';
$old_date_from  = $_POST['date_from'] ?? '';
$old_date_to    = $_POST['date_to'] ?? '';
$old_reason     = trim($_POST['reason'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_type = $old_leave_type;
  $date_from  = $old_date_from;
  $date_to    = $old_date_to;
  $reason     = $old_reason;

  $allowed = ['sick','incentive','emergency'];
  if (!in_array($leave_type, $allowed, true)) $err = "Invalid leave type.";
  elseif (!$date_from || !$date_to) $err = "Please choose date range.";
  elseif ($reason === '') $err = "Reason is required.";

  $days = calc_days_inclusive($date_from, $date_to);
  if (!$err && $days <= 0) $err = "Invalid dates.";

  if (!$err) {
    $bal = 0;
    if ($leave_type === 'sick') $bal = (int)$user['sick_leave_balance'];
    if ($leave_type === 'incentive') $bal = (int)$user['incentive_leave_balance'];
    if ($leave_type === 'emergency') $bal = (int)$user['emergency_leave_balance'];

    if ($days > $bal) $err = "Not enough leave balance. Available: {$bal}, Requested: {$days}.";
  }

  $medStored = null;
  if (!$err && $leave_type === 'sick' && $days >= 4) {
    if (!isset($_FILES['med_cert']) || $_FILES['med_cert']['error'] === UPLOAD_ERR_NO_FILE) {
      $err = "Medical certificate is required for Sick Leave 4 days or more.";
    } else {
      $uploadDir = __DIR__ . "/uploads/leave_docs/";
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

      $f = $_FILES['med_cert'];
      if ($f['error'] !== UPLOAD_ERR_OK) {
        $err = "Upload failed. Please try again.";
      } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf','jpg','jpeg','png'];
        if (!in_array($ext, $allowedExt, true)) {
          $err = "Med cert must be PDF/JPG/PNG.";
        } else if ((int)$f['size'] > 10 * 1024 * 1024) {
          $err = "File too large (max 10MB).";
        } else {
          $medStored = $userId . "_" . bin2hex(random_bytes(10)) . "." . $ext;
          if (!move_uploaded_file($f['tmp_name'], $uploadDir . $medStored)) {
            $err = "Could not save file.";
          }
        }
      }
    }
  }

  if (!$err) {
    $dept = $user['department'] ?? '';
    $ins = $conn->prepare("INSERT INTO leave_requests
      (user_id, department, leave_type, date_from, date_to, days, reason, med_cert_file, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_head')");
    $ins->bind_param("issssiss", $userId, $dept, $leave_type, $date_from, $date_to, $days, $reason, $medStored);
    $ins->execute();

    $_SESSION['leave_request_success'] = "Your leave request has been submitted successfully.";
    header("Location: dashboard_employee.php");
    exit;
  }
}

$previewDays = 0;
if ($old_date_from && $old_date_to) {
  $previewDays = calc_days_inclusive($old_date_from, $old_date_to);
}

ob_start();
?>

<style>
.leave-page * { box-sizing: border-box; }

.leave-page {
  --lp-bg: #2a2b3d;
  --lp-card: #313348;
  --lp-card-2: #252636;
  --lp-border: #3c3f58;
  --lp-text: #f3f4f6;
  --lp-muted: #9ca3af;
  --lp-accent: #6f6486;
  --lp-accent-2: #8b7fb0;
  --lp-success: #22c55e;
  --lp-warning: #f59e0b;
  --lp-danger: #ef4444;
  --lp-info: #3b82f6;
  color: var(--lp-text);
}

.leave-page .hero-card,
.leave-page .balance-card,
.leave-page .form-card,
.leave-page .helper-card {
  border: 1px solid var(--lp-border);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
}

.leave-page .hero-card {
  background: linear-gradient(135deg, var(--lp-accent), #4d4764 55%, #383b55 100%);
  border-radius: 24px;
  padding: 28px;
  position: relative;
  overflow: hidden;
  margin-bottom: 22px;
}

.leave-page .hero-card::before,
.leave-page .hero-card::after {
  content: "";
  position: absolute;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  pointer-events: none;
}

.leave-page .hero-card::before {
  width: 220px;
  height: 220px;
  top: -90px;
  right: -40px;
}

.leave-page .hero-card::after {
  width: 140px;
  height: 140px;
  bottom: -50px;
  right: 120px;
}

.leave-page .hero-grid {
  display: grid;
  grid-template-columns: 1.2fr .8fr;
  gap: 20px;
  position: relative;
  z-index: 1;
}

.leave-page .page-badge {
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

.leave-page .hero-title {
  margin: 16px 0 8px;
  font-size: 34px;
  line-height: 1.15;
  font-weight: 800;
  color: #fff;
}

.leave-page .hero-subtitle {
  margin: 0;
  color: rgba(255,255,255,0.82);
  font-size: 14px;
  line-height: 1.7;
  max-width: 700px;
}

.leave-page .hero-side {
  background: rgba(17,24,39,0.24);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 22px;
  padding: 20px;
  align-self: stretch;
}

.leave-page .hero-side-label {
  color: rgba(255,255,255,0.72);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: 8px;
}

.leave-page .hero-side-value {
  font-size: 38px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.leave-page .hero-side-note {
  margin-top: 10px;
  color: rgba(255,255,255,0.78);
  font-size: 13px;
  line-height: 1.6;
}

.leave-page .balance-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin-top: 22px;
}

.leave-page .balance-card {
  background: var(--lp-card);
  border-radius: 22px;
  padding: 22px;
  min-height: 150px;
  position: relative;
  overflow: hidden;
}

.leave-page .balance-card::after {
  content: "";
  position: absolute;
  width: 92px;
  height: 92px;
  border-radius: 50%;
  top: -28px;
  right: -24px;
  opacity: .16;
}

.leave-page .balance-card.balance-sick::after { background: var(--lp-info); }
.leave-page .balance-card.balance-incentive::after { background: var(--lp-success); }
.leave-page .balance-card.balance-emergency::after { background: var(--lp-warning); }

.leave-page .balance-label {
  color: var(--lp-muted);
  font-size: 13px;
  margin-bottom: 18px;
}

.leave-page .balance-value {
  font-size: 36px;
  line-height: 1;
  font-weight: 800;
  color: #fff;
}

.leave-page .balance-foot {
  margin-top: 14px;
  color: #cbd5e1;
  font-size: 13px;
}

.leave-page .main-grid {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 22px;
  margin-top: 24px;
}

.leave-page .form-card,
.leave-page .helper-card {
  background: var(--lp-card-2);
  border-radius: 24px;
  padding: 24px;
}

.leave-page .panel-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: #fff;
}

.leave-page .panel-subtitle {
  margin-top: 6px;
  color: var(--lp-muted);
  font-size: 13px;
}

.leave-page .error-box {
  margin-top: 18px;
  border-radius: 18px;
  padding: 16px 18px;
  background: rgba(239,68,68,0.15);
  border: 1px solid rgba(239,68,68,0.35);
  color: #fecaca;
  font-size: 14px;
  line-height: 1.6;
}

.leave-page .form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.leave-page .field-group {
  margin-top: 18px;
}

.leave-page .field-group.full {
  grid-column: 1 / -1;
}

.leave-page .field-label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  margin-bottom: 8px;
}

.leave-page .field-help {
  margin-top: 8px;
  color: var(--lp-muted);
  font-size: 12px;
  line-height: 1.6;
}

.leave-page .input,
.leave-page .select,
.leave-page .textarea,
.leave-page .file-input {
  width: 100%;
  background: #2f3146;
  border: 1px solid #424663;
  color: #fff;
  border-radius: 16px;
  padding: 14px 15px;
  outline: none;
  transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.leave-page .input:focus,
.leave-page .select:focus,
.leave-page .textarea:focus,
.leave-page .file-input:focus {
  border-color: #8b7fb0;
  box-shadow: 0 0 0 4px rgba(139, 127, 176, 0.15);
}

.leave-page .select option {
  color: #111827;
}

.leave-page .textarea {
  min-height: 150px;
  resize: vertical;
}

.leave-page .submit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  margin-top: 22px;
  border: 0;
  border-radius: 18px;
  background: linear-gradient(135deg, #6f6486 0%, #5d5677 100%);
  color: #fff;
  padding: 15px 18px;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  transition: transform .2s ease, box-shadow .2s ease, opacity .2s ease;
  box-shadow: 0 16px 28px rgba(0,0,0,0.22);
}

.leave-page .submit-btn:hover {
  transform: translateY(-1px);
  opacity: .96;
}

.leave-page .helper-list {
  display: grid;
  gap: 14px;
  margin-top: 18px;
}

.leave-page .helper-item {
  background: var(--lp-card);
  border: 1px solid var(--lp-border);
  border-radius: 18px;
  padding: 16px;
}

.leave-page .helper-item-title {
  font-size: 14px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 6px;
}

.leave-page .helper-item-text {
  color: var(--lp-muted);
  font-size: 13px;
  line-height: 1.65;
}

.leave-page .summary-box {
  background: linear-gradient(180deg, rgba(111,100,134,0.18), rgba(49,51,72,1));
  border: 1px solid rgba(139,127,176,0.30);
  border-radius: 18px;
  padding: 18px;
  margin-top: 18px;
}

.leave-page .summary-title {
  font-size: 14px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 10px;
}

.leave-page .summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  color: #d1d5db;
  font-size: 13px;
  border-bottom: 1px dashed rgba(255,255,255,0.08);
}

.leave-page .summary-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.leave-page .summary-row strong {
  color: #fff;
  font-weight: 700;
}

@media (max-width: 1100px) {
  .leave-page .hero-grid,
  .leave-page .main-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .leave-page .hero-title {
    font-size: 28px;
  }

  .leave-page .balance-grid,
  .leave-page .form-grid {
    grid-template-columns: 1fr;
  }

  .leave-page .hero-card,
  .leave-page .balance-card,
  .leave-page .form-card,
  .leave-page .helper-card {
    border-radius: 20px;
  }
}
</style>

<div class="leave-page">
  <section class="hero-card">
    <div class="hero-grid">
      <div>
        <span class="page-badge">Employee Leave Form</span>
        <h1 class="hero-title">Submit a Leave Request</h1>
        <p class="hero-subtitle">
          Complete the form below to send your leave request to your department head.
          Your request will be checked against your current leave balance before submission.
        </p>
      </div>

      <div class="hero-side">
        <div class="hero-side-label">Requested Days Preview</div>
        <div class="hero-side-value"><?php echo max(0, (int)$previewDays); ?></div>
        <div class="hero-side-note">
          The number of leave days is counted inclusively from your start date up to your end date.
        </div>
      </div>
    </div>
  </section>

  <section class="balance-grid">
    <div class="balance-card balance-sick">
      <div class="balance-label">Sick Leave</div>
      <div class="balance-value"><?php echo (int)$user['sick_leave_balance']; ?></div>
      <div class="balance-foot">Available credits for health-related absences.</div>
    </div>

    <div class="balance-card balance-incentive">
      <div class="balance-label">Incentive Leave</div>
      <div class="balance-value"><?php echo (int)$user['incentive_leave_balance']; ?></div>
      <div class="balance-foot">Available credits for personal leave use.</div>
    </div>

    <div class="balance-card balance-emergency">
      <div class="balance-label">Emergency Leave</div>
      <div class="balance-value"><?php echo (int)$user['emergency_leave_balance']; ?></div>
      <div class="balance-foot">Available credits for urgent situations.</div>
    </div>
  </section>

  <section class="main-grid">
    <div class="form-card">
      <h2 class="panel-title">Leave Request Form</h2>
      <div class="panel-subtitle">Fill in the required details before submitting your request.</div>

      <?php if ($err): ?>
        <div class="error-box"><?php echo htmlspecialchars($err); ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="leaveForm">
        <div class="form-grid">
          <div class="field-group">
            <label class="field-label" for="leave_type">Leave Type</label>
            <select id="leave_type" name="leave_type" class="select" required>
              <option value="" disabled <?php echo $old_leave_type === '' ? 'selected' : ''; ?>>Select leave type</option>
              <option value="sick" <?php echo $old_leave_type === 'sick' ? 'selected' : ''; ?>>Sick Leave</option>
              <option value="incentive" <?php echo $old_leave_type === 'incentive' ? 'selected' : ''; ?>>Incentive Leave</option>
              <option value="emergency" <?php echo $old_leave_type === 'emergency' ? 'selected' : ''; ?>>Emergency Leave</option>
            </select>
          </div>

          <div class="field-group">
            <label class="field-label" for="date_from">Date From</label>
            <input id="date_from" type="date" name="date_from" class="input" value="<?php echo htmlspecialchars($old_date_from); ?>" required>
          </div>

          <div class="field-group">
            <label class="field-label" for="date_to">Date To</label>
            <input id="date_to" type="date" name="date_to" class="input" value="<?php echo htmlspecialchars($old_date_to); ?>" required>
          </div>

          <div class="field-group full">
            <label class="field-label" for="reason">Reason</label>
            <textarea id="reason" name="reason" class="textarea" required><?php echo htmlspecialchars($old_reason); ?></textarea>
            <div class="field-help">
              Sick leave requests for 4 days or more require a medical certificate upload.
            </div>
          </div>

          <div class="field-group full">
            <label class="field-label" for="med_cert">Medical Certificate</label>
            <input id="med_cert" type="file" name="med_cert" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
            <div class="field-help">
              Accepted file types: PDF, JPG, JPEG, PNG. Maximum file size: 10MB.
            </div>
          </div>
        </div>

        <button type="submit" class="submit-btn">
          <span>Submit Leave Request</span>
        </button>
      </form>
    </div>

    <aside class="helper-card">
      <h2 class="panel-title">Before You Submit</h2>
      <div class="panel-subtitle">Important reminders for your leave request.</div>

      <div class="helper-list">
        <div class="helper-item">
          <div class="helper-item-title">Balance Check</div>
          <div class="helper-item-text">
            The requested number of days must not exceed your available leave balance.
          </div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Medical Certificate Rule</div>
          <div class="helper-item-text">
            Sick leave requests lasting 4 days or more require a valid medical certificate.
          </div>
        </div>

        <div class="helper-item">
          <div class="helper-item-title">Approval Flow</div>
          <div class="helper-item-text">
            Your request is submitted first to your department head for review and approval.
          </div>
        </div>
      </div>

      <div class="summary-box">
        <div class="summary-title">Current Balance Summary</div>
        <div class="summary-row">
          <span>Sick Leave</span>
          <strong><?php echo (int)$user['sick_leave_balance']; ?></strong>
        </div>
        <div class="summary-row">
          <span>Incentive Leave</span>
          <strong><?php echo (int)$user['incentive_leave_balance']; ?></strong>
        </div>
        <div class="summary-row">
          <span>Emergency Leave</span>
          <strong><?php echo (int)$user['emergency_leave_balance']; ?></strong>
        </div>
      </div>
    </aside>
  </section>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/employee_layout.php";
