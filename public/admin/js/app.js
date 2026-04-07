/**
 * Core Application
 * Auth state, page routing, sidebar, keyboard shortcuts, initialization.
 */

// ======================== Global State ========================
let token = localStorage.getItem('admin_token') || '';
let adminInfo = JSON.parse(localStorage.getItem('admin_info') || 'null');
let currentPage = 'dashboard';
let currentPostId = null;
let currentReportId = null;

// Track which pages have been loaded into the DOM
const loadedPages = {};

// Page HTML templates — registered by each page's JS file
const PAGE_TEMPLATES = {};

// ======================== Status/Category Maps ========================
const POST_STATUS = { 0: '待审核', 1: '已发布', 2: '已找到', 3: '已关闭', 4: '已驳回' };
const POST_STATUS_CLASS = { 0: 'pending', 1: 'active', 2: 'found', 3: 'closed', 4: 'rejected' };
const CATEGORY = { 1: '宠物', 2: '老人', 3: '儿童', 4: '其它物品' };
const CATEGORY_CLASS = { 1: 'pet', 2: 'elder', 3: 'child', 4: 'other' };
const CATEGORY_ICON = { 1: '🐾', 2: '👴', 3: '👶', 4: '📦' };
const REPORT_TARGET = { 1: '启事', 2: '线索', 3: '用户' };
const REPORT_REASON = { 1: '虚假信息', 2: '广告', 3: '涉及违法', 4: '骚扰', 5: '其他' };
const REPORT_STATUS = { 0: '待处理', 1: '有效', 2: '无效', 3: '已忽略' };
const REPORT_STATUS_CLASS = { 0: 'pending', 1: 'active', 2: 'rejected', 3: 'closed' };

// ======================== Page Loading ========================

/**
 * Load a page's HTML template into the DOM from PAGE_TEMPLATES registry.
 * Each page JS file registers its HTML via PAGE_TEMPLATES['pageName'] = `...`;
 */
function loadPageHTML(page) {
  if (loadedPages[page]) return;

  const html = PAGE_TEMPLATES[page];
  if (!html) {
    console.error('[App] No template registered for page:', page);
    toast('页面模板未注册: ' + page, 'error');
    return;
  }

  const container = document.getElementById('mainContent');
  const wrapper = document.createElement('div');
  wrapper.id = 'pageWrapper_' + page;
  wrapper.innerHTML = html;
  container.appendChild(wrapper);

  // Bind modal overlay click-to-close for newly added modals
  wrapper.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', (e) => {
      if (e.target === el) el.classList.remove('show');
    });
  });

  loadedPages[page] = true;
}

// ======================== Initialization ========================
document.addEventListener('DOMContentLoaded', () => {
  if (token && adminInfo) {
    showApp();
  } else {
    showLogin();
  }

  // Keyboard shortcuts
  document.addEventListener('keydown', (e) => {
    // ESC to close modals
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.show').forEach(el => el.classList.remove('show'));
    }
    // Ctrl/Cmd + K: Focus visible search input
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      const searchInputs = document.querySelectorAll('input[type="text"]:not([type="hidden"])');
      const visibleInput = Array.from(searchInputs).find(input => input.offsetParent !== null);
      if (visibleInput) visibleInput.focus();
    }
    // F5 or Ctrl/Cmd + R: Refresh current page data
    if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key === 'r')) {
      e.preventDefault();
      refreshCurrentPage();
    }
  });
});

// ======================== Auth ========================

function showLogin() {
  document.getElementById('loginPage').classList.remove('hidden');
  document.getElementById('appLayout').classList.add('hidden');
}

function showApp() {
  document.getElementById('loginPage').classList.add('hidden');
  document.getElementById('appLayout').classList.remove('hidden');
  updateAdminUI();
  switchPage('dashboard');
}

function updateAdminUI() {
  if (!adminInfo) return;
  document.getElementById('adminName').textContent = adminInfo.realname || adminInfo.username;
  document.getElementById('adminRole').textContent = adminInfo.role == 2 ? '超级管理员' : '审核员';
  document.getElementById('adminAvatar').textContent = (adminInfo.realname || adminInfo.username || 'A')[0].toUpperCase();

  // Hide manager nav for non-super admins
  const managerNav = document.querySelector('.nav-item[data-page="managers"]');
  if (managerNav) {
    managerNav.classList.toggle('hidden', adminInfo.role != 2);
  }
}

// Login form handler
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const username = document.getElementById('loginUsername').value.trim();
  const password = document.getElementById('loginPassword').value;
  const errorEl = document.getElementById('loginError');
  const btn = document.getElementById('btnLogin');

  if (!username || !password) {
    errorEl.textContent = '请输入用户名和密码';
    return;
  }

  btn.disabled = true;
  btn.textContent = '登录中...';
  errorEl.textContent = '';

  try {
    const res = await apiPost('/auth/login', { username, password });
    if (res.code === 0) {
      token = res.data.token;
      adminInfo = res.data.admin;
      localStorage.setItem('admin_token', token);
      localStorage.setItem('admin_info', JSON.stringify(adminInfo));
      showApp();
    } else {
      errorEl.textContent = res.msg || '登录失败';
    }
  } catch (err) {
    errorEl.textContent = '网络异常，请稍后重试';
  } finally {
    btn.disabled = false;
    btn.textContent = '登 录';
  }
});

function logout() {
  if (!confirm('确定退出登录？')) return;
  token = '';
  adminInfo = null;
  localStorage.removeItem('admin_token');
  localStorage.removeItem('admin_info');
  // Clear loaded pages cache so re-login starts fresh
  Object.keys(loadedPages).forEach(k => delete loadedPages[k]);
  document.getElementById('mainContent').innerHTML = '';
  showLogin();
}

// ======================== Page Router ========================

// Map of page names to their data loading functions
const PAGE_LOADERS = {
  dashboard:  () => loadDashboard(),
  audit:      () => loadAuditList(1),
  reports:    () => loadReportList(1),
  users:      () => loadUserList(1),
  clues:      () => loadClueList(1),
  managers:   () => loadManagerList(1),
  wallet:     () => switchWalletTab('recharge'),
  signin:     () => { loadSignStats(); loadSignLogs(1); loadTaskDefinitions(); loadTaskLogs(1); },
  settings:   () => { loadBannerConfig(); loadAboutConfig(); loadLangList(); },
};

function switchPage(page) {
  currentPage = page;

  // Update nav active state
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });

  // Load page HTML if not yet in DOM
  loadPageHTML(page);

  // Hide all pages, show target
  document.querySelectorAll('.page').forEach(el => el.classList.add('hidden'));
  const pageEl = document.querySelector('#pageWrapper_' + page + ' .page');
  if (pageEl) {
    pageEl.classList.remove('hidden');
  }

  // Load page data
  const loader = PAGE_LOADERS[page];
  if (loader) loader();
}

/** Refresh the currently active page's data */
function refreshCurrentPage() {
  switch (currentPage) {
    case 'dashboard': loadDashboard(); break;
    case 'audit': loadAuditList(auditData.page || 1); break;
    case 'reports': loadReportList(reportData.page || 1); break;
    case 'users': loadUserList(userData.page || 1); break;
    case 'clues': loadClueList(clueData.page || 1); break;
    case 'managers': loadManagerList(managerData.page || 1); break;
    case 'wallet': switchWalletTab(currentWalletTab); break;
    case 'signin': loadSignStats(); loadSignLogs(signinData.page || 1); loadTaskDefinitions(); loadTaskLogs(taskLogData.page || 1); break;
    case 'settings': loadBannerConfig(); loadAboutConfig(); loadLangList(); break;
  }
}
