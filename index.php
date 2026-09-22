<?php
/**
 * Commercial Enterprise Gym Management + Touch POS System Router & Entrypoint
 */

require_once __DIR__ . '/config/database.php';

// Handle Login Authentication
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            logAuditAction($user['id'], 'User Login', 'AUTH', null, ['email' => $email]);
            header("Location: index.php");
            exit;
        } else {
            $loginError = "Invalid credentials. Default: admin@fitzone.com / admin123";
        }
    }
}

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    'dashboard', 'pos', 'members', 'trials', 'member_profile', 'memberships', 'attendance', 
    'trainers', 'pt', 'pool', 'workout', 'diet', 'progress', 'inventory', 'expenses', 
    'staff', 'payroll', 'payments', 'reports', 'communication', 'devices', 
    'audit_logs', 'settings', 'payslip', 'marketing', 'receipt'
];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

$accessRestricted = false;
if (isset($_SESSION['user_id']) && !hasPageAccess($page)) {
    $accessRestricted = true;
    $attemptedPage = $page;
    $page = 'dashboard';
}

$primaryColor = getSetting('primary_color', '#2563EB');
$gymName = getSetting('gym_name', 'THE CLUB 777®');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="<?= htmlspecialchars($primaryColor) ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="FITZONE GYM">
  <meta name="description" content="Commercial Enterprise Gym Management System with Touch POS, Members & Offline Support">
  <title><?= htmlspecialchars($gymName) ?></title>

  <!-- PWA Icons & Manifest -->
  <link rel="icon" type="image/png" href="logo.png">
  <link rel="apple-touch-icon" href="logo.png">
  <link rel="manifest" href="manifest.json">

  <!-- Offline-First Asynchronous Fonts (Zero blocking when offline) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" media="print" onload="this.media='all'">

  <!-- CSS Stylesheets -->
  <link rel="stylesheet" href="assets/css/variables.css?v=<?= file_exists(__DIR__ . '/assets/css/variables.css') ? filemtime(__DIR__ . '/assets/css/variables.css') : time() ?>">
  <link rel="stylesheet" href="assets/css/main.css?v=<?= file_exists(__DIR__ . '/assets/css/main.css') ? filemtime(__DIR__ . '/assets/css/main.css') : time() ?>">
  <link rel="stylesheet" href="assets/css/pos.css?v=<?= file_exists(__DIR__ . '/assets/css/pos.css') ? filemtime(__DIR__ . '/assets/css/pos.css') : time() ?>">
  <link rel="stylesheet" href="assets/css/print.css?v=<?= file_exists(__DIR__ . '/assets/css/print.css') ? filemtime(__DIR__ . '/assets/css/print.css') : time() ?>">
  <link rel="stylesheet" href="assets/css/enhanced.css?v=<?= file_exists(__DIR__ . '/assets/css/enhanced.css') ? filemtime(__DIR__ . '/assets/css/enhanced.css') : time() ?>">

  <style>
    :root {
      --primary: <?= htmlspecialchars($primaryColor) ?>;
    }
  </style>
</head>
<body>

<?php if (!isset($_SESSION['user_id']) && $page === 'receipt'): ?>
  <!-- Public Customer Fee Receipt View (No Login Required) -->
  <div style="min-height:100vh; background:#F8FAFC; padding:1.5rem 1rem;">
    <?php include __DIR__ . '/views/receipt_view.php'; ?>
  </div>

<?php elseif (!isset($_SESSION['user_id'])): ?>
  <!-- Enterprise Branded Login Screen -->
  <div style="min-height:100vh; display:flex; align-items:center; justify-content:center; background:radial-gradient(circle at 50% 20%, #1E293B 0%, #0F172A 100%); padding:1.25rem;">
    <form method="POST" class="card" style="width:100%; max-width:440px; padding:2.5rem; background:rgba(30, 41, 59, 0.85); backdrop-filter:blur(16px); border:1px solid rgba(255,255,255,0.12); border-radius:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
      <input type="hidden" name="login_action" value="1">
      <div style="text-align:center; margin-bottom:1.75rem;">
        <div style="display:flex; justify-content:center; margin-bottom:0.85rem;">
          <img src="logo.png" alt="<?= htmlspecialchars($gymName) ?>" style="height:76px; width:auto; max-width:180px; object-fit:contain; filter:drop-shadow(0 6px 14px rgba(0,0,0,0.35));" onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; box-shadow:0 8px 24px rgba(245,158,11,0.4);\'>👑</div>';">
        </div>
        <h1 style="font-size:1.75rem; font-weight:900; color:#FFFFFF; margin:0; letter-spacing:-0.03em;"><?= htmlspecialchars($gymName) ?></h1>
        <p style="font-size:0.85rem; color:#94A3B8; margin-top:0.25rem; font-weight:600;">Enterprise POS &amp; Gym Management System</p>
      </div>

      <?php if (!empty($loginError)): ?>
        <div style="padding:0.75rem 1rem; background:rgba(239,68,68,0.15); color:#FCA5A5; border:1px solid rgba(239,68,68,0.3); border-radius:12px; font-size:0.85rem; margin-bottom:1.25rem; text-align:center; font-weight:700;">
          <?= htmlspecialchars($loginError) ?>
        </div>
      <?php endif; ?>

      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label" style="color:#CBD5E1; font-weight:700; font-size:0.85rem;">Email Address</label>
        <input type="email" name="email" id="loginEmailInput" class="form-control" value="admin@theclub777.com" required placeholder="admin@theclub777.com" style="background:#0F172A; border-color:#334155; color:#F8FAFC; border-radius:12px; padding:0.75rem 1rem;">
      </div>

      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" style="color:#CBD5E1; font-weight:700; font-size:0.85rem;">Password</label>
        <input type="password" name="password" id="loginPasswordInput" class="form-control" value="admin123" required placeholder="••••••••" style="background:#0F172A; border-color:#334155; color:#F8FAFC; border-radius:12px; padding:0.75rem 1rem;">
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="border-radius:12px; font-weight:900; letter-spacing:0.02em; padding:0.85rem; box-shadow:0 4px 16px rgba(2,132,199,0.35);">
        ⚡ LOGIN TO SYSTEM
      </button>


    </form>
  </div>

<?php else: ?>
  <!-- Authenticated Application Shell -->
  <?php include __DIR__ . '/views/header.php'; ?>

  <!-- Offline Status Bar -->
  <div id="offlineStatusBar">
    <span>📡</span>
    <span>You are working <strong>Offline</strong> — Transactions are saved locally and will sync when connection is restored.</span>
    <span id="offlinePendingCount" style="margin-left:0.5rem; background:rgba(255,255,255,0.2); padding:0.1rem 0.5rem; border-radius:99px; font-size:0.78rem;"></span>
  </div>

  <!-- Sidebar Backdrop (mobile) -->
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

  <div class="app-body">
    <?php include __DIR__ . '/views/sidebar.php'; ?>

    <main class="app-main" id="appMain">
      <?php if (!empty($accessRestricted)): ?>
        <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:0.85rem 1.25rem; border-radius:12px; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; font-size:0.88rem; font-weight:700; box-shadow:var(--shadow-card);">
          <div style="display:flex; align-items:center; gap:0.6rem;">
            <span style="font-size:1.2rem;">🔒</span>
            <span>Access Restricted: Your user account (<strong><?= htmlspecialchars(getRoleDisplayName($_SESSION['user_role'] ?? 'Staff')) ?></strong>) does not have permission to access the <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $attemptedPage ?? 'requested'))) ?></strong> module.</span>
          </div>
          <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#991B1B; font-weight:900; font-size:1.1rem; cursor:pointer;" aria-label="Dismiss">✕</button>
        </div>
      <?php endif; ?>
      <?php 
        $viewPath = __DIR__ . "/views/{$page}_view.php";
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            include __DIR__ . "/views/dashboard_view.php";
        }
      ?>
    </main>
  </div>

  <?php include __DIR__ . '/views/bottom_nav.php'; ?>

  <!-- Core JavaScript Framework -->
  <script src="assets/js/app.js?v=<?= file_exists(__DIR__ . '/assets/js/app.js') ? filemtime(__DIR__ . '/assets/js/app.js') : time() ?>"></script>
  <script src="assets/js/pos.js?v=<?= file_exists(__DIR__ . '/assets/js/pos.js') ? filemtime(__DIR__ . '/assets/js/pos.js') : time() ?>"></script>
  <script src="assets/js/offline.js?v=<?= file_exists(__DIR__ . '/assets/js/offline.js') ? filemtime(__DIR__ . '/assets/js/offline.js') : time() ?>"></script>
  <script src="assets/js/scanner.js?v=<?= file_exists(__DIR__ . '/assets/js/scanner.js') ? filemtime(__DIR__ . '/assets/js/scanner.js') : time() ?>"></script>

  <!-- PWA Service Worker + Install Prompt -->
  <script>
  (function() {
    // ── Register Service Worker ──────────────────────────────
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js', { scope: '/GYM/' })
          .then(reg => { reg.update(); })
          .catch(() => {});
      });
    }

    // ── Offline/Online Banner ────────────────────────────────
    const bar = document.getElementById('offlineStatusBar');

    function updateOfflineBanner() {
      if (!navigator.onLine) {
        bar.classList.add('visible');
        if (window.OfflineManager) {
          window.OfflineManager.countPending(n => {
            const el = document.getElementById('offlinePendingCount');
            if (el) el.textContent = n > 0 ? n + ' pending' : '';
          });
        }
      } else {
        bar.classList.remove('visible');
      }
    }

    window.addEventListener('online',  updateOfflineBanner);
    window.addEventListener('offline', updateOfflineBanner);
    updateOfflineBanner();

    // ── Sidebar close helper ─────────────────────────────────
    window.closeSidebar = function() {
      const sidebar = document.getElementById('appSidebar');
      const backdrop = document.getElementById('sidebarBackdrop');
      if (sidebar) sidebar.classList.remove('open');
      if (backdrop) backdrop.classList.remove('visible');
    };

    // Patch toggleSidebar to also show backdrop
    document.addEventListener('DOMContentLoaded', () => {
      const toggleBtn = document.getElementById('toggleSidebarBtn');
      const sidebar   = document.getElementById('appSidebar');
      const backdrop  = document.getElementById('sidebarBackdrop');
      if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
          const isMobile = window.innerWidth <= 768;
          if (isMobile) {
            sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('visible');
          } else {
            sidebar.classList.toggle('collapsed');
          }
        });
      }
    });

  })();
  </script>
<?php endif; ?>

</body>
</html>
