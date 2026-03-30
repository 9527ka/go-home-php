/**
 * Reports Page
 * Handle user-submitted reports (spam, abuse, etc.).
 */

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
