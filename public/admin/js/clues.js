/**
 * Clues Page
 * Manage user-submitted clues linked to posts.
 */

let clueData = { list: [], page: 1, total: 0 };

async function loadClueList(page) {
  const post_id = document.getElementById('cluePostId').value;
  const status = document.getElementById('clueStatusFilter').value;
  const loading = document.getElementById('clueLoading');
  const empty = document.getElementById('clueEmpty');
  const table = document.getElementById('clueTable');
  const pagination = document.getElementById('cluePagination');

  loading.classList.remove('hidden');
  empty.classList.add('hidden');
  table.classList.add('hidden');
  pagination.innerHTML = '';

  const res = await apiGet('/clue/list', { page, post_id, status });
  loading.classList.add('hidden');

  if (res.code !== 0) return toast(res.msg, 'error');
  clueData = res.data;

  if (!clueData.list || clueData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }

  table.classList.remove('hidden');
  document.getElementById('clueTbody').innerHTML = clueData.list.map(c => `
    <tr>
      <td>#${c.id}</td>
      <td>#${c.post_id} ${escHtml(c.post ? c.post.name : '')}</td>
      <td>${escHtml(c.user ? c.user.nickname : '匿名')}</td>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(c.content)}">${escHtml(c.content)}</td>
      <td><span class="status-tag ${c.status === 1 ? 'active' : 'rejected'}">${c.status === 1 ? '正常' : '异常'}</span></td>
      <td>${formatTime(c.created_at)}</td>
      <td>
        <button class="btn-sm btn-takedown" onclick="deleteClue(${c.id})">删除</button>
      </td>
    </tr>
  `).join('');
  renderPagination(pagination, clueData.page, Math.ceil(clueData.total / 20), loadClueList);
}

async function deleteClue(id) {
  if (!confirm('确定删除该线索吗？')) return;
  const res = await apiPost('/clue/delete', { id });
  if (res.code === 0) {
    toast('已删除');
    loadClueList(clueData.page);
  } else {
    toast(res.msg, 'error');
  }
}
