<!-- Communication & Automated Messaging Hub View with Anti-Ban Offer Engine -->
<?php
$db = getDB();
$templates = $db->query("SELECT * FROM marketing_templates ORDER BY id ASC")->fetchAll();
$logs = $db->query("
    SELECT l.*, m.name as member_name 
    FROM marketing_logs l 
    LEFT JOIN members m ON l.member_id = m.id 
    ORDER BY l.sent_at DESC, l.id DESC LIMIT 40
")->fetchAll();

// Fetch current WhatsApp Disclaimer Settings
$disclaimerText = getSetting('whatsapp_disclaimer', "_Note: Terms & conditions apply. Membership fees are non-refundable. For queries, contact THE CLUB 777® reception._");
$disclaimerEnabled = getSetting('whatsapp_disclaimer_enabled', '1');
$isDiscOn = ($disclaimerEnabled === '1' || $disclaimerEnabled === 'true' || $disclaimerEnabled === 'yes' || $disclaimerEnabled === 1);

// Fetch counts for reminder milestones
$countToday = (int) $db->query("
    SELECT COUNT(*) FROM member_subscriptions WHERE status = 'active' AND end_date = CURRENT_DATE()
")->fetchColumn();

$count2Days = (int) $db->query("
    SELECT COUNT(*) FROM member_subscriptions WHERE status = 'active' AND end_date = DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY)
")->fetchColumn();

$count7Days = (int) $db->query("
    SELECT COUNT(*) FROM member_subscriptions WHERE status = 'active' AND end_date = DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
")->fetchColumn();

$count21Days = (int) $db->query("
    SELECT COUNT(*) FROM member_subscriptions WHERE status = 'active' AND end_date = DATE_ADD(CURRENT_DATE(), INTERVAL 21 DAY)
")->fetchColumn();

$countBirthdays = (int) $db->query("
    SELECT COUNT(*) FROM members WHERE dob IS NOT NULL AND MONTH(dob) = MONTH(CURRENT_DATE()) AND DAY(dob) = DAY(CURRENT_DATE())
")->fetchColumn();

$countExpired = (int) $db->query("SELECT COUNT(*) FROM members WHERE status = 'expired' OR status = 'inactive'")->fetchColumn();
?>

<div class="page-content comm-container">
  
  <!-- ========================================================= -->
  <!-- 1. COMPACT PAGE HEADER & ACTIONS                          -->
  <!-- ========================================================= -->
  <header class="comm-header-card">
    <div class="comm-header-main">
      <div class="comm-header-tag">
        <span class="comm-live-dot" aria-hidden="true"></span>
        <span>AUTOMATED MESSAGING ENGINE</span>
      </div>
      <h1 class="comm-title">
        <span aria-hidden="true">📱</span> <span>WhatsApp Marketing &amp; Messaging</span>
      </h1>
      <p class="comm-subtitle">Manage member reminders, campaigns, birthdays and message history.</p>
    </div>

    <div class="comm-header-actions">
      <button type="button" class="btn btn-primary comm-action-btn btn-campaign-gradient" onclick="openOfferCampaignModal()" title="Launch custom offer or win-back promotion">
        <span>🎁 Send Offer / Campaign</span>
      </button>
      <button type="button" class="btn btn-success comm-action-btn" onclick="runAutomatedScan()" title="Scan & queue daily automated reminders">
        <span>⚡ Run Reminder Scan</span>
      </button>
      <button type="button" class="btn btn-secondary comm-action-btn" onclick="openModal('disclaimerModal')" title="Configure message footer and terms">
        <span>📝 Edit Disclaimer</span>
      </button>
    </div>
  </header>

  <!-- ========================================================= -->
  <!-- 2. WHATSAPP GATEWAY CONNECTION CARD                       -->
  <!-- ========================================================= -->
  <section class="card comm-gateway-card" aria-label="WhatsApp Gateway System">
    <div class="gateway-header">
      <div class="gateway-title-group">
        <div class="gateway-icon-wrap" aria-hidden="true">🟢</div>
        <div>
          <div class="gateway-title-row">
            <h2 class="gateway-title">WhatsApp Gateway</h2>
            <span id="commNodeWaStatusBadge" class="badge comm-status-badge badge-warning">
              ⏳ Checking Status...
            </span>
          </div>
          <p class="gateway-subtitle">Direct background messaging via local Node.js multi-device engine.</p>
        </div>
      </div>

      <div class="gateway-actions">
        <button type="button" class="btn btn-sm btn-secondary comm-gw-btn" onclick="checkCommNodeWaStatus(true)">
          🔄 Refresh Status
        </button>
        <button type="button" id="commNodeWaTestBtn" class="btn btn-sm btn-success comm-gw-btn" onclick="sendCommNodeTestPing()" style="display:none;">
          ⚡ Send Test Message
        </button>
        <button type="button" id="commNodeWaLogoutBtn" class="btn btn-sm btn-danger comm-gw-btn" onclick="logoutCommNodeWa()" style="display:none;">
          🚪 Disconnect
        </button>
      </div>
    </div>

    <!-- Dynamic Gateway Container -->
    <div id="commNodeWaContainer" class="gateway-body-container">
      
      <!-- State 1: QR Ready -->
      <div id="commNodeWaQrBox" class="gateway-state-box" style="display:none;">
        <div class="qr-panel">
          <h3 class="qr-heading">Connect WhatsApp</h3>
          <p class="qr-sub">Scan this QR code using WhatsApp &rarr; <strong>Settings &rarr; Linked Devices &rarr; Link a Device</strong></p>
          <div class="qr-image-wrapper">
            <img id="commNodeWaQrImg" src="" alt="WhatsApp QR Code" class="qr-image">
          </div>
          <span class="qr-auto-poll">⏳ Auto-detecting scan... Connection will update instantly.</span>
        </div>
      </div>

      <!-- State 2: Connected -->
      <div id="commNodeWaConnectedBox" class="gateway-state-box" style="display:none;">
        <div class="connected-panel">
          <div class="connected-left">
            <div class="connected-badge-icon" aria-hidden="true">✓</div>
            <div>
              <div class="connected-title">Connected &amp; Online</div>
              <div class="connected-meta">
                Phone: <strong id="commNodeWaUserPhone">+91 98765 43210</strong> &bull; Account: <span id="commNodeWaUserName">Gym Bot</span>
              </div>
              <div class="connected-note">Background auto-reminders and milestone notices are active.</div>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-primary" onclick="sendCommNodeTestPing()">
            ✉️ Send Test Message
          </button>
        </div>
      </div>

      <!-- State 3: Offline -->
      <div id="commNodeWaOfflineBox" class="gateway-state-box" style="display:none;">
        <div class="offline-panel">
          <div class="offline-icon" aria-hidden="true">⚠️</div>
          <div class="offline-info">
            <div class="offline-title">Offline &bull; WhatsApp gateway is not connected</div>
            <p class="offline-desc">To enable automated background WhatsApp messaging, run the launcher in your gym directory:</p>
            <code class="offline-code-box">C:\xampp\htdocs\GYM\start_whatsapp_gateway.bat</code>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 3. COMPACT PROTECTION STATUS CARD                         -->
  <!-- ========================================================= -->
  <section class="card comm-protection-card" aria-label="Anti-Ban Protection Status">
    <div class="protection-header">
      <div class="protection-left">
        <span class="protection-shield-icon" aria-hidden="true">🛡️</span>
        <div>
          <strong class="protection-title">Protection Status: <span class="text-success">Active</span></strong>
          <span class="protection-sub">Automated safety controls enabled on all outgoing messaging</span>
        </div>
      </div>
      <div class="protection-chips-group">
        <span class="protection-chip">✓ Dynamic Spintax</span>
        <span class="protection-chip">✓ Reference Hashes</span>
        <span class="protection-chip">✓ Opt-Out Shield</span>
        <span class="protection-chip">✓ 8s Safe Delay</span>
      </div>
    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 4. MILESTONE KPI METRIC TILES                             -->
  <!-- ========================================================= -->
  <section class="comm-kpi-grid" aria-label="Reminder Milestone Categories">
    
    <!-- 1. Expired / Fees Due -->
    <div class="card comm-kpi-card border-top-red" onclick="loadMilestone('fees_due')" role="button" tabindex="0" title="Click to view Expired / Fees Due members">
      <div class="comm-kpi-header">
        <span class="comm-kpi-label text-danger">Fees Due / Expired</span>
        <span class="comm-kpi-icon text-danger" aria-hidden="true">🚨</span>
      </div>
      <div class="comm-kpi-val text-danger"><?= number_format($countExpired) ?></div>
      <span class="comm-kpi-caption">Overdue &amp; inactive</span>
    </div>

    <!-- 2. Expiring Today -->
    <div class="card comm-kpi-card border-top-crimson" onclick="loadMilestone('same_day')" role="button" tabindex="0" title="Click to view members expiring today">
      <div class="comm-kpi-header">
        <span class="comm-kpi-label">Expiring Today</span>
        <span class="comm-kpi-icon text-danger" aria-hidden="true">🔔</span>
      </div>
      <div class="comm-kpi-val text-danger"><?= number_format($countToday) ?></div>
      <span class="comm-kpi-caption">0 days left</span>
    </div>

    <!-- 3. 2 Days Remaining -->
    <div class="card comm-kpi-card border-top-orange" onclick="loadMilestone('days_2')" role="button" tabindex="0" title="Click to view members expiring in 2 days">
      <div class="comm-kpi-header">
        <span class="comm-kpi-label">2 Days Remaining</span>
        <span class="comm-kpi-icon text-warning" aria-hidden="true">⚡</span>
      </div>
      <div class="comm-kpi-val text-warning"><?= number_format($count2Days) ?></div>
      <span class="comm-kpi-caption">Urgent renewal alert</span>
    </div>

    <!-- 4. 1 Week Remaining -->
    <div class="card comm-kpi-card border-top-blue" onclick="loadMilestone('week_1')" role="button" tabindex="0" title="Click to view members expiring in 7 days">
      <div class="comm-kpi-header">
        <span class="comm-kpi-label">1 Week Remaining</span>
        <span class="comm-kpi-icon text-primary" aria-hidden="true">⚠️</span>
      </div>
      <div class="comm-kpi-val text-primary"><?= number_format($count7Days) ?></div>
      <span class="comm-kpi-caption">7 days before expiry</span>
    </div>

    <!-- 5. 3 Weeks Remaining -->
    <div class="card comm-kpi-card border-top-purple" onclick="loadMilestone('weeks_3')" role="button" tabindex="0" title="Click to view members expiring in 21 days">
      <div class="comm-kpi-header">
        <span class="comm-kpi-label">3 Weeks Remaining</span>
        <span class="comm-kpi-icon text-purple" aria-hidden="true">⏳</span>
      </div>
      <div class="comm-kpi-val text-purple"><?= number_format($count21Days) ?></div>
      <span class="comm-kpi-caption">21 days early notice</span>
    </div>

    <!-- 6. Birthdays Today -->
    <div class="card comm-kpi-card border-top-amber" onclick="loadMilestone('birthdays')" role="button" tabindex="0" title="Click to view today's birthdays">
      <div class="comm-kpi-header">
        <span class="comm-kpi-label text-amber">Birthdays Today</span>
        <span class="comm-kpi-icon text-amber" aria-hidden="true">🎂</span>
      </div>
      <div class="comm-kpi-val text-amber"><?= number_format($countBirthdays) ?></div>
      <span class="comm-kpi-caption">1-click birthday wish</span>
    </div>

  </section>

  <!-- ========================================================= -->
  <!-- 5. MILESTONE ACTION CENTER (REMINDER QUEUE)               -->
  <!-- ========================================================= -->
  <section class="card comm-queue-card" aria-label="Member Reminder Queue">
    <div class="comm-queue-header">
      <div class="queue-title-wrap">
        <h2 class="card-title" id="activeMilestoneTitle">🎂 Today's Birthdays &amp; Renewal Candidates</h2>
        <span class="card-subtitle">Select a category below to review personalized message previews and send 1-click reminders.</span>
      </div>

      <!-- Compact Pill-Style Milestone Filter Tabs -->
      <div class="comm-milestone-tabs" role="tablist" aria-label="Milestone Categories">
        <button type="button" class="milestone-pill-btn" onclick="loadMilestone('fees_due')">
          <span class="dot-red" aria-hidden="true"></span> Expired (<?= $countExpired ?>)
        </button>
        <button type="button" class="milestone-pill-btn" onclick="loadMilestone('same_day')">
          <span class="dot-red" aria-hidden="true"></span> Today (<?= $countToday ?>)
        </button>
        <button type="button" class="milestone-pill-btn" onclick="loadMilestone('days_2')">
          <span class="dot-orange" aria-hidden="true"></span> 2 Days (<?= $count2Days ?>)
        </button>
        <button type="button" class="milestone-pill-btn" onclick="loadMilestone('week_1')">
          <span class="dot-blue" aria-hidden="true"></span> 1 Week (<?= $count7Days ?>)
        </button>
        <button type="button" class="milestone-pill-btn" onclick="loadMilestone('weeks_3')">
          <span class="dot-purple" aria-hidden="true"></span> 3 Weeks (<?= $count21Days ?>)
        </button>
        <button type="button" class="milestone-pill-btn active" onclick="loadMilestone('birthdays')">
          <span class="dot-amber" aria-hidden="true"></span> Birthdays (<?= $countBirthdays ?>)
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div id="milestoneLoading" class="comm-loading-state" style="display:none;">
      <div class="loading-spinner" aria-hidden="true"></div>
      <span>Loading member reminder queue...</span>
    </div>

    <!-- Milestone Members Table Container -->
    <div class="table-responsive" id="milestoneTableContainer">
      <table class="table comm-table" style="width:100%;">
        <thead>
          <tr>
            <th scope="col" style="width:200px;">Member</th>
            <th scope="col" style="width:140px;">Contact Phone</th>
            <th scope="col" style="width:140px;">Plan / Event</th>
            <th scope="col">Message Preview (With Disclaimer)</th>
            <th scope="col" style="width:150px; text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody id="milestoneTableBody">
          <!-- Populated dynamically by loadMilestone() -->
        </tbody>
      </table>
    </div>
  </section>

  <!-- ========================================================= -->
  <!-- 6. SENT MESSAGE HISTORY WITH CLIENT-SIDE SEARCH           -->
  <!-- ========================================================= -->
  <section class="card comm-history-card" aria-label="Sent Message History">
    <div class="comm-history-header">
      <div>
        <h2 class="card-title">
          <span aria-hidden="true">📤</span> <span>Message History</span>
        </h2>
        <span class="card-subtitle">Log of recent automated and manual WhatsApp messages dispatched</span>
      </div>

      <div class="history-controls-group">
        <input type="text" id="historySearchInput" class="form-control history-search-box" placeholder="Search member, phone, message..." oninput="filterHistoryTable()" autocomplete="off">
        <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload()" title="Refresh sent logs">
          🔄 Refresh
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table comm-table" id="historyTable" style="width:100%;">
        <thead>
          <tr>
            <th scope="col" style="width:180px;">Member</th>
            <th scope="col" style="width:130px;">Phone</th>
            <th scope="col" style="width:140px;">Campaign / Event</th>
            <th scope="col">Message</th>
            <th scope="col" style="width:90px;">Status</th>
            <th scope="col" style="width:140px;">Sent Time</th>
            <th scope="col" style="width:110px; text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody id="historyTableBody">
          <?php if (empty($logs)): ?>
            <tr id="historyEmptyRow">
              <td colspan="7" class="comm-empty-cell">
                <div class="empty-state-wrap">
                  <div class="empty-state-icon" aria-hidden="true">💬</div>
                  <h3 class="empty-state-title">No messages sent yet</h3>
                  <p class="empty-state-text">Automated reminder deliveries and broadcast records will appear here.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php
                $cleanP = preg_replace('/[^0-9]/', '', $log['recipient_phone']);
                if (strlen($cleanP) === 10) $cleanP = '91' . $cleanP;
                $waUrl = "https://wa.me/{$cleanP}?text=" . urlencode($log['message_body']);
              ?>
              <tr class="history-row"
                  data-name="<?= htmlspecialchars(strtolower($log['member_name'] ?: 'target member')) ?>"
                  data-phone="<?= htmlspecialchars($log['recipient_phone'] ?: '') ?>"
                  data-event="<?= htmlspecialchars(strtolower($log['trigger_event'] ?: '')) ?>"
                  data-msg="<?= htmlspecialchars(strtolower($log['message_body'] ?: '')) ?>">
                
                <td data-label="Member">
                  <strong class="member-name-text"><?= htmlspecialchars($log['member_name'] ?: 'Target Member') ?></strong>
                </td>
                
                <td data-label="Phone">
                  <span class="phone-text"><?= htmlspecialchars($log['recipient_phone']) ?></span>
                </td>
                
                <td data-label="Campaign/Event">
                  <span class="badge badge-info uppercase font-bold">
                    <?= strtoupper(str_replace('_', ' ', $log['trigger_event'])) ?>
                  </span>
                </td>
                
                <td data-label="Message">
                  <div class="msg-preview-text" title="<?= htmlspecialchars($log['message_body']) ?>">
                    <?= htmlspecialchars($log['message_body']) ?>
                  </div>
                </td>
                
                <td data-label="Status">
                  <span class="badge badge-success font-bold">SENT</span>
                </td>
                
                <td data-label="Sent Time">
                  <span class="time-text"><?= date('d M Y, h:i A', strtotime($log['sent_at'])) ?></span>
                </td>
                
                <td data-label="Action" style="text-align:right;">
                  <a href="<?= $waUrl ?>" target="_blank" class="btn btn-sm btn-whatsapp-compact" title="Open direct WhatsApp chat">
                    <span>💬 Open WA</span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            
            <tr id="historyNoMatchRow" style="display:none;">
              <td colspan="7" class="comm-empty-cell">
                <div class="empty-state-wrap">
                  <div class="empty-state-icon" aria-hidden="true">🔍</div>
                  <h3 class="empty-state-title">No matching message logs found</h3>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

</div>

<!-- ========================================================= -->
<!-- 7. OFFER / PROMO CAMPAIGN MODAL WIZARD                    -->
<!-- ========================================================= -->
<div class="modal" id="offerCampaignModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('offerCampaignModal')" aria-hidden="true"></div>
  <div class="card comm-modal-card border-top-pink" role="dialog" aria-labelledby="campaignModalTitle" aria-modal="true">
    
    <div class="comm-modal-header">
      <div>
        <h3 id="campaignModalTitle" class="modal-title text-pink">🎁 Safe WhatsApp Offer Campaign</h3>
        <span class="modal-subtitle text-success">🛡️ Anti-Ban Engine: Spintax Variations + Safe Delay + Opt-Out Shield</span>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('offerCampaignModal')" aria-label="Close dialog">✕</button>
    </div>

    <!-- Step 1: Create Campaign Setup -->
    <div id="offerSetupStep" class="wizard-step">
      <div class="form-group">
        <label class="form-label" for="offerTarget">1. Select Target Audience Group</label>
        <select id="offerTarget" class="form-control">
          <option value="expired_all">Inactive &amp; Expired Members (Win-Back Campaign)</option>
          <option value="inactive_30_days">Expired &gt; 30 Days Ago (High Priority Re-engagement)</option>
          <option value="expiring_this_week">Members Expiring This Week</option>
          <option value="active_all">All Active Members (Upgrade / PT Special Offer)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="offerTemplatePicker">2. Select High-Converting Preset Template</label>
        <select id="offerTemplatePicker" class="form-control" onchange="applyOfferPreset(this.value)">
          <option value="">-- Choose Pre-Designed Preset --</option>
          <option value="win_back_discount">🎉 Win-Back Special: 30% Off on Gym Renewal + Zero Admission Fee</option>
          <option value="festive_renewal">🔥 Festive Power Offer: Renew 3 Months &amp; Get 1 Month FREE!</option>
          <option value="pt_combo">🏋️ Personal Training Blast: 12 Sessions + Diet Plan at 25% Off</option>
          <option value="refer_friend">🤝 Buddy Workout Offer: Bring a friend &amp; both get 15 Extra Days!</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="offerMessageText">3. Offer Message Text (English)</label>
        <textarea id="offerMessageText" class="form-control font-mono text-sm" rows="5" required placeholder="{Hi|Hello|Hey} {name}! We miss seeing you at THE CLUB 777®..."></textarea>
        <div class="spintax-help-text">
          Tags: <code>{Hi|Hello|Hey}</code> (Spintax randomizer), <code>{name}</code>, <code>{gym_name}</code>, <code>{gym_phone}</code>.<br>
          <em>*Unique reference hashes and opt-out footer are attached automatically.*</em>
        </div>
      </div>

      <div class="comm-modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('offerCampaignModal')">Cancel</button>
        <button type="button" class="btn btn-primary btn-pink" onclick="prepareSafeOfferQueue()">
          🛡️ Build Campaign Queue &rarr;
        </button>
      </div>
    </div>

    <!-- Step 2: Interactive Dispatch Queue -->
    <div id="offerQueueStep" class="wizard-step" style="display:none;">
      <div class="queue-status-box">
        <div class="queue-status-row">
          <div>
            <strong class="queue-status-title">Safe Dispatch Queue Ready</strong>
            <div id="queueCountDisplay" class="queue-status-sub">0 Recipients in Queue</div>
          </div>
          <button type="button" class="btn btn-sm btn-secondary" onclick="backToOfferSetup()">← Edit Campaign</button>
        </div>

        <div class="queue-progress-wrapper">
          <div class="queue-progress-labels">
            <span>Dispatch Progress</span>
            <span id="queueProgressPct" class="font-bold">0%</span>
          </div>
          <div class="queue-progress-track">
            <div id="queueProgressBar" class="queue-progress-fill"></div>
          </div>
        </div>
      </div>

      <!-- Controls -->
      <div class="queue-controls-row">
        <button type="button" class="btn btn-success btn-block" id="startAutoSendBtn" onclick="startSafeAutoSend()">
          ⚡ START SAFE AUTO-SENDER (8s Delay)
        </button>
        <button type="button" class="btn btn-secondary btn-block" id="pauseAutoSendBtn" onclick="pauseSafeAutoSend()" style="display:none;">
          ⏸️ Pause Sending
        </button>
      </div>

      <!-- Recipient Card Items -->
      <div id="queueItemsList" class="queue-items-container">
        <!-- Filled by JS -->
      </div>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- 8. EDIT MESSAGE DISCLAIMER MODAL                          -->
<!-- ========================================================= -->
<div class="modal" id="disclaimerModal" style="display:none;">
  <div class="modal-overlay" onclick="closeModal('disclaimerModal')" aria-hidden="true"></div>
  <div class="card comm-modal-card" role="dialog" aria-labelledby="discModalTitle" aria-modal="true">
    <div class="comm-modal-header">
      <div>
        <h3 id="discModalTitle" class="modal-title">Message Disclaimer &amp; Footer</h3>
        <span class="modal-subtitle">Automatically appended to outgoing WhatsApp notifications</span>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('disclaimerModal')" aria-label="Close dialog">✕</button>
    </div>

    <form onsubmit="event.preventDefault(); submitDisclaimerEdit();">
      <div class="form-group">
        <label class="checkbox-label">
          <input type="checkbox" id="discEnabled" class="custom-checkbox" <?= $isDiscOn ? 'checked' : '' ?> onchange="updateDisclaimerLivePreview()">
          <span>Enable Disclaimer on all outgoing WhatsApp messages</span>
        </label>
        <p class="checkbox-subtext">
          When active, this disclaimer is automatically attached at the end of renewal reminders, offers, and birthday messages.
        </p>
      </div>

      <div class="form-group" style="margin-top:1.15rem;">
        <label class="form-label" for="discBody">Disclaimer / Footer Notice Text</label>
        <textarea id="discBody" class="form-control" rows="4" placeholder="_Note: Terms & conditions apply. Membership fees are non-refundable. For queries, contact THE CLUB 777® reception._" onkeyup="updateDisclaimerLivePreview()"><?= htmlspecialchars($disclaimerText) ?></textarea>
      </div>

      <!-- WhatsApp Style Live Preview -->
      <div class="form-group">
        <label class="form-label">Live Preview in WhatsApp</label>
        <div class="wa-preview-bubble">
          <div class="wa-preview-body">Hello Rahul! Your gym membership expires today.</div>
          <div class="wa-preview-footer" id="discLivePreview">
            <?= htmlspecialchars($disclaimerText) ?>
          </div>
        </div>
      </div>

      <div class="comm-modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('disclaimerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">✓ Save Settings</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================= -->
<!-- STYLESHEET FOR WHATSAPP MARKETING & MESSAGING HUB         -->
<!-- ========================================================= -->
<style>
.comm-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding-bottom: 3rem;
}

/* 1. Header */
.comm-header-card {
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1.15rem 1.35rem;
  box-shadow: var(--shadow-subtle);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.comm-header-main {
  flex: 1 1 300px;
}

.comm-header-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--primary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.25rem;
}

.comm-live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10B981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulseDot 2s infinite ease-in-out;
}

.comm-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  letter-spacing: -0.02em;
}

.comm-subtitle {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0.2rem 0 0 0;
}

.comm-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.comm-action-btn {
  font-weight: 700;
  font-size: 0.82rem;
  padding: 0.5rem 0.95rem;
  min-height: 38px;
}

.btn-campaign-gradient {
  background: linear-gradient(135deg, #EC4899 0%, #8B5CF6 100%);
  border: none;
  color: #FFFFFF;
}
.btn-campaign-gradient:hover {
  background: linear-gradient(135deg, #DB2777 0%, #7C3AED 100%);
  color: #FFFFFF;
}

/* 2. WhatsApp Gateway Card */
.comm-gateway-card {
  background: #0F172A;
  background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
  color: #FFFFFF;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-lg);
  padding: 1.25rem 1.45rem;
  box-shadow: var(--shadow-card);
}

.gateway-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.gateway-title-group {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.gateway-icon-wrap {
  font-size: 1.4rem;
  line-height: 1;
}

.gateway-title-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.gateway-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #FFFFFF;
  margin: 0;
  letter-spacing: -0.02em;
}

.comm-status-badge {
  font-size: 0.72rem;
  font-weight: 800;
  padding: 0.25rem 0.6rem;
}

.gateway-subtitle {
  font-size: 0.78rem;
  color: #94A3B8;
  margin: 0.2rem 0 0 0;
}

.gateway-actions {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.comm-gw-btn {
  font-weight: 700;
  font-size: 0.78rem;
}

.gateway-body-container {
  margin-top: 1rem;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-md);
  padding: 1rem 1.25rem;
}

/* States */
.qr-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
}

.qr-heading {
  font-size: 0.95rem;
  font-weight: 800;
  color: #FCD34D;
  margin: 0;
}

.qr-sub {
  font-size: 0.8rem;
  color: #CBD5E1;
  max-width: 480px;
  margin: 0;
}

.qr-image-wrapper {
  background: #FFFFFF;
  padding: 0.65rem;
  border-radius: var(--radius-md);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
  margin: 0.4rem 0;
}

.qr-image {
  width: 170px;
  height: 170px;
  display: block;
}

.qr-auto-poll {
  font-size: 0.72rem;
  color: #94A3B8;
}

.connected-panel {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.connected-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.connected-badge-icon {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #059669;
  color: #FFFFFF;
  font-weight: 800;
  font-size: 1.3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.connected-title {
  font-size: 1rem;
  font-weight: 800;
  color: #34D399;
}

.connected-meta {
  font-size: 0.82rem;
  color: #E2E8F0;
  margin-top: 0.1rem;
}

.connected-note {
  font-size: 0.72rem;
  color: #94A3B8;
  margin-top: 0.15rem;
}

.offline-panel {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  flex-wrap: wrap;
}

.offline-icon {
  font-size: 1.8rem;
  line-height: 1;
}

.offline-title {
  font-size: 0.95rem;
  font-weight: 800;
  color: #F87171;
}

.offline-desc {
  font-size: 0.78rem;
  color: #CBD5E1;
  margin: 0.15rem 0 0.35rem 0;
}

.offline-code-box {
  background: #000000;
  padding: 0.3rem 0.65rem;
  border-radius: 6px;
  font-family: var(--font-mono);
  font-size: 0.78rem;
  color: #4ADE80;
  display: inline-block;
}

/* 3. Protection Card */
.comm-protection-card {
  padding: 0.75rem 1.15rem;
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
}

.protection-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.protection-left {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.protection-shield-icon {
  font-size: 1.35rem;
}

.protection-title {
  font-size: 0.88rem;
  color: var(--text-primary);
  display: block;
}

.protection-sub {
  font-size: 0.74rem;
  color: var(--text-muted);
}

.protection-chips-group {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.protection-chip {
  background: #ECFDF5;
  color: #065F46;
  border: 1px solid #A7F3D0;
  padding: 0.18rem 0.55rem;
  border-radius: 99px;
  font-size: 0.7rem;
  font-weight: 700;
}

/* 4. KPI Milestone Grid */
.comm-kpi-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.85rem;
}

.comm-kpi-card {
  padding: 0.95rem 1.05rem;
  border-radius: var(--radius-lg);
  background: var(--bg-surface);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-subtle);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 102px;
  cursor: pointer;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.comm-kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-card);
}

.border-top-red     { border-top: 3.5px solid #DC2626; }
.border-top-crimson { border-top: 3.5px solid #EF4444; }
.border-top-orange  { border-top: 3.5px solid #F97316; }
.border-top-blue    { border-top: 3.5px solid #3B82F6; }
.border-top-purple  { border-top: 3.5px solid #8B5CF6; }
.border-top-amber   { border-top: 3.5px solid #F59E0B; }
.border-top-pink    { border-top: 4px solid #EC4899; }

.comm-kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.comm-kpi-label {
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.comm-kpi-icon {
  font-size: 0.95rem;
  line-height: 1;
}

.comm-kpi-val {
  font-size: 1.65rem;
  font-weight: 800;
  font-family: var(--font-heading);
  line-height: 1;
}

.comm-kpi-caption {
  font-size: 0.68rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-top: 0.25rem;
}

/* 5. Milestone Queue Card & Table */
.comm-queue-card, .comm-history-card {
  padding: 0;
  overflow: hidden;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  background: var(--bg-surface);
  box-shadow: var(--shadow-card);
}

.comm-queue-header, .comm-history-header {
  padding: 1.15rem 1.35rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.85rem;
}

.queue-title-wrap .card-title, .comm-history-header .card-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
}

.comm-milestone-tabs {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.milestone-pill-btn {
  background: var(--bg-surface-secondary);
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
  padding: 0.32rem 0.65rem;
  border-radius: var(--radius-full);
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
  transition: all var(--transition-fast);
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-family: inherit;
}

.milestone-pill-btn:hover {
  background: var(--bg-surface-hover);
  color: var(--text-primary);
}

.milestone-pill-btn.active {
  background: var(--text-primary);
  color: #FFFFFF;
  border-color: var(--text-primary);
}

.dot-red    { width: 6px; height: 6px; border-radius: 50%; background: #EF4444; }
.dot-orange { width: 6px; height: 6px; border-radius: 50%; background: #F97316; }
.dot-blue   { width: 6px; height: 6px; border-radius: 50%; background: #3B82F6; }
.dot-purple { width: 6px; height: 6px; border-radius: 50%; background: #8B5CF6; }
.dot-amber  { width: 6px; height: 6px; border-radius: 50%; background: #F59E0B; }

.comm-loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  padding: 2.5rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.loading-spinner {
  width: 18px;
  height: 18px;
  border: 2.5px solid var(--border-color);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Tables */
.comm-table thead th {
  background: var(--bg-surface-secondary);
  color: var(--text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.75rem 1.15rem;
  border-bottom: 1px solid var(--border-color);
  white-space: nowrap;
}

.comm-table tbody td {
  padding: 0.75rem 1.15rem;
  vertical-align: middle;
  border-bottom: 1px solid var(--border-light);
  font-size: 0.84rem;
  background: var(--bg-surface);
}

.comm-table tbody tr:last-child td {
  border-bottom: none;
}

.comm-table tbody tr:hover td {
  background: #F8FAFC;
}

.member-name-text {
  color: var(--text-primary);
  font-size: 0.86rem;
}

.phone-text {
  font-weight: 600;
  color: var(--text-secondary);
}

.time-text {
  font-size: 0.78rem;
  color: var(--text-muted);
}

.msg-preview-text {
  max-width: 320px;
  font-size: 0.78rem;
  color: var(--text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.4;
}

.btn-whatsapp-compact {
  background: #25D366;
  color: #FFFFFF;
  font-weight: 800;
  font-size: 0.75rem;
  padding: 0.28rem 0.65rem;
  border: none;
  text-decoration: none;
  border-radius: var(--radius-sm);
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.btn-whatsapp-compact:hover {
  background: #1EBE5D;
  color: #FFFFFF;
}

.history-controls-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.history-search-box {
  width: 240px;
  padding: 0.4rem 0.75rem;
  font-size: 0.8rem;
}

/* Empty Cell */
.comm-empty-cell {
  text-align: center;
  padding: 3rem 1.5rem !important;
}

.empty-state-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-state-icon {
  font-size: 2rem;
  margin-bottom: 0.4rem;
}

.empty-state-title {
  font-size: 1rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.empty-state-text {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin: 0.2rem 0 0 0;
}

/* 6. Modals */
.comm-modal-card {
  position: relative;
  z-index: 10;
  background: #FFFFFF;
  width: 92%;
  max-width: 580px;
  border-radius: var(--radius-xl);
  padding: 1.45rem;
  box-shadow: var(--shadow-modal);
  border: 1px solid var(--border-color);
  max-height: 90vh;
  overflow-y: auto;
}

.comm-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.15rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

.modal-subtitle {
  font-size: 0.74rem;
  display: block;
  margin-top: 0.15rem;
}

.modal-close-btn {
  background: none;
  border: none;
  font-size: 1.2rem;
  color: var(--text-muted);
  cursor: pointer;
  line-height: 1;
}

.spintax-help-text {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-top: 0.35rem;
  line-height: 1.4;
}

.comm-modal-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.35rem;
}

.comm-modal-actions .btn {
  flex: 1;
  justify-content: center;
  font-weight: 700;
}

.btn-pink {
  background: #DB2777;
  border-color: #DB2777;
  color: #FFFFFF;
}
.btn-pink:hover {
  background: #BE185D;
  color: #FFFFFF;
}

/* Queue Screen */
.queue-status-box {
  background: var(--bg-main);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.85rem 1rem;
  margin-bottom: 0.85rem;
}

.queue-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.queue-status-title {
  font-size: 0.95rem;
  color: var(--text-primary);
}

.queue-status-sub {
  font-size: 0.76rem;
  color: var(--text-muted);
}

.queue-progress-wrapper {
  margin-top: 0.75rem;
}

.queue-progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-bottom: 0.25rem;
}

.queue-progress-track {
  width: 100%;
  height: 7px;
  background: #E2E8F0;
  border-radius: 99px;
  overflow: hidden;
}

.queue-progress-fill {
  width: 0%;
  height: 100%;
  background: #10B981;
  border-radius: 99px;
  transition: width 0.3s ease;
}

.queue-controls-row {
  margin-bottom: 0.85rem;
}

.queue-items-container {
  max-height: 260px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.45rem;
  background: #F8FAFC;
}

/* Disclaimer Checkbox & Preview */
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.88rem;
}

.custom-checkbox {
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
}

.checkbox-subtext {
  font-size: 0.74rem;
  color: var(--text-muted);
  margin: 0.25rem 0 0 1.6rem;
}

.wa-preview-bubble {
  background: #E7FED8;
  border: 1px solid #D1F8BE;
  border-radius: 12px;
  padding: 0.85rem 1rem;
  font-size: 0.84rem;
  color: #111827;
  position: relative;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.wa-preview-body {
  font-weight: 500;
}

.wa-preview-footer {
  margin-top: 0.45rem;
  padding-top: 0.35rem;
  border-top: 1px dashed rgba(0, 0, 0, 0.15);
  font-size: 0.74rem;
  color: #4B5563;
  font-style: italic;
}

/* ========================================================= -->
<!-- RESPONSIVE BREAKPOINTS                                    -->
<!-- ========================================================= --> */
@media (max-width: 1200px) {
  .comm-kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .comm-header-card {
    flex-direction: column;
    align-items: flex-start;
    padding: 1rem;
  }

  .comm-header-actions {
    width: 100%;
  }

  .comm-action-btn {
    flex: 1 1 140px;
    justify-content: center;
  }

  .comm-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.65rem;
  }

  .comm-milestone-tabs {
    overflow-x: auto;
    width: 100%;
    padding-bottom: 0.25rem;
  }

  .comm-history-header {
    flex-direction: column;
    align-items: stretch;
  }

  .history-controls-group {
    width: 100%;
  }
  .history-search-box {
    flex: 1;
    width: 100%;
  }

  /* Table to Mobile Cards */
  .comm-table thead {
    display: none;
  }

  .comm-table, 
  .comm-table tbody, 
  .comm-table tr, 
  .comm-table td {
    display: block;
    width: 100%;
  }

  .comm-table tbody tr {
    margin: 0.75rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 0.75rem 0.9rem;
    box-shadow: var(--shadow-subtle);
  }

  .comm-table tbody td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border-light);
    font-size: 0.82rem;
  }

  .comm-table tbody td:last-child {
    border-bottom: none;
    padding-top: 0.65rem;
  }

  .comm-table tbody td::before {
    content: attr(data-label);
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-right: 0.75rem;
  }

  .comm-table tbody td[data-label="Action"]::before {
    display: none;
  }

  .comm-table tbody td .btn-whatsapp-compact,
  .comm-table tbody td .btn-success {
    width: 100%;
    text-align: center;
    justify-content: center;
  }

  .comm-empty-cell {
    display: block !important;
  }
  .comm-empty-cell::before {
    display: none !important;
  }
}

@media (max-width: 480px) {
  .comm-kpi-grid {
    grid-template-columns: 1fr;
  }
  .comm-action-btn {
    flex: 1 1 100%;
  }
}
</style>

<!-- ========================================================= -->
<!-- JAVASCRIPT ENGINE & LOGIC (100% PRESERVED)                -->
<!-- ========================================================= -->
<script>
let expiringGroupsData = null;
let currentOfferQueue = [];
let queueIndex = 0;
let isAutoSending = false;
let autoSendTimer = null;

const offerPresets = {
  'win_back_discount': "{Hi|Hello|Hey} {name}! 🎉 We miss your fitness energy at {gym_name}! This week only, rejoin with *30% OFF* on all quarterly & annual memberships + ZERO admission fees! Reply YES or visit reception to claim your exclusive comeback pass.",
  'festive_renewal': "🔥 Festive Power Offer for {name}! Renew your workout membership at {gym_name} this week and get *1 FULL MONTH FREE* added to your plan! Don't let your transformation pause—offer valid till Sunday only.",
  'pt_combo': "{Hi|Hello|Dear} {name}! 🏋️ Ready to level up your physique? Get our *1-on-1 Personal Training Transformation Package (12 Sessions + Custom Diet Plan)* at an exclusive 25% discount this month at {gym_name}!",
  'refer_friend': "🤝 Workout with your friend at {gym_name}! Refer a gym buddy this week, and both of you will receive *15 Extra Days FREE* on your active memberships! Visit the front desk with your friend today."
};

function applyOfferPreset(key) {
  if (offerPresets[key]) {
    document.getElementById('offerMessageText').value = offerPresets[key];
  }
}

function openOfferCampaignModal() {
  document.getElementById('offerSetupStep').style.display = 'block';
  document.getElementById('offerQueueStep').style.display = 'none';
  if (!document.getElementById('offerMessageText').value) {
    applyOfferPreset('win_back_discount');
  }
  openModal('offerCampaignModal');
}

function backToOfferSetup() {
  pauseSafeAutoSend();
  document.getElementById('offerSetupStep').style.display = 'block';
  document.getElementById('offerQueueStep').style.display = 'none';
}

function prepareSafeOfferQueue() {
  const target = document.getElementById('offerTarget').value;
  const msg = document.getElementById('offerMessageText').value.trim();

  if (!msg) {
    showToast('Please enter offer message text', 'warning');
    return;
  }

  showToast('🛡️ Building Anti-Ban queue with unique text hashes...', 'info');

  fetch('api/marketing.php?action=prepare_offer_queue', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      target_group: target,
      offer_message: msg
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success && res.data.queue.length > 0) {
      currentOfferQueue = res.data.queue;
      queueIndex = 0;
      renderQueueUI();
      document.getElementById('offerSetupStep').style.display = 'none';
      document.getElementById('offerQueueStep').style.display = 'block';
    } else {
      showToast(res.message || 'No members found in selected group', 'warning');
    }
  });
}

function renderQueueUI() {
  document.getElementById('queueCountDisplay').innerText = `${currentOfferQueue.length} Recipients in Queue`;
  const container = document.getElementById('queueItemsList');
  container.innerHTML = '';

  currentOfferQueue.forEach((item, idx) => {
    const div = document.createElement('div');
    div.id = `qitem_${idx}`;
    div.style = "display:flex; justify-content:space-between; align-items:center; background:#FFFFFF; border:1px solid #E2E8F0; padding:0.6rem 0.85rem; border-radius:8px; font-size:0.82rem;";
    div.innerHTML = `
      <div>
        <strong style="color:#0F172A;">${escapeHtml(item.name)}</strong> <span style="color:#64748B; font-size:0.75rem;">(${escapeHtml(item.phone)})</span>
        <div style="font-size:0.72rem; color:#64748B; max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
          ${escapeHtml(item.message.slice(0, 60))}...
        </div>
      </div>
      <div>
        <button class="btn btn-sm btn-success" id="qbtn_${idx}" onclick="sendSingleFromQueue(${idx})" style="padding:0.25rem 0.65rem; font-size:0.75rem; font-weight:700;">
          💬 Send
        </button>
      </div>
    `;
    container.appendChild(div);
  });

  updateProgress();
}

function updateProgress() {
  const pct = currentOfferQueue.length > 0 ? Math.round((queueIndex / currentOfferQueue.length) * 100) : 0;
  document.getElementById('queueProgressPct').innerText = `${pct}% (${queueIndex}/${currentOfferQueue.length})`;
  document.getElementById('queueProgressBar').style.width = `${pct}%`;
}

function sendSingleFromQueue(idx) {
  const item = currentOfferQueue[idx];
  if (!item) return;

  // Open WhatsApp in new tab safely
  window.open(item.wa_url, '_blank');

  // Mark sent in UI
  const row = document.getElementById(`qitem_${idx}`);
  if (row) {
    row.style.background = '#ECFDF5';
    row.style.borderColor = '#10B981';
  }
  const btn = document.getElementById(`qbtn_${idx}`);
  if (btn) {
    btn.innerText = '✓ Sent';
    btn.disabled = true;
    btn.className = 'btn btn-sm btn-secondary';
  }

  // Log in background
  fetch('api/marketing.php?action=log_offer_sent', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      member_id: item.member_id,
      phone: item.phone,
      message: item.message
    })
  });
}

function startSafeAutoSend() {
  if (queueIndex >= currentOfferQueue.length) {
    showToast('All messages in queue already processed!', 'info');
    return;
  }

  isAutoSending = true;
  document.getElementById('startAutoSendBtn').style.display = 'none';
  document.getElementById('pauseAutoSendBtn').style.display = 'block';

  processNextInQueue();
}

function pauseSafeAutoSend() {
  isAutoSending = false;
  if (autoSendTimer) clearTimeout(autoSendTimer);
  document.getElementById('startAutoSendBtn').style.display = 'block';
  document.getElementById('pauseAutoSendBtn').style.display = 'none';
  document.getElementById('startAutoSendBtn').innerText = '▶️ RESUME SAFE AUTO-SENDER';
}

function processNextInQueue() {
  if (!isAutoSending || queueIndex >= currentOfferQueue.length) {
    pauseSafeAutoSend();
    if (queueIndex >= currentOfferQueue.length) {
      showToast('🎉 All Offer Messages Sent Safely with Zero Ban Risk!', 'success');
    }
    return;
  }

  sendSingleFromQueue(queueIndex);
  queueIndex++;
  updateProgress();

  if (queueIndex < currentOfferQueue.length && isAutoSending) {
    // 🛡️ Random Humanized Safe Delay between 6 and 10 seconds
    const randomDelay = Math.floor(Math.random() * 4000) + 6000;
    autoSendTimer = setTimeout(processNextInQueue, randomDelay);
  } else {
    pauseSafeAutoSend();
  }
}

function loadMilestone(groupKey) {
  const titles = {
    'fees_due': '🚨 Members with Expired Plans / Fees Overdue',
    'birthdays': '🎂 Members Celebrating Birthday Today',
    'same_day': '🔔 Members Expiring Today (Same-Day Alert)',
    'days_2': '🚨 Members Expiring in 2 Days',
    'week_1': '⚠️ Members Expiring in 1 Week (7 Days)',
    'weeks_3': '⏳ Members Expiring in 3 Weeks (21 Days)'
  };
  document.getElementById('activeMilestoneTitle').innerText = titles[groupKey] || 'Expiring Members Queue';

  // Update active pill button
  document.querySelectorAll('.milestone-pill-btn').forEach(btn => {
    btn.classList.remove('active');
    if (btn.getAttribute('onclick')?.includes(groupKey)) {
      btn.classList.add('active');
    }
  });

  if (!expiringGroupsData) {
    document.getElementById('milestoneLoading').style.display = 'flex';
    fetch('api/marketing.php?action=expiring_groups')
      .then(res => res.json())
      .then(res => {
        document.getElementById('milestoneLoading').style.display = 'none';
        if (res.success) {
          expiringGroupsData = res.data;
          renderMilestoneTable(groupKey);
        }
      });
  } else {
    renderMilestoneTable(groupKey);
  }
}

function renderMilestoneTable(groupKey) {
  const tbody = document.getElementById('milestoneTableBody');
  tbody.innerHTML = '';

  const list = expiringGroupsData ? (expiringGroupsData[groupKey] || []) : [];
  if (list.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="comm-empty-cell"><div class="empty-state-wrap"><div class="empty-state-icon">🎉</div><h3 class="empty-state-title">No members in this category</h3><p class="empty-state-text">No active records match the selected milestone queue.</p></div></td></tr>`;
    return;
  }

  list.forEach(m => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td data-label="Member">
        <strong class="member-name-text">${escapeHtml(m.name)}</strong>
        <div style="font-size:0.72rem; color:var(--text-muted); font-family:var(--font-mono);">${escapeHtml(m.member_code)}</div>
      </td>
      <td data-label="Contact Phone">
        <span class="phone-text">${escapeHtml(m.phone)}</span>
      </td>
      <td data-label="Plan/Event">
        <span class="badge badge-info uppercase font-bold">${escapeHtml(m.plan_title || 'Birthday Wish')}</span>
        ${m.end_date ? `<div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.15rem;">Exp: ${m.end_date}</div>` : ''}
      </td>
      <td data-label="Message Preview">
        <div class="msg-preview-text" title="${escapeHtml(m.formatted_message)}">
          ${escapeHtml(m.formatted_message)}
        </div>
      </td>
      <td data-label="Action" style="text-align:right;">
        <a href="${m.wa_url}" target="_blank" class="btn btn-whatsapp-compact" title="Open direct WhatsApp chat with message">
          <span>💬 Send WhatsApp</span>
        </a>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function runAutomatedScan() {
  showToast('Running automated WhatsApp reminder scan...', 'info');
  fetch('api/marketing.php?action=run_automated_reminders')
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(res.message || 'Scan failed', 'danger');
      }
    });
}

function updateDisclaimerLivePreview() {
  const txt = document.getElementById('discBody')?.value || '';
  const isEnabled = document.getElementById('discEnabled')?.checked;
  const preview = document.getElementById('discLivePreview');
  if (preview) {
    preview.style.display = isEnabled ? 'block' : 'none';
    preview.textContent = txt || '_Note: Terms & conditions apply._';
  }
}

function submitDisclaimerEdit() {
  const isEnabled = document.getElementById('discEnabled').checked;
  const disc = document.getElementById('discBody').value.trim();

  fetch('api/marketing.php?action=save_disclaimer', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      disclaimer: disc,
      enabled: isEnabled
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast('WhatsApp Message Disclaimer updated successfully!', 'success');
      closeModal('disclaimerModal');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(res.message || 'Failed to save disclaimer', 'danger');
    }
  });
}

function filterHistoryTable() {
  const query = (document.getElementById('historySearchInput')?.value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.history-row');
  let visible = 0;

  rows.forEach(r => {
    const name = r.getAttribute('data-name') || '';
    const phone = r.getAttribute('data-phone') || '';
    const ev = r.getAttribute('data-event') || '';
    const msg = r.getAttribute('data-msg') || '';

    if (!query || name.includes(query) || phone.includes(query) || ev.includes(query) || msg.includes(query)) {
      r.style.display = '';
      visible++;
    } else {
      r.style.display = 'none';
    }
  });

  const noMatch = document.getElementById('historyNoMatchRow');
  if (noMatch) {
    noMatch.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
  }
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ---------------- NODE.JS WHATSAPP GATEWAY INTEGRATION ----------------
let commNodeWaPollTimer = null;

function checkCommNodeWaStatus(manual = false) {
  fetch('api/whatsapp.php?action=status')
    .then(res => res.json())
    .then(res => {
      const data = res.data || {};
      const badge = document.getElementById('commNodeWaStatusBadge');
      const qrBox = document.getElementById('commNodeWaQrBox');
      const connBox = document.getElementById('commNodeWaConnectedBox');
      const offBox = document.getElementById('commNodeWaOfflineBox');
      const testBtn = document.getElementById('commNodeWaTestBtn');
      const logoutBtn = document.getElementById('commNodeWaLogoutBtn');

      if (!data.online) {
        badge.innerText = '🔴 Offline';
        badge.className = 'badge comm-status-badge badge-danger';
        qrBox.style.display = 'none';
        connBox.style.display = 'none';
        offBox.style.display = 'block';
        testBtn.style.display = 'none';
        logoutBtn.style.display = 'none';
        if (manual) showToast('Node.js WhatsApp service is offline', 'danger');
        return;
      }

      if (data.status === 'connected') {
        badge.innerText = '🟢 Online / Connected';
        badge.className = 'badge comm-status-badge badge-success';
        qrBox.style.display = 'none';
        offBox.style.display = 'none';
        connBox.style.display = 'block';
        testBtn.style.display = 'inline-block';
        logoutBtn.style.display = 'inline-block';

        if (data.user) {
          document.getElementById('commNodeWaUserPhone').innerText = '+' + (data.user.phone || '9876543210');
          document.getElementById('commNodeWaUserName').innerText = data.user.name || 'Gym Bot';
        }

        if (commNodeWaPollTimer) {
          clearInterval(commNodeWaPollTimer);
          commNodeWaPollTimer = null;
        }

        if (manual) showToast('WhatsApp Multi-Device Gateway is Connected!', 'success');

      } else if (data.status === 'qr_ready' || data.qr) {
        badge.innerText = '📱 Scan QR Code';
        badge.className = 'badge comm-status-badge badge-warning';
        offBox.style.display = 'none';
        connBox.style.display = 'none';
        qrBox.style.display = 'block';
        testBtn.style.display = 'none';
        logoutBtn.style.display = 'inline-block';

        if (data.qr) {
          document.getElementById('commNodeWaQrImg').src = data.qr;
        }

        // Start auto-poll timer if not running
        if (!commNodeWaPollTimer) {
          commNodeWaPollTimer = setInterval(() => checkCommNodeWaStatus(false), 4000);
        }

      } else {
        badge.innerText = '⏳ Connecting...';
        badge.className = 'badge comm-status-badge badge-info';
      }
    })
    .catch(err => {
      console.error('WhatsApp Gateway status check error:', err);
    });
}

function sendCommNodeTestPing() {
  showToast('Sending test ping via Node.js Gateway...', 'info');
  fetch('api/whatsapp.php?action=test_ping', { method: 'POST' })
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        showToast(res.message || 'Test message sent successfully!', 'success');
      } else {
        showToast(res.message || 'Failed to send test message', 'danger');
      }
    });
}

function logoutCommNodeWa() {
  if (!confirm('Are you sure you want to disconnect WhatsApp session? You will need to scan QR code again.')) return;

  fetch('api/whatsapp.php?action=logout', { method: 'POST' })
    .then(res => res.json())
    .then(res => {
      showToast('Session reset. Ready for new QR scan.', 'info');
      setTimeout(() => checkCommNodeWaStatus(true), 1500);
    });
}

document.addEventListener('DOMContentLoaded', () => {
  loadMilestone('birthdays');
  checkCommNodeWaStatus(false);
});
</script>
