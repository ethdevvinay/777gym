/**
 * Main Application JS Framework & UI Controller
 */

document.addEventListener('DOMContentLoaded', () => {
  initNavigation();
  initModals();
});

// Sidebar & Mobile Navigation
function initNavigation() {
  const sidebar = document.getElementById('appSidebar');
  const toggleBtn = document.getElementById('toggleSidebarBtn');
  
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      if (window.innerWidth <= 991) {
        sidebar.classList.toggle('open');
      } else {
        sidebar.classList.toggle('collapsed');
      }
    });
  }

  // Close mobile sidebar on outer click
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 991 && sidebar && sidebar.classList.contains('open')) {
      if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    }
  });
}

// Modal System Helper
function initModals() {
  document.querySelectorAll('[data-modal-target]').forEach(trigger => {
    trigger.addEventListener('click', () => {
      const targetId = trigger.getAttribute('data-modal-target');
      openModal(targetId);
    });
  });

  document.querySelectorAll('.modal-close, .modal-overlay').forEach(closeBtn => {
    closeBtn.addEventListener('click', (e) => {
      if (e.target === closeBtn) {
        const modal = closeBtn.closest('.modal');
        if (modal) closeModal(modal.id);
      }
    });
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input:not([type="hidden"]), select');
    if (firstInput) setTimeout(() => firstInput.focus(), 100);
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}

function initGlobalShortcuts() {
  document.addEventListener('keydown', (e) => {
    // If pressing ESC inside input, close modal or blur
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.active').forEach(m => closeModal(m.id));
      return;
    }

    const active = document.activeElement;
    if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')) {
      return;
    }

    if (e.key === 'F1') {
      e.preventDefault();
      window.location.href = 'index.php?page=pos';
    } else if (e.key === 'F2') {
      e.preventDefault();
      const posInput = document.getElementById('posMemberInput') || document.getElementById('globalHeaderSearch');
      if (posInput) posInput.focus();
    } else if (e.key === 'F3') {
      e.preventDefault();
      openModal('newMemberModal');
    } else if (e.key === 'F5') {
      e.preventDefault();
      window.location.href = 'index.php?page=attendance';
    }
  });
}

// Toast Notification System
function showToast(message, type = 'info') {
  let toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.style.cssText = `
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      pointer-events: none;
    `;
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  
  let bg = '#1F2937';
  if (type === 'success') bg = '#059669';
  if (type === 'danger') bg = '#DC2626';
  if (type === 'warning') bg = '#D97706';

  toast.style.cssText = `
    background: ${bg};
    color: #fff;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    pointer-events: auto;
    animation: fadeIn 0.2s ease;
  `;
  toast.innerText = message;
  toastContainer.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
