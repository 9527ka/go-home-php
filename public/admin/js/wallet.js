/**
 * Wallet Page
 * Manages all 5 wallet sub-tabs: recharge, withdrawal, transactions, red packets, settings.
 */

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
  const res = await apiGet('/wallet/recharge/list', { page, status: status || undefined });
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg, 'error');

  const data = res.data;
  rechargeData = { list: data.data || [], page: data.current_page || page, total: data.total || 0 };

  if (!rechargeData.list.length) { empty.classList.remove('hidden'); return; }

  table.classList.remove('hidden');
  document.getElementById('rechargeTbody').innerHTML = rechargeData.list.map(r => {
    const st = r.status ?? 0;
    return `<tr>
      <td>#${r.id}</td>
      <td>${r.user ? escHtml(r.user.nickname || '用户'+r.user_id) : r.user_id}</td>
      <td style="font-weight:600;color:#10b981;">¥${parseFloat(r.amount).toFixed(2)}</td>
      <td>${escHtml(r.payment_method || '-')}</td>
      <td>${r.proof_image ? `<a href="${r.proof_image}" target="_blank" style="color:#667eea;">查看</a>` : '-'}</td>
      <td>${formatTime(r.created_at)}</td>
      <td><span class="status-tag ${ORDER_STATUS_CLASS[st]}">${ORDER_STATUS[st]}</span></td>
      <td><div class="btn-group">
        ${st === 0 ? `<button class="btn-sm btn-approve" onclick="doRechargeApprove(${r.id})">通过</button>
        <button class="btn-sm btn-reject" onclick="currentRechargeId=${r.id};document.getElementById('rechargeRejectRemark').value='';openModal('modalRechargeReject')">拒绝</button>` : '-'}
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
      <td><span class="status-tag ${ORDER_STATUS_CLASS[st]}">${ORDER_STATUS[st]}</span></td>
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
