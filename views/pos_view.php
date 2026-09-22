<!-- ============================================================
     THE CLUB 777® — COMMERCIAL TOUCH POS INTERFACE (2026)
     ============================================================ -->
<?php
$db = getDB();

// Fetch Categories
$categories = $db->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// Fetch Products & Services
$products = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' 
    ORDER BY p.id ASC
")->fetchAll();

// Fetch Swimming Pool Plans, Gym Membership Types, Steam, Sauna, VIP Combos & PT Packages
$gymPlans = $db->query("SELECT * FROM membership_types WHERE status = 'active' AND (service_type = 'gym' OR service_type IS NULL) ORDER BY duration_days ASC, price ASC")->fetchAll();
$poolPlans = $db->query("SELECT * FROM pool_plans WHERE status = 'active' ORDER BY duration_days ASC, price ASC")->fetchAll();
$steamPlans = $db->query("SELECT * FROM membership_types WHERE status = 'active' AND service_type = 'steam' ORDER BY duration_days ASC, price ASC")->fetchAll();
$saunaPlans = $db->query("SELECT * FROM membership_types WHERE status = 'active' AND service_type = 'sauna' ORDER BY duration_days ASC, price ASC")->fetchAll();
$vipPlans = $db->query("SELECT * FROM membership_types WHERE status = 'active' AND (service_type = 'vip_combo' OR category = 'vip') ORDER BY duration_days ASC, price ASC")->fetchAll();
$ptPackages = $db->query("SELECT * FROM pt_packages ORDER BY id ASC")->fetchAll();

$totalItems = count($gymPlans) + count($poolPlans) + count($steamPlans) + count($saunaPlans) + count($vipPlans) + count($ptPackages) + count($products);
$currency = getSetting('currency_symbol', '₹');

// Auto-select member if passed via URL (from Trials, Profile, Dashboard, etc.)
$preselectedMember = null;
$preselectedMemberId = intval($_GET['member_id'] ?? 0);
if ($preselectedMemberId > 0) {
    $stmtM = $db->prepare("SELECT id, name, member_code, phone, status, photo_url FROM members WHERE id = ? LIMIT 1");
    $stmtM->execute([$preselectedMemberId]);
    $preselectedMember = $stmtM->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>

<div class="pos-container">
  
  <!-- ========================================================= -->
  <!-- LEFT ZONE: Search, Categories & Catalog Grid              -->
  <!-- ========================================================= -->
  <main class="pos-main-zone" role="main">
    
    <!-- Top Member Search & Quick Barcode Header -->
    <header class="pos-search-bar" role="search">
      <!-- Member Quick Search Box -->
      <div class="pos-member-search">
        <span class="search-icon" aria-hidden="true">🔍</span>
        <input type="text" id="posMemberInput" placeholder="Search Member by Name, Phone, Code or Biometric ID (F2)..." autocomplete="off" aria-label="Search Member">
        <div id="memberSearchResults" class="pos-search-dropdown" style="display:none;"></div>
      </div>

      <!-- Live Product Catalog Search / Filter Box -->
      <div class="pos-catalog-filter-wrap">
        <span class="pos-catalog-filter-icon" aria-hidden="true">⚡</span>
        <input type="text" id="posCatalogSearch" placeholder="Filter Items / SKU..." oninput="filterCatalogItems(this.value)" aria-label="Filter items in catalog">
      </div>

      <div id="selectedMemberBox" class="pos-selected-member-wrap"></div>

      <!-- Camera QR Scanner Button -->
      <button type="button" class="pos-btn-action pos-btn-scan" onclick="Scanner.openCameraScanner((code) => { selectMember({id:1001, name:'Rahul Kumar', member_code:code, phone:'9876500001', status:'active'}); })" title="Scan Member QR/Barcode with Camera">
        <span aria-hidden="true">📷</span> <span>Scan QR</span>
      </button>

      <!-- Register New Member Button -->
      <button type="button" class="pos-btn-action pos-btn-new-member" onclick="openModal('newMemberModal')" title="Register New Member (F3)">
        <span aria-hidden="true">+</span> <span>New Member</span>
      </button>
    </header>

    <!-- Category Chips Horizontal Bar -->
    <nav class="pos-categories" role="tablist" aria-label="Product and Membership Categories">
      <button type="button" class="cat-chip active" data-cat="all">🌟 All (<?= $totalItems ?>)</button>
      <button type="button" class="cat-chip cat-gym" data-cat="gym_plans">🏋️ Gym (<?= count($gymPlans) ?>)</button>
      <button type="button" class="cat-chip cat-pool" data-cat="pool_plans">🏊 Pool (<?= count($poolPlans) ?>)</button>
      <button type="button" class="cat-chip cat-steam" data-cat="steam_plans">🧖 Steam (<?= count($steamPlans) ?>)</button>
      <button type="button" class="cat-chip cat-sauna" data-cat="sauna_plans">♨️ Sauna (<?= count($saunaPlans) ?>)</button>
      <button type="button" class="cat-chip cat-vip" data-cat="vip_plans">👑 VIP Combo (<?= count($vipPlans) ?>)</button>
      <button type="button" class="cat-chip cat-pt" data-cat="pt_packages">💪 PT (<?= count($ptPackages) ?>)</button>
      <?php foreach ($categories as $cat): ?>
        <?php if (!in_array($cat['id'], [1, 6, 7, 8, 9])): ?>
          <button type="button" class="cat-chip" data-cat="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></button>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <!-- Product & Plans Responsive Grid -->
    <section class="pos-product-grid-container" aria-label="POS Catalog Grid">
      <div class="pos-product-grid" id="posProductGrid">
        
        <!-- 1. GYM MEMBERSHIP PLANS CARDS -->
        <?php foreach ($gymPlans as $gp): ?>
          <?php 
            $gpItem = [
              'id' => $gp['id'],
              'name' => $gp['title'],
              'price' => floatval($gp['price']),
              'type' => 'membership',
              'duration_days' => $gp['duration_days'],
              'service_type' => 'gym',
              'free_gifts' => $gp['free_gifts'] ?? ''
            ];
          ?>
          <div class="product-card card-gym" data-cat-id="gym_plans" data-name="<?= htmlspecialchars(strtolower($gp['title'] . ' gym fitness workout cardio')) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($gpItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-gym">🏋️ GYM</span>
            <div class="product-content">
              <div class="product-name"><?= htmlspecialchars($gp['title']) ?></div>
              <div class="product-stock">
                ⏱️ <?= $gp['duration_days'] ?> Days <?= !empty($gp['max_freeze_count']) ? '&bull; Max ' . $gp['max_freeze_count'] . ' Freezes' : '' ?>
              </div>
              <?php if (!empty($gp['free_gifts'])): ?>
                <div class="product-gift-tag gift-gym">
                  🎁 <?= htmlspecialchars($gp['free_gifts']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price">₹<?= number_format($gp['price'], 2) ?></span>
                <?php if (!empty($gp['original_price']) && $gp['original_price'] > $gp['price']): ?>
                  <del class="product-orig-price">₹<?= number_format($gp['original_price'], 0) ?></del>
                <?php endif; ?>
              </div>
              <span class="add-item-btn" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- 2. SWIMMING POOL PLANS CARDS -->
        <?php foreach ($poolPlans as $pp): ?>
          <?php 
            $ppItem = [
              'id' => $pp['id'],
              'name' => $pp['title'],
              'price' => floatval($pp['price']),
              'type' => 'pool_plan',
              'duration_days' => $pp['duration_days'],
              'service_type' => 'pool',
              'free_gifts' => $pp['free_gifts'] ?? ''
            ];
          ?>
          <div class="product-card card-pool" data-cat-id="pool_plans" data-name="<?= htmlspecialchars(strtolower($pp['title'] . ' pool swim swimming heated')) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($ppItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-pool">🏊 POOL</span>
            <div class="product-content">
              <div class="product-name"><?= htmlspecialchars($pp['title']) ?></div>
              <div class="product-stock">
                ⏱️ <?= $pp['duration_days'] ?> Days &bull; <?= htmlspecialchars($pp['slot_timing'] ?? 'All Slots') ?>
              </div>
              <?php if (!empty($pp['free_gifts'])): ?>
                <div class="product-gift-tag gift-pool">
                  🎁 <?= htmlspecialchars($pp['free_gifts']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price" style="color:#0284C7;">₹<?= number_format($pp['price'], 2) ?></span>
                <?php if (!empty($pp['original_price']) && $pp['original_price'] > $pp['price']): ?>
                  <del class="product-orig-price">₹<?= number_format($pp['original_price'], 0) ?></del>
                <?php endif; ?>
              </div>
              <span class="add-item-btn" style="color:#0284C7;" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- 3. STEAM BATH PLANS CARDS -->
        <?php foreach ($steamPlans as $sp): ?>
          <?php 
            $spItem = [
              'id' => $sp['id'],
              'name' => $sp['title'],
              'price' => floatval($sp['price']),
              'type' => 'membership',
              'duration_days' => $sp['duration_days'],
              'service_type' => 'steam',
              'coupons_count' => $sp['coupons_count'] ?? 0
            ];
          ?>
          <div class="product-card card-steam" data-cat-id="steam_plans" data-name="<?= htmlspecialchars(strtolower($sp['title'] . ' steam bath spa')) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($spItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-steam">🧖 STEAM</span>
            <div class="product-content">
              <div class="product-name" style="color:#0369A1;"><?= htmlspecialchars($sp['title']) ?></div>
              <div class="product-stock" style="color:#0284C7;">
                ⏱️ <?= $sp['duration_days'] ?> Days &bull; 🎟️ <?= $sp['coupons_count'] ?> Coupons
              </div>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price" style="color:#0369A1;">₹<?= number_format($sp['price'], 2) ?></span>
              </div>
              <span class="add-item-btn" style="color:#0369A1;" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- 4. SAUNA BATH PLANS CARDS -->
        <?php foreach ($saunaPlans as $sn): ?>
          <?php 
            $snItem = [
              'id' => $sn['id'],
              'name' => $sn['title'],
              'price' => floatval($sn['price']),
              'type' => 'membership',
              'duration_days' => $sn['duration_days'],
              'service_type' => 'sauna',
              'coupons_count' => $sn['coupons_count'] ?? 0
            ];
          ?>
          <div class="product-card card-sauna" data-cat-id="sauna_plans" data-name="<?= htmlspecialchars(strtolower($sn['title'] . ' sauna bath dry')) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($snItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-sauna">♨️ SAUNA</span>
            <div class="product-content">
              <div class="product-name" style="color:#92400E;"><?= htmlspecialchars($sn['title']) ?></div>
              <div class="product-stock" style="color:#B45309;">
                ⏱️ <?= $sn['duration_days'] ?> Days &bull; 🎟️ <?= $sn['coupons_count'] ?> Coupons
              </div>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price" style="color:#D97706;">₹<?= number_format($sn['price'], 2) ?></span>
              </div>
              <span class="add-item-btn" style="color:#D97706;" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- 5. VIP ALL-INCLUSIVE COMBOS CARDS -->
        <?php foreach ($vipPlans as $vp): ?>
          <?php 
            $vpItem = [
              'id' => $vp['id'],
              'name' => $vp['title'],
              'price' => floatval($vp['price']),
              'type' => 'membership',
              'duration_days' => $vp['duration_days'],
              'service_type' => 'vip_combo',
              'free_gifts' => $vp['free_gifts'] ?? ''
            ];
          ?>
          <div class="product-card card-vip" data-cat-id="vip_plans" data-name="<?= htmlspecialchars(strtolower($vp['title'] . ' vip combo all in one jacuzzi')) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($vpItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-vip">👑 VIP COMBO</span>
            <div class="product-content">
              <div class="product-name" style="color:#9D174D;"><?= htmlspecialchars($vp['title']) ?></div>
              <div class="product-stock" style="color:#BE185D;">
                ⭐ Gym + Pool + Steam + Sauna + Jacuzzi
              </div>
              <?php if (!empty($vp['free_gifts'])): ?>
                <div class="product-gift-tag gift-vip">
                  🎁 <?= htmlspecialchars($vp['free_gifts']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price" style="color:#BE185D;">₹<?= number_format($vp['price'], 2) ?></span>
                <?php if (!empty($vp['original_price']) && $vp['original_price'] > $vp['price']): ?>
                  <del class="product-orig-price">₹<?= number_format($vp['original_price'], 0) ?></del>
                <?php endif; ?>
              </div>
              <span class="add-item-btn" style="color:#BE185D;" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- 6. PT PACKAGES CARDS -->
        <?php foreach ($ptPackages as $pt): ?>
          <?php 
            $ptSessions = intval($pt['sessions_count'] ?? $pt['sessions_total'] ?? 12);
            $ptValidity = intval($pt['validity_days'] ?? 30);
            $ptItem = [
              'id' => $pt['id'],
              'name' => $pt['title'],
              'price' => floatval($pt['price']),
              'type' => 'pt_package',
              'sessions_total' => $ptSessions,
              'validity_days' => $ptValidity
            ];
          ?>
          <div class="product-card card-pt" data-cat-id="pt_packages" data-name="<?= htmlspecialchars(strtolower($pt['title'] . ' pt trainer personal coaching')) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($ptItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-pt">💪 PT</span>
            <div class="product-content">
              <div class="product-name" style="color:#6B21A8;"><?= htmlspecialchars($pt['title']) ?></div>
              <div class="product-stock" style="color:#7E22CE;">
                🔥 <?= $ptSessions ?> Sessions &bull; <?= $ptValidity ?> Days
              </div>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price" style="color:#7E22CE;">₹<?= number_format($pt['price'], 2) ?></span>
              </div>
              <span class="add-item-btn" style="color:#7E22CE;" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- 7. RETAIL PRODUCTS & SUPPLEMENTS CARDS -->
        <?php foreach ($products as $prod): ?>
          <?php 
            $prodItem = [
              'id' => $prod['id'],
              'name' => $prod['name'],
              'price' => floatval($prod['price']),
              'type' => 'product',
              'stock' => $prod['stock_quantity'] ?? 0
            ];
          ?>
          <div class="product-card card-retail" data-cat-id="<?= $prod['category_id'] ?>" data-name="<?= htmlspecialchars(strtolower($prod['name'] . ' ' . ($prod['barcode'] ?? '') . ' ' . ($prod['sku'] ?? ''))) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($prodItem)) ?>)" role="button" tabindex="0">
            <span class="product-type-badge badge-retail">📦 <?= ucfirst($prod['type'] ?? 'Item') ?></span>
            <div class="product-content">
              <div class="product-name"><?= htmlspecialchars($prod['name']) ?></div>
              <div class="product-stock">Stock: <?= $prod['stock_quantity'] ?> <?= htmlspecialchars($prod['unit'] ?? 'pcs') ?></div>
            </div>
            <div class="product-footer">
              <div class="product-price-box">
                <span class="product-price">₹<?= number_format($prod['price'], 2) ?></span>
              </div>
              <span class="add-item-btn" aria-hidden="true">+</span>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    </section>

    <!-- POS Footer Keyboard Shortcuts Bar -->
    <footer class="pos-keyboard-shortcuts" aria-label="POS Keyboard Shortcuts">
      <span class="shortcut-tag"><kbd>F1</kbd> POS</span>
      <span class="shortcut-tag"><kbd>F2</kbd> Search Member</span>
      <span class="shortcut-tag"><kbd>F3</kbd> New Member</span>
      <span class="shortcut-tag"><kbd>F4</kbd> Checkout</span>
      <span class="shortcut-tag"><kbd>ESC</kbd> Close</span>
      <span class="shortcut-tag"><kbd>ENTER</kbd> Complete</span>
    </footer>
  </main>

  <!-- ========================================================= -->
  <!-- RIGHT ZONE: Persistent Cart Sidebar                       -->
  <!-- ========================================================= -->
  <aside class="pos-cart-zone" id="posCartZone" aria-label="Shopping Cart">
    
    <!-- Cart Top Header -->
    <div class="cart-header">
      <span class="cart-title">
        <span aria-hidden="true">🛒</span> 
        <span>Live Billing Cart</span>
        <span id="cartCountBadge" class="cart-count-badge" style="display:none;">0</span>
      </span>
      <button type="button" class="clear-cart-btn" onclick="clearCart()" title="Clear all items in cart">
        🗑️ Clear Cart
      </button>
    </div>

    <!-- Member Assignment Status Banner in Cart -->
    <div id="cartMemberNotice" class="cart-member-notice-wrap"></div>

    <!-- Dynamic Cart Items List -->
    <div class="cart-items-list" id="cartItemsContainer" role="region" aria-live="polite">
      <!-- Items dynamically populated via pos.js -->
    </div>

    <!-- Cart Summary & Auto-Calculating Deal/Discount Footer -->
    <div class="cart-summary-footer">
      <div class="summary-row">
        <span style="font-weight:700; color:var(--text-secondary);">Subtotal (Original)</span>
        <span id="subtotalVal" class="font-mono" style="font-weight:800; font-size:1.05rem;">₹0.00</span>
      </div>

      <!-- Auto-Calculating Discount Box (Deal Amount / ₹ Disc / % Disc) -->
      <div class="deal-discount-card" style="background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px; padding:0.6rem 0.75rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
          <span style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.04em;">
            🏷️ Discount &amp; Deal Price
          </span>
          <span id="discountBadgeSummary" class="badge" style="background:#ECFDF5; color:#065F46; font-size:0.7rem; font-weight:800; padding:0.15rem 0.45rem; display:none;">
            0% OFF
          </span>
        </div>

        <!-- 3-Way Synchronized Inputs: Deal Amount vs Flat Disc vs Disc % -->
        <div style="display:grid; grid-template-columns:1.2fr 1fr 0.9fr; gap:0.4rem;">
          <div>
            <label style="font-size:0.68rem; font-weight:800; color:#B45309; display:block; margin-bottom:2px;">
              🤝 Deal Amount (₹)
            </label>
            <input type="number" step="1" id="cartDealPriceInput" class="form-control font-mono" placeholder="Final ₹" oninput="onDealPriceChange(this.value)" style="height:32px; font-size:0.82rem; padding:0.2rem 0.4rem; font-weight:800; border-color:#FDE68A; background:#FFFBEB; color:#92400E;" title="Type final negotiated price (itne me diya)">
          </div>

          <div>
            <label style="font-size:0.68rem; font-weight:800; color:var(--text-secondary); display:block; margin-bottom:2px;">
              Discount (₹)
            </label>
            <input type="number" step="1" id="cartDiscountInput" class="form-control font-mono" placeholder="₹ 0" oninput="onDiscountAmtChange(this.value)" style="height:32px; font-size:0.82rem; padding:0.2rem 0.4rem; font-weight:700;">
          </div>

          <div>
            <label style="font-size:0.68rem; font-weight:800; color:var(--text-secondary); display:block; margin-bottom:2px;">
              Disc (%)
            </label>
            <input type="number" step="0.1" max="100" id="cartDiscountPctInput" class="form-control font-mono" placeholder="0%" oninput="onDiscountPctChange(this.value)" style="height:32px; font-size:0.82rem; padding:0.2rem 0.4rem; font-weight:700;">
          </div>
        </div>

        <!-- Quick Discount Presets -->
        <div style="display:flex; gap:0.25rem; flex-wrap:wrap; margin-top:0.45rem;">
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('pct', 5)">5%</button>
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('pct', 10)">10%</button>
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('pct', 15)">15%</button>
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('pct', 20)">20%</button>
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('amt', 500)">-₹500</button>
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('amt', 1000)">-₹1000</button>
          <button type="button" class="pos-preset-disc-btn" onclick="applyPresetDiscount('round', 0)">Round Off</button>
        </div>
      </div>

      <!-- Grand Total Row -->
      <div class="summary-row total">
        <span>Total Payable</span>
        <span id="grandTotalVal" class="font-mono">₹0.00</span>
      </div>

      <!-- High-Impact Pay Button -->
      <button type="button" class="pay-now-btn" onclick="triggerPayment()">
        <span>💳 PAY NOW (</span><span id="payNowAmount" class="font-mono">₹0.00</span><span>)</span>
      </button>
    </div>
  </aside>

</div>

<!-- ========================================================= -->
  <!-- PAYMENT CHECKOUT MODAL                                    -->
  <!-- ========================================================= -->
<div class="modal" id="paymentModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('paymentModal')" aria-hidden="true"></div>
  <div class="card payment-modal-content" role="dialog" aria-labelledby="modalTitlePayment" aria-modal="true">
    
    <!-- Modal Header -->
    <div class="modal-header-flex">
      <div class="modal-title-wrap">
        <div class="modal-icon-badge" aria-hidden="true">💳</div>
        <div>
          <h3 id="modalTitlePayment" class="modal-title">Complete Checkout</h3>
          <span class="modal-subtitle">Settle bill, choose collection mode &amp; record sale</span>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('paymentModal')" aria-label="Close dialog">✕</button>
    </div>

    <!-- Hero Bill Summary Banner -->
    <div class="payable-total-banner">
      <div class="payable-info-col">
        <div class="payable-member-pill" id="checkoutMemberPill">
          👤 <span id="checkoutMemberName">Guest Member</span>
        </div>
        <div class="payable-items-count" id="checkoutItemCountSummary">🛒 0 Items in Cart</div>
      </div>
      <div class="payable-amount-col">
        <div class="payable-label">Total Bill Payable</div>
        <div class="payable-amount font-mono" id="checkoutPayableTotal">₹0.00</div>
        <div id="checkoutDiscountBreakdown" class="payable-discount-pill" style="display:none;"></div>
      </div>
    </div>

    <!-- Payment Status / Collection Mode Selector -->
    <div class="form-group" style="margin-bottom:0.75rem;">
      <label class="form-label" style="font-size:0.78rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary); letter-spacing:0.04em;">
        1. Payment Status / Collection Mode *
      </label>
      <div class="payment-status-selector">
        <div class="pstat-chip pstat-paid active" data-pstat="paid" onclick="setPosPaymentStatus('paid')" role="button" tabindex="0">
          <span class="pstat-title">🟢 FULL PAID</span>
          <span class="pstat-sub">100% Settled</span>
        </div>
        <div class="pstat-chip pstat-partial" data-pstat="partial" onclick="setPosPaymentStatus('partial')" role="button" tabindex="0">
          <span class="pstat-title">🟡 PARTIAL / DUE</span>
          <span class="pstat-sub">Split &amp; Promise Date</span>
        </div>
        <div class="pstat-chip pstat-unpaid" data-pstat="unpaid" onclick="setPosPaymentStatus('unpaid')" role="button" tabindex="0">
          <span class="pstat-title">🔴 FULL PENDING</span>
          <span class="pstat-sub">Pay Later</span>
        </div>
      </div>
    </div>

    <!-- Partial / Pending Due Calculator Box with Custom Promise Days -->
    <div id="posPartialDueBox" class="pos-partial-due-box" style="display:none;">
      <div class="due-calc-grid">
        <div>
          <label class="due-field-label" for="posPaidAmountInput">Paid Amount Now (₹)</label>
          <input type="number" step="0.01" id="posPaidAmountInput" class="form-control due-paid-input font-mono" placeholder="0.00" oninput="calculatePosDueBalance()" style="height:36px; font-size:0.9rem; font-weight:800;">
        </div>
        <div>
          <label class="due-field-label">Remaining Pending Due (₹)</label>
          <div class="due-balance-val font-mono" id="posDueBalanceDisplay">₹0.00</div>
        </div>
      </div>

      <!-- Promise Due Days & Custom Date Selector -->
      <div class="due-promise-section" style="margin-top:0.65rem;">
        <label class="due-field-label" for="posDueDateInput">
          📅 Payment Promised In (Days / Custom Date) *
        </label>
        <div class="due-days-pill-group">
          <button type="button" class="pos-due-day-btn active" data-days="2" onclick="setPosPromiseDays(2, this)">
            +2 Days
          </button>
          <button type="button" class="pos-due-day-btn" data-days="3" onclick="setPosPromiseDays(3, this)">
            +3 Days
          </button>
          <button type="button" class="pos-due-day-btn" data-days="7" onclick="setPosPromiseDays(7, this)">
            +7 Days (1 Wk)
          </button>
          <button type="button" class="pos-due-day-btn" data-days="15" onclick="setPosPromiseDays(15, this)">
            +15 Days
          </button>
          <button type="button" class="pos-due-day-btn" data-days="custom" onclick="setPosPromiseDays('custom', this)">
            📅 Custom Date
          </button>
        </div>

        <div class="due-custom-date-row" style="margin-top:0.4rem; display:flex; align-items:center; gap:0.5rem;">
          <input type="date" id="posDueDateInput" class="form-control due-date-picker font-mono" onchange="onPosCustomDueDateChange()" style="max-width:160px; height:34px; font-size:0.85rem;">
          <span class="due-date-preview-tag" id="posDueDateLabelPreview" style="font-size:0.75rem; font-weight:700; color:#B45309;"></span>
        </div>
      </div>
    </div>

    <!-- Payment Method Chips (Cash / UPI / Card / Split) -->
    <div class="form-group" style="margin-top:0.75rem; margin-bottom:0.75rem;">
      <label class="form-label" style="font-size:0.78rem; font-weight:800; text-transform:uppercase; color:var(--text-secondary); letter-spacing:0.04em;">
        2. Payment Method
      </label>
      <div class="payment-method-selector">
        <div class="pm-chip active" data-pm="Cash" role="button" tabindex="0">💵 CASH</div>
        <div class="pm-chip" data-pm="UPI" role="button" tabindex="0">📱 UPI</div>
        <div class="pm-chip" data-pm="Card" role="button" tabindex="0">💳 CARD</div>
        <div class="pm-chip" data-pm="Split" role="button" tabindex="0">🔀 SPLIT</div>
      </div>
    </div>

    <!-- Split Payment Calculator Box -->
    <div class="split-payment-box" id="splitPaymentContainer" style="display:none; margin-top:0.5rem; background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:10px; padding:0.65rem;">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
        <div>
          <label style="font-size:0.72rem; font-weight:700; color:var(--text-secondary); display:block; margin-bottom:2px;">💵 Cash Amount (₹)</label>
          <input type="number" step="0.01" id="splitCashAmount" class="form-control font-mono" placeholder="0.00" oninput="calculateSplitBalance()">
        </div>
        <div>
          <label style="font-size:0.72rem; font-weight:700; color:var(--text-secondary); display:block; margin-bottom:2px;">📱 UPI Amount (₹)</label>
          <input type="number" step="0.01" id="splitUpiAmount" class="form-control font-mono" placeholder="0.00" oninput="calculateSplitBalance()">
        </div>
      </div>
    </div>

    <!-- Remarks / Notes Box -->
    <div class="form-group" style="margin-top:0.5rem;">
      <label class="form-label" style="font-size:0.74rem; font-weight:700; color:var(--text-secondary);">
        Remarks / Follow-up Notes
      </label>
      <input type="text" id="posBillNotes" class="form-control" placeholder="e.g. Remaining balance promised by Friday / UTR ref..." style="height:38px; font-size:0.85rem;">
    </div>

    <!-- WhatsApp PDF Invoice Notification Toggle Card -->
    <label class="pos-whatsapp-toggle-card">
      <input type="checkbox" id="posSendWhatsappInvoice" checked>
      <div class="pos-wa-text-col">
        <div class="pos-wa-title">💬 Auto-send PDF Invoice link on WhatsApp to client</div>
        <div class="pos-wa-sub">Instant official fee receipt link delivered to member's phone</div>
      </div>
    </label>

    <!-- Modal Action Buttons Footer -->
    <div class="modal-action-footer">
      <button type="button" class="pos-btn-cancel" onclick="closeModal('paymentModal')">
        Cancel
      </button>
      <button type="button" class="pos-btn-complete-sale" onclick="processCheckout()">
        <span>⚡ COMPLETE SALE (ENTER)</span>
      </button>
    </div>

  </div>
</div>

<!-- ========================================================= -->
<!-- NEW MEMBER REGISTRATION MODAL                             -->
<!-- ========================================================= -->
<div class="modal" id="newMemberModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('newMemberModal')" aria-hidden="true"></div>
  <div class="card payment-modal-content" style="max-width:600px;" role="dialog" aria-labelledby="modalTitleNewMember" aria-modal="true">
    
    <div class="card-header modal-header-flex">
      <div class="modal-title-wrap">
        <span class="modal-icon" aria-hidden="true">👤</span>
        <div>
          <h3 id="modalTitleNewMember" class="modal-title">Quick Member Registration</h3>
          <span class="modal-subtitle">Instant member creation for POS billing</span>
        </div>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('newMemberModal')" aria-label="Close dialog">✕</button>
    </div>

    <form id="posNewMemberForm" onsubmit="handlePosNewMember(event)">
      <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:0.75rem; margin-top:0.75rem;">
        <div class="form-group">
          <label class="form-label font-bold">Full Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Vinay Grover">
        </div>
        <div class="form-group">
          <label class="form-label font-bold">Phone Number *</label>
          <input type="tel" name="phone" class="form-control" required placeholder="10-digit mobile" maxlength="10">
        </div>
      </div>

      <div class="form-group" style="margin-top:0.5rem;">
        <label class="form-label font-bold">Gender</label>
        <select name="gender" class="form-control">
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:0.6rem; margin-top:1.25rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newMemberModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #F59E0B 0%, #D97706 100%); border:none; font-weight:800;">
          + Create &amp; Select Member
        </button>
      </div>
    </form>

  </div>
</div>

<!-- ========================================================= -->
<!-- INLINE GUARANTEED POS HANDLERS & REAL-TIME CALCULATOR     -->
<!-- ========================================================= -->
<script>
// 1. When user inputs "Deal Price / Itne me diya ₹"
window.onDealPriceChange = function(dealVal) {
  if (typeof POSState === 'undefined' || !POSState.cart) return;
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
  if (typeof calculateTotals === 'function') calculateTotals();
};

// 2. When user inputs "Discount Amount in ₹"
window.onDiscountAmtChange = function(amtVal) {
  if (typeof POSState === 'undefined' || !POSState.cart) return;
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
  if (typeof calculateTotals === 'function') calculateTotals();
};

// 3. When user inputs "Discount Percentage %"
window.onDiscountPctChange = function(pctVal) {
  if (typeof POSState === 'undefined' || !POSState.cart) return;
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
  if (typeof calculateTotals === 'function') calculateTotals();
};

// 4. Quick Presets
window.applyPresetDiscount = function(type, val) {
  if (typeof POSState === 'undefined' || !POSState.cart) return;
  const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  if (subtotal <= 0) {
    if (typeof showToast === 'function') showToast('Please add items or membership plans to cart first', 'warning');
    return;
  }
  if (type === 'pct') {
    const pctInput = document.getElementById('cartDiscountPctInput');
    if (pctInput) pctInput.value = val;
    window.onDiscountPctChange(val);
  } else if (type === 'amt') {
    const discInput = document.getElementById('cartDiscountInput');
    if (discInput) discInput.value = val;
    window.onDiscountAmtChange(val);
  } else if (type === 'round') {
    const roundStep = subtotal > 2000 ? 100 : 50;
    const rounded = Math.floor(subtotal / roundStep) * roundStep;
    const dealInput = document.getElementById('cartDealPriceInput');
    if (dealInput) dealInput.value = rounded;
    window.onDealPriceChange(rounded);
  }
};

// 5. Quick POS New Member Form Handler
window.handlePosNewMember = function(e) {
  if (e) e.preventDefault();
  const form = document.getElementById('posNewMemberForm');
  if (!form) return;

  const name = form.querySelector('[name="name"]')?.value?.trim();
  const phone = form.querySelector('[name="phone"]')?.value?.trim();
  const gender = form.querySelector('[name="gender"]')?.value || 'Male';

  if (!name || !phone) {
    if (typeof showToast === 'function') showToast('Full Name and Phone are required', 'warning');
    return;
  }

  fetch('api/members.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, phone, gender })
  })
  .then(r => r.json())
  .then(res => {
    if (res.success && res.data) {
      if (typeof showToast === 'function') showToast('🎉 Member Registered & Selected for POS Billing!', 'success');
      if (typeof selectMember === 'function') {
        selectMember(res.data);
      } else if (typeof POSState !== 'undefined') {
        POSState.selectedMember = res.data;
        if (typeof updateSelectedMemberUI === 'function') updateSelectedMemberUI();
      }
      closeModal('newMemberModal');
      form.reset();
    } else {
      if (typeof showToast === 'function') showToast(res.message || 'Registration failed', 'danger');
    }
  })
  .catch(err => {
    if (typeof showToast === 'function') showToast('Failed to register member', 'danger');
  });
};

<?php if (!empty($preselectedMember)): ?>
// Automatically select the member passed via URL (from Trials page, Profile, etc.)
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    var memberData = <?= json_encode($preselectedMember) ?>;
    if (typeof selectMember === 'function') {
      selectMember(memberData);
    } else if (typeof POSState !== 'undefined') {
      POSState.selectedMember = memberData;
      if (typeof updateSelectedMemberUI === 'function') updateSelectedMemberUI();
    }
    if (typeof showToast === 'function') {
      showToast('👤 Member Auto-Selected: <?= addslashes($preselectedMember['name']) ?>', 'success');
    }
  }, 200);
});
<?php endif; ?>
</script>
