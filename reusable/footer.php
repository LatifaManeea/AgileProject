<footer class="py-5">
  <div class="container">
    <div class="row">
      <div class="col-md-5 ">
        
        
      <h5>Founders</h5>
          <table class="table">
                  <thead style="background-color: inherit; ">
                  <tr>
                  <th style="background-color: inherit; color: #ff6f61;"> Name</th>
                  <th style="background-color: inherit; color: #ff6f61;">ID</th>
                  </tr>
                </thead> 
                <tbody style="background-color: inherit;">
                  <tr>
                  <td style="background-color: inherit; color: white">Latifa Almaneea</td>
                  <td style="background-color: inherit; color: white;">445009197</td>
                  </tr>
                  <tr>
                  <td style="background-color: inherit; color: white;">Amjad alosaimi</td>
                  <td style="background-color: inherit; color: white;">445009178</td>
                  </tr>
                  <tr>
                  <td style="background-color: inherit; color: white;">Joud Almanea</td>
                  <td style="background-color: inherit; color: white;">445009185</td>
                  </tr>
                  <tr>
                  <td style="background-color: inherit; color: white;"> Ajwan Alomar</td>
                  <td style="background-color: inherit; color: white;">445009175</td>
                  </tr>
                  <tr>
                  <td style="background-color: inherit; color: white;">Reuf Algonaimi</td>
                  <td style="background-color: inherit; color: white;">445009194</td>
                  </tr>
                </tbody>
                </table> 
               </div>
      <div class="col-md-3">
        <h5>Opening Hours</h5>
        <ul class="list-unstyled hours">
          <br>
          <li>Sunday - Thursday: 9am - 12am</li>
          <li>Friday: 1pm - 12am</li>
          <li>Saturday: 10am - 12am</li>
        </ul>
      </div>
      <div class="col-md-3">
        <h5>Contact Us</h5>
        <ul class="list-unstyled contact">
          <br>
          <li><i class="fas fa-map-marker-alt"></i> 123 Olaya Street, Suite 100</li>
          <li><i class="fas fa-phone"></i> (+966) 11-493-2646</li>
          <li><i class="fas fa-envelope"></i> info@glamoursalon.com</li>
        </ul>
      </div>
    </div>
    <div class="text-center mt-3">
      <p class="mb-0">&copy; <?php echo date('Y'); ?> Glamour Salon. All Rights Reserved.</p>
    </div>
  </div>
</footer>

<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
?>
<div id="notifBell" class="notif-bell">
  <i class="fas fa-bell"></i>
  <span id="notifBadge" style="display:none;"></span>
</div>

<div id="notifPanel" class="notif-panel">
  <div class="np-header">
    <span><i class="fas fa-bell me-2"></i>Notifications</span>
    <button type="button" class="np-close" aria-label="Close"><i class="fas fa-times"></i></button>
  </div>
  <div id="notifList" class="np-body"></div>
</div>

<style>
/* Bell */
.notif-bell {
  position: fixed; bottom: 28px; right: 28px; width:56px; height:56px;
  background: linear-gradient(45deg,#ff6f61,#bd4f43); color:#fff; border-radius:50%;
  display:flex; align-items:center; justify-content:center; cursor:pointer;
  box-shadow:0 6px 18px rgba(255,111,97,.4); z-index:9999; transition:.3s;
}
.notif-bell:hover { transform:scale(1.08); }
.notif-bell i { font-size:22px; }
#notifBadge {
  position:absolute; top:-4px; right:-4px; background:#dc3545; color:#fff;
  border-radius:50%; min-width:22px; height:22px; font-size:12px; font-weight:600;
  display:flex; align-items:center; justify-content:center; border:2px solid #fff;
  animation:badgePulse 1.4s infinite;
}
@keyframes badgePulse {0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}

/* Panel */
.notif-panel {
  position:fixed; bottom:100px; right:28px; width:360px; max-height:480px;
  background:#fff; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,.25);
  overflow:hidden; opacity:0; visibility:hidden; transform:translateY(15px) scale(.95);
  transition:.28s; z-index:9998;
}
.notif-panel.active { opacity:1; visibility:visible; transform:translateY(0) scale(1); }

.np-header {
  background: linear-gradient(135deg,#ffe6f2,#f0e6ff);
  padding:14px 18px; display:flex; justify-content:space-between; align-items:center;
  font-weight:600; color:#333;
}
.np-close { background:none; border:none; color:#666; cursor:pointer; font-size:18px; }
.np-close:hover { color:#ff6f61; }

.np-body { padding:12px 14px; overflow-y:auto; max-height:400px; }
.np-item {
  display:flex; gap:12px; padding:12px; background:#f8f9fa; border-radius:10px;
  margin-bottom:10px; position:relative; transition:.25s;
  border-left:4px solid #ff6f61;
}
.np-item.dismissed { opacity:.45; filter:grayscale(100%); }
.np-icon {
  width:42px; height:42px; background: linear-gradient(45deg,#ff6f61,#bd4f43);
  border-radius:50%; display:flex; align-items:center; justify-content:center;
  color:#fff; flex-shrink:0;
}
.np-title { font-weight:600; font-size:.95rem; margin-bottom:3px; }
.np-time { color:#dc3545; font-size:.75rem; font-weight:600; margin-bottom:6px; }
.np-detail { font-size:.75rem; color:#555; margin-bottom:6px; }
.np-ack {
  background: linear-gradient(45deg,#ff6f61,#bd4f43); color:#fff; border:none;
  padding:4px 12px; border-radius:18px; font-size:.7rem;
}
.np-ack:hover { box-shadow:0 3px 10px rgba(255,111,97,.4); }

.np-empty { text-align:center; padding:40px 20px; color:#888; }
.np-empty i { font-size:42px; }

@media (max-width:650px){
  .notif-panel{ right:14px; width: calc(100vw - 28px); }
  .notif-bell{ right:14px; bottom:20px; width:50px; height:50px; }
}

/* Scrollbar (optional minimal) */
.np-body::-webkit-scrollbar { width:6px; }
.np-body::-webkit-scrollbar-track { background:transparent; }
.np-body::-webkit-scrollbar-thumb { background:#ffb3ad; border-radius:4px; }

/* Type coloring kept simple */
.np-item[data-type="booking_confirmed"] { border-left: 4px solid #20c997; }
.np-item[data-type="appointment_cancelled"] { border-left: 4px solid #dc3545; }
.np-item[data-type="appointment_modified"] { border-left: 4px solid #0d6efd; }
.np-item[data-type="upcoming"] { border-left: 4px solid #ff6f61; }
</style>

<script>
(function(){
  const bell = document.getElementById('notifBell');
  const panel = document.getElementById('notifPanel');
  const list = document.getElementById('notifList');
  const badge = document.getElementById('notifBadge');
  let open = false;
  let data = [];

  const iconMap = {
    'upcoming': 'fa-clock',
    'booking_confirmed': 'fa-check-circle',
    'appointment_modified': 'fa-edit',
    'appointment_cancelled': 'fa-ban'
  };

  function togglePanel(){ open = !open; panel.classList.toggle('active', open); if(open) refreshUIStates(); }
  bell.addEventListener('click', e => { e.stopPropagation(); togglePanel(); });
  document.querySelector('.np-close').addEventListener('click', togglePanel);
  document.addEventListener('click', e => { if(open && !panel.contains(e.target) && !bell.contains(e.target)){ open=false; panel.classList.remove('active'); } });

  function updateBadge(){
    const unread = data.filter(n => !n.read).length;
    if(unread === 0){ badge.style.display='none'; } else { badge.style.display='flex'; badge.textContent = unread; }
  }

  function refreshUIStates(){ updateBadge(); }

  function renderList(){
    if(!data || data.length === 0){
      list.innerHTML = '<div class="np-empty"><i class="fas fa-bell-slash mb-2"></i><p>No notifications</p></div>';
      updateBadge();
      return;
    }
    list.innerHTML = data.map(n => {
      const icon = iconMap[n.type] || 'fa-info-circle';
      const dismissed = n.read ? ' dismissed' : '';
      return `
        <div class="np-item${dismissed}" data-id="${n.id}" data-type="${n.type}">
          <div class="np-icon"><i class="fas ${icon}"></i></div>
          <div class="np-content">
            <div class="np-title">${escapeHtml(n.title || '')}</div>
            <div class="np-detail"><small>${escapeHtml(n.message || '')}</small></div>
            ${n.read ? '' : '<button class="np-ack btn btn-sm" type="button"><i class="fas fa-check me-1"></i>Got It</button>'}
          </div>
        </div>`;
    }).join('');
    updateBadge();
  }

  function escapeHtml(str){
    return (str||'').replace(/[&<>"] /g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',' ':' ' }[c]||c));
  }

  async function fetchNotifications(){
    try {
      const res = await fetch('/reusable/notifications_api.php', { credentials: 'same-origin' });
      if(!res.ok) throw new Error('Failed to fetch');
      data = await res.json();
      renderList();
    } catch(e) {
      console.error(e);
    }
  }

  // Dismiss handling (client-only)
  list.addEventListener('click', e=>{
    if(e.target.closest('.np-ack')){
      const item = e.target.closest('.np-item');
      const id = parseInt(item.dataset.id, 10);
      // Optimistic UI
      item.classList.add('dismissed');
      e.target.remove();
      const idx = data.findIndex(n=>n.id===id);
      if(idx>=0){ data[idx].read = true; }
      refreshUIStates();
      // Persist
      fetch('/reusable/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'ack', id })
      }).catch(()=>{});
    }
  });

  // Initial badge calc
  document.addEventListener('DOMContentLoaded', ()=>{ fetchNotifications(); });
  // Optional: periodic refresh
  setInterval(fetchNotifications, 60*1000);
})();
</script>
<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>