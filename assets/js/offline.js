/**
 * Offline Mode & IndexedDB Synchronization Engine
 */

class OfflineEngine {
  constructor() {
    this.db = null;
    this.initDB();
    this.initNetworkWatcher();
    this.registerServiceWorker();
  }

  registerServiceWorker() {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('sw.js').then((reg) => {
        reg.update();
      }).catch((err) => {
        // Fallback cleanup if registration fails
      });
    }
  }

  initDB() {
    const request = indexedDB.open('GymPOSOfflineDB', 1);
    request.onupgradeneeded = (e) => {
      this.db = e.target.result;
      if (!this.db.objectStoreNames.contains('pending_sales')) {
        this.db.createObjectStore('pending_sales', { keyPath: 'id', autoIncrement: true });
      }
    };
    request.onsuccess = (e) => {
      this.db = e.target.result;
      this.updateSyncBadge();
    };
  }

  initNetworkWatcher() {
    window.addEventListener('online', () => {
      this.updateSyncBadge();
      showToast('Connection Restored! Syncing data...', 'success');
      this.syncPendingData();
    });

    window.addEventListener('offline', () => {
      this.updateSyncBadge();
      showToast('Working Offline — Sales will be saved locally', 'warning');
    });
  }

  updateSyncBadge() {
    const badge = document.getElementById('globalSyncBadge');
    if (!badge) return;

    if (navigator.onLine) {
      this.countPending((count) => {
        if (count > 0) {
          badge.className = 'sync-status-badge offline';
          badge.innerHTML = `<span class="status-dot pulse"></span> ↻ Syncing ${count} items...`;
          this.syncPendingData();
        } else {
          badge.className = 'sync-status-badge';
          badge.innerHTML = `<span class="status-dot"></span> ● Online`;
        }
      });
    } else {
      this.countPending((count) => {
        badge.className = 'sync-status-badge offline';
        badge.innerHTML = `<span class="status-dot"></span> ● Offline (${count} pending)`;
      });
    }
  }

  queueTransaction(payload) {
    if (!this.db) return;
    const tx = this.db.transaction('pending_sales', 'readwrite');
    const store = tx.objectStore('pending_sales');
    store.add({
      payload: payload,
      timestamp: new Date().toISOString()
    });
    tx.oncomplete = () => {
      this.updateSyncBadge();
    };
  }

  countPending(callback) {
    if (!this.db) { callback(0); return; }
    const tx = this.db.transaction('pending_sales', 'readonly');
    const store = tx.objectStore('pending_sales');
    const req = store.count();
    req.onsuccess = () => callback(req.result);
  }

  syncPendingData() {
    if (!this.db || !navigator.onLine) return;
    const tx = this.db.transaction('pending_sales', 'readonly');
    const store = tx.objectStore('pending_sales');
    const req = store.getAll();

    req.onsuccess = () => {
      const items = req.result;
      if (items.length === 0) return;

      fetch('api/sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ batch: items })
      })
      .then(res => res.text())
      .then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          return { success: false, message: text };
        }
      })
      .then(res => {
        if (res.success) {
          const clearTx = this.db.transaction('pending_sales', 'readwrite');
          clearTx.objectStore('pending_sales').clear();
          clearTx.oncomplete = () => {
            this.updateSyncBadge();
            showToast('All offline transactions synced!', 'success');
          };
        }
      })
      .catch(() => {});
    };
  }
}

window.OfflineManager = new OfflineEngine();
