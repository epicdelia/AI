/**
 * Multilingual Translation & 24 EU Languages Engine for Delia's Creator Studio & Landing Page.
 * Powered by Google Translate & mcamara/laravel-localization.
 */

export const EU_LANGUAGES = [
  { code: 'en', name: 'English', native: 'English', flag: '🇬🇧', flagCode: 'gb' },
  { code: 'ro', name: 'Romanian', native: 'Română', flag: '🇷🇴', flagCode: 'ro' },
  { code: 'de', name: 'German', native: 'Deutsch', flag: '🇩🇪', flagCode: 'de' },
  { code: 'fr', name: 'French', native: 'Français', flag: '🇫🇷', flagCode: 'fr' },
  { code: 'es', name: 'Spanish', native: 'Español', flag: '🇪🇸', flagCode: 'es' },
  { code: 'it', name: 'Italian', native: 'Italiano', flag: '🇮🇹', flagCode: 'it' },
  { code: 'nl', name: 'Dutch', native: 'Nederlands', flag: '🇳🇱', flagCode: 'nl' },
  { code: 'pl', name: 'Polish', native: 'Polski', flag: '🇵🇱', flagCode: 'pl' },
  { code: 'pt', name: 'Portuguese', native: 'Português', flag: '🇵🇹', flagCode: 'pt' },
  { code: 'sv', name: 'Swedish', native: 'Svenska', flag: '🇸🇪', flagCode: 'se' },
  { code: 'da', name: 'Danish', native: 'Dansk', flag: '🇩🇰', flagCode: 'dk' },
  { code: 'fi', name: 'Finnish', native: 'Suomi', flag: '🇫🇮', flagCode: 'fi' },
  { code: 'el', name: 'Greek', native: 'Ελληνικά', flag: '🇬🇷', flagCode: 'gr' },
  { code: 'cs', name: 'Czech', native: 'Čeština', flag: '🇨🇿', flagCode: 'cz' },
  { code: 'hu', name: 'Hungarian', native: 'Magyar', flag: '🇭🇺', flagCode: 'hu' },
  { code: 'bg', name: 'Bulgarian', native: 'Български', flag: '🇧🇬', flagCode: 'bg' },
  { code: 'sk', name: 'Slovak', native: 'Slovenčina', flag: '🇸🇰', flagCode: 'sk' },
  { code: 'hr', name: 'Croatian', native: 'Hrvatski', flag: '🇭🇷', flagCode: 'hr' },
  { code: 'lt', name: 'Lithuanian', native: 'Lietuvių', flag: '🇱🇹', flagCode: 'lt' },
  { code: 'sl', name: 'Slovenian', native: 'Slovenščina', flag: '🇸🇮', flagCode: 'si' },
  { code: 'et', name: 'Estonian', native: 'Eesti', flag: '🇪🇪', flagCode: 'ee' },
  { code: 'lv', name: 'Latvian', native: 'Latviešu', flag: '🇱🇻', flagCode: 'lv' },
  { code: 'ga', name: 'Irish', native: 'Gaeilge', flag: '🇮🇪', flagCode: 'ie' },
  { code: 'mt', name: 'Maltese', native: 'Malti', flag: '🇲🇹', flagCode: 'mt' },
];

let gtInitialized = false;

/**
 * Gets the current active language code.
 */
export function getCurrentLanguage() {
  try {
    const saved = localStorage.getItem('site_locale');
    if (saved && EU_LANGUAGES.some(l => l.code === saved)) return saved;

    // Check googtrans cookie: /en/ro -> ro
    const m = document.cookie.match(/(?:^|;\s*)googtrans=\/en\/([a-z]{2})/);
    if (m && m[1] && EU_LANGUAGES.some(l => l.code === m[1])) return m[1];
  } catch (e) {}
  return 'en';
}

/**
 * Injects Google Translate scripts and hides the default intrusive UI banner.
 */
export function initGoogleTranslate() {
  if (gtInitialized || typeof window === 'undefined') return;
  gtInitialized = true;

  // Add hidden container
  if (!document.getElementById('google_translate_element')) {
    const el = document.createElement('div');
    el.id = 'google_translate_element';
    el.style.display = 'none';
    document.body.appendChild(el);
  }

  // Inject CSS to clean up Google banner & frame
  const style = document.createElement('style');
  style.id = 'google-translate-cleanup-style';
  style.textContent = `
    .goog-te-banner-frame.skiptranslate,
    .goog-te-banner-frame,
    body > .skiptranslate,
    #goog-gt-tt,
    .goog-tooltip,
    .goog-tooltip:hover { display: none !important; visibility: hidden !important; }
    body { top: 0px !important; position: static !important; }
    .goog-text-highlight { background: transparent !important; box-shadow: none !important; }
    font { background: transparent !important; }
  `;
  document.head.appendChild(style);

  // Global callback for Google Translate
  window.googleTranslateElementInit = function () {
    new window.google.translate.TranslateElement(
      {
        pageLanguage: 'en',
        includedLanguages: EU_LANGUAGES.map(l => l.code).join(','),
        autoDisplay: false,
      },
      'google_translate_element'
    );
  };

  // Load Google script
  const script = document.createElement('script');
  script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
  script.async = true;
  document.body.appendChild(script);
}

/**
 * Triggers instant in-place translation to the target language.
 */
export function setLanguage(code) {
  const lang = EU_LANGUAGES.find(l => l.code === code) || EU_LANGUAGES[0];

  try {
    localStorage.setItem('site_locale', lang.code);
  } catch (e) {}

  // Set cookies for Google Translate (both host and root paths)
  const host = window.location.hostname;
  const cookieVal = `/en/${lang.code}`;
  document.cookie = `googtrans=${cookieVal}; path=/;`;
  document.cookie = `googtrans=${cookieVal}; path=/; domain=.${host};`;
  document.cookie = `googtrans=${cookieVal}; path=/; domain=${host};`;

  // Sync with Laravel session / backend
  try {
    fetch(`/api/locale/${lang.code}`, { method: 'POST', credentials: 'same-origin' }).catch(() => {});
  } catch (e) {}

  // Trigger Google Translate select element if loaded
  const select = document.querySelector('.goog-te-combo');
  if (select) {
    select.value = lang.code;
    select.dispatchEvent(new Event('change'));
  } else {
    // If not loaded yet, reload to let the cookie apply
    window.location.reload();
  }

  // Dispatch custom event for UI updates
  window.dispatchEvent(new CustomEvent('locale:changed', { detail: lang }));
}

/**
 * Creates a reactive Language Picker component.
 * @param {Object} options
 * @param {'header'|'dashboard'|'footer'} [options.variant='header']
 */
export function createLanguagePicker(options = {}) {
  initGoogleTranslate();
  const variant = options.variant || 'header';
  const container = document.createElement('div');
  container.className = `lang-picker-container lang-picker-${variant}`;

  let isOpen = false;
  let current = getCurrentLanguage();

  const getLangObj = (c) => EU_LANGUAGES.find(l => l.code === c) || EU_LANGUAGES[0];

  // Button
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = `lang-picker-btn lang-btn-${variant}`;
  btn.setAttribute('aria-haspopup', 'listbox');
  btn.setAttribute('aria-expanded', 'false');
  btn.setAttribute('title', 'Change language (24 EU languages)');

  const updateBtnContent = () => {
    const l = getLangObj(current);
    if (variant === 'footer') {
      btn.innerHTML = `
        <span class="lang-flag-wrap" aria-hidden="true">
          <img src="https://flagcdn.com/w40/${l.flagCode || l.code}.png" alt="" class="lang-flag-img" width="18" height="13" onerror="this.style.display='none';this.nextElementSibling.style.display='inline'" />
          <span class="lang-flag-fallback" style="display:none">${l.flag}</span>
        </span>
        <span class="lang-label">${l.native} (${l.code.toUpperCase()})</span>
        <svg class="lang-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
      `;
    } else {
      // Modern Icon Button for header & dashboard
      btn.innerHTML = `
        <svg class="lang-globe-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="2" y1="12" x2="22" y2="12"></line>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
        </svg>
        <span class="lang-icon-badge" title="${l.name}">
          <img src="https://flagcdn.com/w40/${l.flagCode || l.code}.png" alt="${l.code.toUpperCase()}" class="lang-flag-micro" width="13" height="9" onerror="this.style.display='none'" />
        </span>
      `;
    }
  };
  updateBtnContent();

  // Dropdown Menu
  const menu = document.createElement('div');
  menu.className = `lang-picker-dropdown lang-dropdown-${variant}`;
  menu.setAttribute('role', 'listbox');
  menu.style.display = 'none';

  // Header inside menu
  const menuHead = document.createElement('div');
  menuHead.className = 'lang-dropdown-head';
  menuHead.innerHTML = `
    <strong>European Union Languages (24)</strong>
    <span class="lang-dropdown-sub">Translate website instantly with 1-click</span>
  `;
  menu.appendChild(menuHead);

  // Search input
  const searchWrap = document.createElement('div');
  searchWrap.className = 'lang-search-wrap';
  const searchInput = document.createElement('input');
  searchInput.type = 'text';
  searchInput.className = 'lang-search-input';
  searchInput.placeholder = 'Search EU language...';
  searchWrap.appendChild(searchInput);
  menu.appendChild(searchWrap);

  // List of items
  const list = document.createElement('div');
  list.className = 'lang-options-list';

  const renderOptions = (filter = '') => {
    list.innerHTML = '';
    const q = filter.toLowerCase().trim();
    const filtered = EU_LANGUAGES.filter(
      l => l.name.toLowerCase().includes(q) || l.native.toLowerCase().includes(q) || l.code.toLowerCase().includes(q)
    );

    if (filtered.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'lang-empty';
      empty.textContent = 'No language found';
      list.appendChild(empty);
      return;
    }

    filtered.forEach(lang => {
      const opt = document.createElement('button');
      opt.type = 'button';
      opt.className = `lang-option-item ${lang.code === current ? 'is-active' : ''}`;
      opt.setAttribute('role', 'option');
      opt.setAttribute('aria-selected', String(lang.code === current));
      opt.innerHTML = `
        <span class="lang-opt-flag-wrap">
          <img src="https://flagcdn.com/w40/${lang.flagCode || lang.code}.png" alt="${lang.name}" class="lang-flag-img" width="22" height="16" onerror="this.style.display='none';this.nextElementSibling.style.display='inline'" />
          <span class="lang-flag-fallback" style="display:none">${lang.flag}</span>
        </span>
        <div class="lang-opt-info">
          <span class="lang-opt-native">${lang.native}</span>
          <span class="lang-opt-name">${lang.name}</span>
        </div>
        ${lang.code === current ? '<span class="lang-opt-check">✓</span>' : ''}
      `;
      opt.onclick = (e) => {
        e.stopPropagation();
        current = lang.code;
        updateBtnContent();
        closeMenu();
        setLanguage(lang.code);
      };
      list.appendChild(opt);
    });
  };
  renderOptions();

  searchInput.addEventListener('input', (e) => renderOptions(e.target.value));
  menu.appendChild(list);

  const openMenu = () => {
    isOpen = true;
    menu.style.display = 'block';
    btn.setAttribute('aria-expanded', 'true');
    searchInput.value = '';
    renderOptions();
    setTimeout(() => searchInput.focus(), 50);
  };

  const closeMenu = () => {
    isOpen = false;
    menu.style.display = 'none';
    btn.setAttribute('aria-expanded', 'false');
  };

  btn.onclick = (e) => {
    e.stopPropagation();
    if (isOpen) closeMenu();
    else openMenu();
  };

  document.addEventListener('click', (e) => {
    if (!container.contains(e.target)) closeMenu();
  });

  window.addEventListener('locale:changed', (e) => {
    if (e.detail && e.detail.code) {
      current = e.detail.code;
      updateBtnContent();
      renderOptions();
    }
  });

  container.appendChild(btn);
  container.appendChild(menu);

  return container;
}
