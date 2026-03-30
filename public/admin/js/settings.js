/**
 * Settings Page
 * System settings: language management, etc.
 */

async function loadLangList() {
  const loading = document.getElementById('langLoading');
  loading.classList.remove('hidden');
  const res = await apiGet('/settings/languages');
  loading.classList.add('hidden');

  if (res.code !== 0) return;
  document.getElementById('langTbody').innerHTML = res.data.map(l => `
    <tr>
      <td>${l.id}</td>
      <td><code style="background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:12px;">${l.code}</code></td>
      <td>${l.name}</td>
      <td>${l.is_default ? '<span style="color:#10b981;font-weight:600;">✅ 默认</span>' : '-'}</td>
      <td><span class="status-tag ${l.status === 1 ? 'active' : 'rejected'}">${l.status === 1 ? '启用' : '禁用'}</span></td>
      <td>${l.sort_order}</td>
      <td>
        <button class="btn-sm btn-view" onclick="updateLangStatus(${l.id}, ${l.status === 1 ? 0 : 1})">${l.status === 1 ? '禁用' : '启用'}</button>
      </td>
    </tr>
  `).join('');
}

async function updateLangStatus(id, status) {
  const res = await apiPost('/settings/language/update', { id, status });
  if (res.code === 0) {
    toast('操作成功');
    loadLangList();
  } else {
    toast(res.msg, 'error');
  }
}
