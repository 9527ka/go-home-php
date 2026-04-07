/**
 * 签到/任务管理模块
 */

PAGE_TEMPLATES['signin'] = `
<div id="pageSignin" class="page">
  <div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h2>📅 签到管理</h2>
        <p>管理签到系统、每日任务及奖励配置</p>
      </div>
      <button class="btn-filter" id="btnSignToggle" onclick="toggleSign(true)">关闭签到</button>
    </div>
  </div>

  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-value" id="statTodaySign">0</div>
      <div class="stat-label">今日签到人数</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" id="statTodaySignReward">0.00</div>
      <div class="stat-label">今日签到奖励(爱心币)</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" id="statTodayTask">0</div>
      <div class="stat-label">今日任务完成</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" id="statTodayTaskReward">0.00</div>
      <div class="stat-label">今日任务奖励(爱心币)</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" id="statTotalSign">0</div>
      <div class="stat-label">累计签到人次</div>
    </div>
  </div>

  <div class="section-card" style="margin-bottom:20px;">
    <div class="section-title">签到日志</div>
    <div class="filter-bar">
      <input type="date" id="signLogDate">
      <button class="btn-filter" onclick="loadSignLogs(1)">筛选</button>
    </div>
    <table>
      <thead><tr><th>ID</th><th>用户</th><th>日期</th><th>周期天数</th><th>基础奖励</th><th>暴击</th><th>最终奖励</th><th>IP</th></tr></thead>
      <tbody id="signLogTableBody">
        <tr><td colspan="8" style="text-align:center;color:#999;">加载中...</td></tr>
      </tbody>
    </table>
    <div class="pagination" id="signLogPagination"></div>
  </div>

  <div class="section-card" style="margin-bottom:20px;">
    <div class="section-title">任务配置</div>
    <table>
      <thead><tr><th>ID</th><th>标识</th><th>名称</th><th>奖励(爱心币)</th><th>目标次数</th><th>状态</th><th>操作</th></tr></thead>
      <tbody id="taskDefTableBody">
        <tr><td colspan="7" style="text-align:center;color:#999;">加载中...</td></tr>
      </tbody>
    </table>
  </div>

  <div class="section-card">
    <div class="section-title">任务完成日志</div>
    <div class="filter-bar">
      <input type="date" id="taskLogDate">
      <button class="btn-filter" onclick="loadTaskLogs(1)">筛选</button>
    </div>
    <table>
      <thead><tr><th>ID</th><th>用户</th><th>任务</th><th>奖励</th><th>日期</th><th>完成时间</th></tr></thead>
      <tbody id="taskLogTableBody">
        <tr><td colspan="6" style="text-align:center;color:#999;">加载中...</td></tr>
      </tbody>
    </table>
    <div class="pagination" id="taskLogPagination"></div>
  </div>
</div>
`;

let signinData = { page: 1 };
let taskLogData = { page: 1 };

// ======================== 统计 ========================

async function loadSignStats() {
  const res = await apiGet('/sign/stats');
  if (res.code !== 0) return;
  const d = res.data;

  document.getElementById('statTodaySign').textContent = d.today_sign_count;
  document.getElementById('statTodaySignReward').textContent = d.today_reward_total.toFixed(2);
  document.getElementById('statTodayTask').textContent = d.today_task_count;
  document.getElementById('statTodayTaskReward').textContent = d.today_task_reward.toFixed(2);
  document.getElementById('statTotalSign').textContent = d.total_sign_count;

  // 开关状态
  const toggleBtn = document.getElementById('btnSignToggle');
  toggleBtn.textContent = d.sign_enabled ? '关闭签到' : '开启签到';
  toggleBtn.className = 'btn ' + (d.sign_enabled ? 'btn-danger' : 'btn-success');
  toggleBtn.onclick = () => toggleSign(!d.sign_enabled);
}

async function toggleSign(enabled) {
  const action = enabled ? '开启' : '关闭';
  if (!confirm(`确定${action}签到功能？`)) return;

  const res = await apiPost('/sign/toggle', { enabled: enabled ? 1 : 0 });
  toast(res.msg || action + '成功', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) loadSignStats();
}

// ======================== 签到日志 ========================

async function loadSignLogs(page) {
  signinData.page = page;
  const dateFilter = document.getElementById('signLogDate')?.value || '';

  const res = await apiGet('/sign/logs', { page, date: dateFilter });
  if (res.code !== 0) return;

  const { data: list, current_page, last_page, total } = res.data;

  const tbody = document.getElementById('signLogTableBody');
  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;">暂无数据</td></tr>';
    document.getElementById('signLogPagination').innerHTML = '';
    return;
  }

  tbody.innerHTML = list.map(row => `
    <tr>
      <td>${row.id}</td>
      <td>${row.user?.nickname || '-'} (ID:${row.user_id})</td>
      <td>${row.sign_date}</td>
      <td>第${row.day_in_cycle}天</td>
      <td>${row.base_reward}</td>
      <td>${row.bonus_rate > 1 ? `<span class="badge badge-warning">${row.bonus_rate}x</span>` : '1x'}</td>
      <td><strong>${row.final_reward}</strong></td>
      <td>${row.ip || '-'}</td>
    </tr>
  `).join('');

  document.getElementById('signLogPagination').innerHTML = buildPagination(current_page, last_page, 'loadSignLogs');
}

// ======================== 任务定义 ========================

async function loadTaskDefinitions() {
  const res = await apiGet('/sign/tasks');
  if (res.code !== 0) return;

  const list = res.data;
  const tbody = document.getElementById('taskDefTableBody');

  tbody.innerHTML = list.map(t => `
    <tr>
      <td>${t.id}</td>
      <td>${t.task_key}</td>
      <td>
        <input type="text" value="${t.name}" id="taskName_${t.id}" style="width:80px;padding:2px 6px;">
      </td>
      <td>
        <input type="number" value="${t.reward}" step="0.01" min="0"
               id="taskReward_${t.id}" style="width:70px;padding:2px 6px;">
      </td>
      <td>${t.target_count}</td>
      <td>
        <select id="taskEnabled_${t.id}" style="padding:2px 6px;">
          <option value="1" ${t.is_enabled ? 'selected' : ''}>启用</option>
          <option value="0" ${!t.is_enabled ? 'selected' : ''}>禁用</option>
        </select>
      </td>
      <td>
        <button class="btn btn-sm btn-primary" onclick="saveTaskDef(${t.id})">保存</button>
      </td>
    </tr>
  `).join('');
}

async function saveTaskDef(id) {
  const name = document.getElementById(`taskName_${id}`).value;
  const reward = document.getElementById(`taskReward_${id}`).value;
  const isEnabled = document.getElementById(`taskEnabled_${id}`).value;

  const res = await apiPost('/sign/task/update', {
    id,
    name,
    reward: parseFloat(reward),
    is_enabled: parseInt(isEnabled),
  });

  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) loadTaskDefinitions();
}

// ======================== 任务完成日志 ========================

async function loadTaskLogs(page) {
  taskLogData.page = page;
  const dateFilter = document.getElementById('taskLogDate')?.value || '';

  const res = await apiGet('/sign/task-logs', { page, date: dateFilter });
  if (res.code !== 0) return;

  const { data: list, current_page, last_page } = res.data;

  const tbody = document.getElementById('taskLogTableBody');
  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">暂无数据</td></tr>';
    document.getElementById('taskLogPagination').innerHTML = '';
    return;
  }

  tbody.innerHTML = list.map(row => `
    <tr>
      <td>${row.id}</td>
      <td>${row.user?.nickname || '-'} (ID:${row.user_id})</td>
      <td>${row.task_key}</td>
      <td>${row.reward_amount}</td>
      <td>${row.log_date}</td>
      <td>${row.completed_at || '-'}</td>
    </tr>
  `).join('');

  document.getElementById('taskLogPagination').innerHTML = buildPagination(current_page, last_page, 'loadTaskLogs');
}
