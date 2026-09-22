<!-- Top Header Component -->
<header class="app-header">
  
  <!-- Left: Sidebar Toggle & Brand Area -->
  <div class="header-left">
    <button type="button" class="toggle-sidebar-btn" id="toggleSidebarBtn" title="Toggle Navigation (Menu)" aria-label="Toggle Sidebar Navigation">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    
    <a href="index.php" class="brand-logo" title="THE CLUB 777® &bull; GYM &bull; SWIM &bull; SPA">
      <img src="logo.png" alt="Logo" class="brand-logo-img" style="height:36px; width:auto; max-width:44px; object-fit:contain; border-radius:8px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
      <span class="brand-logo-icon" aria-hidden="true" style="display:none; background:linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color:#fff; border-radius:10px; width:34px; height:34px; align-items:center; justify-content:center; font-size:1.2rem; box-shadow:0 2px 8px rgba(245,158,11,0.4);">👑</span>
      <div class="brand-text-wrap">
        <div class="brand-title-line">
          <span class="brand-gym-title" style="color:#B45309; font-weight:900;">THE CLUB</span>
          <span class="brand-name" style="color:#D97706; font-weight:900;">777®</span>
        </div>
        <span class="brand-badge" style="background:#FEF3C7; color:#92400E; font-weight:800; font-size:0.65rem;">GYM &bull; SWIM &bull; SPA</span>
      </div>
    </a>
  </div>

  <!-- Center: Global Quick Member Search -->
  <div class="header-search">
    <span class="search-icon" aria-hidden="true">🔍</span>
    <input type="text" id="globalHeaderSearch" placeholder="Search member name, phone or code (F2)..."
      aria-label="Global Member Search"
      onfocus="if(window.location.search.indexOf('pos')===-1) window.location.href='index.php?page=pos&focus=search'">
    <kbd class="header-search-kbd" title="Press F2 to Search">F2</kbd>
  </div>

  <!-- Right: Operational Utilities, IST Clock, Sync & User Menu -->
  <div class="header-right">
    
    <!-- 1. Mode Switcher (POS Mode vs Admin Management Mode) -->
    <?php $page = $_GET['page'] ?? 'dashboard'; ?>
    <?php if ($page === 'pos'): ?>
      <a href="index.php?page=dashboard" class="header-mode-btn mode-admin" title="Switch to Full Management Dashboard">
        <span aria-hidden="true">📊</span> <span>Admin Mode</span>
      </a>
    <?php else: ?>
      <a href="index.php?page=pos" class="header-mode-btn mode-pos" title="Switch to Touch POS Terminal">
        <span aria-hidden="true">🛒</span> <span>POS Mode</span>
      </a>
    <?php endif; ?>

    <!-- 2. Indian Standard Time (IST) Live Clock Badge -->
    <div class="header-clock-badge" title="Indian Standard Time (IST / Asia/Kolkata +05:30)">
      <span class="clock-flag" aria-hidden="true">🇮🇳</span>
      <span id="headerLiveIstClock" class="clock-text font-mono"><?= date('d M Y, h:i:s A') ?> IST</span>
    </div>

    <!-- 3. Network Sync Status Badge -->
    <div class="sync-status-badge" id="globalSyncBadge" title="Network Connection Status &bull; Click to Sync" onclick="if(window.OfflineManager)window.OfflineManager.syncPendingData()" role="button" tabindex="0">
      <span class="status-dot"></span>
      <span class="sync-label">Online</span>
    </div>

    <!-- 4. User Profile Area & Logout -->
    <div class="header-user-section">
      <div class="user-profile-menu" title="Logged in as <?= htmlspecialchars($_SESSION['user_name'] ?? 'Receptionist') ?>">
        <div class="user-avatar" aria-hidden="true">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'R', 0, 1)) ?>
        </div>
        <div class="user-info">
          <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Receptionist') ?></span>
          <span class="user-role"><?= htmlspecialchars(getRoleDisplayName($_SESSION['user_role'] ?? 'Staff')) ?></span>
        </div>
      </div>

      <button type="button" onclick="handleLogout()" class="header-logout-btn" title="Sign out of THE CLUB 777® System" aria-label="Sign Out">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        <span class="logout-label">Logout</span>
      </button>
    </div>

  </div>
</header>

<!-- ========================================================= -->
<!-- GLOBAL NEW MEMBER REGISTRATION MODAL (F3)                 -->
<!-- ========================================================= -->
<div class="modal" id="newMemberModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('newMemberModal')" aria-hidden="true"></div>
  <div class="card new-member-modal-card" role="dialog" aria-labelledby="newMemberModalTitle" aria-modal="true">
    
    <!-- Modal Header -->
    <div class="new-member-modal-header">
      <div class="modal-header-info">
        <div class="modal-title-row">
          <span class="modal-title-icon" aria-hidden="true">👤</span>
          <h3 id="newMemberModalTitle" class="modal-title">Add New Member</h3>
          <kbd class="modal-shortcut-badge" title="Shortcut key">F3</kbd>
        </div>
        <p class="modal-subtitle">Quick Registration &bull; Register a new gym member in seconds.</p>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('newMemberModal')" aria-label="Close dialog">✕</button>
    </div>

    <!-- Registration Form -->
    <form id="newMemberForm" onsubmit="event.preventDefault(); submitQuickMember();" class="new-member-form">
      
      <!-- Section 1: Basic Information -->
      <div class="form-section-title">1. Basic Information</div>
      <div class="new-member-grid">
        <div class="form-group grid-full">
          <label class="form-label" for="nmName">Full Name <span class="required-star">*</span></label>
          <input type="text" id="nmName" class="form-control" required placeholder="e.g. Vikram Sharma" autocomplete="name">
        </div>

        <div class="form-group">
          <label class="form-label" for="nmPhone">Mobile Number <span class="required-star">*</span></label>
          <input type="tel" id="nmPhone" class="form-control font-mono" required placeholder="e.g. 9876543210" pattern="[0-9]{10}" title="10-digit mobile phone number" autocomplete="tel">
        </div>

        <div class="form-group">
          <label class="form-label" for="nmEmail">Email Address (Optional)</label>
          <input type="email" id="nmEmail" class="form-control" placeholder="e.g. member@gmail.com" autocomplete="email">
        </div>
      </div>

      <!-- Section 2: Personal Information -->
      <div class="form-section-title">2. Personal Details &amp; Emergency Contact</div>
      <div class="new-member-grid">
        <div class="form-group">
          <label class="form-label" for="nmDob">Date of Birth (DOB) 🎂</label>
          <input type="date" id="nmDob" class="form-control font-mono" value="1998-01-15">
        </div>

        <div class="form-group">
          <label class="form-label" for="nmGender">Gender</label>
          <select id="nmGender" class="form-control">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="form-group grid-full">
          <label class="form-label" for="nmEmergency">Emergency Contact Phone</label>
          <input type="tel" id="nmEmergency" class="form-control font-mono" placeholder="e.g. 9876500000">
        </div>
      </div>

      <!-- Section 3: WhatsApp Greeting Notice -->
      <div class="whatsapp-onboard-notice">
        <span class="notice-icon" aria-hidden="true">📱</span>
        <div class="notice-text">
          <strong>Automated Member Onboarding:</strong> Automatic WhatsApp Welcome Greeting &amp; Birthday reminders will be activated instantly upon registration.
        </div>
      </div>

      <!-- Modal Action Buttons -->
      <div class="new-member-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newMemberModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-save-member">
          <span>✓ Save &amp; Register Member</span>
        </button>
      </div>
    </form>
</div>
</div>

<!-- ========================================================= -->
<!-- GLOBAL BIOMETRIC ENROLLMENT MODAL                        -->
<!-- ========================================================= -->
<div class="modal" id="biometricEnrollModal" style="display:none; z-index:1050; position:fixed; inset:0; background:rgba(0,0,0,0.65); align-items:center; justify-content:center; backdrop-filter:blur(3px);">
  <div class="modal-overlay" onclick="closeBiometricEnrollModal()" style="position:absolute; inset:0;" aria-hidden="true"></div>
  <div class="card" style="position:relative; z-index:10; background:var(--bg-surface, #fff); width:92%; max-width:480px; border-radius:18px; padding:1.5rem; box-shadow:var(--shadow-modal, 0 10px 25px rgba(0,0,0,0.2)); border:1px solid var(--border-color, #E5E7EB);">
    
    <!-- Modal Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color, #E5E7EB); padding-bottom:0.85rem; margin-bottom:1rem;">
      <div style="display:flex; align-items:center; gap:0.6rem;">
        <span style="font-size:1.6rem;">📟</span>
        <div>
          <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--text-primary, #111827);">Biometric Gate Enrollment</h3>
          <p style="margin:0; font-size:0.75rem; color:var(--text-secondary, #6B7280);">eSSL X2008 &bull; Gate Machine Live Sync</p>
        </div>
      </div>
      <button type="button" onclick="closeBiometricEnrollModal()" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:var(--text-muted, #9CA3AF);">✕</button>
    </div>

    <!-- Member Info Card -->
    <div style="background:var(--bg-surface-secondary, #F9FAFB); border:1px solid var(--border-color, #E5E7EB); border-radius:12px; padding:0.9rem; margin-bottom:1.1rem; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <div style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:var(--text-muted, #6B7280); letter-spacing:0.04em;">Member Profile</div>
        <div id="bioMemberName" style="font-size:1.05rem; font-weight:900; color:var(--text-primary, #111827); margin-top:2px;">—</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:var(--text-muted, #6B7280); letter-spacing:0.04em;">Machine PIN</div>
        <span id="bioMemberPin" style="display:inline-block; background:#EFF6FF; color:#2563EB; font-weight:900; font-family:var(--font-mono, monospace); font-size:1.1rem; padding:2px 10px; border-radius:8px; border:1px solid #BFDBFE;">—</span>
      </div>
    </div>

    <!-- 5-Minute Window Notice -->
    <div style="background:#FEF3C7; border:1px solid #FCD34D; border-radius:10px; padding:0.75rem 0.9rem; margin-bottom:1.1rem; display:flex; align-items:flex-start; gap:0.6rem;">
      <span style="font-size:1.2rem;">⏱️</span>
      <div style="font-size:0.78rem; color:#92400E; line-height:1.4;">
        <strong>5-Minute Gate Window:</strong> Member profile is auto-synced to the gate terminal. Ask the member to walk to the machine to register their Fingerprint or Face.
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
      <button type="button" id="btnEnrollFp" onclick="sendBiometricCommand('fp')" class="btn btn-primary" style="padding:0.75rem 0.5rem; display:flex; flex-direction:column; align-items:center; gap:0.35rem; font-weight:800; border-radius:12px;">
        <span style="font-size:1.4rem;">👆</span>
        <span style="font-size:0.82rem;">Enroll Fingerprint</span>
      </button>

      <button type="button" id="btnEnrollFace" onclick="sendBiometricCommand('face')" class="btn" style="background:#0284C7; color:#fff; padding:0.75rem 0.5rem; display:flex; flex-direction:column; align-items:center; gap:0.35rem; font-weight:800; border-radius:12px; border:none;">
        <span style="font-size:1.4rem;">👤</span>
        <span style="font-size:0.82rem;">Enroll Face</span>
      </button>
    </div>

    <!-- Live Status Box -->
    <div id="bioStatusBox" style="display:none; padding:0.75rem 0.9rem; border-radius:10px; font-size:0.8rem; margin-bottom:1rem;"></div>

    <!-- Manual Keypad Instructions Accordion -->
    <details style="background:var(--bg-surface-secondary, #F9FAFB); border:1px solid var(--border-color, #E5E7EB); border-radius:10px; padding:0.65rem 0.85rem; font-size:0.76rem; color:var(--text-secondary, #4B5563); margin-bottom:1rem;">
      <summary style="cursor:pointer; font-weight:700; color:var(--text-primary, #111827);">💡 Direct Machine Option (Without Web Click)</summary>
      <div style="margin-top:0.5rem; line-height:1.5;">
        1. Member walks to machine ➡️ Press <strong>M/OK</strong> button on keypad.<br>
        2. Select <strong>User Mgt</strong> ➡️ <strong>All Users</strong>.<br>
        3. Member <strong id="bioManualName">—</strong> (PIN <strong id="bioManualPin">—</strong>) is already listed!<br>
        4. Select User ➡️ Tap <strong>Fingerprint</strong> (touch 3 times) or <strong>Face</strong>.<br>
        5. Press ESC to save. Attendance will instantly push live to the gym dashboard!
      </div>
    </details>

    <!-- Modal Footer -->
    <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
      <button type="button" class="btn btn-secondary" style="border-radius:10px; font-weight:700; width:100%;" onclick="closeBiometricEnrollModal()">Done / Close</button>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- HEADER & MODAL COMPONENT STYLES                           -->
<!-- ========================================================= -->
<style>
/* ─── Top App Header ────────────────────────────────────────── */
.app-header {
  height: var(--header-height, 66px);
  background-color: var(--bg-surface);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  z-index: 40;
  flex-shrink: 0;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  position: sticky;
  top: 0;
  gap: 1rem;
}

/* Header Left */
.header-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  flex-shrink: 0;
}

.toggle-sidebar-btn {
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  transition: all var(--transition-fast);
}

.toggle-sidebar-btn:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
  border-color: var(--primary-border);
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  text-decoration: none;
}

.brand-logo-icon {
  font-size: 1.55rem;
  line-height: 1;
}

.brand-text-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  justify-content: center;
}

.brand-title-line {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
  line-height: 1;
}

.brand-gym-title {
  font-family: var(--font-heading);
  font-weight: 900;
  font-size: 1.35rem;
  color: var(--primary);
  letter-spacing: -0.03em;
  line-height: 1;
}

.brand-name {
  font-family: var(--font-heading);
  font-weight: 800;
  font-size: 0.92rem;
  color: var(--text-primary);
  letter-spacing: 0.01em;
  line-height: 1;
  text-transform: uppercase;
}

.brand-badge {
  background: var(--bg-surface-secondary);
  color: var(--text-muted);
  padding: 0.12rem 0.4rem;
  border-radius: var(--radius-sm);
  font-size: 0.62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border: 1px solid var(--border-color);
  line-height: 1;
  align-self: flex-start;
}

/* Header Center Search */
.header-search {
  flex: 1;
  max-width: 460px;
  position: relative;
  display: flex;
  align-items: center;
}

.header-search input {
  width: 100%;
  height: 42px;
  padding: 0 3.2rem 0 2.5rem;
  font-size: 0.88rem;
  font-weight: 500;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  background-color: var(--bg-surface-secondary);
  color: var(--text-primary);
  outline: none;
  transition: all var(--transition-fast);
}

.header-search input:focus {
  border-color: var(--primary);
  background-color: var(--bg-surface);
  box-shadow: 0 0 0 3px var(--primary-light);
}

.header-search .search-icon {
  position: absolute;
  left: 0.85rem;
  color: var(--text-muted);
  font-size: 0.95rem;
  pointer-events: none;
}

.header-search-kbd {
  position: absolute;
  right: 0.65rem;
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
  font-family: var(--font-mono);
  pointer-events: none;
}

/* Header Right */
.header-right {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-shrink: 0;
}

.header-mode-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.85rem;
  font-size: 0.78rem;
  font-weight: 800;
  border-radius: var(--radius-full);
  text-decoration: none;
  transition: all var(--transition-fast);
  height: 36px;
  box-sizing: border-box;
}

.mode-pos {
  background: var(--primary);
  color: #FFFFFF;
  border: 1px solid var(--primary);
}
.mode-pos:hover {
  background: var(--primary-hover);
  color: #FFFFFF;
}

.mode-admin {
  background: var(--bg-surface-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}
.mode-admin:hover {
  background: var(--bg-surface-hover);
  color: var(--primary);
}

.header-clock-badge {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  padding: 0.38rem 0.75rem;
  border-radius: var(--radius-md);
  font-size: 0.78rem;
  color: var(--text-secondary);
  height: 36px;
  box-sizing: border-box;
}

.clock-flag {
  font-size: 0.95rem;
  line-height: 1;
}

.clock-text {
  font-weight: 700;
}

.sync-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
  padding: 0.38rem 0.75rem;
  border-radius: var(--radius-full);
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
  user-select: none;
  height: 36px;
  box-sizing: border-box;
  transition: all var(--transition-fast);
}
.sync-status-badge:hover {
  background: #D1FAE5;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10B981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulseDot 2s infinite ease-in-out;
}

.header-pwa-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  padding: 0.38rem 0.75rem;
  border-radius: var(--radius-md);
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  height: 36px;
  transition: all var(--transition-fast);
}
.header-pwa-btn:hover {
  background: var(--bg-surface-hover);
  color: var(--text-primary);
}

.header-user-section {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-left: 0.35rem;
  border-left: 1px solid var(--border-color);
}

.user-profile-menu {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--primary);
  color: #FFFFFF;
  font-weight: 800;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}

.user-info {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.user-name {
  font-weight: 700;
  font-size: 0.84rem;
  color: var(--text-primary);
  white-space: nowrap;
}

.user-role {
  font-size: 0.7rem;
  color: var(--text-muted);
  font-weight: 600;
}

.header-logout-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  color: #DC2626;
  padding: 0.38rem 0.65rem;
  border-radius: var(--radius-md);
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  height: 36px;
  transition: all var(--transition-fast);
}
.header-logout-btn:hover {
  background: #FEE2E2;
  color: #B91C1C;
}

/* ─── Global New Member Modal ───────────────────────────────── */
.new-member-modal-card {
  position: relative;
  z-index: 10;
  background: #FFFFFF;
  width: 92%;
  max-width: 540px;
  border-radius: var(--radius-xl);
  padding: 1.45rem;
  box-shadow: var(--shadow-modal);
  border: 1px solid var(--border-color);
  max-height: 90vh;
  overflow-y: auto;
}

.new-member-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.15rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-title-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.modal-title-icon {
  font-size: 1.25rem;
  line-height: 1;
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  letter-spacing: -0.02em;
}

.modal-shortcut-badge {
  background: var(--bg-surface-secondary);
  border: 1px solid var(--border-color);
  color: var(--primary);
  font-size: 0.68rem;
  font-weight: 800;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  font-family: var(--font-mono);
}

.modal-subtitle {
  font-size: 0.76rem;
  color: var(--text-muted);
  margin: 0.25rem 0 0 0;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  color: var(--text-muted);
  cursor: pointer;
  line-height: 1;
  padding: 0.25rem;
  border-radius: var(--radius-sm);
  transition: color var(--transition-fast);
}
.modal-close:hover {
  color: var(--danger);
}

.form-section-title {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--primary);
  margin: 0.95rem 0 0.55rem 0;
}
.form-section-title:first-of-type {
  margin-top: 0;
}

.new-member-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.grid-full {
  grid-column: 1 / -1;
}

.required-star {
  color: var(--danger);
  font-weight: 800;
}

.whatsapp-onboard-notice {
  background: #ECFDF5;
  border: 1px solid #A7F3D0;
  border-radius: var(--radius-md);
  padding: 0.65rem 0.85rem;
  font-size: 0.76rem;
  color: #065F46;
  margin-top: 1rem;
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  line-height: 1.4;
}

.notice-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
  line-height: 1.2;
}

.new-member-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.35rem;
}

.new-member-actions .btn {
  flex: 1;
  justify-content: center;
  font-weight: 700;
  height: 42px;
}

.btn-save-member {
  font-size: 0.88rem;
}

/* ─── Responsive Adjustments ─────────────────────────────────── */
@media (max-width: 1024px) {
  .header-clock-badge {
    display: none;
  }
}

@media (max-width: 768px) {
  .app-header {
    height: auto;
    padding: 0.65rem 0.85rem;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .header-left {
    order: 1;
  }

  .header-right {
    order: 2;
    margin-left: auto;
    gap: 0.4rem;
  }

  .header-search {
    order: 3;
    max-width: 100%;
    width: 100%;
    margin-top: 0.25rem;
  }

  .user-info {
    display: none;
  }

  .logout-label {
    display: none;
  }

  .new-member-grid {
    grid-template-columns: 1fr;
  }

  .new-member-actions {
    flex-direction: column;
  }
}

@media (max-width: 480px) {
  .brand-badge {
    display: none;
  }
  .header-pwa-btn {
    display: none;
  }
  .sync-label {
    display: none;
  }
}
</style>

<!-- ========================================================= -->
<!-- JAVASCRIPT CONTROLLERS (100% PRESERVED)                   -->
<!-- ========================================================= -->
<script>
  function updateIstClock() {
    try {
      const now = new Date();
      const options = {
        timeZone: 'Asia/Kolkata',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      };
      const formatter = new Intl.DateTimeFormat('en-GB', options);
      const nowStr = formatter.format(now);
      const el = document.getElementById('headerLiveIstClock');
      if (el) el.innerText = nowStr + ' IST';
    } catch(e) {}
  }
  setInterval(updateIstClock, 1000);

  function handleLogout() {
    if (confirm('Are you sure you want to log out of THE CLUB 777® System?')) {
      fetch('api/auth.php?action=logout')
        .then(() => {
          window.location.href = 'index.php?page=login';
        });
    }
  }

  function submitQuickMember() {
    const nameInput = document.getElementById('nmName');
    const phoneInput = document.getElementById('nmPhone');
    const dobInput = document.getElementById('nmDob');
    const emailInput = document.getElementById('nmEmail');
    const genderInput = document.getElementById('nmGender');
    const emergencyInput = document.getElementById('nmEmergency');

    if (!nameInput.value.trim() || !phoneInput.value.trim()) {
      showToast('Full Name and Mobile Phone number are required', 'warning');
      return;
    }

    const payload = {
      name: nameInput.value.trim(),
      phone: phoneInput.value.trim(),
      dob: dobInput ? dobInput.value : null,
      email: emailInput ? emailInput.value.trim() : '',
      gender: genderInput ? genderInput.value : 'Male',
      emergency_contact: emergencyInput ? emergencyInput.value.trim() : ''
    };

    fetch('api/members.php?action=create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          showToast('Member Registered Successfully!', 'success');
          closeModal('newMemberModal');
          document.getElementById('newMemberForm').reset();

          // Open Biometric Gate Enrollment Dialog directly!
          const memberPin = res.data.biometric_id || res.data.id;
          const memberName = res.data.name;
          openBiometricModal(memberName, memberPin, false, false);

          if (typeof selectMember === 'function') {
            selectMember(res.data);
          }
        } else {
          showToast(res.message || 'Registration failed', 'danger');
        }
      })
      .catch(err => {
        showToast('Network error during member registration', 'danger');
      });
  }

  /* ─── Global Biometric Enrollment Controller ─── */
  let activeBioPin = '';
  let activeBioName = '';
  let bioPollTimer = null;

  function openBiometricModal(name, pin, isFp, isFace) {
    activeBioPin = String(pin || '').replace(/[^0-9]/g, '');
    activeBioName = name || 'Member';

    const nameEl = document.getElementById('bioMemberName');
    const pinEl = document.getElementById('bioMemberPin');
    const manualNameEl = document.getElementById('bioManualName');
    const manualPinEl = document.getElementById('bioManualPin');

    if (nameEl) nameEl.innerText = activeBioName;
    if (pinEl) pinEl.innerText = activeBioPin || '—';
    if (manualNameEl) manualNameEl.innerText = activeBioName;
    if (manualPinEl) manualPinEl.innerText = activeBioPin || '—';

    const statusBox = document.getElementById('bioStatusBox');
    if (statusBox) {
      statusBox.style.display = 'none';
      statusBox.innerHTML = '';
    }

    const modal = document.getElementById('biometricEnrollModal');
    if (modal) {
      modal.style.display = 'flex';
    }
  }

  function closeBiometricEnrollModal() {
    if (bioPollTimer) clearInterval(bioPollTimer);
    const modal = document.getElementById('biometricEnrollModal');
    if (modal) modal.style.display = 'none';
    if (window.location.search.includes('page=members') || window.location.search.includes('page=attendance')) {
      window.location.reload();
    }
  }

  function sendBiometricCommand(type) {
    if (!activeBioPin) {
      showToast('Invalid Biometric PIN', 'warning');
      return;
    }

    const action = (type === 'face') ? 'enroll_face' : 'enroll_fp';
    const label = (type === 'face') ? 'Face' : 'Fingerprint';
    const statusBox = document.getElementById('bioStatusBox');

    if (statusBox) {
      statusBox.style.display = 'block';
      statusBox.style.background = '#EFF6FF';
      statusBox.style.border = '1px solid #BFDBFE';
      statusBox.style.color = '#1E40AF';
      statusBox.innerHTML = `<strong>⏳ Connecting to Machine...</strong><br>Sending ${label} enrollment command for User #${activeBioPin}.`;
    }

    fetch(`api/device_commands.php?action=${action}&pin=${activeBioPin}`, { method: 'POST' })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          if (statusBox) {
            statusBox.style.background = '#ECFDF5';
            statusBox.style.border = '1px solid #A7F3D0';
            statusBox.style.color = '#065F46';
            statusBox.innerHTML = `
              <strong>📡 Machine Ready &amp; Waiting!</strong><br>
              ${type === 'face' ? '👤 Ask member to look at the machine camera.' : '👆 Ask member to place their finger on the sensor 3 times.'}<br>
              <span style="font-size:0.75rem; color:#047857; margin-top:4px; display:inline-block;">⏱️ Member has 5 minutes at the gate machine.</span>
            `;
          }
          showToast(`Machine ready! Ask member to touch scanner.`, 'success');
        } else {
          if (statusBox) {
            statusBox.style.background = '#FEF2F2';
            statusBox.style.border = '1px solid #FECACA';
            statusBox.style.color = '#991B1B';
            statusBox.innerHTML = `<strong>⚠️ Command Error:</strong> ${res.message || 'Could not queue command'}`;
          }
        }
      })
      .catch(err => {
        if (statusBox) {
          statusBox.style.background = '#FEF2F2';
          statusBox.style.border = '1px solid #FECACA';
          statusBox.style.color = '#991B1B';
          statusBox.innerHTML = `<strong>⚠️ Network error:</strong> Could not reach device API`;
        }
      });
  }
</script>