/**
 * Managers Page
 * CRUD for admin accounts (super admin only).
 */

PAGE_TEMPLATES['managers'] = `
<div id="pageManagers" class="page">
  <div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h2>🛡️ 管理员管理</h2>
        <p>管理后台系统管理员账号</p>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn-filter" onclick="refreshCurrentPage()" style="background:#6b7280;" title="刷新数据">🔄 刷新</button>
        <button class="btn-filter" onclick="showCreateManager()">+ 新增管理员</button>
      </div>
    </div>
  </div>
  <div class="section-card">
    <div id="managerLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <table id="managerTable">
      <thead><tr><th>ID</th><th>用户名</th><th>真实姓名</th><th>角色</th><th>状态</th><th>最后登录</th><th>操作</th></tr></thead>
      <tbody id="managerTbody"></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalManager">
  <div class="modal">
    <h3 id="managerModalTitle">🛡️ 管理员</h3>
    <input type="hidden" id="managerId">
    <div class="form-group">
      <label>用户名</label>
      <input type="text" id="managerUsername" placeholder="登录用户名">
    </div>
    <div class="form-group">
      <label>密码</label>
      <input type="password" id="managerPassword" placeholder="不修改请留空">
    </div>
    <div class="form-group">
      <label>真实姓名</label>
      <input type="text" id="managerRealname">
    </div>
    <div class="form-group">
      <label>角色</label>
      <select id="managerRole" style="width:100%;height:44px;border:1.5px solid #e0e0e0;border-radius:10px;padding:0 12px;">
        <option value="1">审核员</option>
        <option value="2">超级管理员</option>
      </select>
    </div>
    <div class="form-group" id="managerStatusGroup">
      <label>状态</label>
      <select id="managerStatus" style="width:100%;height:44px;border:1.5px solid #e0e0e0;border-radius:10px;padding:0 12px;">
        <option value="1">正常</option>
        <option value="2">禁用</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalManager')">取消</button>
      <button class="btn-confirm" onclick="saveManager()">保存</button>
    </div>
  </div>
</div>
`;

let managerData = { list: [], page: 1, total: 0 };

// ======================== Manager List ========================

async function loadManagerList(page) {
  const loading = document.getElementById('managerLoading');
  loading.classList.remove('hidden');

  const res = await apiGet('/manager/list', { page });
  loading.classList.add('hidden');

  if (res.code !== 0) return toast(res.msg, 'error');
  managerData = res.data;

  document.getElementById('managerTbody').innerHTML = managerData.list.map(m => `
    <tr>
      <td>#${m.id}</td>
      <td>${escHtml(m.username)}</td>
      <td>${escHtml(m.realname || '-')}</td>
      <td><span class="category-tag ${m.role === 2 ? 'elder' : 'pet'}">${m.role === 2 ? '超级管理员' : '审核员'}</span></td>
      <td><span class="status-tag ${m.status === 1 ? 'active' : 'rejected'}">${m.status === 1 ? '正常' : '禁用'}</span></td>
      <td>${formatTime(m.last_login_at)}</td>
      <td>
        <button class="btn-sm btn-view" onclick="editManager(${m.id})">编辑</button>
        ${m.id !== 1 ? `<button class="btn-sm btn-takedown" onclick="deleteManager(${m.id})">删除</button>` : ''}
      </td>
    </tr>
  `).join('');
}

// ======================== Create / Edit ========================

function showCreateManager() {
  document.getElementById('managerModalTitle').textContent = '➕ 新增管理员';
  document.getElementById('managerId').value = '';
  document.getElementById('managerUsername').value = '';
  document.getElementById('managerUsername').disabled = false;
  document.getElementById('managerPassword').value = '';
  document.getElementById('managerPassword').placeholder = '请输入密码';
  document.getElementById('managerRealname').value = '';
  document.getElementById('managerRole').value = '1';
  document.getElementById('managerStatusGroup').classList.add('hidden');
  openModal('modalManager');
}

function editManager(id) {
  const m = managerData.list.find(item => item.id === id);
  if (!m) return;
  document.getElementById('managerModalTitle').textContent = '🛡️ 编辑管理员';
  document.getElementById('managerId').value = m.id;
  document.getElementById('managerUsername').value = m.username;
  document.getElementById('managerUsername').disabled = true;
  document.getElementById('managerPassword').value = '';
  document.getElementById('managerPassword').placeholder = '不修改请留空';
  document.getElementById('managerRealname').value = m.realname;
  document.getElementById('managerRole').value = m.role;
  document.getElementById('managerStatus').value = m.status;
  document.getElementById('managerStatusGroup').classList.toggle('hidden', m.id === 1);
  openModal('modalManager');
}

async function saveManager() {
  const id = document.getElementById('managerId').value;
  const username = document.getElementById('managerUsername').value.trim();
  const password = document.getElementById('managerPassword').value;
  const realname = document.getElementById('managerRealname').value.trim();
  const role = document.getElementById('managerRole').value;
  const status = document.getElementById('managerStatus').value;

  if (!username) return toast('请输入用户名', 'warning');
  if (!id && !password) return toast('请输入密码', 'warning');

  const path = id ? '/manager/update' : '/manager/create';
  const data = { id, username, password, realname, role, status };
  const res = await apiPost(path, data);
  if (res.code === 0) {
    toast('保存成功');
    closeModal('modalManager');
    loadManagerList(managerData.page);
  } else {
    toast(res.msg, 'error');
  }
}

// ======================== Delete ========================

async function deleteManager(id) {
  if (!confirm('确定删除该管理员吗？')) return;
  const res = await apiPost('/manager/delete', { id });
  if (res.code === 0) {
    toast('已删除');
    loadManagerList(managerData.page);
  } else {
    toast(res.msg, 'error');
  }
}
