/**
 * Dashboard Page
 * Loads and displays platform statistics and quick actions.
 */

PAGE_TEMPLATES['dashboard'] = `
<div id="pageDashboard" class="page">
  <div class="page-header">
    <h2>📊 数据概览</h2>
    <p>平台运营数据实时统计</p>
  </div>
  <div class="stats-grid" id="statsGrid"></div>
  <div class="section-card">
    <div class="section-title">快捷操作</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <button class="btn-filter" onclick="switchPage('audit')" style="background:#f59e0b;">📋 去审核待发布内容</button>
      <button class="btn-filter" onclick="switchPage('reports')" style="background:#ef4444;">🚨 处理举报信息</button>
    </div>
  </div>
</div>
`;

async function loadDashboard() {
  const res = await apiGet('/dashboard');
  if (res.code !== 0) return;
  const d = res.data;

  // Update sidebar badges
  if (d.pending_posts > 0) {
    document.getElementById('auditBadge').textContent = d.pending_posts;
    document.getElementById('auditBadge').classList.remove('hidden');
  } else {
    document.getElementById('auditBadge').classList.add('hidden');
  }
  if (d.pending_reports > 0) {
    document.getElementById('reportBadge').textContent = d.pending_reports;
    document.getElementById('reportBadge').classList.remove('hidden');
  } else {
    document.getElementById('reportBadge').classList.add('hidden');
  }

  // Render stat cards
  const cards = [
    { icon: '⏳', label: '待审核启事', value: d.pending_posts, color: 'orange' },
    { icon: '✅', label: '已发布启事', value: d.active_posts, color: 'green' },
    { icon: '📝', label: '启事总数', value: d.total_posts, color: 'blue' },
    { icon: '🚨', label: '待处理举报', value: d.pending_reports, color: 'red' },
    { icon: '👥', label: '注册用户', value: d.total_users, color: 'purple' },
    { icon: '📮', label: '今日发布', value: d.today_posts, color: 'cyan' },
    { icon: '💡', label: '今日线索', value: d.today_clues, color: 'pink' },
  ];

  document.getElementById('statsGrid').innerHTML = cards.map(c => `
    <div class="stat-card ${c.color}">
      <div class="stat-icon">${c.icon}</div>
      <div class="stat-value">${c.value}</div>
      <div class="stat-label">${c.label}</div>
    </div>
  `).join('');
}
