/**
 * Offline Mode & IndexedDB Synchronization Engine v2
 * Handles 100% offline POS Sales and Attendance punches.
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
      const swScope = window.location.pathname.startsWith('/GYM') ? '/GYM/' : './';
      navigator.serviceWorker.register('sw.js', { scope: swScope }).then((reg) => {
        reg.update();
      }).catch(() => {});
    }
  }

  initDB() {
    const request = indexedDB.open('GymPOSOfflineDB', 2);
    request.onupgradeneeded = (e) => {
      this.db = e.target.result;
      if (!this.db.objectStoreNames.contains('pending_sales')) {
        this.db.createObjectStore('pending_sales', { keyPath: 'id', autoIncrement: true });
      }
      if (!this.db.objectStoreNames.contains('pending_attendance')) {
        this.db.createObjectStore('pending_attendance', { keyPath: 'id', autoIncrement: true });
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
      if (typeof showToast === 'function') {
        showToast('🟢 Connection Restored! Syncing offline data...', 'success');
      }
      this.syncPendingData();
    });

    window.addEventListener('offline', () => {
      this.updateSyncBadge();
      if (typeof showToast === 'function') {
        showToast('⚠️ Working Offline — Sales & Attendance will be saved locally', 'warning');
      }
    });
  }

  updateSyncBadge() {
    const badge = document.getElementById('globalSyncBadge');
    if (!badge) return;

    this.countPending((count) => {
      if (navigator.onLine) {
        if (count > 0) {
          badge.className = 'sync-status-badge offline';
          badge.innerHTML = `<span class="status-dot pulse"></span> ↻ Syncing ${count} offline items...`;
          this.syncPendingData();
        } else {
          badge.className = 'sync-status-badge';
          badge.innerHTML = `<span class="status-dot"></span> ● Online`;
        }
      } else {
        badge.className = 'sync-status-badge offline';
        badge.innerHTML = `<span class="status-dot"></span> ● Offline (${count} queued)`;
      }
    });
  }

  queueTransaction(payload) {
    if (!this.db) return;
    const tx = this.db.transaction('pending_sales', 'readwrite');
    const store = tx.objectStore('pending_sales');
    store.add({
      type: 'sale',
      payload: payload,
      timestamp: new Date().toISOString()
    });
    tx.oncomplete = () => {
      this.updateSyncBadge();
    };
  }

  queueAttendance(payload) {
    if (!this.db) return;
    const tx = this.db.transaction('pending_attendance', 'readwrite');
    const store = tx.objectStore('pending_attendance');
    store.add({
      type: 'attendance',
      payload: payload,
      timestamp: payload.time || new Date().toISOString()
    });
    tx.oncomplete = () => {
      this.updateSyncBadge();
    };
  }

  countPending(callback) {
    if (!this.db) { callback(0); return; }
    try {
      const tx = this.db.transaction(['pending_sales', 'pending_attendance'], 'readonly');
      const salesReq = tx.objectStore('pending_sales').count();
      const attReq = tx.objectStore('pending_attendance').count();

      let total = 0;
      let completed = 0;

      const checkDone = () => {
        completed++;
        if (completed === 2) callback(total);
      };

      salesReq.onsuccess = () => { total += salesReq.result; checkDone(); };
      attReq.onsuccess   = () => { total += attReq.result;   checkDone(); };
      salesReq.onerror   = () => checkDone();
      attReq.onerror     = () => checkDone();
    } catch (e) {
      callback(0);
    }
  }

  syncPendingData() {
    if (!this.db || !navigator.onLine) return;

    try {
      const tx = this.db.transaction(['pending_sales', 'pending_attendance'], 'readonly');
      const salesStore = tx.objectStore('pending_sales');
      const attStore   = tx.objectStore('pending_attendance');

      const reqSales = salesStore.getAll();
      const reqAtt   = attStore.getAll();

      let salesItems = [];
      let attItems = [];
      let loaded = 0;

      const sendBatch = () => {
        loaded++;
        if (loaded < 2) return;

        const allItems = [...salesItems, ...attItems];
        if (allItems.length === 0) return;

        fetch('api/sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ batch: allItems })
        })
        .then(res => res.json())
        .then(res => {
          if (res.success) {
            const clearTx = this.db.transaction(['pending_sales', 'pending_attendance'], 'readwrite');
            clearTx.objectStore('pending_sales').clear();
            clearTx.objectStore('pending_attendance').clear();
            clearTx.oncomplete = () => {
              this.updateSyncBadge();
              if (typeof showToast === 'function') {
                showToast(`✅ Synced ${res.data?.synced_count || allItems.length} offline records to server!`, 'success');
              }
            };
          }
        })
        .catch(() => {});
      };

      reqSales.onsuccess = () => { salesItems = reqSales.result || []; sendBatch(); };
      reqAtt.onsuccess   = () => { attItems   = reqAtt.result   || []; sendBatch(); };
    } catch (e) {}
  }
}

window.OfflineManager = new OfflineEngine();
