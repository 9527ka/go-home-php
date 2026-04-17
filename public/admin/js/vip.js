/**
 * VIP 管理模块
 */

PAGE_TEMPLATES['vip'] = `
<div id="pageVip" class="page">
  <div class="page-header">
    <h2>👑 VIP 管理</h2>
    <p>配置 VIP 等级、查看购买订单、管理用户 VIP 状态</p>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <button class="btn-filter vip-tab active" data-vtab="levels" onclick="switchVipTab('levels')" style="background:#667eea;">⭐ 等级配置</button>
    <button class="btn-filter vip-tab" data-vtab="orders" onclick="switchVipTab('orders')" style="background:#6b7280;">💳 购买订单</button>
    <button class="btn-filter vip-tab" data-vtab="users" onclick="switchVipTab('users')" style="background:#6b7280;">👥 用户VIP</button>
  </div>

  <div id="vipTabLevels" class="section-card vip-tab-content">
    <div class="section-title">等级配置（level_key / level_order 固定不可改，数值字段可编辑后点保存）</div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>key</th>
            <th>名称</th>
            <th>价格</th>
            <th>天数</th>
            <th>签到加成</th>
            <th>暴击加成</th>
            <th>最高倍率</th>
            <th>提现费率</th>
            <th>日提现额度</th>
            <th>头像效果</th>
            <th>昵称效果</th>
            <th>红包效果</th>
            <th>启用</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody id="vipLevelTbody"><tr><td colspan="15" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
      </table>
    </div>
  </div>

  <div id="vipTabOrders" class="section-card vip-tab-content hidden">
    <div class="section-title">💳 购买订单</div>
    <div class="filter-bar">
      <input type="number" id="vipOrderUserId" placeholder="用户ID" style="width:120px;">
      <select id="vipOrderLevelKey">
        <option value="">全部等级</option>
        <option value="silver">白银</option>
        <option value="gold">黄金</option>
        <option value="platinum">铂金</option>
        <option value="diamond">钻石</option>
        <option value="supreme">至尊</option>
      </select>
      <button class="btn-filter" onclick="loadVipOrders(1)">筛选</button>
    </div>
    <table>
      <thead><tr><th>ID</th><th>用户</th><th>等级</th><th>金额</th><th>天数</th><th>新到期</th><th>时间</th></tr></thead>
      <tbody id="vipOrderTbody"><tr><td colspan="7" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
    </table>
    <div class="pagination" id="vipOrderPagination"></div>
  </div>

  <div id="vipTabUsers" class="section-card vip-tab-content hidden">
    <div class="section-title">👥 用户 VIP 状态</div>
    <div class="filter-bar">
      <input type="number" id="vipUserFilterId" placeholder="用户ID" style="width:120px;">
      <button class="btn-filter" onclick="loadVipUsers(1)">筛选</button>
      <button class="btn-filter" onclick="openVipGrantModal()" style="background:#10b981;">➕ 手动授予</button>
    </div>
    <table>
      <thead><tr><th>用户ID</th><th>昵称</th><th>编号</th><th>当前等级</th><th>到期时间</th><th>操作</th></tr></thead>
      <tbody id="vipUserTbody"><tr><td colspan="6" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
    </table>
    <div class="pagination" id="vipUserPagination"></div>
  </div>

  <!-- 授予弹窗 -->
  <div class="modal-overlay" id="vipGrantModal">
    <div class="modal">
      <div class="modal-header"><h3>手动授予/调整 VIP</h3></div>
      <div class="modal-body">
        <div class="form-group"><label>用户ID</label><input type="number" id="grantUserId"></div>
        <div class="form-group"><label>等级</label>
          <select id="grantLevelKey">
            <option value="normal">普通（清除VIP）</option>
            <option value="silver">白银</option>
            <option value="gold">黄金</option>
            <option value="platinum">铂金</option>
            <option value="diamond">钻石</option>
            <option value="supreme">至尊</option>
          </select>
        </div>
        <div class="form-group"><label>到期时间（留空按默认天数）</label><input type="datetime-local" id="grantExpiredAt"></div>
      </div>
      <div class="modal-footer">
        <button class="btn" onclick="document.getElementById('vipGrantModal').classList.remove('show')">取消</button>
        <button class="btn btn-primary" onclick="submitVipGrant()">确定</button>
      </div>
    </div>
  </div>
</div>
`;

let currentVipTab = 'levels';
let vipOrderPage = 1;
let vipUserPage = 1;

function switchVipTab(tab) {
  currentVipTab = tab;
  document.querySelectorAll('.vip-tab').forEach(el => {
    const active = el.dataset.vtab === tab;
    el.classList.toggle('active', active);
    el.style.background = active ? '#667eea' : '#6b7280';
  });
  document.querySelectorAll('.vip-tab-content').forEach(el => el.classList.add('hidden'));
  const map = { levels: 'vipTabLevels', orders: 'vipTabOrders', users: 'vipTabUsers' };
  document.getElementById(map[tab]).classList.remove('hidden');
  if (tab === 'levels') loadVipLevels();
  if (tab === 'orders') loadVipOrders(1);
  if (tab === 'users') loadVipUsers(1);
}

// ======================== 等级配置 ========================

async function loadVipLevels() {
  const res = await apiGet('/vip/levels');
  if (res.code !== 0) return;
  const tbody = document.getElementById('vipLevelTbody');
  const list = res.data || [];
  tbody.innerHTML = list.map(lv => `
    <tr>
      <td>${lv.id}</td>
      <td>${lv.level_key}</td>
      <td><input type="text" id="vip_name_${lv.id}" value="${lv.level_name}" style="width:60px;"></td>
      <td><input type="number" id="vip_price_${lv.id}" value="${lv.price}" step="0.01" style="width:80px;"></td>
      <td><input type="number" id="vip_days_${lv.id}" value="${lv.duration_days}" style="width:50px;"></td>
      <td><input type="number" id="vip_sbr_${lv.id}" value="${lv.sign_bonus_rate}" step="0.0001" style="width:70px;"></td>
      <td><input type="number" id="vip_cpb_${lv.id}" value="${lv.crit_prob_bonus}" step="0.0001" style="width:70px;"></td>
      <td><input type="number" id="vip_cmm_${lv.id}" value="${lv.crit_max_multiple}" style="width:50px;"></td>
      <td><input type="number" id="vip_wfr_${lv.id}" value="${lv.withdraw_fee_rate}" step="0.0001" style="width:70px;"></td>
      <td><input type="number" id="vip_wdl_${lv.id}" value="${lv.withdraw_daily_limit}" step="0.01" style="width:100px;"></td>
      <td><input type="text" id="vip_bek_${lv.id}" value="${lv.badge_effect_key}" style="width:110px;"></td>
      <td><input type="text" id="vip_nek_${lv.id}" value="${lv.name_effect_key}" style="width:110px;"></td>
      <td><input type="text" id="vip_rpe_${lv.id}" value="${lv.red_packet_effect_key}" style="width:110px;"></td>
      <td>
        <select id="vip_en_${lv.id}" style="padding:2px 4px;">
          <option value="1" ${lv.is_enabled ? 'selected' : ''}>启用</option>
          <option value="0" ${!lv.is_enabled ? 'selected' : ''}>禁用</option>
        </select>
      </td>
      <td><button class="btn btn-sm btn-primary" onclick="saveVipLevel(${lv.id})">保存</button></td>
    </tr>
  `).join('');
}

async function saveVipLevel(id) {
  const payload = {
    id,
    level_name:           document.getElementById(`vip_name_${id}`).value,
    price:                parseFloat(document.getElementById(`vip_price_${id}`).value),
    duration_days:        parseInt(document.getElementById(`vip_days_${id}`).value),
    sign_bonus_rate:      parseFloat(document.getElementById(`vip_sbr_${id}`).value),
    crit_prob_bonus:      parseFloat(document.getElementById(`vip_cpb_${id}`).value),
    crit_max_multiple:    parseInt(document.getElementById(`vip_cmm_${id}`).value),
    withdraw_fee_rate:    parseFloat(document.getElementById(`vip_wfr_${id}`).value),
    withdraw_daily_limit: parseFloat(document.getElementById(`vip_wdl_${id}`).value),
    badge_effect_key:     document.getElementById(`vip_bek_${id}`).value,
    name_effect_key:      document.getElementById(`vip_nek_${id}`).value,
    red_packet_effect_key: document.getElementById(`vip_rpe_${id}`).value,
    is_enabled:           parseInt(document.getElementById(`vip_en_${id}`).value),
  };
  const res = await apiPost('/vip/level/update', payload);
  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) loadVipLevels();
}

// ======================== 购买订单 ========================

async function loadVipOrders(page) {
  vipOrderPage = page;
  const userId = document.getElementById('vipOrderUserId')?.value || '';
  const levelKey = document.getElementById('vipOrderLevelKey')?.value || '';
  const res = await apiGet('/vip/orders', { page, user_id: userId, level_key: levelKey });
  if (res.code !== 0) return;
  const { data: list, current_page, last_page } = res.data;
  const tbody = document.getElementById('vipOrderTbody');
  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">暂无数据</td></tr>';
    document.getElementById('vipOrderPagination').innerHTML = '';
    return;
  }
  tbody.innerHTML = list.map(o => `
    <tr>
      <td>${o.id}</td>
      <td>${o.user?.nickname || '-'} (ID:${o.user_id})</td>
      <td>${o.level_key}</td>
      <td>${o.price}</td>
      <td>${o.duration_days}</td>
      <td>${o.new_expired_at}</td>
      <td>${o.created_at}</td>
    </tr>
  `).join('');
  document.getElementById('vipOrderPagination').innerHTML = buildPagination(current_page, last_page, 'loadVipOrders');
}

// ======================== 用户VIP ========================

async function loadVipUsers(page) {
  vipUserPage = page;
  const userId = document.getElementById('vipUserFilterId')?.value || '';
  const res = await apiGet('/vip/users', { page, user_id: userId });
  if (res.code !== 0) return;
  const { data: list, current_page, last_page } = res.data;
  const tbody = document.getElementById('vipUserTbody');
  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">暂无数据</td></tr>';
    document.getElementById('vipUserPagination').innerHTML = '';
    return;
  }
  tbody.innerHTML = list.map(u => `
    <tr>
      <td>${u.user_id}</td>
      <td>${u.nickname || '-'}</td>
      <td>${u.user_code || '-'}</td>
      <td>${u.level_key}</td>
      <td>${u.expired_at}</td>
      <td><button class="btn btn-sm btn-primary" onclick="openVipGrantModalFor(${u.user_id}, '${u.level_key}')">调整</button></td>
    </tr>
  `).join('');
  document.getElementById('vipUserPagination').innerHTML = buildPagination(current_page, last_page, 'loadVipUsers');
}

function openVipGrantModal() {
  document.getElementById('grantUserId').value = '';
  document.getElementById('grantLevelKey').value = 'silver';
  document.getElementById('grantExpiredAt').value = '';
  document.getElementById('vipGrantModal').classList.add('show');
}

function openVipGrantModalFor(userId, levelKey) {
  document.getElementById('grantUserId').value = userId;
  document.getElementById('grantLevelKey').value = levelKey;
  document.getElementById('grantExpiredAt').value = '';
  document.getElementById('vipGrantModal').classList.add('show');
}

async function submitVipGrant() {
  const userId = parseInt(document.getElementById('grantUserId').value);
  const levelKey = document.getElementById('grantLevelKey').value;
  const expiredAtLocal = document.getElementById('grantExpiredAt').value;
  if (!userId || !levelKey) { toast('用户ID与等级必填', 'error'); return; }

  const body = { user_id: userId, level_key: levelKey };
  if (expiredAtLocal) body.expired_at = expiredAtLocal.replace('T', ' ') + ':00';

  const res = await apiPost('/vip/user/grant', body);
  toast(res.msg || '操作完成', res.code === 0 ? 'success' : 'error');
  if (res.code === 0) {
    document.getElementById('vipGrantModal').classList.remove('show');
    loadVipUsers(vipUserPage);
  }
}
