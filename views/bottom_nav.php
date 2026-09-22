<!-- Mobile Sticky Bottom Navigation Component -->
<?php 
$page = $_GET['page'] ?? 'dashboard'; 
?>
<nav class="mobile-bottom-nav" aria-label="Mobile Bottom Navigation">
  <div class="mobile-nav-items">
    
    <!-- 1. Home / Dashboard -->
    <a href="index.php?page=dashboard" 
       class="mobile-nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" 
       aria-label="Dashboard"
       <?= $page === 'dashboard' ? 'aria-current="page"' : '' ?>>
      <div class="nav-icon-container">
        <svg class="nav-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
      </div>
      <span class="nav-label">Home</span>
    </a>

    <!-- 2. POS / Sales -->
    <a href="index.php?page=pos" 
       class="mobile-nav-link <?= $page === 'pos' ? 'active' : '' ?>" 
       aria-label="Point of Sale"
       <?= $page === 'pos' ? 'aria-current="page"' : '' ?>>
      <div class="nav-icon-container">
        <svg class="nav-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
      </div>
      <span class="nav-label">POS</span>
    </a>

    <!-- 3. Members -->
    <a href="index.php?page=members" 
       class="mobile-nav-link <?= $page === 'members' ? 'active' : '' ?>" 
       aria-label="Member Management"
       <?= $page === 'members' ? 'aria-current="page"' : '' ?>>
      <div class="nav-icon-container">
        <svg class="nav-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
      </div>
      <span class="nav-label">Members</span>
    </a>

    <!-- 4. Check-In / Attendance -->
    <a href="index.php?page=attendance" 
       class="mobile-nav-link nav-link-highlight <?= $page === 'attendance' ? 'active' : '' ?>" 
       aria-label="Attendance Check-In"
       <?= $page === 'attendance' ? 'aria-current="page"' : '' ?>>
      <div class="nav-icon-container">
        <svg class="nav-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
      </div>
      <span class="nav-label">Check-In</span>
    </a>

    <!-- 5. More / Sidebar Toggle -->
    <a href="javascript:void(0)" 
       class="mobile-nav-link" 
       id="mobileNavMoreBtn"
       role="button"
       aria-label="More Navigation Menu"
       onclick="toggleMobileMoreMenu()">
      <div class="nav-icon-container">
        <svg class="nav-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </div>
      <span class="nav-label">More</span>
    </a>

  </div>
</nav>

<style>
/* ============================================================
   PREMIUM MOBILE STICKY BOTTOM NAVIGATION BAR
   Modern SaaS App Layout & Touch Standards
   ============================================================ */

.mobile-bottom-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 90;
  background: var(--bg-surface, #FFFFFF);
  border-top: 1px solid var(--border-color, #E2E8F0);
  border-top-left-radius: 18px;
  border-top-right-radius: 18px;
  box-shadow: 0 -4px 22px rgba(15, 23, 42, 0.08);
  padding: 0.35rem 0.5rem calc(0.35rem + env(safe-area-inset-bottom, 0px)) 0.5rem;
  user-select: none;
  -webkit-user-select: none;
}

.mobile-nav-items {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  align-items: center;
  justify-items: center;
  max-width: 540px;
  margin: 0 auto;
  gap: 0.2rem;
}

.mobile-nav-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 0.35rem 0.2rem;
  color: var(--text-muted, #64748B);
  text-decoration: none;
  border-radius: 12px;
  transition: transform 0.15s ease, color 0.15s ease, background-color 0.15s ease;
  min-height: 52px;
  touch-action: manipulation;
  position: relative;
}

.mobile-nav-link:active {
  transform: scale(0.95);
  background-color: var(--bg-surface-secondary, #F1F5F9);
}

/* Icon Container with uniform geometry */
.nav-icon-container {
  width: 32px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}

.nav-svg-icon {
  width: 22px;
  height: 22px;
  stroke-width: 2;
  transition: stroke-width 0.15s ease, transform 0.15s ease;
}

/* Text Label */
.nav-label {
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1.2;
  margin-top: 0.15rem;
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  transition: color 0.15s ease, font-weight 0.15s ease;
}

/* Active State */
.mobile-nav-link.active {
  color: var(--primary, #2563EB);
}

.mobile-nav-link.active .nav-icon-container {
  background-color: var(--primary-light, #EFF6FF);
  color: var(--primary, #2563EB);
  transform: translateY(-1px);
}

.mobile-nav-link.active .nav-svg-icon {
  stroke-width: 2.3;
}

.mobile-nav-link.active .nav-label {
  color: var(--primary, #2563EB);
  font-weight: 800;
}

/* Distinct subtle accent for Check-In action */
.mobile-nav-link.nav-link-highlight:not(.active) .nav-icon-container {
  background-color: #F8FAFC;
}

.mobile-nav-link.nav-link-highlight.active .nav-icon-container {
  background-color: var(--primary-light, #EFF6FF);
}

/* Responsive display rule */
@media (max-width: 768px) {
  .mobile-bottom-nav {
    display: block;
  }
}

@media (max-width: 360px) {
  .mobile-nav-link {
    padding: 0.25rem 0.1rem;
    min-height: 48px;
  }
  .nav-svg-icon {
    width: 20px;
    height: 20px;
  }
  .nav-label {
    font-size: 0.65rem;
  }
}
</style>

<script>
function toggleMobileMoreMenu() {
  const sidebar = document.getElementById('appSidebar');
  const moreBtn = document.getElementById('mobileNavMoreBtn');
  if (sidebar) {
    sidebar.classList.toggle('open');
    if (moreBtn) {
      if (sidebar.classList.contains('open')) {
        moreBtn.classList.add('active');
      } else {
        moreBtn.classList.remove('active');
      }
    }
  }
}

// Keep More button state in sync if sidebar is closed via outside click or backdrop
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('appSidebar');
  const moreBtn = document.getElementById('mobileNavMoreBtn');
  if (sidebar && moreBtn) {
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
          if (sidebar.classList.contains('open')) {
            moreBtn.classList.add('active');
          } else {
            moreBtn.classList.remove('active');
          }
        }
      });
    });
    observer.observe(sidebar, { attributes: true });
  }
});
</script>
