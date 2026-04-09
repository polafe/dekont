/**
 * API Configuration
 * Ortama gore otomatik ayarlanir
 */
const API_CONFIG = (() => {
  function getBaseUrl() {
    // PHP hostunda veya local sunucuda (XAMPP/WAMP vb.) ayni origin kullanılır
    return '';
  }

  const base = window.__API_BASE_URL__ || getBaseUrl();

  const endpoints = {
    login: '/api/login',
    kurslar: '/api/kurslar',
    mtsk: '/api/mtsk',
    ayarlar: '/api/ayarlar',
    dekont: '/api/dekont',
    dekontlar: '/api/dekontlar',
    kullanicilar: '/api/kullanicilar',
    'kullanicilar/toplu': '/api/kullanicilar/toplu'
  };

  return {
    base,
    endpoints,
    url: (endpoint) => base + endpoints[endpoint]
  };
})();
