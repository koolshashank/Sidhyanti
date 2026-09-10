    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const menuToggleBtn = document.getElementById('menuToggleBtn');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

  function openSidebar() {
    sidebar.classList.add('open');
    backdrop.classList.add('show');
    menuToggleBtn.setAttribute('aria-expanded', 'true');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
    menuToggleBtn.setAttribute('aria-expanded', 'false');
  }

  if (menuToggleBtn) menuToggleBtn.addEventListener('click', openSidebar);
  if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
  if (backdrop) backdrop.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSidebar();
  });
  sidebar.querySelectorAll('a.nav-link').forEach((link) => {
    link.addEventListener('click', closeSidebar);
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) closeSidebar();
  });
})();
(function () {
  const bell = document.getElementById('notifBellBtn');
  const dot = document.getElementById('notifDot');
  const markAllBtn = document.getElementById('notifMarkAllBtn');
  const list = document.getElementById('notifPanelList');
  if (!bell) return;

  function markRead(body) {
    return fetch('notification_mark_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    }).then(r => r.json());
  }

  list.querySelectorAll('.notif-item.unread').forEach(item => {
    item.addEventListener('click', () => {
      if (!item.classList.contains('unread')) return;
      const id = item.dataset.notifId;
      item.classList.remove('unread');
      const itemDot = item.querySelector('.notif-item-dot');
      if (itemDot) itemDot.remove();
      markRead('id=' + encodeURIComponent(id)).then(res => {
        if (res && typeof res.unread_count === 'number') updateBellCount(res.unread_count);
      });
    });
  });

  if (markAllBtn) {
    markAllBtn.addEventListener('click', () => {
      list.querySelectorAll('.notif-item.unread').forEach(item => {
        item.classList.remove('unread');
        const itemDot = item.querySelector('.notif-item-dot');
        if (itemDot) itemDot.remove();
      });
      markAllBtn.remove();
      markRead('all=1').then(res => {
        if (res && typeof res.unread_count === 'number') updateBellCount(res.unread_count);
      });
    });
  }

  function updateBellCount(count) {
    if (count <= 0 && dot) dot.remove();
  }
})();
</script>
</body>
</html>
