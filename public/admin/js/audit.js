/**
 * Audit Page
 * Content review for user-submitted posts (missing persons/pets).
 */

PAGE_TEMPLATES['audit'] = `
<div id="pageAudit" class="page">
  <div class="page-header">
    <h2>📋 内容审核</h2>
    <p>审核用户发布的寻人/寻宠启事</p>
  </div>
  <div class="section-card">
    <div class="filter-bar">
      <select id="auditStatusFilter">
        <option value="">全部状态</option>
        <option value="0">待审核</option>
        <option value="1">已发布</option>
        <option value="2">已找到</option>
        <option value="3">已关闭</option>
        <option value="4">已驳回</option>
      </select>
      <button class="btn-filter" onclick="loadAuditList(1)">筛选</button>
    </div>
    <div id="auditLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="auditEmpty" class="empty-state hidden"><div class="icon">✅</div><p>暂无待审核内容</p></div>
    <table id="auditTable" class="hidden">
      <thead><tr><th>ID</th><th>分类</th><th>名字</th><th>发布者</th><th>发布时间</th><th>状态</th><th>操作</th></tr></thead>
      <tbody id="auditTbody"></tbody>
    </table>
    <div class="pagination" id="auditPagination"></div>
  </div>
</div>

<div class="modal-overlay" id="modalViewPost">
  <div class="modal" style="width:580px;">
    <h3>📄 启事详情</h3>
    <div id="postDetailContent"></div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalViewPost')">关闭</button>
      <button class="btn-sm btn-approve" id="modalBtnApprove" onclick="doApprove()">✓ 通过</button>
      <button class="btn-sm btn-reject" id="modalBtnReject" onclick="showRejectModal()">✗ 驳回</button>
      <button class="btn-sm btn-takedown" id="modalBtnTakedown" onclick="showTakedownModal()">⚠ 下架</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalReject">
  <div class="modal">
    <h3>✗ 驳回启事</h3>
    <div class="form-group">
      <label>驳回原因（必填）</label>
      <textarea id="rejectRemark" placeholder="请输入驳回原因，将通知给发布者..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalReject')">取消</button>
      <button class="btn-confirm warning" onclick="doReject()">确认驳回</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalTakedown">
  <div class="modal">
    <h3>⚠ 下架启事</h3>
    <div class="form-group">
      <label>下架原因</label>
      <textarea id="takedownRemark" placeholder="请输入下架原因..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalTakedown')">取消</button>
      <button class="btn-confirm danger" onclick="doTakedown()">确认下架</button>
    </div>
  </div>
</div>
`;

let auditData = { list: [], page: 1, total: 0 };

// ======================== Audit List ========================

async function loadAuditList(page) {
  const loading = document.getElementById('auditLoading');
  const empty = document.getElementById('auditEmpty');
  const table = document.getElementById('auditTable');
  const pagination = document.getElementById('auditPagination');

  loading.classList.remove('hidden');
  empty.classList.add('hidden');
  table.classList.add('hidden');
  pagination.innerHTML = '';

  const status = document.getElementById('auditStatusFilter').value;
  const params = { page };
  if (status !== '') params.status = status;
  const res = await apiGet('/audit/list', params);

  loading.classList.add('hidden');

  if (res.code !== 0) {
    toast(res.msg || '加载失败', 'error');
    return;
  }

  auditData = res.data;

  if (!auditData.list || auditData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }

  table.classList.remove('hidden');
  const tbody = document.getElementById('auditTbody');
  tbody.innerHTML = auditData.list.map(post => {
    const cat = post.category || 0;
    const st = post.status ?? 0;
    return `
      <tr>
        <td>#${post.id}</td>
        <td><span class="category-tag ${CATEGORY_CLASS[cat] || ''}">${CATEGORY_ICON[cat] || '❓'} ${CATEGORY[cat] || '未知'}</span></td>
        <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(post.name || '')}">${escHtml(post.name || '无名')}</td>
        <td>${post.user ? escHtml(post.user.nickname || '匿名') : '-'}</td>
        <td>${formatTime(post.created_at)}</td>
        <td><span class="status-tag ${POST_STATUS_CLASS[st]}">${POST_STATUS[st]}</span></td>
        <td>
          <div class="btn-group">
            <button class="btn-sm btn-view" onclick="viewPost(${post.id})">查看</button>
            ${st === 0 ? `<button class="btn-sm btn-approve" onclick="currentPostId=${post.id};doApprove()">通过</button>
            <button class="btn-sm btn-reject" onclick="currentPostId=${post.id};showRejectModal()">驳回</button>` : ''}
            ${st === 1 ? `<button class="btn-sm btn-takedown" onclick="currentPostId=${post.id};showTakedownModal()">下架</button>` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');

  renderPagination(pagination, auditData.page, Math.ceil(auditData.total / 20), loadAuditList);
}

// ======================== View Post Detail ========================

async function viewPost(id) {
  currentPostId = id;
  const post = auditData.list.find(p => p.id === id);
  if (!post) return;

  const cat = post.category || 0;
  const st = post.status ?? 0;

  let imagesHtml = '';
  if (post.images && post.images.length > 0) {
    imagesHtml = `<div class="post-images">${post.images.map(img =>
      `<img src="${img.image_url || img.url || ''}" alt="图片" onerror="this.style.display='none'">`
    ).join('')}</div>`;
  }

  document.getElementById('postDetailContent').innerHTML = `
    <div class="post-detail-grid">
      <div class="field"><div class="label">分类</div><div class="value"><span class="category-tag ${CATEGORY_CLASS[cat]}">${CATEGORY_ICON[cat]} ${CATEGORY[cat] || '未知'}</span></div></div>
      <div class="field"><div class="label">状态</div><div class="value"><span class="status-tag ${POST_STATUS_CLASS[st]}">${POST_STATUS[st]}</span></div></div>
      <div class="field"><div class="label">名字</div><div class="value">${escHtml(post.name || '-')}</div></div>
      <div class="field"><div class="label">性别</div><div class="value">${post.gender === 1 ? '男' : post.gender === 2 ? '女' : '未知'}</div></div>
      <div class="field"><div class="label">年龄</div><div class="value">${post.age || '-'}</div></div>
      <div class="field"><div class="label">联系电话</div><div class="value">${escHtml(post.contact_phone || '-')}</div></div>
      <div class="field"><div class="label">城市</div><div class="value">${escHtml(post.city || '-')}</div></div>
      <div class="field"><div class="label">地址</div><div class="value">${escHtml(post.address || '-')}</div></div>
      <div class="field"><div class="label">发布者</div><div class="value">${post.user ? escHtml(post.user.nickname) : '-'}</div></div>
      <div class="field"><div class="label">发布时间</div><div class="value">${formatTime(post.created_at)}</div></div>
    </div>
    ${imagesHtml}
    <div class="post-desc">${escHtml(post.description || '无详细描述')}</div>
    ${post.audit_remark ? `<div style="margin-top:12px;padding:8px 12px;background:#fef3c7;border-radius:8px;font-size:13px;"><b>审核备注：</b>${escHtml(post.audit_remark)}</div>` : ''}
  `;

  // Show/hide action buttons based on post status
  document.getElementById('modalBtnApprove').classList.toggle('hidden', st !== 0);
  document.getElementById('modalBtnReject').classList.toggle('hidden', st !== 0);
  document.getElementById('modalBtnTakedown').classList.toggle('hidden', st !== 1);

  openModal('modalViewPost');
}

// ======================== Audit Actions ========================

async function doApprove() {
  if (!currentPostId) return;
  if (!confirm('确认通过该启事？')) return;

  const res = await apiPost('/audit/approve', { id: currentPostId });
  if (res.code === 0) {
    toast('审核通过', 'success');
    closeModal('modalViewPost');
    loadAuditList(auditData.page);
    loadDashboard();
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}

function showRejectModal() {
  document.getElementById('rejectRemark').value = '';
  closeModal('modalViewPost');
  openModal('modalReject');
}

async function doReject() {
  const remark = document.getElementById('rejectRemark').value.trim();
  if (!remark) {
    toast('请填写驳回原因', 'warning');
    return;
  }

  const res = await apiPost('/audit/reject', { id: currentPostId, remark });
  if (res.code === 0) {
    toast('已驳回', 'success');
    closeModal('modalReject');
    loadAuditList(auditData.page);
    loadDashboard();
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}

function showTakedownModal() {
  document.getElementById('takedownRemark').value = '';
  closeModal('modalViewPost');
  openModal('modalTakedown');
}

async function doTakedown() {
  const remark = document.getElementById('takedownRemark').value.trim();
  const res = await apiPost('/audit/takedown', { id: currentPostId, remark });
  if (res.code === 0) {
    toast('已下架', 'success');
    closeModal('modalTakedown');
    loadAuditList(auditData.page);
    loadDashboard();
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}
