/**
 * Shared Utilities
 * Toast notifications, modals, pagination, HTML escaping, time formatting.
 */

// ======================== HTML Escaping ========================

/** Escape HTML special characters to prevent XSS */
function escHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// ======================== Time Formatting ========================

/** Format a timestamp into a human-readable relative or absolute string */
function formatTime(str) {
  if (!str) return '-';
  const d = new Date(str);
  if (isNaN(d.getTime())) return str;
  const now = new Date();
  const diff = (now - d) / 1000;
  if (diff < 60) return '刚刚';
  if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
  if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// ======================== Toast Notifications ========================

/** Show a toast message (success/error/warning) */
function toast(msg, type = 'success') {
  const container = document.getElementById('toastContainer');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  container.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateX(100%)';
    el.style.transition = 'all 0.3s';
    setTimeout(() => el.remove(), 300);
  }, 3000);
}

// ======================== Modal Helpers ========================

/** Open a modal by ID */
function openModal(id) {
  document.getElementById(id).classList.add('show');
}

/** Close a modal by ID */
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
}

// ======================== Pagination ========================

/**
 * Render pagination controls into a container element.
 * @param {HTMLElement} container - Target DOM element
 * @param {number} current - Current page number
 * @param {number} totalPages - Total number of pages
 * @param {Function} callback - Function to call with new page number
 */
function renderPagination(container, current, totalPages, callback) {
  if (totalPages <= 1) { container.innerHTML = ''; return; }

  let html = `<span>共 ${totalPages} 页</span><div class="page-btns">`;
  html += `<button class="page-btn" ${current <= 1 ? 'disabled' : ''} onclick="(${callback.name})(${current - 1})">‹ 上一页</button>`;

  // Show max 5 page buttons centered around current
  let start = Math.max(1, current - 2);
  let end = Math.min(totalPages, start + 4);
  start = Math.max(1, end - 4);

  for (let i = start; i <= end; i++) {
    html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="(${callback.name})(${i})">${i}</button>`;
  }

  html += `<button class="page-btn" ${current >= totalPages ? 'disabled' : ''} onclick="(${callback.name})(${current + 1})">下一页 ›</button>`;
  html += `</div>`;

  container.innerHTML = html;
}
