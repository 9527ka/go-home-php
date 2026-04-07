/**
 * Reports Page
 * Handle user-submitted reports (spam, abuse, etc.).
 */

PAGE_TEMPLATES['reports'] = `
<div id="pageReports" class="page">
  <div class="page-header">
    <h2>🚨 举报管理</h2>
    <p>处理用户提交的举报信息</p>
  </div>
  <div class="section-card">
    <div class="filter-bar">
      <select id="reportStatusFilter">
        <option value="">全部</option>
        <option value="0">待处理</option>
        <option value="1">有效</option>
        <option value="2">无效</option>
        <option value="3">已忽略</option>
      </select>
      <button class="btn-filter" onclick="loadReportList(1)">筛选</button>
    </div>
    <div id="reportLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="reportEmpty" class="empty-state hidden"><div class="icon">✅</div><p>暂无举报信息</p></div>
    <table id="reportTable" class="hidden">
      <thead><tr><th>ID</th><th>目标类型</th><th>目标ID</th><th>举报原因</th><th>举报人</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
      <tbody id="reportTbody"></tbody>
    </table>
    <div class="pagination" id="reportPagination"></div>
  </div>
</div>

<div class="modal-overlay" id="modalHandleReport">
  <div class="modal">
    <h3>🚨 处理举报</h3>
    <div id="reportDetailContent" style="margin-bottom:16px;"></div>
    <div class="form-group">
      <label>处理结果</label>
      <select id="handleReportStatus" style="width:100%;height:40px;border:1.5px solid #e0e0e0;border-radius:10px;padding:0 12px;font-size:14px;">
        <option value="1">有效 - 确认违规</option>
        <option value="2">无效 - 未违规</option>
        <option value="3">忽略</option>
      </select>
    </div>
    <div class="form-group">
      <label>处理备注</label>
      <textarea id="handleReportRemark" placeholder="可选：填写处理备注..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalHandleReport')">取消</button>
      <button class="btn-confirm" onclick="doHandleReport()">确认处理</button>
    </div>
  </div>
</div>
`;

let reportData = { list: [], page: 1, total: 0 };

// ======================== Report List ========================

async function loadReportList(page) {
  const loading = document.getElementById('reportLoading');
  const empty = document.getElementById('reportEmpty');
  const table = document.getElementById('reportTable');
  const pagination = document.getElementById('reportPagination');

  loading.classList.remove('hidden');
  empty.classList.add('hidden');
  table.classList.add('hidden');
  pagination.innerHTML = '';

  const status = document.getElementById('reportStatusFilter').value;
  const res = await apiGet('/report/list', { page, status: status || undefined });

  loading.classList.add('hidden');

  if (res.code !== 0) {
    toast(res.msg || '加载失败', 'error');
    return;
  }

  reportData = res.data;

  if (!reportData.list || reportData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }

  table.classList.remove('hidden');
  const tbody = document.getElementById('reportTbody');
  tbody.innerHTML = reportData.list.map(r => {
    const st = r.status ?? 0;
    return `
      <tr>
        <td>#${r.id}</td>
        <td>${REPORT_TARGET[r.target_type] || '未知'}</td>
        <td>#${r.target_id}</td>
        <td>${REPORT_REASON[r.reason] || escHtml(r.description || '其他')}</td>
        <td>${r.reporter ? escHtml(r.reporter.nickname || '匿名') : '-'}</td>
        <td>${formatTime(r.created_at)}</td>
        <td><span class="status-tag ${REPORT_STATUS_CLASS[st]}">${REPORT_STATUS[st]}</span></td>
        <td>
          ${st === 0 ? `<button class="btn-sm btn-handle" onclick="showHandleReport(${r.id})">处理</button>` : `<span style="color:#999;font-size:12px;">${r.handle_remark ? escHtml(r.handle_remark) : '已处理'}</span>`}
        </td>
      </tr>
    `;
  }).join('');

  renderPagination(pagination, reportData.page, Math.ceil(reportData.total / 20), loadReportList);
}

// ======================== Handle Report ========================

function showHandleReport(id) {
  currentReportId = id;
  const report = reportData.list.find(r => r.id === id);
  if (!report) return;

  document.getElementById('reportDetailContent').innerHTML = `
    <div style="background:#f9fafb;border-radius:8px;padding:12px;font-size:13px;line-height:1.8;">
      <div><b>举报目标：</b>${REPORT_TARGET[report.target_type] || '未知'} #${report.target_id}</div>
      <div><b>举报原因：</b>${REPORT_REASON[report.reason] || '其他'}</div>
      ${report.description ? `<div><b>详细说明：</b>${escHtml(report.description)}</div>` : ''}
      <div><b>举报人：</b>${report.reporter ? escHtml(report.reporter.nickname) : '匿名'}</div>
      <div><b>举报时间：</b>${formatTime(report.created_at)}</div>
    </div>
  `;

  document.getElementById('handleReportStatus').value = '1';
  document.getElementById('handleReportRemark').value = '';
  openModal('modalHandleReport');
}

async function doHandleReport() {
  const status = parseInt(document.getElementById('handleReportStatus').value);
  const remark = document.getElementById('handleReportRemark').value.trim();

  const res = await apiPost('/report/handle', {
    id: currentReportId,
    status,
    remark,
  });

  if (res.code === 0) {
    toast('处理成功', 'success');
    closeModal('modalHandleReport');
    loadReportList(reportData.page);
    loadDashboard();
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}
