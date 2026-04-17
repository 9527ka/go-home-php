/**
 * 抽奖管理模块
 */

PAGE_TEMPLATES['lottery'] = `
<div id="pageLottery" class="page">
  <div class="page-header">
    <h2>🎰 抽奖管理</h2>
    <p>配置奖池、奖品权重、查看流水与统计</p>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <button class="btn-filter lot-tab active" data-ltab="pools" onclick="switchLotTab('pools')" style="background:#667eea;">⚙️ 奖池配置</button>
    <button class="btn-filter lot-tab" data-ltab="prizes" onclick="switchLotTab('prizes')" style="background:#6b7280;">🎁 奖品</button>
    <button class="btn-filter lot-tab" data-ltab="logs" onclick="switchLotTab('logs')" style="background:#6b7280;">📄 抽奖流水</button>
    <button class="btn-filter lot-tab" data-ltab="stats" onclick="switchLotTab('stats')" style="background:#6b7280;">📊 统计</button>
  </div>

  <div id="lotTabPools" class="section-card lot-tab-content">
    <div class="section-title">奖池（期望值 = Σ(奖金×权重)/Σ(权重)/单抽价）</div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr><th>ID</th><th>key</th><th>名称</th><th>单抽价</th><th>日上限</th><th>频率(秒)</th><th>大奖阈值</th><th>非充用户系数</th><th>期望回报</th><th>启用</th><th>操作</th></tr>
        </thead>
        <tbody id="lotPoolTbody"><tr><td colspan="11" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
      </table>
    </div>
  </div>

  <div id="lotTabPrizes" class="section-card lot-tab-content hidden">
    <div class="section-title">奖品 <span id="lotExpectedReturn" style="font-size:13px;color:#888;margin-left:10px;"></span></div>
    <div class="filter-bar">
      <label>奖池ID <input type="number" id="lotPrizeFilterPool" value="1" style="width:80px;"></label>
      <button class="btn-filter" onclick="loadLotPrizes()">加载</button>
      <button class="btn-filter" onclick="openLotPrizeCreate()" style="background:#10b981;">➕ 新增</button>
    </div>
    <table>
      <thead><tr><th>ID</th><th>名称</th><th>奖金</th><th>权重</th><th>稀有度</th><th>排序</th><th>启用</th><th>操作</th></tr></thead>
      <tbody id="lotPrizeTbody"><tr><td colspan="8" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
    </table>
  </div>

  <div id="lotTabLogs" class="section-card lot-tab-content hidden">
    <div class="section-title">抽奖流水</div>
    <div class="filter-bar">
      <input type="number" id="lotLogUserId" placeholder="用户ID" style="width:120px;">
      <select id="lotLogIsBig">
        <option value="">全部</option>
        <option value="1">仅大奖</option>
        <option value="0">非大奖</option>
      </select>
      <button class="btn-filter" onclick="loadLotLogs(1)">筛选</button>
    </div>
    <table>
      <thead><tr><th>ID</th><th>用户</th><th>奖品</th><th>消耗</th><th>奖金</th><th>大奖</th><th>已充值</th><th>时间</th></tr></thead>
      <tbody id="lotLogTbody"><tr><td colspan="8" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
    </table>
    <div class="pagination" id="lotLogPagination"></div>
  </div>

  <div id="lotTabStats" class="section-card lot-tab-content hidden">
    <div class="section-title">抽奖统计</div>
    <div class="stats-grid" id="lotStatsGrid" style="margin-top:20px;"></div>
  </div>

  <!-- 新增/编辑奖品弹窗 -->
  <div class="modal-overlay" id="lotPrizeModal">
    <div class="modal">
      <div class="modal-header"><h3 id="lotPrizeModalTitle">新增奖品</h3></div>
      <div class="modal-body">
        <input type="hidden" id="lotPrizeId">
        <div class="form-group"><label>奖池ID</label><input type="number" id="lotPrizePoolId"></div>
        <div class="form-group"><label>名称</label><input type="text" id="lotPrizeName"></div>
        <div class="form-group"><label>奖金(爱心币, 0=谢谢参与)</label><input type="number" id="lotPrizeReward" step="0.01"></div>
        <div class="form-group"><label>权重</label><input type="number" id="lotPrizeWeight"></div>
        <div class="form-group"><label>稀有度</label>
          <select id="lotPrizeRarity">
            <option value="0">普通</option><option value="1">稀有</option><option value="2">史诗</option><option value="3">传说</option>
          </select>
        </div>
        <div class="form-group"><label>图标URL</label><input type="text" id="lotPrizeIcon"></div>
        <div class="form-group"><label>排序</label><input type="number" id="lotPrizeSort"></div>
        <div class="form-group"><label>启用</label>
          <select id="lotPrizeEnabled"><option value="1">启用</option><option value="0">禁用</option></select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" onclick="document.getElementById('lotPrizeModal').classList.remove('show')">取消</button>
        <button class="btn btn-primary" onclick="submitLotPrize()">保存</button>
      </div>
    </div>
  </div>
</div>
`;

let currentLotTab = 'pools';
let lotLogPage = 1;

function switchLotTab(tab) {
  currentLotTab = tab;
  document.querySelectorAll('.lot-tab').forEach(el => {
    const active = el.dataset.ltab === tab;
    el.classList.toggle('active', active);
    el.style.background = active ? '#667eea' : '#6b7280';
  });
  document.querySelectorAll('.lot-tab-content').forEach(el => el.classList.add('hidden'));
  const map = { pools: 'lotTabPools', prizes: 'lotTabPrizes', logs: 'lotTabLogs', stats: 'lotTabStats' };
  document.getElementById(map[tab]).classList.remove('hidden');
  if (tab === 'pools')  loadLotPools();
  if (tab === 'prizes') loadLotPrizes();
  if (tab === 'logs')   loadLotLogs(1);
  if (tab === 'stats')  loadLotStats();
}

async function loadLotPools() {
  const res = await apiGet('/lottery/pools');
  if (res.code !== 0) return;
  const tbody = document.getElementById('lotPoolTbody');
  tbody.innerHTML = res.data.map(p => `
    <tr>
      <td>${p.id}</td>
      <td>${p.pool_key}</td>
      <td><input type="text" id="pool_name_${p.id}" value="${p.name}" style="width:80px;"></td>
      <td><input type="number" id="pool_cost_${p.id}" value="${p.cost_per_draw}" step="0.01" style="width:80px;"></td>
      <td><input type="number" id="pool_daily_${p.id}" value="${p.daily_draw_limit}" style="width:60px;"></td>
      <td><input type="number" id="pool_rate_${p.id}" value="${p.rate_limit_seconds}" style="width:50px;"></td>
      <td><input type="number" id="pool_thresh_${p.id}" value="${p.big_prize_threshold}" step="0.01" style="width:80px;"></td>
      <td><input type="number" id="pool_nrw_${p.id}" value="${p.non_recharged_big_prize_weight}" step="0.0001" style="width:70px;"></td>
      <td><span style="color:${p.expected_return < 0.8 ? '#10b981' : '#ef4444'};font-weight:700;">${(p.expected_return*100).toFixed(2)}%</span></td>
      <td><select id="pool_en_${p.id}" style="padding:2px 4px;">
        <option value="1" ${p.is_enabled?'selected':''}>启用</option>
        <option value="0" ${!p.is_enabled?'selected':''}>禁用</option>
      </select></td>
      <td><button class="btn btn-sm btn-primary" onclick="saveLotPool(${p.id})">保存</button></td>
    </tr>
  `).join('');
}

async function saveLotPool(id) {
  const payload = {
    id,
    name:                           document.getElementById(`pool_name_${id}`).value,
    cost_per_draw:                  parseFloat(document.getElementById(`pool_cost_${id}`).value),
    daily_draw_limit:               parseInt(document.getElementById(`pool_daily_${id}`).value),
    rate_limit_seconds:             parseInt(document.getElementById(`pool_rate_${id}`).value),
    big_prize_threshold:            parseFloat(document.getElementById(`pool_thresh_${id}`).value),
    non_recharged_big_prize_weight: parseFloat(document.getElementById(`pool_nrw_${id}`).value),
    is_enabled:                     parseInt(document.getElementById(`pool_en_${id}`).value),
  };
  const res = await apiPost('/lottery/pool/update', payload);
  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) loadLotPools();
}

async function loadLotPrizes() {
  const poolId = parseInt(document.getElementById('lotPrizeFilterPool')?.value || 1);
  const res = await apiGet('/lottery/prizes', { pool_id: poolId });
  if (res.code !== 0) return;
  const { list, expected_return } = res.data;
  document.getElementById('lotExpectedReturn').textContent =
    `期望回报: ${(expected_return * 100).toFixed(2)}%  (建议 70-80%)`;
  const tbody = document.getElementById('lotPrizeTbody');
  tbody.innerHTML = list.map(p => `
    <tr>
      <td>${p.id}</td>
      <td>${p.name}</td>
      <td>${p.reward_amount}</td>
      <td>${p.weight}</td>
      <td>${['普通','稀有','史诗','传说'][p.rarity] || '-'}</td>
      <td>${p.sort_order}</td>
      <td>${p.is_enabled ? '✓' : '✗'}</td>
      <td>
        <button class="btn btn-sm btn-primary" onclick="openLotPrizeEdit(${p.id})">编辑</button>
        <button class="btn btn-sm btn-danger" onclick="deleteLotPrize(${p.id})">删除</button>
      </td>
    </tr>
  `).join('');
}

function openLotPrizeCreate() {
  document.getElementById('lotPrizeModalTitle').textContent = '新增奖品';
  document.getElementById('lotPrizeId').value = '';
  document.getElementById('lotPrizePoolId').value = document.getElementById('lotPrizeFilterPool')?.value || 1;
  document.getElementById('lotPrizeName').value = '';
  document.getElementById('lotPrizeReward').value = '0';
  document.getElementById('lotPrizeWeight').value = '100';
  document.getElementById('lotPrizeRarity').value = '0';
  document.getElementById('lotPrizeIcon').value = '';
  document.getElementById('lotPrizeSort').value = '0';
  document.getElementById('lotPrizeEnabled').value = '1';
  document.getElementById('lotPrizeModal').classList.add('show');
}

async function openLotPrizeEdit(id) {
  const poolId = parseInt(document.getElementById('lotPrizeFilterPool')?.value || 1);
  const res = await apiGet('/lottery/prizes', { pool_id: poolId });
  if (res.code !== 0) return;
  const p = (res.data.list || []).find(x => x.id === id);
  if (!p) { toast('奖品不存在', 'error'); return; }
  document.getElementById('lotPrizeModalTitle').textContent = `编辑奖品 #${id}`;
  document.getElementById('lotPrizeId').value = id;
  document.getElementById('lotPrizePoolId').value = p.pool_id;
  document.getElementById('lotPrizeName').value = p.name;
  document.getElementById('lotPrizeReward').value = p.reward_amount;
  document.getElementById('lotPrizeWeight').value = p.weight;
  document.getElementById('lotPrizeRarity').value = p.rarity;
  document.getElementById('lotPrizeIcon').value = p.icon_url;
  document.getElementById('lotPrizeSort').value = p.sort_order;
  document.getElementById('lotPrizeEnabled').value = p.is_enabled;
  document.getElementById('lotPrizeModal').classList.add('show');
}

async function submitLotPrize() {
  const id = document.getElementById('lotPrizeId').value;
  const payload = {
    pool_id:       parseInt(document.getElementById('lotPrizePoolId').value),
    name:          document.getElementById('lotPrizeName').value,
    reward_amount: parseFloat(document.getElementById('lotPrizeReward').value),
    weight:        parseInt(document.getElementById('lotPrizeWeight').value),
    rarity:        parseInt(document.getElementById('lotPrizeRarity').value),
    icon_url:      document.getElementById('lotPrizeIcon').value,
    sort_order:    parseInt(document.getElementById('lotPrizeSort').value),
    is_enabled:    parseInt(document.getElementById('lotPrizeEnabled').value),
  };
  const url = id ? '/lottery/prize/update' : '/lottery/prize/create';
  if (id) payload.id = parseInt(id);
  const res = await apiPost(url, payload);
  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) {
    document.getElementById('lotPrizeModal').classList.remove('show');
    loadLotPrizes();
  }
}

async function deleteLotPrize(id) {
  if (!confirm(`删除奖品 #${id}？`)) return;
  const res = await apiPost('/lottery/prize/delete', { id });
  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) loadLotPrizes();
}

async function loadLotLogs(page) {
  lotLogPage = page;
  const userId = document.getElementById('lotLogUserId')?.value || '';
  const isBig = document.getElementById('lotLogIsBig')?.value || '';
  const res = await apiGet('/lottery/logs', { page, user_id: userId, is_big_prize: isBig });
  if (res.code !== 0) return;
  const { data: list, current_page, last_page } = res.data;
  const tbody = document.getElementById('lotLogTbody');
  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;">暂无数据</td></tr>';
    document.getElementById('lotLogPagination').innerHTML = '';
    return;
  }
  tbody.innerHTML = list.map(r => `
    <tr>
      <td>${r.id}</td>
      <td>${r.user?.nickname || '-'} (ID:${r.user_id})</td>
      <td>${r.prize_name}</td>
      <td>${r.cost}</td>
      <td style="color:${r.reward_amount>0 ? '#10b981' : '#999'};">${r.reward_amount}</td>
      <td>${r.is_big_prize ? '🎉' : '-'}</td>
      <td>${r.is_recharged_user ? '✓' : '✗'}</td>
      <td>${r.created_at}</td>
    </tr>
  `).join('');
  document.getElementById('lotLogPagination').innerHTML = buildPagination(current_page, last_page, 'loadLotLogs');
}

async function loadLotStats() {
  const res = await apiGet('/lottery/stats');
  if (res.code !== 0) return;
  const d = res.data;
  const grid = document.getElementById('lotStatsGrid');
  const render = (label, s) => `
    <div class="stat-card">
      <div class="stat-value">${s.count}</div>
      <div class="stat-label">${label}次数</div>
      <div style="font-size:11px;color:#666;margin-top:4px;">
        消耗 ${s.cost.toFixed(2)} / 奖励 ${s.reward.toFixed(2)}<br>
        <span style="color:${s.net>=0?'#10b981':'#ef4444'};font-weight:700;">净收入 ${s.net.toFixed(2)}</span>
      </div>
    </div>`;
  grid.innerHTML = render('今日', d.day) + render('近7天', d.week) + render('本月', d.month);
}
