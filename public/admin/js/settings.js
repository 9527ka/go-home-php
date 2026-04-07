/**
 * Settings Page
 * System settings: language management, banner config, etc.
 */

PAGE_TEMPLATES['settings'] = `
<div id="pageSettings" class="page">
  <div class="page-header">
    <h2>⚙️ 系统设置</h2>
    <p>管理公告横幅、关于我们、语言等基础配置</p>
  </div>

  <div class="section-card" style="margin-bottom:20px;">
    <div class="section-title">📢 公告横幅</div>
    <p style="font-size:13px;color:#666;margin-bottom:16px;">控制聊天室顶部的滚动公告显示、内容和跳转链接。</p>
    <div id="bannerLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="bannerConfig" class="hidden">
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">开关状态</label>
        <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
          <span id="bannerStatusTag" class="status-tag active">已开启</span>
          <button class="btn-sm btn-view" id="btnBannerToggle" onclick="toggleBanner()">关闭</button>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">公告内容</label>
        <input type="text" id="bannerTextInput" placeholder="输入滚动公告文字..." style="width:100%;margin-top:6px;">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">跳转链接</label>
        <input type="text" id="bannerLinkInput" placeholder="https://..." style="width:100%;margin-top:6px;">
      </div>
      <button class="btn-filter" onclick="saveBannerConfig()">保存公告配置</button>
    </div>
  </div>

  <div class="section-card" style="margin-bottom:20px;">
    <div class="section-title">📄 关于我们</div>
    <p style="font-size:13px;color:#666;margin-bottom:16px;">配置 App「关于我们」页面显示的内容。</p>
    <div id="aboutLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <div id="aboutConfig" class="hidden">
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">App 版本号</label>
        <input type="text" id="aboutVersion" placeholder="v1.0.0" style="width:100%;margin-top:6px;">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">Telegram 联系方式</label>
        <input type="text" id="aboutTelegram" placeholder="@username" style="width:100%;margin-top:6px;">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">官方网站链接</label>
        <input type="text" id="aboutWebsiteUrl" placeholder="https://..." style="width:100%;margin-top:6px;">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">官方网站显示名</label>
        <input type="text" id="aboutWebsiteName" placeholder="example.com" style="width:100%;margin-top:6px;">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">平台宗旨</label>
        <textarea id="aboutMission" rows="3" placeholder="平台宗旨描述..." style="width:100%;margin-top:6px;"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">安全保障</label>
        <textarea id="aboutSafety" rows="3" placeholder="安全保障描述..." style="width:100%;margin-top:6px;"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">公益免费说明</label>
        <textarea id="aboutFreeService" rows="3" placeholder="公益免费说明..." style="width:100%;margin-top:6px;"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">免责声明</label>
        <textarea id="aboutDisclaimer" rows="3" placeholder="免责声明内容..." style="width:100%;margin-top:6px;"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
        <label style="font-weight:600;">隐私政策</label>
        <textarea id="aboutPrivacy" rows="3" placeholder="隐私政策内容..." style="width:100%;margin-top:6px;"></textarea>
      </div>
      <button class="btn-filter" onclick="saveAboutConfig()">保存关于我们配置</button>
    </div>
  </div>

  <div class="section-card">
    <div class="section-title">🌐 语言管理</div>
    <p style="font-size:13px;color:#666;margin-bottom:12px;">管理系统支持的多语言配置，用户可在前端切换语言。</p>
    <div id="langLoading" class="loading-spinner hidden"><div class="spinner"></div>加载中...</div>
    <table id="langTable">
      <thead><tr><th>ID</th><th>代码</th><th>名称</th><th>默认</th><th>状态</th><th>排序</th><th>操作</th></tr></thead>
      <tbody id="langTbody"></tbody>
    </table>
  </div>
</div>
`;

// ======================== Banner Config ========================

let bannerState = { enabled: false, text: '', link: '' };

async function loadBannerConfig() {
  const loading = document.getElementById('bannerLoading');
  const config = document.getElementById('bannerConfig');
  loading.classList.remove('hidden');
  config.classList.add('hidden');

  const res = await apiGet('/wallet/settings');
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg || '加载公告配置失败', 'error');

  // Extract banner settings from wallet settings list
  const settings = res.data || [];
  const findVal = (key) => {
    const item = settings.find(s => s.key === key);
    return item ? item.value : '';
  };

  bannerState.enabled = findVal('banner_enabled') === '1';
  bannerState.text = findVal('banner_text');
  bannerState.link = findVal('banner_link');

  // Update UI
  document.getElementById('bannerTextInput').value = bannerState.text;
  document.getElementById('bannerLinkInput').value = bannerState.link;
  updateBannerStatusUI();
  config.classList.remove('hidden');
}

function updateBannerStatusUI() {
  const tag = document.getElementById('bannerStatusTag');
  const btn = document.getElementById('btnBannerToggle');
  if (bannerState.enabled) {
    tag.className = 'status-tag active';
    tag.textContent = '已开启';
    btn.textContent = '关闭';
  } else {
    tag.className = 'status-tag rejected';
    tag.textContent = '已关闭';
    btn.textContent = '开启';
  }
}

async function toggleBanner() {
  const newVal = bannerState.enabled ? '0' : '1';
  const res = await apiPost('/wallet/settings/update', { key: 'banner_enabled', value: newVal });
  if (res.code === 0) {
    bannerState.enabled = !bannerState.enabled;
    updateBannerStatusUI();
    toast('公告已' + (bannerState.enabled ? '开启' : '关闭'));
  } else {
    toast(res.msg || '操作失败', 'error');
  }
}

async function saveBannerConfig() {
  const text = document.getElementById('bannerTextInput').value.trim();
  const link = document.getElementById('bannerLinkInput').value.trim();

  if (!text) return toast('公告内容不能为空', 'error');

  // Save text and link sequentially
  const r1 = await apiPost('/wallet/settings/update', { key: 'banner_text', value: text });
  if (r1.code !== 0) return toast(r1.msg || '保存公告内容失败', 'error');

  const r2 = await apiPost('/wallet/settings/update', { key: 'banner_link', value: link });
  if (r2.code !== 0) return toast(r2.msg || '保存跳转链接失败', 'error');

  bannerState.text = text;
  bannerState.link = link;
  toast('公告配置已保存');
}

// ======================== About Config ========================

// Map of about field keys to their DOM input IDs
const ABOUT_FIELDS = [
  { key: 'about_version',      id: 'aboutVersion' },
  { key: 'about_telegram',     id: 'aboutTelegram' },
  { key: 'about_website_url',  id: 'aboutWebsiteUrl' },
  { key: 'about_website_name', id: 'aboutWebsiteName' },
  { key: 'about_mission',      id: 'aboutMission' },
  { key: 'about_safety',       id: 'aboutSafety' },
  { key: 'about_free_service', id: 'aboutFreeService' },
  { key: 'about_disclaimer',   id: 'aboutDisclaimer' },
  { key: 'about_privacy',      id: 'aboutPrivacy' },
];

async function loadAboutConfig() {
  const loading = document.getElementById('aboutLoading');
  const config = document.getElementById('aboutConfig');
  loading.classList.remove('hidden');
  config.classList.add('hidden');

  const res = await apiGet('/wallet/settings');
  loading.classList.add('hidden');
  if (res.code !== 0) return toast(res.msg || '加载关于我们配置失败', 'error');

  const settings = res.data || [];
  const findVal = (key) => {
    const item = settings.find(s => s.key === key);
    return item ? item.value : '';
  };

  // Populate form fields
  for (const f of ABOUT_FIELDS) {
    const el = document.getElementById(f.id);
    if (el) el.value = findVal(f.key);
  }

  config.classList.remove('hidden');
}

async function saveAboutConfig() {
  // Save all about fields sequentially
  for (const f of ABOUT_FIELDS) {
    const el = document.getElementById(f.id);
    if (!el) continue;
    const value = el.value.trim();
    const res = await apiPost('/wallet/settings/update', { key: f.key, value });
    if (res.code !== 0) {
      return toast(res.msg || '保存失败: ' + f.key, 'error');
    }
  }
  toast('关于我们配置已保存');
}

// ======================== Language Management ========================

async function loadLangList() {
  const loading = document.getElementById('langLoading');
  loading.classList.remove('hidden');
  const res = await apiGet('/settings/languages');
  loading.classList.add('hidden');

  if (res.code !== 0) return;
  document.getElementById('langTbody').innerHTML = res.data.map(l => `
    <tr>
      <td>${l.id}</td>
      <td><code style="background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:12px;">${l.code}</code></td>
      <td>${l.name}</td>
      <td>${l.is_default ? '<span style="color:#10b981;font-weight:600;">✅ 默认</span>' : '-'}</td>
      <td><span class="status-tag ${l.status === 1 ? 'active' : 'rejected'}">${l.status === 1 ? '启用' : '禁用'}</span></td>
      <td>${l.sort_order}</td>
      <td>
        <button class="btn-sm btn-view" onclick="updateLangStatus(${l.id}, ${l.status === 1 ? 0 : 1})">${l.status === 1 ? '禁用' : '启用'}</button>
      </td>
    </tr>
  `).join('');
}

async function updateLangStatus(id, status) {
  const res = await apiPost('/settings/language/update', { id, status });
  if (res.code === 0) {
    toast('操作成功');
    loadLangList();
  } else {
    toast(res.msg, 'error');
  }
}
