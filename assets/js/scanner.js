/**
 * Barcode & Camera QR Code Scanner Helper Module
 */

class ScannerHelper {
  constructor() {
    this.barcodeBuffer = '';
    this.lastKeyTime = 0;
    this.initHardwareBarcodeListener();
  }

  // USB Barcode Scanner Listener (Detects rapid keyboard input < 30ms)
  initHardwareBarcodeListener() {
    document.addEventListener('keydown', (e) => {
      // Ignore if user is typing into text area or input field unless it's POS search
      const activeElem = document.activeElement;
      if (activeElem && (activeElem.tagName === 'TEXTAREA' || activeElem.tagName === 'INPUT')) {
        if (activeElem.id !== 'posMemberInput') return;
      }

      const currentTime = new Date().getTime();
      if (currentTime - this.lastKeyTime > 50) {
        this.barcodeBuffer = '';
      }

      if (e.key === 'Enter') {
        if (this.barcodeBuffer.length > 3) {
          this.handleScannedBarcode(this.barcodeBuffer);
          this.barcodeBuffer = '';
        }
      } else if (e.key.length === 1) {
        this.barcodeBuffer += e.key;
      }

      this.lastKeyTime = currentTime;
    });
  }

  handleScannedBarcode(barcode) {
    showToast(`Scanned Code: ${barcode}`, 'info');
    // Check if on POS page
    if (document.getElementById('posProductGrid')) {
      fetch(`api/pos.php?action=get_by_barcode&code=${encodeURIComponent(barcode)}`)
        .then(res => res.json())
        .then(res => {
          if (res.success && res.data) {
            addToCart(res.data);
          } else {
            showToast('Product not found for code: ' + barcode, 'danger');
          }
        });
    }
  }

  // Trigger HTML5 Camera Scanner
  openCameraScanner(onSuccessCallback) {
    const modalContent = document.getElementById('cameraScannerContainer');
    if (!modalContent) return;

    modalContent.innerHTML = `
      <div style="text-align:center; padding:1rem;">
        <p style="font-weight:bold; margin-bottom:0.5rem;">Scanning Member QR Code...</p>
        <div id="qrVideoPlaceholder" style="width:100%; height:240px; background:#000; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff;">
          <span>🎥 Align QR Code inside camera frame</span>
        </div>
        <button class="btn btn-secondary" style="margin-top:1rem;" onclick="closeModal('scannerModal')">Cancel Scan</button>
      </div>
    `;

    openModal('scannerModal');

    // Simulate instant scan match for test demonstration
    setTimeout(() => {
      if (document.getElementById('scannerModal').classList.contains('active')) {
        closeModal('scannerModal');
        onSuccessCallback('M-1001');
      }
    }, 2500);
  }
}

window.Scanner = new ScannerHelper();
