/**
 * API Client
 * Handles all HTTP communication with the backend.
 */

// API configuration
const API_BASE = location.origin;
const ADMIN_PREFIX = '/admin';

/**
 * Core fetch wrapper with auth, error handling, and token expiry detection.
 * @param {string} method - HTTP method (GET/POST)
 * @param {string} path - API path (appended to ADMIN_PREFIX)
 * @param {object} body - Request body or query params for GET
 * @returns {Promise<object>} Parsed JSON response
 */
async function apiFetch(method, path, body) {
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
    },
  };
  if (token) {
    opts.headers['Authorization'] = 'Bearer ' + token;
  }
  if (body && method !== 'GET') {
    opts.body = JSON.stringify(body);
  }

  let url = API_BASE + ADMIN_PREFIX + path;
  if (method === 'GET' && body) {
    const params = new URLSearchParams();
    Object.entries(body).forEach(([k, v]) => {
      if (v !== '' && v !== null && v !== undefined) params.append(k, v);
    });
    const qs = params.toString();
    if (qs) url += '?' + qs;
  }

  const resp = await fetch(url, method === 'GET' ? { headers: opts.headers } : opts);
  const data = await resp.json();

  // Token expired — force re-login
  if (data.code === 1002 || data.code === 1003) {
    toast('登录已过期，请重新登录', 'error');
    logout();
    return data;
  }

  return data;
}

/** GET shorthand */
function apiGet(path, params) { return apiFetch('GET', path, params); }

/** POST shorthand */
function apiPost(path, body) { return apiFetch('POST', path, body); }
