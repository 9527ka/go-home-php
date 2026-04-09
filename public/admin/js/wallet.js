/**
 * Wallet Page
 * Manages all 5 wallet sub-tabs: recharge, withdrawal, transactions, red packets, settings.
 */

PAGE_TEMPLATES['wallet'] = `
<div id="pageWallet" class="page">
  <div class="page-header">
    <h2>💰 钱包管理</h2>
    <p>管理充值、提现、流水、红包及钱包配置</p>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <button class="btn-filter wallet-tab active" data-wtab="recharge" onclick="switchWalletTab('recharge')" style="background:#667eea;">💳 充值审核</button>
    <button class="btn-filter wallet-tab" data-wtab="withdrawal" onclick="switchWalletTab('withdrawal')" style="background:#6b7280;">💸 提现审核</button>
    <button class="btn-filter wallet-tab" data-wtab="transactions" onclick="switchWalletTab('transactions')" style="background:#6b7280;">📄 流水记录</button>
    <button class="btn-filter wallet-tab" data-wtab="redpacket" onclick="switchWalletTab('redpacket')" style="background:#6b7280;">🧧 红包记录</button>
    <button class="btn-filter wallet-tab" data-wtab="wsettings" onclick="switchWalletTab('wsettings')" style="background:#6b7280;">⚙️ 钱包配置</button>
  </div>

  <div id="walletTabRecharge" class="section-card wallet-tab-content">
    <div class="section-title">💳 充值订单</div>
    <div class="filter-bar">
      <select id="rechargeStatusFilter">
        <option value="">全部状态</option>
        <option value="0">待审核</option>
        <option value="1">已通过</option>
        <option value="2">已拒绝</option>
      </select>
      <select id="rechargePaymentTypeFilter">
        <option value="">全部支付方式</option>
        <option value="0">USDT手动</option>
        <option value="1">Apple IAP</option>
      </select>
      <button class="btn-filter" onclick="loadRechargeList(1)">筛选</button>
    </div>
    <div id="rechargeLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="rechargeEmpty" class="empty-state hidden"><div class="icon">💳</div><p>暂无充值订单</p></div>
    <table id="rechargeTable" class="hidden">
      <thead><tr><th>ID</th><th>用户</th><th>金额</th><th>支付方式</th><th>凭证</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
      <tbody id="rechargeTbody"></tbody>
    </table>
    <div class="pagination" id="rechargePagination"></div>
  </div>

  <div id="walletTabWithdrawal" class="section-card wallet-tab-content hidden">
    <div class="section-title">💸 提现订单</div>
    <div class="filter-bar">
      <select id="withdrawalStatusFilter">
        <option value="">全部状态</option>
        <option value="0">待审核</option>
        <option value="1">已通过</option>
        <option value="2">已拒绝</option>
      </select>
      <button class="btn-filter" onclick="loadWithdrawalList(1)">筛选</button>
    </div>
    <div id="withdrawalLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="withdrawalEmpty" class="empty-state hidden"><div class="icon">💸</div><p>暂无提现订单</p></div>
    <table id="withdrawalTable" class="hidden">
      <thead><tr><th>ID</th><th>用户</th><th>金额</th><th>手续费</th><th>实际到账</th><th>收款信息</th><th>时间</th><th>状态</th><th>操作</th></tr></thead>
      <tbody id="withdrawalTbody"></tbody>
    </table>
    <div class="pagination" id="withdrawalPagination"></div>
  </div>

  <div id="walletTabTransactions" class="section-card wallet-tab-content hidden">
    <div class="section-title">📄 流水记录</div>
    <div class="filter-bar">
      <input type="text" id="txnUserId" placeholder="用户ID" style="width:100px;">
      <select id="txnTypeFilter">
        <option value="">全部类型</option>
        <option value="1">充值</option>
        <option value="2">提现</option>
        <option value="3">提现退回</option>
        <option value="4">发红包</option>
        <option value="5">收红包</option>
        <option value="6">打赏</option>
        <option value="7">置顶</option>
        <option value="8">红包退回</option>
      </select>
      <button class="btn-filter" onclick="loadTransactionList(1)">筛选</button>
    </div>
    <div id="txnLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="txnEmpty" class="empty-state hidden"><div class="icon">📄</div><p>暂无流水记录</p></div>
    <table id="txnTable" class="hidden">
      <thead><tr><th>ID</th><th>用户</th><th>类型</th><th>金额</th><th>变动前</th><th>变动后</th><th>备注</th><th>时间</th></tr></thead>
      <tbody id="txnTbody"></tbody>
    </table>
    <div class="pagination" id="txnPagination"></div>
  </div>

  <div id="walletTabRedpacket" class="section-card wallet-tab-content hidden">
    <div class="section-title">🧧 红包记录</div>
    <div id="rpLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="rpEmpty" class="empty-state hidden"><div class="icon">🧧</div><p>暂无红包记录</p></div>
    <table id="rpTable" class="hidden">
      <thead><tr><th>ID</th><th>发送者</th><th>总金额</th><th>个数</th><th>剩余金额</th><th>祝福语</th><th>类型</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
      <tbody id="rpTbody"></tbody>
    </table>
    <div class="pagination" id="rpPagination"></div>
  </div>

  <div id="walletTabWsettings" class="section-card wallet-tab-content hidden">
    <div class="section-title">⚙️ 钱包配置</div>
    <div id="wsLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <table id="wsTable">
      <thead><tr><th>配置项</th><th>当前值</th><th>说明</th><th>操作</th></tr></thead>
      <tbody id="wsTbody"></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalRechargeReject">
  <div class="modal">
    <h3>❌ 拒绝充值</h3>
    <div class="form-group">
      <label>拒绝原因</label>
      <textarea id="rechargeRejectRemark" placeholder="请输入拒绝原因..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalRechargeReject')">取消</button>
      <button class="btn-confirm danger" onclick="doRechargeReject()">确认拒绝</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalWithdrawalApprove">
  <div class="modal">
    <h3>✅ 通过提现</h3>
    <div class="form-group">
      <label>交易哈希/备注</label>
      <input type="text" id="withdrawalTxHash" placeholder="转账凭证或交易哈希...">
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalWithdrawalApprove')">取消</button>
      <button class="btn-confirm" onclick="doWithdrawalApprove()">确认通过</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalWithdrawalReject">
  <div class="modal">
    <h3>❌ 拒绝提现</h3>
    <div class="form-group">
      <label>拒绝原因</label>
      <textarea id="withdrawalRejectRemark" placeholder="请输入拒绝原因..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalWithdrawalReject')">取消</button>
      <button class="btn-confirm danger" onclick="doWithdrawalReject()">确认拒绝</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalRpClaims">
  <div class="modal" style="width:560px;">
    <h3>🧧 领取详情</h3>
    <div id="rpClaimsLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <table id="rpClaimsTable" class="hidden">
      <thead><tr><th>用户</th><th>金额</th><th>领取时间</th></tr></thead>
      <tbody id="rpClaimsTbody"></tbody>
    </table>
    <div id="rpClaimsEmpty" class="empty-state hidden"><div class="icon">🧧</div><p>暂无领取记录</p></div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalRpClaims')">关闭</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalWalletSetting">
  <div class="modal">
    <h3>⚙️ 编辑配置</h3>
    <input type="hidden" id="wsKey">
    <div class="form-group">
      <label id="wsLabel">配置项</label>
      <input type="text" id="wsValue" placeholder="配置值">
    </div>
    <div class="form-group">
      <label>说明</label>
      <p id="wsDesc" style="font-size:13px;color:#666;"></p>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('modalWalletSetting')">取消</button>
      <button class="btn-confirm" onclick="saveWalletSetting()">保存</button>
    </div>
  </div>
</div>
`;

// ======================== Wallet State ========================
let currentWalletTab = 'recharge';
let rechargeData = { list: [], page: 1, total: 0 };
let withdrawalData = { list: [], page: 1, total: 0 };
let txnData = { list: [], page: 1, total: 0 };
let rpData = { list: [], page: 1, total: 0 };
let currentRechargeId = null;
let currentWithdrawalId = null;
let walletSettings = [];

// ======================== Wallet Constants ========================
const ORDER_STATUS = { 0: '待审核', 1: '已通过', 2: '已拒绝' };
const ORDER_STATUS_CLASS = { 0: 'pending', 1: 'active', 2: 'rejected' };
const RP_STATUS = { 1: '进行中', 2: '已抢完', 3: '已过期' };
const RP_STATUS_CLASS = { 1: 'active', 2: 'closed', 3: 'rejected' };
const RP_TARGET_TYPE = { 1: '公共', 2: '私聊', 3: '群聊' };
const TXN_TYPE = { 1: '充值', 2: '提现', 3: '提现退回', 4: '发红包', 5: '收红包', 6: '打赏', 7: '置顶', 8: '红包退回' };

// ======================== Tab Switching ========================

function switchWalletTab(tab) {
  currentWalletTab = tab;

  // Update tab button styles
  document.querySelectorAll('.wallet-tab').forEach(el => {
    el.style.background = el.dataset.wtab === tab ? '#667eea' : '#6b7280';
  });

  // Hide all tab content
  document.querySelectorAll('.wallet-tab-content').forEach(el => el.classList.add('hidden'));

  // Show + load selected tab
  switch (tab) {
    case 'recharge':
      document.getElementById('walletTabRecharge').classList.remove('hidden');
      loadRechargeList(1);
      break;
    case 'withdrawal':
      document.getElementById('walletTabWithdrawal').classList.remove('hidden');
      loadWithdrawalList(1);
      break;
    case 'transactions':
      document.getElementById('walletTabTransactions').classList.remove('hidden');
      loadTransactionList(1);
      break;
    case 'redpacket':
      document.getElementById('walletTabRedpacket').classList.remove('hidden');
      loadRedPacketList(1);
      break;
    case 'wsettings':
      document.getElementById('walletTabWsettings').classList.remove('hidden');
      loadWalletSettings();
      break;
  }
}

// ======================== Recharge ========================

async function loadRechargeList(page) {
  const loading = document.getElementById('rechargeLoading');
  const empty = document.getElementById('rechargeEmpty');
  const table = document.getElementById('rechargeTable');
  const pagination = document.getElementById('rechargePagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const status = document.getElementById('rechargeStatusFilter').value;
  const paymentType = document.getElementById('rechargePaymentTypeFilter').value;
  const res = await apiGet('/wallet/recharge/list', { page, status: status || undefined, payment_type: paymentType !== '' ? paymentType : undefined });
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  const data = res.data;
  rechargeData = { list: data.data || [], page: data.current_page || page, total: data.total || 0 };

  if (!rechargeData.list.length) { empty.classList.remove('hidden'); return; }

  table.classList.remove('hidden');
  document.getElementById('rechargeTbody').innerHTML = rechargeData.list.map(r => {
    const st = r.status ?? 0;
    const isIap = r.payment_type === 1;
    const paymentLabel = isIap ? '<span style="color:#667eea;font-weight:500;">Apple IAP</span>' : 'USDT手动';
    return `<tr>
      <td>#${r.id}</td>
      <td>${r.user ? escHtml(r.user.nickname || '用户'+r.user_id) : r.user_id}</td>
      <td style="font-weight:600;color:#10b981;">¥${parseFloat(r.amount).toFixed(2)}</td>
      <td>${paymentLabel}${isIap && r.iap_product_id ? `<div style="font-size:11px;color:#999;">${escHtml(r.iap_product_id)}</div>` : ''}</td>
      <td>${r.screenshot_url ? `<a href="${r.screenshot_url}" target="_blank" style="color:#667eea;">查看</a>` : '-'}</td>
      <td>${formatTime(r.created_at)}</td>
      <td><span class="status-tag ${ORDER_STATUS_CLASS[st]}">${ORDER_STATUS[st]}</span>${st === 2 && r.admin_remark ? `<div style="font-size:11px;color:#ef4444;margin-top:2px;">${escHtml(r.admin_remark)}</div>` : ''}</td>
      <td><div class="btn-group">
        ${st === 0 && !isIap ? `<button class="btn-sm btn-approve" onclick="doRechargeApprove(${r.id})">通过</button>
        <button class="btn-sm btn-reject" onclick="currentRechargeId=${r.id};document.getElementById('rechargeRejectRemark').value='';openModal('modalRechargeReject')">拒绝</button>` : (isIap && st === 1 ? '<span style="font-size:12px;color:#10b981;">自动到账</span>' : '-')}
      </div></td>
    </tr>`;
  }).join('');
  renderPagination(pagination, rechargeData.page, Math.ceil(rechargeData.total / 20), loadRechargeList);
}

async function doRechargeApprove(id) {
  if (!confirm('确认通过该充值申请？')) return;
  const res = await apiPost('/wallet/recharge/approve', { order_id: id });
  if (res.code === 0) { toast('充值已通过'); loadRechargeList(rechargeData.page); }
  else toast(res.msg, 'error');
}

async function doRechargeReject() {
  const remark = document.getElementById('rechargeRejectRemark').value.trim();
  const res = await apiPost('/wallet/recharge/reject', { order_id: currentRechargeId, remark });
  if (res.code === 0) { toast('已拒绝'); closeModal('modalRechargeReject'); loadRechargeList(rechargeData.page); }
  else toast(res.msg, 'error');
}

// ======================== Withdrawal ========================

async function loadWithdrawalList(page) {
  const loading = document.getElementById('withdrawalLoading');
  const empty = document.getElementById('withdrawalEmpty');
  const table = document.getElementById('withdrawalTable');
  const pagination = document.getElementById('withdrawalPagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const status = document.getElementById('withdrawalStatusFilter').value;
  const res = await apiGet('/wallet/withdrawal/list', { page, status: status || undefined });
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  const data = res.data;
  withdrawalData = { list: data.data || [], page: data.current_page || page, total: data.total || 0 };

  if (!withdrawalData.list.length) { empty.classList.remove('hidden'); return; }

  table.classList.remove('hidden');
  document.getElementById('withdrawalTbody').innerHTML = withdrawalData.list.map(w => {
    const st = w.status ?? 0;
    const fee = parseFloat(w.fee || 0);
    const amount = parseFloat(w.amount || 0);
    const actual = (amount - fee).toFixed(2);
    return `<tr>
      <td>#${w.id}</td>
      <td>${w.user ? escHtml(w.user.nickname || '用户'+w.user_id) : w.user_id}</td>
      <td style="font-weight:600;color:#ef4444;">¥${amount.toFixed(2)}</td>
      <td>¥${fee.toFixed(2)}</td>
      <td style="font-weight:600;">¥${actual}</td>
      <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(w.account_info || '')}">${escHtml(w.account_info || w.withdraw_account || '-')}</td>
      <td>${formatTime(w.created_at)}</td>
      <td><span class="status-tag ${ORDER_STATUS_CLASS[st]}">${ORDER_STATUS[st]}</span>${st === 2 && w.admin_remark ? `<div style="font-size:11px;color:#ef4444;margin-top:2px;">${escHtml(w.admin_remark)}</div>` : ''}</td>
      <td><div class="btn-group">
        ${st === 0 ? `<button class="btn-sm btn-approve" onclick="currentWithdrawalId=${w.id};document.getElementById('withdrawalTxHash').value='';openModal('modalWithdrawalApprove')">通过</button>
        <button class="btn-sm btn-reject" onclick="currentWithdrawalId=${w.id};document.getElementById('withdrawalRejectRemark').value='';openModal('modalWithdrawalReject')">拒绝</button>` : '-'}
      </div></td>
    </tr>`;
  }).join('');
  renderPagination(pagination, withdrawalData.page, Math.ceil(withdrawalData.total / 20), loadWithdrawalList);
}

async function doWithdrawalApprove() {
  const txHash = document.getElementById('withdrawalTxHash').value.trim();
  const res = await apiPost('/wallet/withdrawal/approve', { order_id: currentWithdrawalId, tx_hash: txHash });
  if (res.code === 0) { toast('提现已通过'); closeModal('modalWithdrawalApprove'); loadWithdrawalList(withdrawalData.page); }
  else toast(res.msg, 'error');
}

async function doWithdrawalReject() {
  const remark = document.getElementById('withdrawalRejectRemark').value.trim();
  const res = await apiPost('/wallet/withdrawal/reject', { order_id: currentWithdrawalId, remark });
  if (res.code === 0) { toast('提现已拒绝，余额已退回'); closeModal('modalWithdrawalReject'); loadWithdrawalList(withdrawalData.page); }
  else toast(res.msg, 'error');
}

// ======================== Transactions ========================

async function loadTransactionList(page) {
  const loading = document.getElementById('txnLoading');
  const empty = document.getElementById('txnEmpty');
  const table = document.getElementById('txnTable');
  const pagination = document.getElementById('txnPagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const userId = document.getElementById('txnUserId').value;
  const type = document.getElementById('txnTypeFilter').value;
  const res = await apiGet('/wallet/transactions', { page, user_id: userId || undefined, type: type || undefined });
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  const data = res.data;
  txnData = { list: data.data || [], page: data.current_page || page, total: data.total || 0 };

  if (!txnData.list.length) { empty.classList.remove('hidden'); return; }

  table.classList.remove('hidden');
  document.getElementById('txnTbody').innerHTML = txnData.list.map(t => {
    const amt = parseFloat(t.amount || 0);
    const isPositive = amt >= 0;
    return `<tr>
      <td>#${t.id}</td>
      <td>${t.user ? escHtml(t.user.nickname || '用户'+t.user_id) : t.user_id}</td>
      <td><span class="status-tag ${isPositive ? 'active' : 'rejected'}">${TXN_TYPE[t.type] || '未知'}</span></td>
      <td style="font-weight:600;color:${isPositive ? '#10b981' : '#ef4444'};">${isPositive ? '+' : ''}${amt.toFixed(2)}</td>
      <td>¥${parseFloat(t.balance_before || 0).toFixed(2)}</td>
      <td>¥${parseFloat(t.balance_after || 0).toFixed(2)}</td>
      <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(t.remark || '')}">${escHtml(t.remark || '-')}</td>
      <td>${formatTime(t.created_at)}</td>
    </tr>`;
  }).join('');
  renderPagination(pagination, txnData.page, Math.ceil(txnData.total / 20), loadTransactionList);
}

// ======================== Red Packets ========================

async function loadRedPacketList(page) {
  const loading = document.getElementById('rpLoading');
  const empty = document.getElementById('rpEmpty');
  const table = document.getElementById('rpTable');
  const pagination = document.getElementById('rpPagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const res = await apiGet('/wallet/red-packet/list', { page });
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  const data = res.data;
  rpData = { list: data.data || [], page: data.current_page || page, total: data.total || 0 };

  if (!rpData.list.length) { empty.classList.remove('hidden'); return; }

  table.classList.remove('hidden');
  document.getElementById('rpTbody').innerHTML = rpData.list.map(r => {
    const st = r.status ?? 1;
    return `<tr>
      <td>#${r.id}</td>
      <td>${r.user ? escHtml(r.user.nickname || '用户'+r.user_id) : r.user_id}</td>
      <td style="font-weight:600;color:#ef4444;">¥${parseFloat(r.total_amount || 0).toFixed(2)}</td>
      <td>${r.total_count}</td>
      <td>¥${parseFloat(r.remaining_amount || 0).toFixed(2)}</td>
      <td>${escHtml(r.greeting || '恭喜发财')}</td>
      <td><span class="category-tag pet">${RP_TARGET_TYPE[r.target_type] || '未知'}</span></td>
      <td><span class="status-tag ${RP_STATUS_CLASS[st] || ''}">${RP_STATUS[st] || '未知'}</span></td>
      <td>${formatTime(r.created_at)}</td>
      <td><button class="btn-sm btn-view" onclick="viewRpClaims(${r.id})">领取详情</button></td>
    </tr>`;
  }).join('');
  renderPagination(pagination, rpData.page, Math.ceil(rpData.total / 20), loadRedPacketList);
}

async function viewRpClaims(rpId) {
  const loading = document.getElementById('rpClaimsLoading');
  const table = document.getElementById('rpClaimsTable');
  const empty = document.getElementById('rpClaimsEmpty');
  loading.classList.remove('hidden'); table.classList.add('hidden'); empty.classList.add('hidden');
  openModal('modalRpClaims');

  const res = await apiGet('/wallet/red-packet/claims', { red_packet_id: rpId });
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  const claims = res.data.data || [];
  if (!claims.length) { empty.classList.remove('hidden'); return; }

  table.classList.remove('hidden');
  document.getElementById('rpClaimsTbody').innerHTML = claims.map(c => `<tr>
    <td>${c.user ? escHtml(c.user.nickname || '用户'+c.user_id) : c.user_id}</td>
    <td style="font-weight:600;color:#10b981;">¥${parseFloat(c.amount || 0).toFixed(2)}</td>
    <td>${formatTime(c.created_at)}</td>
  </tr>`).join('');
}

// ======================== Wallet Settings ========================

function renderSettingValue(s) {
  if (s.type === 'toggle') {
    const checked = s.value === '1';
    const color = checked ? '#22c55e' : '#ccc';
    const offset = checked ? '20px' : '2px';
    const label = checked ? '已开启' : '已关闭';
    return `<span style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;" onclick="toggleWalletSetting('${escHtml(s.key)}', '${checked ? '0' : '1'}')">
      <span style="display:inline-block;width:40px;height:22px;background:${color};border-radius:11px;position:relative;transition:background 0.2s;">
        <span style="position:absolute;top:2px;left:${offset};width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,0.2);transition:left 0.2s;"></span>
      </span>
      <span style="font-size:13px;color:${checked ? '#22c55e' : '#999'};">${label}</span>
    </span>`;
  }
  return `<span style="font-weight:500;">${escHtml(s.value)}</span>`;
}

async function loadWalletSettings() {
  const loading = document.getElementById('wsLoading');
  loading.classList.remove('hidden');
  const res = await apiGet('/wallet/settings');
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  walletSettings = res.data || [];
  document.getElementById('wsTbody').innerHTML = walletSettings.map(s => `<tr>
    <td><code style="background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:12px;">${escHtml(s.key)}</code></td>
    <td>${renderSettingValue(s)}</td>
    <td style="font-size:13px;color:#666;">${escHtml(s.description || '-')}</td>
    <td>${s.type === 'toggle' ? '' : `<button class="btn-sm btn-view" onclick="editWalletSetting('${escHtml(s.key)}')">编辑</button>`}</td>
  </tr>`).join('');
}

async function toggleWalletSetting(key, newValue) {
  const res = await apiPost('/wallet/settings/update', { key, value: newValue });
  if (res.code === 0) { toast('配置已更新'); loadWalletSettings(); }
  else toast(res.msg, 'error');
}

function editWalletSetting(key) {
  const s = walletSettings.find(i => i.key === key);
  if (!s) return;
  document.getElementById('wsKey').value = s.key;
  document.getElementById('wsLabel').textContent = s.description || s.key;
  document.getElementById('wsValue').value = s.value;
  document.getElementById('wsDesc').textContent = s.key;
  openModal('modalWalletSetting');
}

async function saveWalletSetting() {
  const key = document.getElementById('wsKey').value;
  const value = document.getElementById('wsValue').value.trim();
  const res = await apiPost('/wallet/settings/update', { key, value });
  if (res.code === 0) { toast('配置已更新'); closeModal('modalWalletSetting'); loadWalletSettings(); }
  else toast(res.msg, 'error');
}
