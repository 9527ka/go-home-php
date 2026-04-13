/**
 * Chat Monitoring & Moderation Page
 * Three tabs: private messages / group messages / groups (ban + all-mute)
 */

PAGE_TEMPLATES['chat'] = `
<div id="pageChat" class="page">
  <div class="page-header">
    <h2>💬 聊天监控</h2>
    <p>查看所有私聊 / 群聊消息，封禁群或全员禁言</p>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <button class="btn-filter chat-tab active" data-ctab="private" onclick="switchChatTab('private')" style="background:#667eea;">📨 私聊消息</button>
    <button class="btn-filter chat-tab" data-ctab="group" onclick="switchChatTab('group')" style="background:#6b7280;">👥 群聊消息</button>
    <button class="btn-filter chat-tab" data-ctab="groups" onclick="switchChatTab('groups')" style="background:#6b7280;">🛡️ 群组管控</button>
  </div>

  <!-- 私聊消息 -->
  <div id="chatTabPrivate" class="section-card chat-tab-content">
    <div class="section-title">📨 私聊消息</div>
    <div class="filter-bar">
      <input type="text" id="pmKeyword" placeholder="搜索消息内容...">
      <input type="number" id="pmFromId" placeholder="发送者ID" style="width:110px;">
      <input type="number" id="pmToId" placeholder="接收者ID" style="width:110px;">
      <input type="datetime-local" id="pmStartAt">
      <input type="datetime-local" id="pmEndAt">
      <button class="btn-filter" onclick="loadChatPrivate(1)">筛选</button>
    </div>
    <div id="pmLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="pmEmpty" class="empty-state hidden"><div class="icon">📨</div><p>暂无私聊消息</p></div>
    <table id="pmTable" class="hidden">
      <thead><tr><th>ID</th><th>发送者</th><th>→</th><th>接收者</th><th>类型</th><th>内容</th><th>时间</th></tr></thead>
      <tbody id="pmTbody"></tbody>
    </table>
    <div class="pagination" id="pmPagination"></div>
  </div>

  <!-- 群聊消息 -->
  <div id="chatTabGroup" class="section-card chat-tab-content hidden">
    <div class="section-title">👥 群聊消息</div>
    <div class="filter-bar">
      <input type="text" id="gmKeyword" placeholder="搜索消息内容...">
      <input type="number" id="gmGroupId" placeholder="群ID" style="width:100px;">
      <input type="number" id="gmUserId" placeholder="发送者ID" style="width:110px;">
      <input type="datetime-local" id="gmStartAt">
      <input type="datetime-local" id="gmEndAt">
      <button class="btn-filter" onclick="loadChatGroup(1)">筛选</button>
    </div>
    <div id="gmLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="gmEmpty" class="empty-state hidden"><div class="icon">👥</div><p>暂无群聊消息</p></div>
    <table id="gmTable" class="hidden">
      <thead><tr><th>ID</th><th>群</th><th>发送者</th><th>类型</th><th>内容</th><th>时间</th></tr></thead>
      <tbody id="gmTbody"></tbody>
    </table>
    <div class="pagination" id="gmPagination"></div>
  </div>

  <!-- 群组管控 -->
  <div id="chatTabGroups" class="section-card chat-tab-content hidden">
    <div class="section-title">🛡️ 群组管控</div>
    <div class="filter-bar">
      <input type="text" id="grpKeyword" placeholder="搜索群名...">
      <select id="grpBannedFilter">
        <option value="">全部</option>
        <option value="0">正常</option>
        <option value="1">已封禁</option>
      </select>
      <button class="btn-filter" onclick="loadChatGroups(1)">筛选</button>
    </div>
    <div id="grpLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="grpEmpty" class="empty-state hidden"><div class="icon">👥</div><p>暂无群组</p></div>
    <table id="grpTable" class="hidden">
      <thead><tr><th>ID</th><th>群名</th><th>群主</th><th>成员数</th><th>状态</th><th>封禁</th><th>全员禁言</th><th>创建时间</th><th>操作</th></tr></thead>
      <tbody id="grpTbody"></tbody>
    </table>
    <div class="pagination" id="grpPagination"></div>
  </div>
</div>
`;

// ======================== State ========================
let currentChatTab = 'private';
let pmData  = { list: [], page: 1, total: 0 };
let gmData  = { list: [], page: 1, total: 0 };
let grpData = { list: [], page: 1, total: 0 };

// ======================== Tab Switching ========================

function switchChatTab(tab) {
  currentChatTab = tab;

  document.querySelectorAll('.chat-tab').forEach(el => {
    el.style.background = el.dataset.ctab === tab ? '#667eea' : '#6b7280';
  });

  document.querySelectorAll('.chat-tab-content').forEach(el => el.classList.add('hidden'));

  switch (tab) {
    case 'private':
      document.getElementById('chatTabPrivate').classList.remove('hidden');
      loadChatPrivate(1);
      break;
    case 'group':
      document.getElementById('chatTabGroup').classList.remove('hidden');
      loadChatGroup(1);
      break;
    case 'groups':
      document.getElementById('chatTabGroups').classList.remove('hidden');
      loadChatGroups(1);
      break;
  }
}

// ======================== Helpers ========================

const MSG_TYPE_LABEL = { text: '文字', image: '图片', video: '视频', voice: '语音', red_packet: '红包' };

function renderMsgContent(row) {
  const type = row.msg_type || 'text';
  if (type === 'text') return escHtml(row.content || '');
  if (type === 'image') return `<a href="${escHtml(row.media_url || '')}" target="_blank">[图片]</a>`;
  if (type === 'video') return `<a href="${escHtml(row.media_url || '')}" target="_blank">[视频]</a>`;
  if (type === 'voice') return `<a href="${escHtml(row.media_url || '')}" target="_blank">[语音]</a>`;
  if (type === 'red_packet') return `<span style="color:#d4534b;">[红包]</span> ${escHtml(row.content || '')}`;
  return escHtml(row.content || '');
}

function renderUserCell(u, id) {
  if (!u) return `<span style="color:#999;">#${id}</span>`;
  const avatar = u.avatar ? `<img src="${escHtml(u.avatar)}" style="width:22px;height:22px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px;">` : '';
  return `${avatar}${escHtml(u.nickname || '')} <span style="color:#999;">#${id}</span>`;
}

// ======================== Private Messages ========================

async function loadChatPrivate(page) {
  const loading = document.getElementById('pmLoading');
  const empty = document.getElementById('pmEmpty');
  const table = document.getElementById('pmTable');
  const pagination = document.getElementById('pmPagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const params = {
    page,
    limit: 20,
    keyword: document.getElementById('pmKeyword').value,
    from_id: document.getElementById('pmFromId').value,
    to_id: document.getElementById('pmToId').value,
    start_at: document.getElementById('pmStartAt').value.replace('T', ' '),
    end_at: document.getElementById('pmEndAt').value.replace('T', ' '),
  };

  const res = await apiGet('/chat/private', params);
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg || '加载失败', 'error');
  pmData = res.data;

  if (!pmData.list || pmData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }
  table.classList.remove('hidden');
  document.getElementById('pmTbody').innerHTML = pmData.list.map(r => `
    <tr>
      <td>#${r.id}</td>
      <td>${renderUserCell(r.from_user, r.from_id)}</td>
      <td style="color:#999;">→</td>
      <td>${renderUserCell(r.to_user, r.to_id)}</td>
      <td><span class="status-tag">${MSG_TYPE_LABEL[r.msg_type] || r.msg_type}</span></td>
      <td style="max-width:360px;word-break:break-all;">${renderMsgContent(r)}</td>
      <td>${formatTime(r.created_at)}</td>
    </tr>
  `).join('');
  renderPagination(pagination, pmData.page, Math.ceil(pmData.total / pmData.limit), loadChatPrivate);
}

// ======================== Group Messages ========================

async function loadChatGroup(page) {
  const loading = document.getElementById('gmLoading');
  const empty = document.getElementById('gmEmpty');
  const table = document.getElementById('gmTable');
  const pagination = document.getElementById('gmPagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const params = {
    page,
    limit: 20,
    keyword: document.getElementById('gmKeyword').value,
    group_id: document.getElementById('gmGroupId').value,
    user_id: document.getElementById('gmUserId').value,
    start_at: document.getElementById('gmStartAt').value.replace('T', ' '),
    end_at: document.getElementById('gmEndAt').value.replace('T', ' '),
  };

  const res = await apiGet('/chat/group', params);
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg || '加载失败', 'error');
  gmData = res.data;

  if (!gmData.list || gmData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }
  table.classList.remove('hidden');
  document.getElementById('gmTbody').innerHTML = gmData.list.map(r => `
    <tr>
      <td>#${r.id}</td>
      <td>${escHtml(r.group_name || '')} <span style="color:#999;">#${r.group_id}</span></td>
      <td>${renderUserCell(r.user, r.user_id)}</td>
      <td><span class="status-tag">${MSG_TYPE_LABEL[r.msg_type] || r.msg_type}</span></td>
      <td style="max-width:360px;word-break:break-all;">${renderMsgContent(r)}</td>
      <td>${formatTime(r.created_at)}</td>
    </tr>
  `).join('');
  renderPagination(pagination, gmData.page, Math.ceil(gmData.total / gmData.limit), loadChatGroup);
}

// ======================== Groups (Management) ========================

async function loadChatGroups(page) {
  const loading = document.getElementById('grpLoading');
  const empty = document.getElementById('grpEmpty');
  const table = document.getElementById('grpTable');
  const pagination = document.getElementById('grpPagination');
  loading.classList.remove('hidden'); empty.classList.add('hidden'); table.classList.add('hidden'); pagination.innerHTML = '';

  const params = {
    page,
    limit: 20,
    keyword: document.getElementById('grpKeyword').value,
    banned: document.getElementById('grpBannedFilter').value,
  };

  const res = await apiGet('/chat/groups', params);
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg || '加载失败', 'error');
  grpData = res.data;

  if (!grpData.list || grpData.list.length === 0) {
    empty.classList.remove('hidden');
    return;
  }
  table.classList.remove('hidden');
  document.getElementById('grpTbody').innerHTML = grpData.list.map(g => {
    const statusLabel = g.status === 1 ? '<span class="status-tag active">活跃</span>' : '<span class="status-tag closed">已解散</span>';
    const bannedLabel = g.banned == 1
      ? '<span class="status-tag rejected">已封禁</span>'
      : '<span class="status-tag active">正常</span>';
    const allMutedLabel = g.all_muted == 1
      ? '<span class="status-tag pending">全员禁言</span>'
      : '<span class="status-tag active">正常</span>';

    const banBtn = g.banned == 1
      ? `<button class="btn-sm btn-view" style="background:#10b981;" onclick="toggleGroupBan(${g.id}, 0)">解禁</button>`
      : `<button class="btn-sm btn-view" style="background:#ef4444;" onclick="toggleGroupBan(${g.id}, 1)">封禁</button>`;
    const muteBtn = g.all_muted == 1
      ? `<button class="btn-sm btn-view" style="background:#10b981;margin-left:4px;" onclick="toggleGroupMute(${g.id}, 0)">解除全员禁言</button>`
      : `<button class="btn-sm btn-view" style="background:#f59e0b;margin-left:4px;" onclick="toggleGroupMute(${g.id}, 1)">全员禁言</button>`;

    return `<tr>
      <td>#${g.id}</td>
      <td>${escHtml(g.name || '')}</td>
      <td>#${g.owner_id}</td>
      <td>${g.member_count}</td>
      <td>${statusLabel}</td>
      <td>${bannedLabel}</td>
      <td>${allMutedLabel}</td>
      <td>${formatTime(g.created_at)}</td>
      <td style="white-space:nowrap;">${banBtn}${muteBtn}</td>
    </tr>`;
  }).join('');
  renderPagination(pagination, grpData.page, Math.ceil(grpData.total / grpData.limit), loadChatGroups);
}

async function toggleGroupBan(groupId, banned) {
  const label = banned === 1 ? '封禁' : '解禁';
  if (!confirm(`确定要${label}该群吗？`)) return;
  const res = await apiPost('/chat/group/ban', { group_id: groupId, banned });
  if (res.code === 0) {
    toast(res.msg || '操作成功');
    loadChatGroups(grpData.page);
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}

async function toggleGroupMute(groupId, allMuted) {
  const label = allMuted === 1 ? '开启全员禁言' : '关闭全员禁言';
  if (!confirm(`确定要${label}吗？`)) return;
  const res = await apiPost('/chat/group/mute', { group_id: groupId, all_muted: allMuted });
  if (res.code === 0) {
    toast(res.msg || '操作成功');
    loadChatGroups(grpData.page);
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}
