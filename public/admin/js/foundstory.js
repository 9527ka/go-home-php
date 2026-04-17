/**
 * 找回故事审核
 */

PAGE_TEMPLATES['foundstory'] = `
<div id="pageFoundStory" class="page">
  <div class="page-header">
    <h2>🏠 找回故事审核</h2>
    <p>审核用户提交的找回故事，通过后自动发放奖励爱心币</p>
  </div>

  <div class="section-card">
    <div class="filter-bar">
      <select id="fsStatusFilter">
        <option value="0">待审核</option>
        <option value="1">已通过</option>
        <option value="2">已驳回</option>
        <option value="">全部</option>
      </select>
      <button class="btn-filter" onclick="loadFoundStoryList(1)">筛选</button>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th><th>启事</th><th>作者</th><th>找回经过（节选）</th>
          <th>图片</th><th>奖励</th><th>状态</th><th>时间</th><th>操作</th>
        </tr>
      </thead>
      <tbody id="fsTbody"><tr><td colspan="9" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
    </table>
    <div class="pagination" id="fsPagination"></div>
  </div>

  <!-- 审核弹窗 -->
  <div class="modal-overlay" id="fsAuditModal">
    <div class="modal">
      <div class="modal-header"><h3 id="fsAuditTitle">审核</h3></div>
      <div class="modal-body">
        <input type="hidden" id="fsAuditId">
        <input type="hidden" id="fsAuditAction">
        <div id="fsAuditFullContent" style="margin-bottom:12px;color:#333;font-size:13px;line-height:1.5;white-space:pre-wrap;"></div>
        <div id="fsAuditImages" style="margin-bottom:12px;"></div>
        <div class="form-group">
          <label>备注</label>
          <textarea id="fsAuditRemark" style="width:100%;min-height:60px;"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" onclick="document.getElementById('fsAuditModal').classList.remove('show')">取消</button>
        <button class="btn btn-primary" onclick="submitFoundStoryAudit()">确定</button>
      </div>
    </div>
  </div>
</div>
`;

let fsPage = 1;

async function loadFoundStoryList(page) {
  fsPage = page;
  const status = document.getElementById('fsStatusFilter')?.value || '';
  const params = { page };
  if (status !== '') params.status = status;
  const res = await apiGet('/found-story/list', params);
  if (res.code !== 0) return;
  const { data: list, current_page, last_page } = res.data;
  const tbody = document.getElementById('fsTbody');
  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999;">暂无数据</td></tr>';
    document.getElementById('fsPagination').innerHTML = '';
    return;
  }
  const statusLabel = { 0: '待审', 1: '已通过', 2: '已驳回' };
  tbody.innerHTML = list.map(s => {
    const excerpt = (s.content || '').substring(0, 60) + ((s.content || '').length > 60 ? '...' : '');
    const imgCount = s.images ? s.images.split(',').filter(x => x).length : 0;
    return `
      <tr>
        <td>${s.id}</td>
        <td>${s.post?.name || '-'} (P${s.post_id})</td>
        <td>${s.user?.nickname || '-'}</td>
        <td style="max-width:240px;">${excerpt}</td>
        <td>${imgCount}张</td>
        <td>${s.reward_amount}${s.is_rewarded ? ' ✓' : ''}</td>
        <td>${statusLabel[s.status] || '-'}</td>
        <td>${s.created_at}</td>
        <td>
          ${s.status === 0 ? `
            <button class="btn btn-sm btn-primary" onclick='openFsAudit(${JSON.stringify(s)}, "approve")'>通过</button>
            <button class="btn btn-sm btn-danger"  onclick='openFsAudit(${JSON.stringify(s)}, "reject")'>驳回</button>
          ` : '-'}
        </td>
      </tr>
    `;
  }).join('');
  document.getElementById('fsPagination').innerHTML = buildPagination(current_page, last_page, 'loadFoundStoryList');
}

function openFsAudit(story, action) {
  document.getElementById('fsAuditTitle').textContent = action === 'approve' ? '通过找回故事' : '驳回找回故事';
  document.getElementById('fsAuditId').value = story.id;
  document.getElementById('fsAuditAction').value = action;
  document.getElementById('fsAuditFullContent').textContent = story.content || '';
  document.getElementById('fsAuditRemark').value = '';

  // 图片预览
  const imgBox = document.getElementById('fsAuditImages');
  imgBox.innerHTML = '';
  if (story.images) {
    const urls = story.images.split(',').filter(x => x);
    imgBox.innerHTML = urls.map(u => `<img src="${u}" style="max-width:120px;height:80px;object-fit:cover;margin:4px;border-radius:6px;">`).join('');
  }

  document.getElementById('fsAuditModal').classList.add('show');
}

async function submitFoundStoryAudit() {
  const id = parseInt(document.getElementById('fsAuditId').value);
  const action = document.getElementById('fsAuditAction').value;
  const remark = document.getElementById('fsAuditRemark').value;
  const url = action === 'approve' ? '/found-story/approve' : '/found-story/reject';
  const res = await apiPost(url, { id, remark });
  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) {
    document.getElementById('fsAuditModal').classList.remove('show');
    loadFoundStoryList(fsPage);
  }
}
