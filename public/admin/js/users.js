/**
 * Users Page
 * Manage registered users and their account status.
 */

PAGE_TEMPLATES['users'] = `
<div id="pageUsers" class="page">
  <div class="page-header">
    <h2>👥 用户管理</h2>
    <p>管理注册用户及其账号状态</p>
  </div>
  <div class="section-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div class="filter-bar" style="margin:0;flex:1;">
        <input type="text" id="userKeyword" placeholder="搜索昵称/账号...">
        <select id="userStatusFilter">
          <option value="">全部状态</option>
          <option value="1">正常</option>
          <option value="2">禁言</option>
          <option value="3">封禁</option>
        </select>
        <button class="btn-filter" onclick="loadUserList(1)">搜索</button>
      </div>
      <button class="btn-filter" onclick="refreshCurrentPage()" style="background:#6b7280;" title="刷新数据">🔄 刷新</button>
    </div>
    <div id="userLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="userEmpty" class="empty-state hidden"><div class="icon">👥</div><p>暂无用户数据</p></div>
    <table id="userTable" class="hidden">
      <thead><tr><th>ID</th><th>头像</th><th>昵称</th><th>账号</th><th>余额</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
      <tbody id="userTbody"></tbody>
    </table>
    <div class="pagination" id="userPagination"></div>
  </div>
</div>

<div class="modal-overlay" id="modalEditUser">
  <div class="modal">
    <h3>✏️ 编辑用户</h3>
    <input type="hidden" id="editUserId">
    <div class="form-group">
      <label>帐号（手机/邮箱）</label>
      <input type="text" id="editUserAccount" placeholder="手机号或邮箱">
    </div>
    <div class="form-group">
      <label>新密码（不修改请留空）</label>
      <input type="password" id="editUserPassword" placeholder="不修改请留空">
    </div>
    <div class="form-group">
      <label>余额 (USDT)</label>
      <input type="number" id="editUserBalance" step="0.01" min="0" placeholder="0.00">
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalEditUser')">取消</button>
      <button class="btn-confirm" onclick="saveUserEdit()">保存</button>
    </div>
  </div>
</div>
`;

let userData = { list: [], page: 1, total: 0 };

async function loadUserList(page) {
  const keyword = document.getElementById('userKeyword').value;
  const status = document.getElementById('userStatusFilter').value;
  const loading = document.getElementById('userLoading');
  const empty = document.getElementById('userEmpty');
  const table = document.getElementById('userTable');
  const pagination = document.getElementById('userPagination');

  loading.classList.remove('hidden');
  empty.classList.add('hidden');
  table.classList.add('hidden');
  pagination.innerHTML = '';

  const res = await apiGet('/user/list', { page, keyword, status });
  loading.classList.add('hidden');

  if (res.code !== 0) return toast(res.msg, 'error');
  userData = res.data;

  if (!userData.list || userData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }

  table.classList.remove('hidden');
  document.getElementById('userTbody').innerHTML = userData.list.map(u => `
    <tr>
      <td>#${u.id}</td>
      <td><img src="${u.avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.nickname)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;"></td>
      <td>${escHtml(u.nickname)}</td>
      <td>${escHtml(u.account)}</td>
      <td>${u.wallet ? parseFloat(u.wallet.balance).toFixed(2) : '-'}</td>
      <td><span class="status-tag ${u.status === 1 ? 'active' : u.status === 2 ? 'pending' : 'rejected'}">${u.status === 1 ? '正常' : u.status === 2 ? '禁言' : '封禁'}</span></td>
      <td>${formatTime(u.created_at)}</td>
      <td style="white-space:nowrap;">
        <button class="btn-sm btn-view" onclick="editUser(${u.id})">编辑</button>
        ${u.status === 1 ? `
          <button class="btn-sm btn-view" onclick="updateUserStatus(${u.id}, 2)" style="margin-left:4px;background:#f59e0b;">禁言</button>
          <button class="btn-sm btn-view" onclick="updateUserStatus(${u.id}, 3)" style="margin-left:4px;background:#ef4444;">封禁</button>
        ` : `
          <button class="btn-sm btn-view" onclick="updateUserStatus(${u.id}, 1)" style="margin-left:4px;background:#10b981;">解封</button>
        `}
      </td>
    </tr>
  `).join('');
  renderPagination(pagination, userData.page, Math.ceil(userData.total / 20), loadUserList);
}

async function updateUserStatus(id, status) {
  const label = { 1: '解封', 2: '禁言', 3: '封禁' };
  if (!confirm(`确定要${label[status] || '操作'}该用户吗？`)) return;
  const res = await apiPost('/user/status', { id, status });
  if (res.code === 0) {
    toast('操作成功');
    loadUserList(userData.page);
  } else {
    toast(res.msg, 'error');
  }
}

function editUser(id) {
  const user = userData.list.find(u => u.id === id);
  if (!user) return;
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editUserAccount').value = user.account || '';
  document.getElementById('editUserPassword').value = '';
  document.getElementById('editUserBalance').value = user.wallet ? parseFloat(user.wallet.balance).toFixed(2) : '0.00';
  openModal('modalEditUser');
}

async function saveUserEdit() {
  const id = parseInt(document.getElementById('editUserId').value);
  const account = document.getElementById('editUserAccount').value.trim();
  const password = document.getElementById('editUserPassword').value;
  const balance = document.getElementById('editUserBalance').value;

  if (!account) return toast('帐号不能为空', 'error');

  const body = { id, account };
  if (password) body.password = password;
  if (balance !== '') body.balance = balance;

  const res = await apiPost('/user/update', body);
  if (res.code === 0) {
    toast('更新成功');
    closeModal('modalEditUser');
    loadUserList(userData.page);
  } else {
    toast(res.msg, 'error');
  }
}
