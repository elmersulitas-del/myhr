<?php
// employee_layout.php
// Requires: $user, $pageTitle, $active, $content
if (!isset($pageTitle)) $pageTitle = "Employee";
if (!isset($active)) $active = "home";
if (!isset($content)) $content = "";

function emp_nav_item($key, $active) {
  return $key === $active ? "active" : "";
}

$departmentName = trim((string)($user['department'] ?? 'No Department'));
$fullName = trim((string)($user['full_name'] ?? 'Employee User'));
$email = trim((string)($user['email'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <style>
    :root {
      --dk-gray-100: #F3F4F6;
      --dk-gray-200: #E5E7EB;
      --dk-gray-300: #D1D5DB;
      --dk-gray-400: #9CA3AF;
      --dk-gray-500: #6B7280;
      --dk-gray-600: #4B5563;
      --dk-gray-700: #374151;
      --dk-gray-800: #1F2937;
      --dk-gray-900: #111827;
      --dk-dark-bg: #313348;
      --dk-darker-bg: #2a2b3d;
      --navbar-bg-color: #6f6486;
      --sidebar-bg-color: #252636;
      --sidebar-width: 270px;
      --card-radius: 22px;
      --border-color: #3c3f58;
      --card-shadow: 0 16px 35px rgba(0, 0, 0, 0.20);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html, body {
      min-height: 100%;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--dk-darker-bg);
      color: var(--dk-gray-200);
      font-size: .94rem;
    }

    a {
      text-decoration: none;
    }

    .layout-shell {
      min-height: 100vh;
      background:
        radial-gradient(circle at top right, rgba(111, 100, 134, 0.18), transparent 22%),
        radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.08), transparent 25%),
        var(--dk-darker-bg);
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--sidebar-bg-color);
      border-right: 1px solid #2f3145;
      transition: transform .3s ease-in-out;
      z-index: 1200;
    }

    .sidebar-scroll {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow-y: auto;
    }

    .sidebar-header {
      padding: 26px 22px 20px;
      border-bottom: 1px solid #2f3145;
      flex-shrink: 0;
    }

    .brand-badge {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      background: linear-gradient(135deg, #8b7fb0 0%, #6f6486 55%, #4d4764 100%);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 20px;
      box-shadow: 0 10px 25px rgba(111, 100, 134, 0.35);
      flex-shrink: 0;
    }

    .brand-wrap {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-title {
      font-size: 18px;
      font-weight: 800;
      color: #fff;
      margin: 0 0 4px;
      letter-spacing: .01em;
    }

    .brand-subtitle {
      margin: 0;
      font-size: 12px;
      color: var(--dk-gray-400);
      line-height: 1.55;
    }

    .sidebar-search {
      padding: 18px 22px 12px;
      flex-shrink: 0;
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      width: 100%;
      border: 1px solid #34374d;
      background: #2c2e43;
      border-radius: 16px;
      height: 46px;
      color: #fff;
      padding: 0 44px 0 16px;
      outline: none;
      transition: border-color .2s ease, box-shadow .2s ease;
    }

    .search-box input::placeholder {
      color: #8f97ac;
    }

    .search-box input:focus {
      border-color: #8b7fb0;
      box-shadow: 0 0 0 4px rgba(139, 127, 176, 0.15);
    }

    .search-box i {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #8f97ac;
      font-size: 14px;
    }

    .sidebar-nav {
      padding: 10px 14px 22px;
      flex: 1;
    }

    .nav-label {
      font-size: 11px;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: #7f879c;
      font-weight: 700;
      padding: 10px 12px 12px;
    }

    .nav-list {
      list-style: none;
    }

    .nav-list li + li {
      margin-top: 6px;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 14px;
      border-radius: 16px;
      padding: 14px 14px;
      color: var(--dk-gray-300);
      font-size: 14px;
      font-weight: 600;
      transition: all .22s ease;
      border: 1px solid transparent;
    }

    .nav-link:hover {
      background: #313348;
      color: #fff;
      border-color: #404462;
      transform: translateX(2px);
    }

    .nav-link.active {
      background: linear-gradient(135deg, #6f6486 0%, #5d5677 100%);
      color: #fff;
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 12px 25px rgba(0, 0, 0, 0.18);
    }

    .nav-link i {
      width: 20px;
      text-align: center;
      font-size: 15px;
      flex-shrink: 0;
    }

    .sidebar-footer-wrap {
      margin-top: auto;
      padding: 0 14px 22px;
      flex-shrink: 0;
    }

    .sidebar-footer {
      padding: 18px;
      border-radius: 20px;
      background: #2d3046;
      border: 1px solid #3b405d;
      box-shadow: var(--card-shadow);
    }

    .user-name {
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 4px;
    }

    .user-email {
      font-size: 12px;
      color: var(--dk-gray-400);
      line-height: 1.55;
      word-break: break-word;
    }

    .user-department {
      display: inline-flex;
      margin-top: 12px;
      padding: 7px 12px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      background: rgba(111, 100, 134, 0.20);
      color: #ddd6fe;
      border: 1px solid rgba(139, 127, 176, 0.30);
    }

    .logout-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 16px;
      height: 44px;
      border-radius: 14px;
      background: transparent;
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      border: 1px solid #4a506f;
      transition: all .2s ease;
    }

    .logout-btn:hover {
      background: #363a54;
      border-color: #657096;
    }

    .main-panel {
      margin-left: var(--sidebar-width);
      min-height: 100vh;
      transition: margin-left .3s ease-in-out;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: rgba(111, 100, 134, 0.92);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.16);
    }

    .topbar-inner {
      min-height: 74px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 0 28px;
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 14px;
      min-width: 0;
    }

    .menu-toggle {
      display: none;
      width: 44px;
      height: 44px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.10);
      background: rgba(255,255,255,0.08);
      color: #fff;
      cursor: pointer;
      transition: .2s ease;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .menu-toggle:hover {
      background: rgba(255,255,255,0.14);
    }

    .topbar-title {
      font-size: 20px;
      font-weight: 800;
      color: #fff;
      margin: 0;
      line-height: 1.2;
    }

    .topbar-subtitle {
      margin-top: 4px;
      color: rgba(255,255,255,0.78);
      font-size: 13px;
      line-height: 1.5;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }

    .top-chip {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 14px;
      background: rgba(255,255,255,0.10);
      border: 1px solid rgba(255,255,255,0.08);
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
    }

    .top-chip i {
      font-size: 13px;
    }

    .mobile-logout {
      display: none;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px 16px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.12);
      color: #fff;
      background: rgba(255,255,255,0.08);
      font-size: 13px;
      font-weight: 700;
    }

    .page-content {
      padding: 28px;
    }

    .content-shell {
      max-width: 1400px;
      margin: 0 auto;
    }

    .mobile-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.48);
      z-index: 1100;
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s ease;
    }

    .mobile-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }

    @media (max-width: 991px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.mobile-open {
        transform: translateX(0);
      }

      .main-panel {
        margin-left: 0;
      }

      .menu-toggle {
        display: inline-flex;
      }

      .mobile-overlay {
        display: block;
      }
    }

    @media (max-width: 767px) {
      .topbar-inner,
      .page-content {
        padding-left: 16px;
        padding-right: 16px;
      }

      .topbar-inner {
        min-height: 70px;
      }

      .topbar-title {
        font-size: 18px;
      }

      .topbar-subtitle {
        font-size: 12px;
      }

      .top-chip.desktop-only {
        display: none;
      }

      .mobile-logout {
        display: inline-flex;
      }

      .sidebar {
        width: 286px;
      }
    }

    @media (max-width: 480px) {
      .sidebar {
        width: 100%;
        max-width: 320px;
      }

      .topbar-right {
        gap: 8px;
      }

      .mobile-logout span {
        display: none;
      }
    }

    ::-webkit-scrollbar {
      width: 7px;
      height: 7px;
    }

    ::-webkit-scrollbar-thumb {
      background: #454963;
      border-radius: 20px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }
  </style>
</head>

<body>
  <div class="layout-shell">
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <aside class="sidebar" id="employeeSidebar">
      <div class="sidebar-scroll">
        <div class="sidebar-header">
          <div class="brand-wrap">
            <div class="brand-badge">
              <i class="fa-solid fa-user-large"></i>
            </div>
            <div>
              <h1 class="brand-title">Employee Panel</h1>
              <p class="brand-subtitle">MyHR self-service portal for employee requests, updates, and records.</p>
            </div>
          </div>
        </div>

        <div class="sidebar-search">
          <div class="search-box">
            <input type="text" placeholder="Search menu..." id="menuSearch">
            <i class="fa-solid fa-magnifying-glass"></i>
          </div>
        </div>

        <div class="sidebar-nav">
          <div class="nav-label">Main Navigation</div>
          <ul class="nav-list" id="employeeMenuList">
            <li data-label="home dashboard employee">
              <a href="dashboard_employee.php" class="nav-link <?php echo emp_nav_item('home', $active); ?>">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
              </a>
            </li>

            <li data-label="leave request apply request leave form">
              <a href="leave_request.php" class="nav-link <?php echo emp_nav_item('request', $active); ?>">
                <i class="fa-solid fa-file-signature"></i>
                <span>Request Leave</span>
              </a>
            </li>

            <li data-label="records my leaves leave records history">
              <a href="my_leaves.php" class="nav-link <?php echo emp_nav_item('records', $active); ?>">
                <i class="fa-solid fa-folder-open"></i>
                <span>My Leave Records</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="sidebar-footer-wrap">
          <div class="sidebar-footer">
            <div class="user-name"><?php echo htmlspecialchars($fullName); ?></div>
            <?php if ($email !== '') { ?>
              <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
            <?php } ?>
            <div class="user-department"><?php echo htmlspecialchars($departmentName); ?></div>

            <a href="logout.php" class="logout-btn">
              <i class="fa-solid fa-right-from-bracket"></i>
              <span>Logout</span>
            </a>
          </div>
        </div>
      </div>
    </aside>

    <main class="main-panel">
      <header class="topbar">
        <div class="topbar-inner">
          <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open menu">
              <i class="fa-solid fa-bars"></i>
            </button>

            <div>
              <h2 class="topbar-title">Employee Dashboard</h2>
              <div class="topbar-subtitle"><?php echo htmlspecialchars($pageTitle); ?></div>
            </div>
          </div>

          <div class="topbar-right">
            <div class="top-chip desktop-only">
              <i class="fa-regular fa-building"></i>
              <span><?php echo htmlspecialchars($departmentName); ?></span>
            </div>

            <div class="top-chip desktop-only">
              <i class="fa-regular fa-user"></i>
              <span><?php echo htmlspecialchars($fullName); ?></span>
            </div>

            <a href="logout.php" class="mobile-logout">
              <i class="fa-solid fa-right-from-bracket"></i>
              <span>Logout</span>
            </a>
          </div>
        </div>
      </header>

      <div class="page-content">
        <div class="content-shell">
          <?php echo $content; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    (function () {
      const sidebar = document.getElementById('employeeSidebar');
      const overlay = document.getElementById('mobileOverlay');
      const toggleBtn = document.getElementById('menuToggle');
      const searchInput = document.getElementById('menuSearch');
      const menuItems = document.querySelectorAll('#employeeMenuList li');

      function openSidebar() {
        if (window.innerWidth <= 991) {
          sidebar.classList.add('mobile-open');
          overlay.classList.add('active');
        }
      }

      function closeSidebar() {
        if (window.innerWidth <= 991) {
          sidebar.classList.remove('mobile-open');
          overlay.classList.remove('active');
        }
      }

      if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
          if (sidebar.classList.contains('mobile-open')) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });
      }

      if (overlay) {
        overlay.addEventListener('click', closeSidebar);
      }

      window.addEventListener('resize', function () {
        if (window.innerWidth > 991) {
          sidebar.classList.remove('mobile-open');
          overlay.classList.remove('active');
        }
      });

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          const keyword = this.value.toLowerCase().trim();

          menuItems.forEach(function (item) {
            const label = (item.getAttribute('data-label') || '').toLowerCase();
            item.style.display = label.includes(keyword) ? '' : 'none';
          });
        });
      }
    })();
  </script>
</body>
</html>
