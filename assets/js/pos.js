/**
 * Commercial POS System Engine
 * Handles Touch POS cart state, member assignment, split payments, receipts & keyboard shortcuts
 */

const POSState = {
  cart: [],
  selectedMember: null,
  orderDiscount: 0,
  taxRate: 0.00,
  paymentMethod: 'Cash',
  splitPayments: { cash: 0, upi: 0, card: 0 }
};

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('posProductGrid')) {
    initPOS();
    initKeyboardShortcuts();
  }
});

function initPOS() {
  renderCart();
  setupCategoryFilter();
  setupMemberSearch();
  setupPaymentModal();
  updateCartMemberNotice();
}

// Add Item to Cart
function addToCart(product) {
  const existing = POSState.cart.find(i => i.id === product.id && i.type === product.type);
  if (existing) {
    existing.qty += 1;
  } else {
    POSState.cart.push({
      id: product.id,
      name: product.name,
      price: parseFloat(product.price),
      type: product.type || 'product',
      service_type: product.service_type || null,
      duration_days: product.duration_days || null,
      free_gifts: product.free_gifts || null,
      coupons_count: product.coupons_count || 0,
      qty: 1
    });
  }
  renderCart();
  showToast(`Added: ${product.name}`, 'success');
}

// Adjust Cart Qty
function updateCartQty(index, change) {
  if (!POSState.cart[index]) return;
  POSState.cart[index].qty += change;
  if (POSState.cart[index].qty <= 0) {
    POSState.cart.splice(index, 1);
  }
  renderCart();
}

// Remove Line Item
function removeFromCart(index) {
  POSState.cart.splice(index, 1);
  renderCart();
}

// Clear Cart
function clearCart() {
  POSState.cart = [];
  POSState.orderDiscount = 0;
  const dealInput = document.getElementById('cartDealPriceInput');
  const discInput = document.getElementById('cartDiscountInput');
  const pctInput = document.getElementById('cartDiscountPctInput');
  if (dealInput) dealInput.value = '';
  if (discInput) discInput.value = '';
  if (pctInput) pctInput.value = '';
  renderCart();
}

// Render Cart UI
function renderCart() {
  const container = document.getElementById('cartItemsContainer');
  const countBadge = document.getElementById('cartCountBadge');
  if (!container) return;

  const totalItemsCount = POSState.cart.reduce((sum, item) => sum + item.qty, 0);
  if (countBadge) {
    countBadge.innerText = totalItemsCount > 0 ? `${totalItemsCount} ${totalItemsCount === 1 ? 'Item' : 'Items'}` : '0';
    countBadge.style.display = totalItemsCount > 0 ? 'inline-block' : 'none';
  }

  if (POSState.cart.length === 0) {
    container.innerHTML = `
      <div style="text-align:center; padding: 3rem 1.5rem; color: var(--text-muted);">
        <div style="font-size:3rem; margin-bottom:0.75rem;">🛒</div>
        <p style="font-weight:800; font-size:1rem; color:var(--text-primary); margin-bottom:0.25rem;">Cart is Empty</p>
        <p style="font-size:0.82rem; color:var(--text-secondary); line-height:1.4;">Tap any membership plan or facility pass on the left to add to bill</p>
      </div>
    `;
  } else {
    const serviceIcons = {
      gym: '🏋️ GYM',
      pool: '🏊 POOL',
      steam: '🧖 STEAM',
      sauna: '♨️ SAUNA',
      vip_combo: '👑 VIP COMBO',
      product: '📦 PRODUCT',
      pt_package: '💪 PERSONAL TRAINER'
    };

    container.innerHTML = POSState.cart.map((item, idx) => {
      const badgeText = serviceIcons[item.service_type] || (item.type === 'product' ? '📦 RETAIL' : (item.type === 'pt_package' ? '💪 PT' : '⚡ PLAN'));
      const badgeClass = item.service_type || item.type || 'gym';

      return `
        <div class="cart-item-card">
          <div class="cart-item-header">
            <div class="cart-item-tags">
              <span class="cart-item-badge ${escapeHTML(badgeClass)}">${badgeText}</span>
              ${item.duration_days ? `<span class="cart-item-duration">⏱️ ${item.duration_days} Days</span>` : ''}
              ${item.coupons_count > 0 ? `<span class="cart-item-duration">🎟️ ${item.coupons_count} Coupons</span>` : ''}
            </div>
            <button type="button" class="cart-item-delete-btn" onclick="removeFromCart(${idx})" title="Remove item from cart">
              ✕
            </button>
          </div>

          <div class="cart-item-body">
            <div class="cart-item-title">${escapeHTML(item.name)}</div>
            ${item.free_gifts ? `<div class="cart-item-gift">🎁 ${escapeHTML(item.free_gifts)}</div>` : ''}
          </div>

          <div class="cart-item-footer">
            <div class="cart-stepper">
              <button type="button" class="cart-step-btn" onclick="updateCartQty(${idx}, -1)" title="Decrease">-</button>
              <span class="cart-step-qty font-mono">${item.qty}</span>
              <button type="button" class="cart-step-btn" onclick="updateCartQty(${idx}, 1)" title="Increase">+</button>
            </div>
            <div class="cart-item-pricing">
              ${item.qty > 1 ? `<span class="cart-item-unit font-mono">₹${item.price.toFixed(2)} × ${item.qty}</span>` : ''}
              <span class="cart-item-total-price font-mono">₹${(item.price * item.qty).toFixed(2)}</span>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  calculateTotals();
}

// Calculate Cart Totals & Sync Real-Time Discount Engine
function calculateTotals() {
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const discountAmt = Math.max(0, Math.min(subtotal, POSState.orderDiscount || 0));
  POSState.orderDiscount = discountAmt;

  const grandTotal = Math.max(0, subtotal - discountAmt);
  const discountPct = subtotal > 0 ? (discountAmt / subtotal) * 100 : 0;

  if (document.getElementById('subtotalVal')) document.getElementById('subtotalVal').innerText = `₹${subtotal.toFixed(2)}`;
  if (document.getElementById('taxVal')) document.getElementById('taxVal').innerText = `₹0.00`;
  if (document.getElementById('grandTotalVal')) document.getElementById('grandTotalVal').innerText = `₹${grandTotal.toFixed(2)}`;
  if (document.getElementById('payNowAmount')) document.getElementById('payNowAmount').innerText = `₹${grandTotal.toFixed(2)}`;
  if (document.getElementById('checkoutPayableTotal')) document.getElementById('checkoutPayableTotal').innerText = `₹${grandTotal.toFixed(2)}`;

  // Update discount badge
  const badge = document.getElementById('discountBadgeSummary');
  if (badge) {
    if (discountAmt > 0) {
      badge.style.display = 'inline-block';
      badge.innerText = `-${discountPct.toFixed(1).replace(/\.0$/, '')}% (₹${discountAmt.toFixed(0)} Saved)`;
    } else {
      badge.style.display = 'none';
    }
  }
}

// 1. When user inputs "Deal Price / Itne me diya ₹" (e.g. ₹4,777 -> enters ₹4,000)
function onDealPriceChange(dealVal) {
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  if (subtotal <= 0) return;

  const rawVal = parseFloat(dealVal);
  if (isNaN(rawVal) || dealVal === '') {
    POSState.orderDiscount = 0;
    const discInput = document.getElementById('cartDiscountInput');
    const pctInput = document.getElementById('cartDiscountPctInput');
    if (discInput) discInput.value = '';
    if (pctInput) pctInput.value = '';
  } else {
    const finalDeal = Math.max(0, Math.min(subtotal, rawVal));
    const discountAmt = Math.max(0, subtotal - finalDeal);
    const discountPct = subtotal > 0 ? (discountAmt / subtotal) * 100 : 0;

    POSState.orderDiscount = discountAmt;
    const discInput = document.getElementById('cartDiscountInput');
    const pctInput = document.getElementById('cartDiscountPctInput');
    if (discInput) discInput.value = discountAmt > 0 ? discountAmt.toFixed(2).replace(/\.00$/, '') : '';
    if (pctInput) pctInput.value = discountPct > 0 ? discountPct.toFixed(2).replace(/\.00$/, '') : '';
  }
  calculateTotals();
}

// 2. When user inputs "Discount Amount in ₹" (e.g. ₹500 discount)
function onDiscountAmtChange(amtVal) {
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  if (subtotal <= 0) return;

  const rawVal = parseFloat(amtVal);
  if (isNaN(rawVal) || amtVal === '') {
    POSState.orderDiscount = 0;
    const dealInput = document.getElementById('cartDealPriceInput');
    const pctInput = document.getElementById('cartDiscountPctInput');
    if (dealInput) dealInput.value = '';
    if (pctInput) pctInput.value = '';
  } else {
    const safeDiscAmt = Math.max(0, Math.min(subtotal, rawVal));
    const finalDeal = Math.max(0, subtotal - safeDiscAmt);
    const discountPct = subtotal > 0 ? (safeDiscAmt / subtotal) * 100 : 0;

    POSState.orderDiscount = safeDiscAmt;
    const dealInput = document.getElementById('cartDealPriceInput');
    const pctInput = document.getElementById('cartDiscountPctInput');
    if (dealInput) dealInput.value = finalDeal > 0 ? finalDeal.toFixed(2).replace(/\.00$/, '') : '';
    if (pctInput) pctInput.value = discountPct > 0 ? discountPct.toFixed(2).replace(/\.00$/, '') : '';
  }
  calculateTotals();
}

// 3. When user inputs "Discount Percentage %" (e.g. 15% discount)
function onDiscountPctChange(pctVal) {
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  if (subtotal <= 0) return;

  const rawPct = parseFloat(pctVal);
  if (isNaN(rawPct) || pctVal === '') {
    POSState.orderDiscount = 0;
    const dealInput = document.getElementById('cartDealPriceInput');
    const discInput = document.getElementById('cartDiscountInput');
    if (dealInput) dealInput.value = '';
    if (discInput) discInput.value = '';
  } else {
    const safePct = Math.max(0, Math.min(100, rawPct));
    const discAmt = (safePct / 100) * subtotal;
    const finalDeal = Math.max(0, subtotal - discAmt);

    POSState.orderDiscount = discAmt;
    const discInput = document.getElementById('cartDiscountInput');
    const dealInput = document.getElementById('cartDealPriceInput');
    if (discInput) discInput.value = discAmt > 0 ? discAmt.toFixed(2).replace(/\.00$/, '') : '';
    if (dealInput) dealInput.value = finalDeal > 0 ? finalDeal.toFixed(2).replace(/\.00$/, '') : '';
  }
  calculateTotals();
}

// 4. Quick Presets (5%, 10%, 15%, 20%, ₹500, ₹1000, Round Off)
function applyPresetDiscount(type, val) {
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  if (subtotal <= 0) {
    showToast('Please add items or membership plans to cart first', 'warning');
    return;
  }
  if (type === 'pct') {
    const pctInput = document.getElementById('cartDiscountPctInput');
    if (pctInput) pctInput.value = val;
    onDiscountPctChange(val);
  } else if (type === 'amt') {
    const discInput = document.getElementById('cartDiscountInput');
    if (discInput) discInput.value = val;
    onDiscountAmtChange(val);
  } else if (type === 'round') {
    // Round down to nearest ₹50 or ₹100
    const roundStep = subtotal > 2000 ? 100 : 50;
    const rounded = Math.floor(subtotal / roundStep) * roundStep;
    const dealInput = document.getElementById('cartDealPriceInput');
    if (dealInput) dealInput.value = rounded;
    onDealPriceChange(rounded);
  }
}

// Category Filter Controller
function setupCategoryFilter() {
  document.querySelectorAll('.cat-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      const catId = chip.getAttribute('data-cat');
      filterProducts(catId);
    });
  });
}

function filterProducts(catId) {
  document.querySelectorAll('.product-card').forEach(card => {
    if (catId === 'all' || card.getAttribute('data-cat-id') === catId) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}

// Live Catalog Keyword & SKU Search Filter
function filterCatalogItems(q) {
  const term = (q || '').trim().toLowerCase();
  document.querySelectorAll('.product-card').forEach(card => {
    const name = (card.getAttribute('data-name') || '').toLowerCase();
    if (!term || name.includes(term)) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}

// Member Quick Search & QR Listener (Debounced for Ultra-Smooth Performance)
let memberSearchTimer = null;
function setupMemberSearch() {
  const input = document.getElementById('posMemberInput');
  const resultsBox = document.getElementById('memberSearchResults');

  if (input && resultsBox) {
    input.addEventListener('input', () => {
      clearTimeout(memberSearchTimer);
      const q = input.value.trim();
      if (q.length < 2) {
        resultsBox.style.display = 'none';
        return;
      }

      memberSearchTimer = setTimeout(() => {
        fetch(`api/members.php?action=search&q=${encodeURIComponent(q)}`)
          .then(res => res.text())
          .then(text => {
            try { return JSON.parse(text); } catch(e) { return { success: false, data: [] }; }
          })
          .then(res => {
            if (res.success && res.data && res.data.length > 0) {
              resultsBox.innerHTML = res.data.map(m => {
                const initials = (m.name || 'M').trim().split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                const isAct = m.status === 'active';
                return `
                  <div class="search-result-item" onclick="selectMember(${JSON.stringify(m).replace(/"/g, '&quot;')})">
                    <div class="search-result-avatar">${escapeHTML(initials)}</div>
                    <div class="search-result-info">
                      <div class="search-result-name">${escapeHTML(m.name)} <span style="font-weight:600; color:#B45309; font-size:0.78rem;">(${escapeHTML(m.member_code)})</span></div>
                      <div class="search-result-meta">📞 ${escapeHTML(m.phone || 'No phone')} ${m.locker_no ? `&bull; 🔒 ${escapeHTML(m.locker_no)}` : ''}</div>
                    </div>
                    <span class="search-result-badge" style="background:${isAct ? '#ECFDF5' : '#FEE2E2'}; color:${isAct ? '#065F46' : '#DC2626'};">
                      ${isAct ? 'ACTIVE' : 'EXPIRED'}
                    </span>
                  </div>
                `;
              }).join('');
              resultsBox.style.display = 'block';
            } else {
              resultsBox.innerHTML = '<div style="padding:0.85rem; color:var(--text-muted); font-size:0.85rem; text-align:center;">No matching members found</div>';
              resultsBox.style.display = 'block';
            }
          })
          .catch(() => {});
      }, 150);
    });
  }
}

function selectMember(member) {
  POSState.selectedMember = member;
  const container = document.getElementById('selectedMemberBox');
  if (container) {
    container.innerHTML = `
      <div class="selected-member-card">
        <span>👤 ${escapeHTML(member.name)} (${member.member_code})</span>
        <button style="background:none; border:none; color:var(--danger); cursor:pointer; font-weight:bold;" onclick="clearMember()">✕</button>
      </div>
    `;
  }
  const resultsBox = document.getElementById('memberSearchResults');
  if (resultsBox) resultsBox.style.display = 'none';
  const input = document.getElementById('posMemberInput');
  if (input) input.value = '';
  updateCartMemberNotice();
}

function clearMember() {
  POSState.selectedMember = null;
  const container = document.getElementById('selectedMemberBox');
  if (container) container.innerHTML = '';
  updateCartMemberNotice();
}

function updateCartMemberNotice() {
  const notice = document.getElementById('cartMemberNotice');
  if (!notice) return;
  if (POSState.selectedMember) {
    const m = POSState.selectedMember;
    const initials = (m.name || 'M').trim().split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    notice.innerHTML = `
      <div class="cart-member-assigned-card">
        <div class="member-avatar-circle">${escapeHTML(initials)}</div>
        <div class="member-meta-col">
          <div class="member-meta-top">
            <span class="member-assigned-name">${escapeHTML(m.name)}</span>
            <span class="member-assigned-code">${escapeHTML(m.member_code || 'ID')}</span>
          </div>
          <div class="member-meta-sub">
            <span>📞 ${escapeHTML(m.phone || 'No phone')}</span>
            ${m.locker_no ? `<span> &bull; 🔒 ${escapeHTML(m.locker_no)}</span>` : ''}
          </div>
        </div>
        <button type="button" class="member-assigned-change-btn" onclick="clearMember()" title="Change / Remove Member">
          ✕
        </button>
      </div>
    `;
  } else {
    notice.innerHTML = `
      <div class="cart-member-unassigned-card" onclick="document.getElementById('posMemberInput')?.focus()" title="Click to search member (F2)">
        <div class="unassigned-icon-pulse">👤</div>
        <div class="unassigned-text-col">
          <div class="unassigned-title">No Member Selected</div>
          <div class="unassigned-sub">Click here or press <strong>F2</strong> to search/scan member</div>
        </div>
        <span class="unassigned-arrow">→</span>
      </div>
    `;
  }
}

// Payment Modal Controller
POSState.paymentStatus = 'paid'; // 'paid', 'partial', 'unpaid'
POSState.dueDays = 2;

function setPosPromiseDays(days, btn) {
  POSState.dueDays = days;
  document.querySelectorAll('.pos-due-day-btn').forEach(b => {
    b.classList.remove('active');
    b.style.background = '#FFFFFF';
    b.style.color = '#78350F';
    b.style.border = '1px solid #FCD34D';
  });

  if (btn) {
    btn.classList.add('active');
    btn.style.background = '#D97706';
    btn.style.color = '#FFFFFF';
    btn.style.border = 'none';
  }

  const dateInput = document.getElementById('posDueDateInput');
  const labelEl = document.getElementById('posDueDateLabelPreview');

  if (days === 'custom') {
    if (dateInput) dateInput.focus();
    if (labelEl) labelEl.innerText = 'Select custom calendar date';
    return;
  }

  const targetDate = new Date();
  targetDate.setDate(targetDate.getDate() + parseInt(days));
  const dateStr = targetDate.toISOString().split('T')[0];

  if (dateInput) dateInput.value = dateStr;
  
  const options = { day: 'numeric', month: 'short', year: 'numeric' };
  const formatted = targetDate.toLocaleDateString('en-GB', options);
  if (labelEl) labelEl.innerText = `Promised by ${formatted} (In ${days} days)`;
}

function onPosCustomDueDateChange() {
  const dateInput = document.getElementById('posDueDateInput');
  const labelEl = document.getElementById('posDueDateLabelPreview');
  if (!dateInput || !dateInput.value) return;

  const targetDate = new Date(dateInput.value);
  const today = new Date();
  const diffTime = targetDate.getTime() - today.getTime();
  const diffDays = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));

  POSState.dueDays = diffDays;
  const options = { day: 'numeric', month: 'short', year: 'numeric' };
  const formatted = targetDate.toLocaleDateString('en-GB', options);
  if (labelEl) labelEl.innerText = `Promised by ${formatted} (In ${diffDays} days)`;
}

function setPosPaymentStatus(status) {
  POSState.paymentStatus = status;
  document.querySelectorAll('.pstat-chip').forEach(c => c.classList.remove('active'));

  const activeChip = document.querySelector(`.pstat-chip[data-pstat="${status}"]`);
  if (activeChip) activeChip.classList.add('active');

  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const discountAmt = POSState.orderDiscount || 0;
  const grandTotal = Math.max(0, subtotal - discountAmt);

  const dueBox = document.getElementById('posPartialDueBox');
  const paidInput = document.getElementById('posPaidAmountInput');

  if (status === 'paid') {
    if (dueBox) dueBox.style.display = 'none';
    if (paidInput) paidInput.value = grandTotal.toFixed(2);
  } else if (status === 'partial') {
    if (dueBox) dueBox.style.display = 'block';
    if (paidInput) {
      paidInput.value = (grandTotal / 2).toFixed(2);
      calculatePosDueBalance();
    }
    setPosPromiseDays(2);
  } else if (status === 'unpaid') {
    if (dueBox) dueBox.style.display = 'block';
    if (paidInput) {
      paidInput.value = '0.00';
      calculatePosDueBalance();
    }
    setPosPromiseDays(2);
  }
}

function calculatePosDueBalance() {
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const discountAmt = POSState.orderDiscount || 0;
  const grandTotal = Math.max(0, subtotal - discountAmt);

  const paidInput = document.getElementById('posPaidAmountInput');
  const paidVal = parseFloat(paidInput ? paidInput.value : 0) || 0;
  const due = Math.max(0, grandTotal - paidVal);

  const display = document.getElementById('posDueBalanceDisplay');
  if (display) display.innerText = '₹' + due.toFixed(2);
}

function setupPaymentModal() {
  document.querySelectorAll('.pm-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.pm-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      POSState.paymentMethod = chip.getAttribute('data-pm');
      
      const splitBox = document.getElementById('splitPaymentContainer');
      if (splitBox) {
        splitBox.style.display = (POSState.paymentMethod === 'Split') ? 'block' : 'none';
      }
    });
  });
}

function triggerPayment() {
  if (POSState.cart.length === 0) {
    showToast('Cart is empty. Please add items to sale.', 'warning');
    return;
  }

  // Strict Validation: Billing NOT allowed until a member is selected
  if (!POSState.selectedMember) {
    showToast('⚠️ Billing ke liye pehle Member select / search karein! (F2)', 'warning');
    const memberInput = document.getElementById('posMemberInput');
    if (memberInput) {
      memberInput.focus();
      memberInput.classList.add('input-highlight-pulse');
      setTimeout(() => memberInput.classList.remove('input-highlight-pulse'), 2500);
    }
    const notice = document.getElementById('cartMemberNotice');
    if (notice) {
      notice.classList.add('pulse-alert');
      setTimeout(() => notice.classList.remove('pulse-alert'), 2500);
    }
    return;
  }

  calculateTotals();

  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const discountAmt = POSState.orderDiscount || 0;
  const grandTotal = Math.max(0, subtotal - discountAmt);

  const payableEl = document.getElementById('checkoutPayableTotal');
  if (payableEl) payableEl.innerText = '₹' + grandTotal.toFixed(2);

  const memberPillEl = document.getElementById('checkoutMemberName');
  if (memberPillEl && POSState.selectedMember) {
    memberPillEl.innerText = `${POSState.selectedMember.name} (${POSState.selectedMember.member_code})`;
  }

  const itemCountEl = document.getElementById('checkoutItemCountSummary');
  if (itemCountEl) {
    const totalItemsCount = POSState.cart.reduce((sum, item) => sum + item.qty, 0);
    itemCountEl.innerText = `🛒 ${totalItemsCount} ${totalItemsCount === 1 ? 'Item' : 'Items'} in Cart`;
  }

  const breakdownEl = document.getElementById('checkoutDiscountBreakdown');
  if (breakdownEl) {
    if (discountAmt > 0) {
      const discPct = subtotal > 0 ? ((discountAmt / subtotal) * 100).toFixed(1) : 0;
      breakdownEl.style.display = 'block';
      breakdownEl.innerText = `🎉 Saved ₹${discountAmt.toFixed(2)} (${discPct}% OFF)`;
    } else {
      breakdownEl.style.display = 'none';
    }
  }

  setPosPaymentStatus('paid');
  openModal('paymentModal');
}

// Complete Sale Transaction
function processCheckout() {
  // ── Anti-duplicate guard: block if already processing ──────────────────
  if (processCheckout._isProcessing) {
    showToast('⏳ Processing... please wait!', 'warning');
    return;
  }
  processCheckout._isProcessing = true;

  // Disable the confirm button immediately
  const confirmBtn = document.getElementById('posConfirmPayBtn');
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '⏳ Processing...';
  }

  const _resetBtn = () => {
    processCheckout._isProcessing = false;
    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '✅ CONFIRM PAYMENT';
    }
  };

  // Strict Validation: Billing NOT allowed until a member is selected
  if (!POSState.selectedMember) {
    showToast('⚠️ Member select karna mandatory hai! Pehle member search ya scan karein.', 'danger');
    closeModal('paymentModal');
    const memberInput = document.getElementById('posMemberInput');
    if (memberInput) {
      memberInput.focus();
      memberInput.classList.add('input-highlight-pulse');
      setTimeout(() => memberInput.classList.remove('input-highlight-pulse'), 2500);
    }
    _resetBtn();
    return;
  }

  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const discountAmt = POSState.orderDiscount || 0;
  const tax = 0; // Tax is inclusive / not applied at POS level (set tax rate in Settings)
  const grandTotal = Math.max(0, subtotal - discountAmt + tax);

  let paidAmount = grandTotal;
  if (POSState.paymentStatus === 'partial') {
    const paidInput = document.getElementById('posPaidAmountInput');
    paidAmount = parseFloat(paidInput ? paidInput.value : 0) || 0;
  } else if (POSState.paymentStatus === 'unpaid') {
    paidAmount = 0;
  }

  const notes = document.getElementById('posBillNotes') ? document.getElementById('posBillNotes').value.trim() : '';
  const dueDate = document.getElementById('posDueDateInput') ? document.getElementById('posDueDateInput').value : '';

  const payload = {
    member_id: POSState.selectedMember ? POSState.selectedMember.id : null,
    subtotal: subtotal,
    discount: POSState.orderDiscount,
    tax: tax,
    total: grandTotal,
    paid_amount: paidAmount,
    due_date: dueDate,
    due_days: POSState.dueDays,
    payment_status: POSState.paymentStatus,
    payment_method: POSState.paymentMethod,
    notes: notes,
    items: POSState.cart
  };

  // If offline, store locally!
  if (!navigator.onLine) {
    if (window.OfflineManager) {
      window.OfflineManager.queueTransaction(payload);
      showToast('Offline Mode: Transaction saved locally!', 'warning');
      clearCart();
      closeModal('paymentModal');
      _resetBtn();
      return;
    }
  }

  fetch('api/pos.php?action=checkout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.text())
  .then(text => {
    try { return JSON.parse(text); } catch(e) { return { success: false, message: text }; }
  })
  .then(res => {
    if (res.success) {
      showToast(res.message || 'Payment Completed Successfully!', 'success');
      closeModal('paymentModal');
      printThermalReceipt(res.data);
      clearCart();
      clearMember();
      // Reset flag after success (cart cleared = ready for next sale)
      processCheckout._isProcessing = false;
    } else {
      showToast(res.message || 'Payment failed', 'danger');
      _resetBtn();
    }
  })
  .catch(err => {
    showToast('Network error: Queued offline', 'warning');
    _resetBtn();
  });
}
processCheckout._isProcessing = false;


// Print Thermal Receipt
function printThermalReceipt(data) {
  if (data && data.invoice_no) {
    window.lastGeneratedInvoiceNo = data.invoice_no;
  }
  const container = document.getElementById('thermalReceiptModalContent');
  if (!container) return;

  const isDue = (data.due_amount && parseFloat(data.due_amount) > 0);
  const statusBadge = data.payment_status ? data.payment_status.toUpperCase() : 'PAID';

  container.innerHTML = `
    <div class="print-receipt-container" id="printableReceiptArea">
      <div class="thermal-header">
        <div class="thermal-title">THE CLUB 777®</div>
        <div>GYM &bull; SWIM &bull; SPA &bull; MORE</div>
        <div>Phone: 8053576777, 8053570777</div>
        <div>Inv: ${data.invoice_no} | Date: ${data.date}</div>
        <div>Member: ${data.member_name || 'Walk-in'}</div>
        <div style="margin-top:4px;"><span style="background:${isDue ? '#FEF2F2' : '#ECFDF5'}; color:${isDue ? '#DC2626' : '#059669'}; border:1px solid ${isDue ? '#FCA5A5' : '#6EE7B7'}; padding:2px 6px; border-radius:4px; font-weight:bold; font-size:11px;">STATUS: ${statusBadge}</span></div>
      </div>
      <table class="thermal-table">
        <thead>
          <tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th></tr>
        </thead>
        <tbody>
          ${data.items.map(i => `
            <tr>
              <td>${escapeHTML(i.name)}</td>
              <td class="text-right">${i.qty}</td>
              <td class="text-right">₹${(i.price * i.qty).toFixed(2)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
      <div class="thermal-divider"></div>
      <div style="display:flex; justify-content:space-between;"><span>Subtotal:</span><span>₹${parseFloat(data.subtotal).toFixed(2)}</span></div>
      ${parseFloat(data.discount) > 0 ? `<div style="display:flex; justify-content:space-between; color:#059669;"><span>Discount:</span><span>- ₹${parseFloat(data.discount).toFixed(2)}</span></div>` : ''}
      <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:14px; margin-top:4px;">
        <span>TOTAL BILL:</span><span>₹${parseFloat(data.total).toFixed(2)}</span>
      </div>
      <div style="display:flex; justify-content:space-between; margin-top:4px; color:#059669; font-weight:bold;">
        <span>AMOUNT PAID:</span><span>₹${parseFloat(data.paid_amount || data.total).toFixed(2)}</span>
      </div>
      ${isDue ? `
        <div style="display:flex; justify-content:space-between; margin-top:2px; color:#DC2626; font-weight:bold; background:#FEE2E2; padding:2px 4px; border-radius:4px;">
          <span>PENDING DUE BALANCE:</span><span>₹${parseFloat(data.due_amount).toFixed(2)}</span>
        </div>
        ${data.due_date ? `
          <div style="display:flex; justify-content:space-between; margin-top:2px; color:#92400E; font-size:11px; font-weight:bold;">
            <span>PROMISED DUE DATE:</span><span>${data.due_date_formatted || data.due_date}</span>
          </div>
        ` : ''}
      ` : ''}
      <div style="display:flex; justify-content:space-between; margin-top:4px;"><span>Payment Mode:</span><span>${data.payment_method}</span></div>
      <div class="thermal-footer">
        *** Thank you for working out with us! ***
      </div>
    </div>
  `;

  // WhatsApp Auto Trigger & Button Link Generation
  const rawPhone = (data.member_phone || (POSState.selectedMember ? POSState.selectedMember.phone : '') || '').replace(/[^0-9]/g, '');
  const clientPhone = (rawPhone.length === 10) ? ('91' + rawPhone) : rawPhone;
  const appBase = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'https://gym.ethicscomputer.in'
    : window.location.origin;
  const receiptUrl = `${appBase}/index.php?page=receipt&invoice_no=${encodeURIComponent(data.invoice_no)}`;
  const pdfDownloadUrl = `${appBase}/api/generate_invoice_pdf.php?invoice_no=${encodeURIComponent(data.invoice_no)}&output=download`;
  const dueNotice = (data.due_amount && parseFloat(data.due_amount) > 0) ? `\n🔴 *Pending Due:* ₹${parseFloat(data.due_amount).toFixed(2)}` : '\n🟢 *Status:* FULLY PAID (₹0.00 Due)';
  const waBodyRaw = `📄 *Official Fee Receipt — THE CLUB 777®*\n\n`
    + `🧾 *Invoice No:* ${data.invoice_no}\n`
    + `👤 *Member:* ${data.member_name || 'Member'}\n`
    + `💰 *Total Amount:* ₹${parseFloat(data.total).toFixed(2)}\n`
    + `🟢 *Amount Paid:* ₹${parseFloat(data.paid_amount || data.total).toFixed(2)}${dueNotice}\n`
    + `💳 *Payment Mode:* ${data.payment_method || 'Cash'}\n`
    + `📅 *Date:* ${data.date || 'Today'}\n\n`
    + `🔗 *View Official Receipt Online:* \n${receiptUrl}\n\n`
    + `📥 *Download Official A4 PDF Invoice:* \n${pdfDownloadUrl}\n\n`
    + `📍 Behind Shehnai Garden, Near Railway Station, Jhajjar - 124103\n`
    + `📞 Helpline: 8053576777, 8053570777\n\n`
    + `_Stay Strong & Keep Transforming!_ 💪`;
  const waBody = encodeURIComponent(waBodyRaw);
  const waUrl = clientPhone ? `https://wa.me/${clientPhone}?text=${waBody}` : `https://wa.me/?text=${waBody}`;

  const waBtn = document.getElementById('posWhatsAppDirectBtn');
  if (waBtn) {
    waBtn.href = waUrl;
    waBtn.style.display = 'flex';
  }

  // Auto-send PDF Invoice Document + Text Summary via WhatsApp
  const autoSendCheckbox = document.getElementById('posSendWhatsappInvoice') || document.getElementById('posAutoSendWhatsApp');
  if (autoSendCheckbox && autoSendCheckbox.checked && clientPhone) {
    // Step 1: Send the actual PDF document as attachment
    fetch('api/whatsapp.php?action=send_document', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: clientPhone,
        invoice_no: data.invoice_no,
        caption: waBodyRaw
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showToast('✅ PDF Invoice sent to member on WhatsApp!', 'success');
      } else {
        // Fallback: try text message only
        fetch('api/whatsapp.php?action=send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ phone: clientPhone, message: waBodyRaw })
        })
        .then(r => r.json())
        .then(r2 => {
          if (r2.success) {
            showToast('✅ Text invoice sent on WhatsApp (PDF fallback)', 'info');
          } else {
            window.open(waUrl, '_blank');
          }
        })
        .catch(() => window.open(waUrl, '_blank'));
      }
    })
    .catch(() => {
      window.open(waUrl, '_blank');
    });
  }

  openModal('receiptModal');
}

// Desktop Keyboard Shortcuts Manager (F1-F8, ESC, ENTER)
function initKeyboardShortcuts() {
  document.addEventListener('keydown', (e) => {
    // F1 -> Switch to POS
    if (e.key === 'F1') {
      e.preventDefault();
      window.location.href = 'index.php?page=pos';
    }
    // F2 -> Search Member
    if (e.key === 'F2') {
      e.preventDefault();
      const input = document.getElementById('posMemberInput');
      if (input) input.focus();
    }
    // F3 -> New Member
    if (e.key === 'F3') {
      e.preventDefault();
      openModal('newMemberModal');
    }
    // F4 -> Pay Now / Checkout
    if (e.key === 'F4') {
      e.preventDefault();
      triggerPayment();
    }
    // F5 -> Check In
    if (e.key === 'F5') {
      e.preventDefault();
      window.location.href = 'index.php?page=attendance';
    }
    // ESC -> Close Modal
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.active').forEach(m => closeModal(m.id));
    }
    // ENTER -> Confirm Payment if Payment Modal is open
    if (e.key === 'Enter') {
      const pModal = document.getElementById('paymentModal');
      if (pModal && pModal.classList.contains('active')) {
        e.preventDefault();
        processCheckout();
      }
    }
  });
}

function escapeHTML(str) {
  return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Global Window Exports for Inline HTML Handlers
window.POSState = POSState;
window.onDealPriceChange = onDealPriceChange;
window.onDiscountAmtChange = onDiscountAmtChange;
window.onDiscountPctChange = onDiscountPctChange;
window.applyPresetDiscount = applyPresetDiscount;
window.addToCart = addToCart;
window.updateCartQty = updateCartQty;
window.removeFromCart = removeFromCart;
window.clearCart = clearCart;
window.selectMember = selectMember;
window.clearMember = clearMember;
window.setPosPaymentStatus = setPosPaymentStatus;
window.calculatePosDueBalance = calculatePosDueBalance;
window.setPosPromiseDays = setPosPromiseDays;
window.onPosCustomDueDateChange = onPosCustomDueDateChange;
window.triggerPayment = triggerPayment;
window.processCheckout = processCheckout;
window.filterCatalogItems = filterCatalogItems;

