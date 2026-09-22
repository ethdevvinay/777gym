<!-- Recommended Enterprise Main Sidebar Component -->
<?php 
$page = $_GET['page'] ?? 'dashboard'; 
$userRole = getCurrentUserRole();
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main Navigation">

  <nav class="sidebar-nav">

    <!-- ========================================================= -->
    <!-- 1. CORE RECEPTION & BILLING (HIGHEST PRIORITY)            -->
    <!-- ========================================================= -->
    <?php if (hasPageAccess('dashboard') || hasPageAccess('pos') || hasPageAccess('attendance') || hasPageAccess('members') || hasPageAccess('trials') || hasPageAccess('payments') || hasPageAccess('memberships')): ?>
      <div class="sidebar-section-title">CORE OPERATIONS</div>

      <?php if (hasPageAccess('dashboard')): ?>
        <a href="index.php?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>" title="Operational Dashboard & Overview">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span class="nav-text">Dashboard</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('pos')): ?>
        <a href="index.php?page=pos" class="nav-item <?= $page === 'pos' ? 'active' : '' ?>" title="Quick POS Billing & Membership Sale (F1)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
          <span class="nav-text">POS / New Sale</span>
          <kbd class="nav-shortcut-badge">F1</kbd>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('attendance')): ?>
        <a href="index.php?page=attendance" class="nav-item <?= $page === 'attendance' ? 'active' : '' ?>" title="Member Live Attendance & Check-In (F5)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="nav-text">Attendance</span>
          <kbd class="nav-shortcut-badge">F5</kbd>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('members')): ?>
        <a href="index.php?page=members" class="nav-item <?= ($page === 'members' || $page === 'member_profile') ? 'active' : '' ?>" title="Member Directory & Profiles">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span class="nav-text">Members</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('trials')): ?>
        <a href="index.php?page=trials" class="nav-item <?= $page === 'trials' ? 'active' : '' ?>" title="Trial & Short Pass Members (Lead Conversions)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          <span class="nav-text">Trial Passes</span>
          <span class="nav-badge" style="background:#0284C7; color:#FFFFFF; font-weight:800; font-size:0.65rem; padding:1px 5px; border-radius:4px;">TRIAL</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('payments')): ?>
        <a href="index.php?page=payments" class="nav-item <?= $page === 'payments' ? 'active' : '' ?>" title="Collections, Invoices & Due Payments">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <span class="nav-text">Payments &amp; Invoices</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('memberships')): ?>
        <a href="index.php?page=memberships" class="nav-item <?= $page === 'memberships' ? 'active' : '' ?>" title="Gym Plans, Pricing & Subscriptions">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
          </svg>
          <span class="nav-text">Memberships</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 2. FITNESS & TRAINING SERVICES                            -->
    <!-- ========================================================= -->
    <?php if (hasPageAccess('pt') || hasPageAccess('trainers') || hasPageAccess('pool')): ?>
      <div class="sidebar-section-title">FITNESS &amp; TRAINING</div>

      <?php if (hasPageAccess('pt')): ?>
        <a href="index.php?page=pt" class="nav-item <?= $page === 'pt' ? 'active' : '' ?>" title="1-on-1 Personal Training Packages">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <span class="nav-text">Personal Training</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('trainers')): ?>
        <a href="index.php?page=trainers" class="nav-item <?= $page === 'trainers' ? 'active' : '' ?>" title="Fitness Trainers & Coaches">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <span class="nav-text">Trainers</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('pool')): ?>
        <a href="index.php?page=pool" class="nav-item <?= $page === 'pool' ? 'active' : '' ?>" title="Swimming Pool Memberships & Slots">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h10a4 4 0 004-4M3 11a4 4 0 014-4h10a4 4 0 014 4M3 7a4 4 0 014-4h10a4 4 0 014 4" />
          </svg>
          <span class="nav-text">Swimming Pool</span>
          <span class="nav-badge badge-pool">NEW</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 3. MARKETING & MEMBER RETENTION                           -->
    <!-- ========================================================= -->
    <?php if (hasPageAccess('communication') || hasPageAccess('marketing')): ?>
      <div class="sidebar-section-title">MARKETING &amp; RETENTION</div>

      <?php if (hasPageAccess('communication')): ?>
        <a href="index.php?page=communication" class="nav-item <?= $page === 'communication' ? 'active' : '' ?>" title="Automated WhatsApp Renewal & Birthday Engine">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <span class="nav-text">WhatsApp Reminders</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('marketing')): ?>
        <a href="index.php?page=marketing" class="nav-item <?= $page === 'marketing' ? 'active' : '' ?>" title="Promos, Offers & Campaigns">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
          </svg>
          <span class="nav-text">Marketing &amp; Offers</span>
          <span class="nav-badge badge-pro">PRO</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 4. OPERATIONS & FINANCIAL MANAGEMENT                      -->
    <!-- ========================================================= -->
    <?php if (hasPageAccess('inventory') || hasPageAccess('expenses') || hasPageAccess('staff')): ?>
      <div class="sidebar-section-title">OPERATIONS &amp; FINANCE</div>

      <?php if (hasPageAccess('inventory')): ?>
        <a href="index.php?page=inventory" class="nav-item <?= $page === 'inventory' ? 'active' : '' ?>" title="Supplements & Gear Stock">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <span class="nav-text">Inventory</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('expenses')): ?>
        <a href="index.php?page=expenses" class="nav-item <?= $page === 'expenses' ? 'active' : '' ?>" title="Gym Operational Expenses">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span class="nav-text">Expenses</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('staff')): ?>
        <a href="index.php?page=staff" class="nav-item <?= ($page === 'staff' || $page === 'payroll') ? 'active' : '' ?>" title="Staff Directory & Payroll">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 012-2h2a2 2 0 012 2v1m-4 0h4" />
          </svg>
          <span class="nav-text">Staff &amp; Payroll</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 5. ANALYTICS, HARDWARE & SYSTEM                           -->
    <!-- ========================================================= -->
    <?php if (hasPageAccess('reports') || hasPageAccess('devices') || hasPageAccess('audit_logs') || hasPageAccess('settings')): ?>
      <div class="sidebar-section-title">SYSTEM &amp; ANALYTICS</div>

      <?php if (hasPageAccess('reports')): ?>
        <a href="index.php?page=reports" class="nav-item <?= $page === 'reports' ? 'active' : '' ?>" title="Financial & Growth Reports">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <span class="nav-text">Reports &amp; Analytics</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('devices')): ?>
        <a href="index.php?page=devices" class="nav-item <?= $page === 'devices' ? 'active' : '' ?>" title="Biometric Attendance Machine Integration">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
          <span class="nav-text">Attendance Devices</span>
          <span class="nav-badge badge-iot">IoT</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('audit_logs')): ?>
        <a href="index.php?page=audit_logs" class="nav-item <?= $page === 'audit_logs' ? 'active' : '' ?>" title="Security & Activity Audit Trail">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          <span class="nav-text">Audit Logs</span>
        </a>
      <?php endif; ?>

      <?php if (hasPageAccess('settings')): ?>
        <a href="index.php?page=settings" class="nav-item <?= $page === 'settings' ? 'active' : '' ?>" title="Gym Business & System Settings">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span class="nav-text">System Settings</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Logout Action -->
    <a href="javascript:void(0)" onclick="handleLogout()" class="nav-item nav-item-logout" title="Sign out of system">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
      </svg>
      <span class="nav-text">Logout</span>
    </a>

  </nav>
</aside>

<!-- ========================================================= -->
<!-- SIDEBAR COMPONENT SPECIFIC STYLES                         -->
<!-- ========================================================= -->
<style>
  /* Nav Item Enhancements */
  .nav-shortcut-badge {
    background: var(--bg-surface-secondary);
    border: 1px solid var(--border-color);
    color: var(--primary);
    font-family: var(--font-mono);
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    margin-left: auto;
  }

  .nav-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.15rem 0.45rem;
    border-radius: var(--radius-sm);
    margin-left: auto;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .badge-pool {
    background: #0284C7;
    color: #FFFFFF;
  }

  .badge-iot {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
  }

  .badge-pro {
    background: #8B5CF6;
    color: #FFFFFF;
  }

  .nav-item-logout {
    color: #DC2626 !important;
    margin-top: 1rem;
    border-top: 1px dashed var(--border-color);
    padding-top: 0.85rem;
  }

  .nav-item-logout:hover {
    background: #FEF2F2 !important;
    color: #B91C1C !important;
  }

  /* Collapsed Mode Adjustments */
  .app-sidebar.collapsed .sidebar-section-title,
  .app-sidebar.collapsed .nav-text,
  .app-sidebar.collapsed .nav-shortcut-badge,
  .app-sidebar.collapsed .nav-badge {
    display: none;
  }
</style>