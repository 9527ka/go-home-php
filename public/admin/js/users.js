/**
 * Users Page
 * Manage registered users and their account status.
 */

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
