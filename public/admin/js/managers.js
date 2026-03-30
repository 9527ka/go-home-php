/**
 * Managers Page
 * CRUD for admin accounts (super admin only).
 */

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
