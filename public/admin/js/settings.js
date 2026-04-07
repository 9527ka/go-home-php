/**
 * Settings Page
 * System settings: language management, banner config, etc.
 */

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
