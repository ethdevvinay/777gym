<!-- Automated Marketing, Trial Welcome & Festive Offers Engine View Component -->
<?php
$db = getDB();

// Fetch Templates
$templates = $db->query("SELECT * FROM marketing_templates ORDER BY id ASC")->fetchAll();

// Fetch Today's Trial Members
$today = date('Y-m-d');
$todayTrials = $db->query("
    SELECT s.*, m.id as member_id, m.name as member_name, m.phone as member_phone, m.member_code, mt.title as plan_title,
           (SELECT id FROM marketing_logs WHERE member_id = m.id AND trigger_event = 'trial_welcome' AND DATE(sent_at) = '{$today}' LIMIT 1) as is_sent
    FROM member_subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_types mt ON s.membership_type_id = mt.id
    WHERE (DATE(s.start_date) = '{$today}' OR DATE(s.created_at) = '{$today}')
      AND (mt.category = 'trial' OR mt.title LIKE '%trial%' OR mt.title LIKE '%pass%' OR mt.duration_days <= 15)
    ORDER BY s.id DESC
")->fetchAll();

// Fetch Message Logs
$logs = $db->query("
    SELECT l.*, m.name as member_name 
    FROM marketing_logs l 
    LEFT JOIN members m ON l.member_id = m.id 
    ORDER BY l.id DESC LIMIT 30
")->fetchAll();

$membersCount = $db->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$trialCount = $db->query("
    SELECT COUNT(DISTINCT m.id) FROM members m 
    JOIN member_subscriptions s ON m.id = s.member_id 
    JOIN membership_types mt ON s.membership_type_id = mt.id 
    WHERE mt.category = 'trial' OR mt.title LIKE '%trial%' OR mt.title LIKE '%pass%' OR mt.duration_days <= 15
")->fetchColumn();
?>

<div class="page-content">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin:0;">
        📢 Marketing, Trial Welcome &amp; Festive Offers Engine
      </h1>
      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.25rem;">
        Same-Day Trial Welcome Messages, Diwali Mega Offers, Festive Campaigns &amp; Automated WhatsApp Triggers
      </p>
    </div>
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <button class="btn btn-primary" onclick="triggerDailyScan()" style="box-shadow:0 4px 12px rgba(37,99,235,0.25);">
        ⚡ Run Same-Day Auto Scan Now
      </button>
      <a href="index.php?page=communication" class="btn btn-success">
        📱 WhatsApp Reminders Hub
      </a>
    </div>
  </div>

  <!-- Today's Trial Signups Banner / Notification -->
  <?php if (!empty($todayTrials)): ?>
    <div class="card" style="background:#EFF6FF; border:1px solid #93C5FD; margin-bottom:1.5rem; border-left:5px solid #2563EB;">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
        <div>
          <strong style="font-size:1.05rem; color:#1E40AF; display:flex; align-items:center; gap:0.4rem;">
            <span>🏋️‍♂️</span> <span>TODAY'S TRIAL VISITORS (<?= count($todayTrials) ?> Signups Today)</span>
          </strong>
          <p style="font-size:0.8rem; color:#1E3A8A; margin:0.2rem 0 0 0;">
            Send same-day welcome &amp; impressive conversion offers to turn today's trial workouts into full regular memberships!
          </p>
        </div>
        <button class="btn btn-sm btn-primary" onclick="loadOfferPreset('trial')" style="font-weight:800;">
          ✉️ Prepare Trial Welcome Blast
        </button>
      </div>

      <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <?php foreach ($todayTrials as $tt): ?>
          <div style="background:#fff; border:1px solid #BFDBFE; border-radius:8px; padding:0.6rem 0.85rem; display:flex; align-items:center; gap:0.75rem; font-size:0.82rem;">
            <div>
              <strong><?= htmlspecialchars($tt['member_name']) ?></strong>
              <div style="font-size:0.72rem; color:var(--text-muted);"><?= htmlspecialchars($tt['plan_title']) ?> • <?= htmlspecialchars($tt['member_phone']) ?></div>
            </div>
            <?php if ($tt['is_sent']): ?>
              <span class="badge badge-success" style="font-size:0.7rem;">✓ Sent Today</span>
            <?php else: ?>
              <button class="btn btn-sm btn-outline" onclick="sendDirectTrialWelcome(<?= $tt['member_id'] ?>, '<?= addslashes($tt['member_name']) ?>')" style="font-size:0.72rem; padding:0.2rem 0.5rem; color:#2563EB; border-color:#93C5FD;">
                📱 Send Welcome
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Node.js WhatsApp Gateway Connection Hub -->
  <div class="card" style="background:linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color:#FFFFFF; border:1px solid #334155; border-radius:16px; padding:1.5rem; margin-bottom:1.5rem; position:relative; overflow:hidden;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="display:flex; align-items:center; gap:0.6rem;">
          <span style="font-size:1.4rem;">🟢</span>
          <span style="font-size:1.25rem; font-weight:800; letter-spacing:-0.02em;">Node.js WhatsApp Multi-Device Gateway</span>
          <span id="nodeWaStatusBadge" class="badge" style="background:#F59E0B; color:#000; font-weight:800; font-size:0.75rem; text-transform:uppercase;">
            ⏳ Checking Status...
          </span>
        </div>
        <p style="font-size:0.82rem; color:#94A3B8; margin-top:0.35rem; max-width:650px;">
          Send automatic WhatsApp messages directly in the background via local Node.js WebSocket engine. No manual browser tabs required!
        </p>
      </div>

      <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
        <button class="btn btn-sm" onclick="checkNodeWhatsAppStatus(true)" style="background:#334155; color:#FFFFFF; border:1px solid #475569; font-weight:700; font-size:0.78rem;">
          🔄 Refresh Status
        </button>
        <button id="nodeWaTestBtn" class="btn btn-sm btn-success" onclick="sendTestWhatsAppPing()" style="font-weight:800; font-size:0.78rem; display:none;">
          ⚡ Send Test Message
        </button>
        <button id="nodeWaLogoutBtn" class="btn btn-sm btn-danger" onclick="logoutNodeWhatsApp()" style="font-weight:800; font-size:0.78rem; display:none;">
          🚪 Disconnect
        </button>
      </div>
    </div>

    <!-- Gateway Dynamic Body (Connected vs QR Code vs Offline) -->
    <div id="nodeWaContainer" style="margin-top:1.25rem; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:1.25rem;">
      
      <!-- State 1: QR Ready (Scan to Connect) -->
      <div id="nodeWaQrBox" style="display:none; text-align:center; padding:1rem 0;">
        <div style="display:flex; flex-direction:column; align-items:center; gap:0.75rem;">
          <div style="font-size:0.95rem; font-weight:700; color:#FCD34D;">
            📱 SCAN QR CODE WITH WHATSAPP TO LINK BOT
          </div>
          <p style="font-size:0.8rem; color:#CBD5E1; max-width:450px; margin:0;">
            1. Open WhatsApp on your phone<br>
            2. Tap <strong>Settings (or 3 dots) → Linked Devices → Link a Device</strong><br>
            3. Point your camera at this QR code:
          </p>
          <div style="background:#FFFFFF; padding:0.75rem; border-radius:12px; display:inline-block; box-shadow:0 8px 24px rgba(0,0,0,0.4); margin:0.5rem 0;">
            <img id="nodeWaQrImg" src="" alt="WhatsApp QR Code" style="width:200px; height:200px; display:block;">
          </div>
          <div style="font-size:0.75rem; color:#94A3B8; animation:pulse 2s infinite;">
            ⏳ Auto-refreshing QR Code... Scan will be detected automatically.
          </div>
        </div>
      </div>

      <!-- State 2: Connected -->
      <div id="nodeWaConnectedBox" style="display:none;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
          <div style="display:flex; align-items:center; gap:1rem;">
            <div style="width:52px; height:52px; border-radius:50%; background:#059669; display:flex; align-items:center; justify-content:center; font-size:1.6rem;">
              ✓
            </div>
            <div>
              <div style="font-size:1.1rem; font-weight:800; color:#34D399;">
                WhatsApp Multi-Device Gateway is 100% ONLINE!
              </div>
              <div style="font-size:0.85rem; color:#CBD5E1; margin-top:0.15rem;">
                Connected Account: <strong id="nodeWaUserPhone" style="color:#FFFFFF; font-family:monospace; font-size:0.95rem;">+91 98765 43210</strong> (<span id="nodeWaUserName">Gym Bot</span>)
              </div>
              <div style="font-size:0.75rem; color:#94A3B8; margin-top:0.15rem;">
                🛡️ Auto-reminders, renewal notices &amp; promotional offers will dispatch automatically in background.
              </div>
            </div>
          </div>

          <div>
            <button class="btn btn-sm btn-primary" onclick="sendTestWhatsAppPing()" style="font-weight:800;">
              ✉️ Send Test Ping to Admin
            </button>
          </div>
        </div>
      </div>

      <!-- State 3: Offline -->
      <div id="nodeWaOfflineBox" style="display:none;">
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
          <div style="font-size:2rem;">⚠️</div>
          <div>
            <div style="font-size:1rem; font-weight:800; color:#F87171;">
              Node.js WhatsApp Server is Currently Offline
            </div>
            <div style="font-size:0.8rem; color:#CBD5E1; margin-top:0.25rem;">
              To enable automated background WhatsApp messaging, run the launcher in your gym root directory:
            </div>
            <div style="margin-top:0.5rem; background:#000000; padding:0.4rem 0.75rem; border-radius:6px; font-family:monospace; font-size:0.8rem; color:#4ADE80; display:inline-block;">
              C:\xampp\htdocs\GYM\start_whatsapp_gateway.bat
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Main 2-Column Hub -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:1.25rem;">
    
    <!-- Zone 1: Bulk Campaign & Festive Offer Broadcaster -->
    <div class="card" style="border-top:4px solid var(--primary);">
      <div class="card-title" style="font-size:1.15rem; font-weight:800;">📢 Launch Bulk Campaign &amp; Festive Offers</div>
      <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:1rem;">
        Select an audience group or 1-click load high-converting Diwali &amp; Trial Welcome offers.
      </p>

      <!-- Quick Preset Offer Chips -->
      <div style="margin-bottom:1rem;">
        <label class="form-label" style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">⚡ 1-CLICK OFFER TEMPLATES:</label>
        <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
          <button type="button" class="btn btn-sm" onclick="loadOfferPreset('diwali')" style="background:#FFFBEB; color:#B45309; border:1px solid #FCD34D; font-weight:700; font-size:0.75rem;">
            🪔 Diwali Mega Dhamaka
          </button>
          <button type="button" class="btn btn-sm" onclick="loadOfferPreset('trial')" style="background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; font-weight:700; font-size:0.75rem;">
            🏋️‍♂️ Trial Welcome &amp; Perk
          </button>
          <button type="button" class="btn btn-sm" onclick="loadOfferPreset('festive')" style="background:#F0FDF4; color:#15803D; border:1px solid #86EFAC; font-weight:700; font-size:0.75rem;">
            🎊 Festive 25% OFF
          </button>
          <button type="button" class="btn btn-sm" onclick="loadOfferPreset('newyear')" style="background:#FAF5FF; color:#7E22CE; border:1px solid #D8B4FE; font-weight:700; font-size:0.75rem;">
            🎉 New Year Special
          </button>
          <button type="button" class="btn btn-sm" onclick="loadOfferPreset('pool_off')" style="background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD; font-weight:800; font-size:0.75rem;">
            🏊 Pool Off Notice
          </button>
          <button type="button" class="btn btn-sm" onclick="loadOfferPreset('pt_off')" style="background:#F5F3FF; color:#6D28D9; border:1px solid #DDD6FE; font-weight:800; font-size:0.75rem;">
            💪 PT Off Notice
          </button>
        </div>
      </div>

      <form id="broadcastForm" onsubmit="event.preventDefault(); launchCampaign();">
        <div class="form-group">
          <label class="form-label">Target Audience Group *</label>
          <select id="targetGroup" class="form-control" required>
            <option value="pool_members">🏊 All Active Swimming Pool Members</option>
            <option value="pt_members">💪 All Active Personal Training Clients</option>
            <option value="trial_members">🏋️‍♂️ All Trial Pass Holders (<?= $trialCount ?> members - Conversion Target)</option>
            <option value="all">All Registered Members</option>
            <option value="active">Active Members Only (<?= $membersCount ?> members)</option>
            <option value="expired">Expired Members (Win-back Offer)</option>
            <option value="birthdays_today">🎂 Members Celebrating Birthday Today</option>
            <option value="expiring_today">🔔 Members Expiring Today</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Delivery Channel</label>
          <select id="deliveryChannel" class="form-control">
            <option value="whatsapp">📱 WhatsApp Message (Direct &amp; Anti-Ban)</option>
            <option value="sms">💬 Direct SMS Gateway</option>
          </select>
        </div>

        <div class="form-group">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
            <label class="form-label" style="margin-bottom:0;">Campaign Message Text *</label>
            <span id="charCounter" style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">0 chars</span>
          </div>
          <textarea id="campaignMessage" class="form-control" rows="6" placeholder="Type message or click any of the festive offer buttons above..." oninput="updateWaPreview(this.value)" required></textarea>
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; font-size:0.75rem; color:var(--text-muted); margin-top:0.35rem;">
            <div>
              Variables: <span class="badge badge-info">{name}</span> <span class="badge badge-info">{member_code}</span> <span class="badge badge-info">{gym_phone}</span>
            </div>
            <span class="badge" style="background:#ECFDF5; color:#059669; font-weight:800; border:1px solid #A7F3D0;">
              🛡️ Anti-Ban Shield Active
            </span>
          </div>
        </div>

        <!-- Real-time WhatsApp Chat Bubble Visual Preview -->
        <div style="background:#E5DDD5; padding:1rem; border-radius:12px; margin-bottom:1.25rem; border:1px solid #D1D5DB;">
          <div style="font-size:0.72rem; font-weight:800; color:#374151; text-transform:uppercase; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.3rem;">
            <span>💬</span> <span>WhatsApp Live Preview</span>
          </div>
          <div style="background:#DCF8C6; padding:0.85rem 1rem; border-radius:10px 10px 0 10px; max-width:92%; margin-left:auto; box-shadow:0 1px 3px rgba(0,0,0,0.12); font-size:0.85rem; line-height:1.45; color:#111827; position:relative;">
            <div id="waPreviewText" style="white-space:pre-line; word-break:break-word;">
              Click any of the offer templates above to see live WhatsApp preview...
            </div>
            <div style="text-align:right; font-size:0.65rem; color:#6B7280; margin-top:0.3rem; display:flex; align-items:center; justify-content:flex-end; gap:0.2rem;">
              <span><?= date('h:i A') ?></span>
              <span style="color:#3B82F6; font-size:0.8rem; font-weight:800;">✓✓</span>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="font-weight:800; box-shadow:0 4px 15px rgba(37,99,235,0.3);">
          🚀 LAUNCH CAMPAIGN &amp; DISPATCH OFFERS
        </button>
      </form>
    </div>

    <!-- Zone 2: Automated Trigger Templates Directory -->
    <div class="card" style="border-top:4px solid var(--success);">
      <div class="card-title" style="font-size:1.15rem; font-weight:800;">🤖 Automated Trigger Rules &amp; Festive Library</div>
      <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:1rem;">
        Automated trigger messages dispatched when members join trials, celebrate birthdays, or expire.
      </p>

      <div style="display:flex; flex-direction:column; gap:0.85rem; max-height:550px; overflow-y:auto; padding-right:0.3rem;">
        <?php foreach ($templates as $tpl): ?>
          <div style="padding:0.85rem; background:var(--bg-main); border:1px solid var(--border-color); border-radius:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
              <strong style="font-size:0.9rem; color:var(--text-primary);"><?= htmlspecialchars($tpl['title']) ?></strong>
              <span class="badge badge-<?= $tpl['status'] === 'active' ? 'success' : 'secondary' ?>" style="font-size:0.7rem;">
                <?= strtoupper(str_replace('_', ' ', $tpl['trigger_event'])) ?>
              </span>
            </div>
            <p style="font-size:0.8rem; color:var(--text-secondary); line-height:1.35; margin:0; white-space:pre-line;"><?= htmlspecialchars($tpl['template_body']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Zone 3: WhatsApp Sent Logs -->
  <div class="card" style="margin-top:1.5rem;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <span class="card-title">📱 Recent Sent Marketing Messages &amp; WhatsApp Activity</span>
      <span class="badge badge-info">Showing latest 30 logs</span>
    </div>

    <div style="overflow-x:auto;">
      <table class="table" style="width:100%;">
        <thead>
          <tr>
            <th>Member</th>
            <th>Phone</th>
            <th>Trigger / Offer</th>
            <th>Message Body Preview</th>
            <th>Sent Time</th>
            <th>Direct Chat</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No marketing messages dispatched yet.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><strong><?= htmlspecialchars($l['member_name'] ?: 'Guest / Lead') ?></strong></td>
                <td><?= htmlspecialchars($l['recipient_phone']) ?></td>
                <td>
                  <span class="badge badge-<?= $l['trigger_event'] === 'trial_welcome' ? 'primary' : ($l['trigger_event'] === 'diwali_offer' ? 'warning' : 'info') ?>">
                    <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $l['trigger_event']))) ?>
                  </span>
                </td>
                <td style="font-size:0.8rem; max-width:350px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                  <?= htmlspecialchars($l['message_body']) ?>
                </td>
                <td style="font-size:0.78rem; color:var(--text-muted);"><?= date('d M Y, h:i A', strtotime($l['sent_at'])) ?></td>
                <td>
                  <a href="<?= formatWhatsAppUrl($l['recipient_phone'], $l['message_body']) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size:0.75rem; color:#25D366; border-color:#25D366;">
                    📱 Chat
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const offerPresets = {
  diwali: {
    target: 'all',
    text: "🪔 Happy Diwali {name}! May this festival of lights bring abundant health, strength, and joy to you and your family! ✨\n\n💥 THE CLUB 777® DIWALI MEGA FITNESS DHAMAKA:\nGet UP TO 40% OFF on 6-Month & Annual Gym + Pool Memberships!\n🎁 Buy 1-Year Membership & Get 2 Months EXTRA FREE + Free Gym Kit Bag!\n\n⏳ Offer valid till Diwali weekend only. Visit THE CLUB 777® (Near Railway Station, Jhajjar) or call {gym_phone} to lock your festive pass! 🏋️‍♂️🔥"
  },
  trial: {
    target: 'trial_members',
    text: "🏋️‍♂️ Hello {name}! Welcome to THE CLUB 777®! 🎉 We are thrilled you took your trial workout session with us today!\n\n🔥 Hope you had a power-packed workout session! Our certified trainers, state-of-the-art equipment, and shower/steam facilities are here for your fitness transformation.\n\n🎁 SPECIAL TRIAL CONVERSION PERK:\nUpgrade to our Regular 3-Month, 6-Month or Annual Plan within 48 hours & get:\n✅ 100% Admission Fee Waived\n✅ FREE 1-on-1 Personal Training Session\n✅ FREE Customized Nutrition Chart\n\nVisit front desk at Behind Shehnai Garden, Near Railway Station, Jhajjar or call {gym_phone} to claim your offer! 💪"
  },
  festive: {
    target: 'all',
    text: "🎊 Festive Greetings from THE CLUB 777®, {name}! Celebrate this festive season by gifting yourself supreme health & fitness! ✨\n\n🎁 FESTIVE SPECIAL DEAL:\nEnjoy FLAT 25% DISCOUNT on all Transformation & Annual Passes + Free Diet Plan!\n\nContact reception or call {gym_phone} to claim today! 🚀"
  },
  newyear: {
    target: 'all',
    text: "🎉 Happy New Year, {name}! 🚀 Make this year your fittest and strongest yet at THE CLUB 777®!\n\n💥 NEW YEAR SPECIAL PASS:\nGet FLAT 30% OFF on all 6-Month & 12-Month memberships + 2 Free PT Sessions!\n\nReply RESOLUTION or call {gym_phone} to lock your festive discount! 💪"
  },
  pool_off: {
    target: 'pool_members',
    text: "🏊 *Notice: Swimming Pool Closed Tomorrow* 🏊\n\nDear {name},\n\nPlease be informed that the Swimming Pool at THE CLUB 777® will remain *CLOSED tomorrow ({tomorrow_date})* due to scheduled deep cleaning, chemical treatment & water filtration maintenance.\n\n✅ Regular swimming batches will resume as normal from the day after.\n\nWe apologize for the temporary inconvenience and appreciate your cooperation! 🙏\n\nFor queries, contact front desk at {gym_phone}."
  },
  pt_off: {
    target: 'pt_members',
    text: "💪 *Notice: Personal Training (PT) Sessions Off Tomorrow* 💪\n\nDear {name},\n\nPlease note that Personal Training (PT) sessions with your assigned trainer will remain *OFF / SUSPENDED tomorrow ({tomorrow_date})* due to trainer workshop & schedule.\n\n✅ *Note:* Your session count will *NOT* be deducted and will be adjusted in your package.\n\nRegular 1-on-1 PT sessions will resume normally the day after. Keep up your fitness dedication!\n\nFor queries, contact your trainer or reception at {gym_phone}."
  }
};

function updateWaPreview(text) {
  const preview = document.getElementById('waPreviewText');
  const counter = document.getElementById('charCounter');
  if (counter) counter.innerText = (text ? text.length : 0) + ' chars';
  if (!text || !text.trim()) {
    if (preview) preview.innerText = 'Click any of the offer templates above to see live WhatsApp preview...';
  } else {
    let sample = text.replace(/{name}/g, 'Rahul Sharma')
                     .replace(/{member_code}/g, 'MEM-1042')
                     .replace(/{phone}/g, '9876543210')
                     .replace(/{gym_name}/g, 'THE CLUB 777®')
                     .replace(/{gym_phone}/g, '8053576777')
                     .replace(/{tomorrow_date}/g, 'Tomorrow');
    if (preview) preview.innerText = sample;
  }
}

function loadOfferPreset(type) {
  const p = offerPresets[type];
  if (!p) return;
  document.getElementById('targetGroup').value = p.target;
  document.getElementById('campaignMessage').value = p.text;
  updateWaPreview(p.text);
  showToast('Offer template loaded! Review and click Launch.', 'info');
}

function launchCampaign() {
  const target = document.getElementById('targetGroup').value;
  const channel = document.getElementById('deliveryChannel').value;
  const message = document.getElementById('campaignMessage').value.trim();

  if (!message) {
    showToast('Please enter message text', 'warning');
    return;
  }

  fetch('api/marketing.php?action=broadcast', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      target_group: target,
      channel: channel,
      message_text: message
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(res.message || 'Campaign launch failed', 'danger');
    }
  });
}

function triggerDailyScan() {
  fetch('api/marketing.php?action=run_automated_reminders')
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(res.message || 'Scan failed', 'danger');
      }
    });
}

function sendDirectTrialWelcome(memberId, memberName) {
  fetch('api/marketing.php?action=send_direct_message', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      member_id: memberId,
      trigger_event: 'trial_welcome',
      message_text: offerPresets.trial.text
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success && res.data.wa_url) {
      window.open(res.data.wa_url, '_blank');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(res.message || 'Sending failed', 'danger');
    }
  });
}

// ---------------- NODE.JS WHATSAPP GATEWAY INTEGRATION ----------------
let nodeWaPollTimer = null;

function checkNodeWhatsAppStatus(manual = false) {
  fetch('api/whatsapp.php?action=status')
    .then(res => res.json())
    .then(res => {
      const data = res.data || {};
      const badge = document.getElementById('nodeWaStatusBadge');
      const qrBox = document.getElementById('nodeWaQrBox');
      const connBox = document.getElementById('nodeWaConnectedBox');
      const offBox = document.getElementById('nodeWaOfflineBox');
      const testBtn = document.getElementById('nodeWaTestBtn');
      const logoutBtn = document.getElementById('nodeWaLogoutBtn');

      if (!data.online) {
        badge.innerText = '🔴 OFFLINE';
        badge.style.background = '#EF4444';
        badge.style.color = '#FFFFFF';
        qrBox.style.display = 'none';
        connBox.style.display = 'none';
        offBox.style.display = 'block';
        testBtn.style.display = 'none';
        logoutBtn.style.display = 'none';
        if (manual) showToast('Node.js WhatsApp service is offline', 'danger');
        return;
      }

      if (data.status === 'connected') {
        badge.innerText = '🟢 CONNECTED';
        badge.style.background = '#10B981';
        badge.style.color = '#FFFFFF';
        qrBox.style.display = 'none';
        offBox.style.display = 'none';
        connBox.style.display = 'block';
        testBtn.style.display = 'inline-block';
        logoutBtn.style.display = 'inline-block';

        if (data.user) {
          document.getElementById('nodeWaUserPhone').innerText = '+' + (data.user.phone || '9876543210');
          document.getElementById('nodeWaUserName').innerText = data.user.name || 'Gym Bot';
        }

        if (nodeWaPollTimer) {
          clearInterval(nodeWaPollTimer);
          nodeWaPollTimer = null;
        }

        if (manual) showToast('WhatsApp Multi-Device Gateway is Connected!', 'success');

      } else if (data.status === 'qr_ready' || data.qr) {
        badge.innerText = '📱 SCAN QR CODE';
        badge.style.background = '#F59E0B';
        badge.style.color = '#000000';
        offBox.style.display = 'none';
        connBox.style.display = 'none';
        qrBox.style.display = 'block';
        testBtn.style.display = 'none';
        logoutBtn.style.display = 'inline-block';

        if (data.qr) {
          document.getElementById('nodeWaQrImg').src = data.qr;
        }

        // Start auto-poll timer if not running
        if (!nodeWaPollTimer) {
          nodeWaPollTimer = setInterval(() => checkNodeWhatsAppStatus(false), 4000);
        }

      } else {
        badge.innerText = '⏳ CONNECTING...';
        badge.style.background = '#3B82F6';
        badge.style.color = '#FFFFFF';
      }
    })
    .catch(err => {
      console.error('WhatsApp Gateway status check error:', err);
    });
}

function sendTestWhatsAppPing() {
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

function logoutNodeWhatsApp() {
  if (!confirm('Are you sure you want to disconnect WhatsApp session? You will need to scan QR code again.')) return;

  fetch('api/whatsapp.php?action=logout', { method: 'POST' })
    .then(res => res.json())
    .then(res => {
      showToast('Session reset. Ready for new QR scan.', 'info');
      setTimeout(() => checkNodeWhatsAppStatus(true), 1500);
    });
}

// Auto-check status on page load
document.addEventListener('DOMContentLoaded', function() {
  checkNodeWhatsAppStatus(false);
});
</script>
