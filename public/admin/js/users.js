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
      <td><span class="status-tag ${u.status === 1 ? 'active' : 'rejected'}">${u.status === 1 ? '正常' : u.status === 2 ? '禁言' : '封禁'}</span></td>
      <td>${formatTime(u.created_at)}</td>
      <td>
        <button class="btn-sm btn-view" onclick="updateUserStatus(${u.id}, ${u.status === 1 ? 3 : 1})">${u.status === 1 ? '封禁' : '解封'}</button>
      </td>
    </tr>
  `).join('');
  renderPagination(pagination, userData.page, Math.ceil(userData.total / 20), loadUserList);
}

async function updateUserStatus(id, status) {
  if (!confirm(`确定要${status === 1 ? '解封' : '封禁'}该用户吗？`)) return;
  const res = await apiPost('/user/status', { id, status });
  if (res.code === 0) {
    toast('操作成功');
    loadUserList(userData.page);
  } else {
    toast(res.msg, 'error');
  }
}
