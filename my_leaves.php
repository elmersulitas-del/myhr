<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_db_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_db_id'];

$stmt = $conn->prepare("
    SELECT id, full_name, email, department, role, approval_status, profile_completed,
           sick_leave_balance, incentive_leave_balance, emergency_leave_balance
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: login.php");
    exit;
}

if ((int)$user['profile_completed'] !== 1) {
    header("Location: profile_setup.php");
    exit;
}

if (($user['approval_status'] ?? '') !== 'approved') {
    header("Location: waiting_approval.php");
    exit;
}

if (($user['role'] ?? '') !== 'employee') {
    header("Location: login.php");
    exit;
}

$pageTitle = "My Leave Records";
$active = "records";

$leaveRows = [];
$counts = [
    'total' => 0,
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0,
];

$result = $conn->prepare("
    SELECT id, leave_type, date_from, date_to, days, reason, med_cert_file, status, created_at
    FROM leave_requests
    WHERE user_id = ?
    ORDER BY id DESC
");
$result->bind_param("i", $userId);
$result->execute();
$res = $result->get_result();

while ($row = $res->fetch_assoc()) {
    $leaveRows[] = $row;
    $counts['total']++;

    $status = strtolower(trim((string)($row['status'] ?? '')));

    if (strpos($status, 'reject') !== false) {
        $counts['rejected']++;
    } elseif (strpos($status, 'approved') !== false || $status === 'received') {
        $counts['approved']++;
    } else {
        $counts['pending']++;
    }
}

function leave_type_label($type) {
    $type = strtolower(trim((string)$type));
    if ($type === 'sick') return 'Sick Leave';
    if ($type === 'incentive') return 'Incentive Leave';
    if ($type === 'emergency') return 'Emergency Leave';
    return ucwords(str_replace('_', ' ', $type));
}

function leave_status_label($status) {
    $status = trim((string)$status);
    if ($status === '') return 'Unknown';
    return ucwords(str_replace('_', ' ', $status));
}

function leave_status_class($status) {
    $status = strtolower(trim((string)$status));

    if (strpos($status, 'reject') !== false) return 'status-rejected';
    if (strpos($status, 'approved') !== false) return 'status-approved';
    if ($status === 'received') return 'status-approved';
    if (strpos($status, 'pending') !== false) return 'status-pending';

    return 'status-default';
}

function leave_type_class($type) {
    $type = strtolower(trim((string)$type));
    if ($type === 'sick') return 'type-sick';
    if ($type === 'incentive') return 'type-incentive';
    if ($type === 'emergency') return 'type-emergency';
    return 'type-default';
}

ob_start();
?>

<style>
.my-leaves-page * { box-sizing: border-box; }

.my-leaves-page {
    --ml-bg: #2a2b3d;
    --ml-card: #313348;
    --ml-card-2: #252636;
    --ml-border: #3c3f58;
    --ml-text: #f3f4f6;
    --ml-muted: #9ca3af;
    --ml-accent: #6f6486;
    --ml-accent-2: #8b7fb0;
    --ml-success: #22c55e;
    --ml-warning: #f59e0b;
    --ml-danger: #ef4444;
    --ml-info: #3b82f6;
    color: var(--ml-text);
}

.my-leaves-page .hero-card,
.my-leaves-page .stat-card,
.my-leaves-page .table-card,
.my-leaves-page .empty-card {
    border: 1px solid var(--ml-border);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
}

.my-leaves-page .hero-card {
    background: linear-gradient(135deg, var(--ml-accent), #4d4764 55%, #383b55 100%);
    border-radius: 24px;
    padding: 28px;
    position: relative;
    overflow: hidden;
}

.my-leaves-page .hero-card::before,
.my-leaves-page .hero-card::after {
    content: "";
    position: absolute;
    border-radius: 999px;
    background: rgba(255,255,255,0.06);
    pointer-events: none;
}

.my-leaves-page .hero-card::before {
    width: 220px;
    height: 220px;
    top: -90px;
    right: -40px;
}

.my-leaves-page .hero-card::after {
    width: 140px;
    height: 140px;
    bottom: -50px;
    right: 120px;
}

.my-leaves-page .hero-grid {
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.my-leaves-page .page-badge {
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

.my-leaves-page .hero-title {
    margin: 16px 0 8px;
    font-size: 34px;
    line-height: 1.15;
    font-weight: 800;
    color: #fff;
}

.my-leaves-page .hero-subtitle {
    margin: 0;
    color: rgba(255,255,255,0.82);
    font-size: 14px;
    line-height: 1.7;
    max-width: 720px;
}

.my-leaves-page .hero-side {
    background: rgba(17,24,39,0.24);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 22px;
    padding: 20px;
    align-self: stretch;
}

.my-leaves-page .hero-side-label {
    color: rgba(255,255,255,0.72);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: 8px;
}

.my-leaves-page .hero-side-value {
    font-size: 38px;
    line-height: 1;
    font-weight: 800;
    color: #fff;
}

.my-leaves-page .hero-side-note {
    margin-top: 10px;
    color: rgba(255,255,255,0.78);
    font-size: 13px;
    line-height: 1.6;
}

.my-leaves-page .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-top: 22px;
}

.my-leaves-page .stat-card {
    background: var(--ml-card);
    border-radius: 22px;
    padding: 22px;
    min-height: 145px;
    position: relative;
    overflow: hidden;
}

.my-leaves-page .stat-card::after {
    content: "";
    position: absolute;
    width: 92px;
    height: 92px;
    border-radius: 50%;
    top: -28px;
    right: -24px;
    opacity: .16;
}

.my-leaves-page .stat-total::after { background: #a855f7; }
.my-leaves-page .stat-approved::after { background: var(--ml-success); }
.my-leaves-page .stat-pending::after { background: var(--ml-warning); }
.my-leaves-page .stat-rejected::after { background: var(--ml-danger); }

.my-leaves-page .stat-label {
    color: var(--ml-muted);
    font-size: 13px;
    margin-bottom: 18px;
}

.my-leaves-page .stat-value {
    font-size: 36px;
    line-height: 1;
    font-weight: 800;
    color: #fff;
}

.my-leaves-page .stat-foot {
    margin-top: 14px;
    color: #cbd5e1;
    font-size: 13px;
}

.my-leaves-page .table-card,
.my-leaves-page .empty-card {
    background: var(--ml-card-2);
    border-radius: 24px;
    padding: 24px;
    margin-top: 24px;
}

.my-leaves-page .panel-title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: #fff;
}

.my-leaves-page .panel-subtitle {
    margin-top: 6px;
    color: var(--ml-muted);
    font-size: 13px;
}

.my-leaves-page .table-wrap {
    width: 100%;
    overflow-x: auto;
    margin-top: 18px;
}

.my-leaves-page table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
    min-width: 980px;
}

.my-leaves-page thead th {
    text-align: left;
    color: var(--ml-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 0 14px 6px;
    font-weight: 700;
}

.my-leaves-page tbody tr {
    background: var(--ml-card);
}

.my-leaves-page tbody td {
    padding: 16px 14px;
    font-size: 13px;
    color: #e5e7eb;
    vertical-align: top;
    border-top: 1px solid var(--ml-border);
    border-bottom: 1px solid var(--ml-border);
}

.my-leaves-page tbody td:first-child {
    border-left: 1px solid var(--ml-border);
    border-top-left-radius: 16px;
    border-bottom-left-radius: 16px;
}

.my-leaves-page tbody td:last-child {
    border-right: 1px solid var(--ml-border);
    border-top-right-radius: 16px;
    border-bottom-right-radius: 16px;
}

.my-leaves-page .type-badge,
.my-leaves-page .status-badge,
.my-leaves-page .file-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.my-leaves-page .type-sick {
    background: rgba(59,130,246,0.14);
    color: #bfdbfe;
    border: 1px solid rgba(59,130,246,0.32);
}

.my-leaves-page .type-incentive {
    background: rgba(34,197,94,0.14);
    color: #bbf7d0;
    border: 1px solid rgba(34,197,94,0.32);
}

.my-leaves-page .type-emergency {
    background: rgba(245,158,11,0.14);
    color: #fde68a;
    border: 1px solid rgba(245,158,11,0.32);
}

.my-leaves-page .type-default {
    background: rgba(156,163,175,0.14);
    color: #e5e7eb;
    border: 1px solid rgba(156,163,175,0.24);
}

.my-leaves-page .status-approved {
    background: rgba(34,197,94,0.14);
    color: #bbf7d0;
    border: 1px solid rgba(34,197,94,0.32);
}

.my-leaves-page .status-pending {
    background: rgba(245,158,11,0.14);
    color: #fde68a;
    border: 1px solid rgba(245,158,11,0.32);
}

.my-leaves-page .status-rejected {
    background: rgba(239,68,68,0.14);
    color: #fecaca;
    border: 1px solid rgba(239,68,68,0.32);
}

.my-leaves-page .status-default {
    background: rgba(156,163,175,0.14);
    color: #e5e7eb;
    border: 1px solid rgba(156,163,175,0.24);
}

.my-leaves-page .file-badge {
    background: rgba(111,100,134,0.22);
    color: #ddd6fe;
    border: 1px solid rgba(139,127,176,0.35);
}

.my-leaves-page .reason-box {
    color: #d1d5db;
    line-height: 1.65;
    max-width: 300px;
    word-break: break-word;
}

.my-leaves-page .empty-card {
    text-align: center;
    padding: 42px 24px;
}

.my-leaves-page .empty-icon {
    width: 72px;
    height: 72px;
    border-radius: 22px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(111,100,134,0.18);
    border: 1px solid rgba(139,127,176,0.28);
    color: #ddd6fe;
    font-size: 26px;
}

.my-leaves-page .empty-title {
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
}

.my-leaves-page .empty-text {
    color: var(--ml-muted);
    font-size: 14px;
    line-height: 1.7;
    max-width: 560px;
    margin: 0 auto;
}

.my-leaves-page .apply-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 18px;
    padding: 13px 18px;
    border-radius: 16px;
    background: linear-gradient(135deg, #6f6486 0%, #5d5677 100%);
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    border: 0;
}

@media (max-width: 1100px) {
    .my-leaves-page .hero-grid {
        grid-template-columns: 1fr;
    }

    .my-leaves-page .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .my-leaves-page .hero-title {
        font-size: 28px;
    }

    .my-leaves-page .stats-grid {
        grid-template-columns: 1fr;
    }

    .my-leaves-page .hero-card,
    .my-leaves-page .stat-card,
    .my-leaves-page .table-card,
    .my-leaves-page .empty-card {
        border-radius: 20px;
    }
}
</style>

<div class="my-leaves-page">
    <section class="hero-card">
        <div class="hero-grid">
            <div>
                <span class="page-badge">Employee Leave History</span>
                <h1 class="hero-title">My Leave Records</h1>
                <p class="hero-subtitle">
                    View all of your submitted leave requests, check the current approval stage,
                    and review the details of each application in one place.
                </p>
            </div>

            <div class="hero-side">
                <div class="hero-side-label">Total Requests</div>
                <div class="hero-side-value"><?php echo (int)$counts['total']; ?></div>
                <div class="hero-side-note">
                    This includes pending, approved, received, and rejected leave applications linked to your account.
                </div>
            </div>
        </div>
    </section>

    <section class="stats-grid">
        <div class="stat-card stat-total">
            <div class="stat-label">All Requests</div>
            <div class="stat-value"><?php echo (int)$counts['total']; ?></div>
            <div class="stat-foot">Every leave application you have submitted.</div>
        </div>

        <div class="stat-card stat-approved">
            <div class="stat-label">Approved / Received</div>
            <div class="stat-value"><?php echo (int)$counts['approved']; ?></div>
            <div class="stat-foot">Requests that already passed approval or were received.</div>
        </div>

        <div class="stat-card stat-pending">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo (int)$counts['pending']; ?></div>
            <div class="stat-foot">Requests that are still under review.</div>
        </div>

        <div class="stat-card stat-rejected">
            <div class="stat-label">Rejected</div>
            <div class="stat-value"><?php echo (int)$counts['rejected']; ?></div>
            <div class="stat-foot">Requests that were not approved in the workflow.</div>
        </div>
    </section>

    <?php if (count($leaveRows) === 0): ?>
        <section class="empty-card">
            <div class="empty-icon">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <div class="empty-title">No Leave Records Yet</div>
            <p class="empty-text">
                You have not submitted any leave request yet. Once you create one, it will appear here with its dates,
                reason, number of days, and current approval status.
            </p>
            <a href="leave_request.php" class="apply-btn">Create Leave Request</a>
        </section>
    <?php else: ?>
        <section class="table-card">
            <h2 class="panel-title">Leave Request History</h2>
            <div class="panel-subtitle">Latest requests appear first.</div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Leave Type</th>
                            <th>Date Range</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Medical Cert</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveRows as $index => $row): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td>
                                    <span class="type-badge <?php echo leave_type_class($row['leave_type'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(leave_type_label($row['leave_type'] ?? '')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $from = !empty($row['date_from']) ? date('M d, Y', strtotime($row['date_from'])) : '—';
                                        $to = !empty($row['date_to']) ? date('M d, Y', strtotime($row['date_to'])) : '—';
                                        echo htmlspecialchars($from . " - " . $to);
                                    ?>
                                </td>
                                <td><?php echo (int)($row['days'] ?? 0); ?></td>
                                <td>
                                    <div class="reason-box">
                                        <?php echo nl2br(htmlspecialchars((string)($row['reason'] ?? ''))); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['med_cert_file'])): ?>
                                        <span class="file-badge">Attached</span>
                                    <?php else: ?>
                                        <span class="file-badge" style="opacity:.75;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo leave_status_class($row['status'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(leave_status_label($row['status'] ?? '')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        if (!empty($row['created_at']) && strtotime($row['created_at'])) {
                                            echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at'])));
                                        } else {
                                            echo '—';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/employee_layout.php";
