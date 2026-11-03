// Comprehensive VPS Installation Wizard with Responsive Boxed Desktop & Edge-to-Edge Mobile Layout
// Launched when visiting /install or ?install=1 on the main URL.
import { h, api, clear, field, toast } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';

export function renderInstaller(root, onDone) {
  let step = 1;
  const totalSteps = 4;

  const state = {
    // Server checks
    preflight: null,
    preflightLoading: false,
    // Database
    driver: 'mysql',
    host: '127.0.0.1',
    port: '3306',
    database: 'creator_studio',
    username: 'root',
    password: '',
    app_url: window.location.origin,
    table_prefix: '',
    charset: 'utf8mb4',
    collation: 'utf8mb4_unicode_ci',
    auto_create_db: true,
    dbTested: false,
    dbTesting: false,
    dbMessage: '',
    dbError: '',
    // Admin Account
    admin_name: 'Delia Lazarescu',
    admin_email: 'delia@thetechunicorn.com',
    admin_password: '',
    admin_password_confirmation: '',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    currency: 'EUR',
    brand_accent: 'rose',
    seed_staff: true,
    // Optional API keys
    gemini_api_key: '',
    claude_api_key: '',
    openai_api_key: '',
    youtube_api_key: '',
    // Nginx snippet
    nginxConfig: null,
    nginxLoading: false,
    showNginxModal: false,
    // Submission & deploy steps
    submitting: false,
    deployStage: '',
    installError: '',
  };

  const container = h('div', { class: 'installer-page-wrap' });

  async function checkPreflight() {
    state.preflightLoading = true;
    updateView();
    try {
      state.preflight = await api('GET', 'install/status');
    } catch (e) {
      state.preflight = null;
    } finally {
      state.preflightLoading = false;
      updateView();
    }
  }

  async function testDatabase() {
    state.dbTesting = true;
    state.dbMessage = '';
    state.dbError = '';
    updateView();
    try {
      const payload = {
        driver: state.driver,
        host: state.host,
        port: state.port,
        database: state.database,
        username: state.username,
        password: state.password,
      };
      const res = await api('POST', 'install/test-db', payload);
      state.dbTested = true;
      state.dbMessage = res.message || 'Database connection successful!';
      toast(state.dbMessage, 'ok');
    } catch (err) {
      state.dbTested = false;
      state.dbError = err.message || 'Connection failed';
      toast(state.dbError, 'err');
    } finally {
      state.dbTesting = false;
      updateView();
    }
  }

  async function loadNginxSnippet() {
    state.nginxLoading = true;
    updateView();
    try {
      const res = await api('GET', `install/nginx?app_url=${encodeURIComponent(state.app_url)}`);
      state.nginxConfig = res;
      state.showNginxModal = true;
    } catch (err) {
      toast(err.message || 'Could not generate Nginx config', 'err');
    } finally {
      state.nginxLoading = false;
      updateView();
    }
  }

  async function submitInstall() {
    state.submitting = true;
    state.installError = '';
    state.deployStage = 'Writing cryptographic .env configuration & generating APP_KEY...';
    updateView();

    try {
      const payload = {
        driver: state.driver,
        host: state.host,
        port: state.port,
        database: state.database,
        username: state.username,
        password: state.password,
        app_url: state.app_url,
        admin_name: state.admin_name,
        admin_email: state.admin_email,
        admin_password: state.admin_password,
        admin_password_confirmation: state.admin_password_confirmation,
        gemini_api_key: state.gemini_api_key || null,
        claude_api_key: state.claude_api_key || null,
        openai_api_key: state.openai_api_key || null,
        youtube_api_key: state.youtube_api_key || null,
        timezone: state.timezone,
        currency: state.currency,
        brand_accent: state.brand_accent,
        seed_staff: state.seed_staff,
      };

      state.deployStage = 'Executing database migrations & provisioning schemas...';
      updateView();

      const res = await api('POST', 'install/vps', payload);

      state.deployStage = 'Provisioning Delia’s administrator account and team roles...';
      updateView();

      toast('Creator Studio successfully provisioned! Launching...', 'ok');
      setTimeout(() => {
        onDone(res.user);
      }, 700);
    } catch (err) {
      state.installError = err.message || 'Installation failed';
      state.submitting = false;
      state.deployStage = '';
      updateView();
    }
  }

  function getPasswordStrength(p) {
    if (!p) return { score: 0, label: 'Empty', class: '' };
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;

    if (score <= 2) return { score: 1, label: 'Weak', class: 'warn' };
    if (score === 3) return { score: 2, label: 'Moderate', class: 'ok' };
    if (score === 4) return { score: 3, label: 'Strong', class: 'ok' };
    return { score: 4, label: 'Unicorn Grade', class: 'accent' };
  }

  function updateView() {
    clear(container);

    const s = state.preflight?.server;
    const ext = s?.extensions || {};

    // ====================================================
    // TOP PROPER HEADER (Responsive)
    // ====================================================
    const topHeader = h('header', { class: 'installer-header-top' },
      h('div', { class: 'installer-header-brand' },
        brandMark(28),
        h('div', { class: 'installer-header-titles' },
          h('strong', {}, 'Tech Unicorn Creator Studio'),
          h('span', { class: 'muted small' }, 'VPS Production Environment Installer'))),
      h('div', { class: 'installer-header-nav' },
        h('span', { class: 'installer-header-badge' },
          h('span', { class: 'installer-host-dot' }),
          s?.php_ok ? ' Live VPS Online' : ' Evaluating Environment'),
        themeSwitch(),
        h('a', {
          href: '/', class: 'btn sm secondary installer-top-return-btn',
          onclick: (e) => { e.preventDefault(); window.location.href = '/'; },
        }, icon('arrow-left', 12), ' Return to Landing')));

    // ====================================================
    // MASTER SIDEBAR: Brand, Stepper, VPS Host Snapshot & Tips
    // ====================================================
    const brandSection = h('div', { class: 'installer-sidebar-brand' },
      h('div', { class: 'installer-brand-badge' },
        brandMark(30),
        h('div', {},
          h('h3', {}, 'Creator Studio'),
          h('span', { class: 'installer-edition-pill' }, 'VPS Edition v2.5'))),
      h('p', { class: 'installer-brand-tagline' }, 'Autonomous Engineering, Production Pipelines & Client Portals'));

    // Host Snapshot widget
    const osLabel = s?.os_family || 'Linux VPS';
    const phpLabel = s ? `PHP ${s.php_version}` : 'PHP 8.2+';
    const memLabel = s?.memory_limit || '256M+';

    const hostWidget = h('div', { class: 'installer-host-widget' },
      h('div', { class: 'installer-host-row' },
        h('span', { class: 'installer-host-dot' }),
        h('strong', {}, 'Live Host Snapshot')),
      h('div', { class: 'installer-host-chips' },
        h('span', { class: 'installer-host-chip' }, icon('terminal', 12), ' ', osLabel),
        h('span', { class: 'installer-host-chip' }, icon('bolt', 12), ' ', phpLabel),
        h('span', { class: 'installer-host-chip' }, icon('shield', 12), ' ', memLabel)));

    // Steps configuration
    const stepsData = [
      { num: 1, title: 'Pre-Flight Diagnostics', desc: 'Environment, PHP & permissions' },
      { num: 2, title: 'Database & Storage', desc: 'MySQL, PostgreSQL or SQLite' },
      { num: 3, title: 'Creator Admin & Staff', desc: 'Identity, team roles & locale' },
      { num: 4, title: 'AI & Production Launch', desc: 'API keys, Nginx reverse proxy' },
    ];

    const stepAdvice = [
      'We automatically verify PHP 8.2+, required extensions, and file write permissions to ensure zero runtime errors during deployment.',
      'For high-traffic production VPS installations, MySQL 8.0+ or MariaDB 10.11 on NVMe storage delivers optimal query throughput for video analytics.',
      'Administrator credentials give Delia master authority over scripts and clients. Optional staff accounts let editors and designers collaborate safely.',
      'Adding your Google Gemini and YouTube API keys unlocks real-time video ideation, script hooks, and breakout competitor monitoring.',
    ];

    const sidebarSteps = h('nav', { class: 'installer-sidebar-stepper' },
      stepsData.map((st) => {
        const isCurrent = step === st.num;
        const isDone = step > st.num;
        return h('button', {
          type: 'button',
          class: `installer-step-item ${isCurrent ? 'is-current' : ''} ${isDone ? 'is-done' : ''}`,
          onclick: () => {
            if (st.num < step || (step === 1 && s?.php_ok)) {
              step = st.num;
              updateView();
            }
          },
        },
          h('span', { class: 'installer-step-icon' }, isDone ? icon('check', 14) : st.num),
          h('div', { class: 'installer-step-meta' },
            h('strong', {}, st.title),
            h('small', {}, st.desc)));
      }));

    const adviceBox = h('div', { class: 'installer-advice-box' },
      h('div', { class: 'installer-advice-head' },
        icon('sparkle', 14),
        h('strong', {}, 'Production Advice')),
      h('p', {}, stepAdvice[step - 1]));

    const sidebarFooter = h('div', { class: 'installer-sidebar-foot' },
      h('div', { class: 'installer-progress-indicator' },
        h('div', { class: 'installer-progress-bar' },
          h('div', { class: 'installer-progress-fill', style: `width: ${(step / totalSteps) * 100}%` })),
        h('span', { class: 'installer-progress-text' }, `Step ${step} of ${totalSteps} (${Math.round((step / totalSteps) * 100)}%)`)));

    const sidebar = h('aside', { class: 'installer-sidebar' },
      brandSection,
      hostWidget,
      sidebarSteps,
      adviceBox,
      sidebarFooter);

    // ====================================================
    // RIGHT MAIN WORKSPACE: Header, Body & Footer Actions
    // ====================================================
    const workHeader = h('header', { class: 'installer-workspace-head' },
      h('div', { class: 'installer-head-breadcrumbs' },
        h('span', { class: 'muted' }, 'Setup / '),
        h('strong', {}, stepsData[step - 1].title)),
      h('div', { class: 'installer-head-badges' },
        s?.php_ok ? h('span', { class: 'badge ok' }, icon('check', 12), ' System Verified') : h('span', { class: 'badge' }, 'Pre-flight Check'),
        h('span', { class: 'badge sm' }, `Step ${step} of ${totalSteps}`)));

    let workContent = null;
    let secondaryAction = null;
    let primaryAction = null;

    // STEP 1: PRE-FLIGHT
    if (step === 1) {
      const checkItems = [
        { label: 'PHP Version >= 8.2', ok: s?.php_ok, value: s ? `PHP ${s.php_version}` : 'Checking...' },
        { label: 'PHP Memory Allocation', ok: s?.memory_limit, value: s?.memory_limit || '256M' },
        { label: 'storage/ Write Permissions', ok: s?.storage_writable, value: s?.storage_writable ? 'Writable (0775)' : 'Read-only' },
        { label: 'bootstrap/cache/ Permissions', ok: s?.cache_writable, value: s?.cache_writable ? 'Writable (0775)' : 'Read-only' },
        { label: 'MySQL PDO Driver', ok: ext.pdo_mysql, value: ext.pdo_mysql ? 'Installed' : 'Missing' },
        { label: 'SQLite PDO Driver', ok: ext.pdo_sqlite, value: ext.pdo_sqlite ? 'Installed' : 'Missing' },
        { label: 'PostgreSQL PDO Driver', ok: ext.pdo_pgsql, value: ext.pdo_pgsql ? 'Installed' : 'Optional' },
        { label: 'OpenSSL & cURL (TLS)', ok: ext.openssl && ext.curl, value: (ext.openssl && ext.curl) ? 'Active' : 'Missing' },
        { label: 'Mbstring & Fileinfo', ok: ext.mbstring && ext.fileinfo, value: (ext.mbstring && ext.fileinfo) ? 'Active' : 'Missing' },
        { label: 'Tokenizer & BCMath', ok: ext.tokenizer && ext.bcmath, value: (ext.tokenizer && ext.bcmath) ? 'Active' : 'Missing' },
        { label: 'XML & JSON Parsers', ok: ext.xml && ext.json, value: (ext.xml && ext.json) ? 'Active' : 'Missing' },
        { label: 'Base Environment (.env)', ok: s?.env_writable, value: s?.env_writable ? 'Writable' : 'Protected' },
      ];

      const allGood = s?.php_ok && s?.storage_writable && s?.cache_writable;

      const banner = h('div', { class: `installer-status-banner ${allGood ? 'ok' : 'warn'}` },
        h('div', { class: 'installer-status-icon' }, icon(allGood ? 'check' : 'alert-circle', 20)),
        h('div', {},
          h('h4', {}, allGood ? 'VPS Environment Fully Verified' : 'Action Needed on VPS Server'),
          h('p', { class: 'muted' }, allGood
            ? 'All core extensions, memory allocations, and storage directory permissions are verified for high-performance execution.'
            : 'Some permissions or extensions need attention before running production migrations.')));

      const checkGrid = h('div', { class: 'installer-checks-grid' },
        checkItems.map((ci) => h('div', { class: `installer-check-card ${ci.ok ? 'is-ok' : 'is-warn'}` },
          h('span', { class: 'installer-check-icon' }, icon(ci.ok ? 'check' : 'alert-circle', 14)),
          h('div', { class: 'installer-check-details' },
            h('span', { class: 'installer-check-label' }, ci.label),
            h('strong', { class: 'installer-check-val' }, ci.value)))));

      // 1-Click Permissions Command Card
      const fixCmd = s?.fix_permissions_cmd || 'sudo chown -R www-data:www-data /var/www/creator-studio && sudo chmod -R 775 storage bootstrap/cache';
      const fixCard = h('div', { class: 'card installer-code-card' },
        h('div', { class: 'installer-code-head' },
          h('span', {}, icon('terminal', 12), ' VPS Permissions Quick-Fix (Ubuntu/Debian)'),
          h('button', {
            type: 'button', class: 'btn sm ghost', onclick: () => {
              navigator.clipboard?.writeText(fixCmd);
              toast('Permissions command copied to clipboard!', 'ok');
            },
          }, icon('copy', 12), ' Copy Command')),
        h('pre', { class: 'installer-code-pre' }, fixCmd),
        h('small', { class: 'muted' }, 'Run this in your VPS terminal if Nginx or PHP-FPM cannot write session files or cache.'));

      workContent = h('div', { class: 'installer-content-body' },
        h('h2', {}, 'System & Environment Diagnostics'),
        h('p', { class: 'muted installer-lead' }, 'Your server environment is evaluated for hardware resources, PHP version, required cryptographic modules, and filesystem write access.'),
        state.preflightLoading ? h('div', { class: 'card callout' }, icon('refresh', 16), ' Evaluating VPS configuration...') : [banner, checkGrid, fixCard]);

      secondaryAction = h('button', {
        type: 'button', class: 'btn secondary', onclick: checkPreflight, disabled: state.preflightLoading,
      }, icon('refresh', 14), state.preflightLoading ? ' Checking...' : ' Re-Check System');

      primaryAction = h('button', {
        type: 'button', class: 'btn primary', onclick: () => { step = 2; updateView(); },
      }, 'Continue to Database Setup ', icon('arrow', 14));
    }

    // STEP 2: DATABASE CONFIGURATION
    else if (step === 2) {
      const isSqlite = state.driver === 'sqlite';
      const isPostgres = state.driver === 'pgsql';

      // Driver picker pills
      const driverPills = h('div', { class: 'installer-driver-pills' },
        [
          { id: 'mysql', label: 'MySQL / MariaDB', sub: 'Standard VPS Production' },
          { id: 'pgsql', label: 'PostgreSQL / PgVector', sub: 'AI Embeddings & Vector' },
          { id: 'sqlite', label: 'SQLite File', sub: 'Zero-config local file' },
        ].map((d) => h('button', {
          type: 'button',
          class: `installer-driver-pill ${state.driver === d.id ? 'is-active' : ''}`,
          onclick: () => {
            state.driver = d.id;
            state.port = d.id === 'pgsql' ? '5432' : (d.id === 'mysql' ? '3306' : '');
            state.dbTested = false;
            updateView();
          },
        },
          h('strong', {}, d.label),
          h('small', {}, d.sub))));

      const hostInput = h('input', { type: 'text', class: 'input', value: state.host, oninput: (e) => { state.host = e.target.value; state.dbTested = false; } });
      const portInput = h('input', { type: 'number', class: 'input', value: state.port, oninput: (e) => { state.port = e.target.value; state.dbTested = false; } });
      const dbInput = h('input', { type: 'text', class: 'input', value: state.database, oninput: (e) => { state.database = e.target.value; state.dbTested = false; } });
      const userInput = h('input', { type: 'text', class: 'input', value: state.username, oninput: (e) => { state.username = e.target.value; state.dbTested = false; } });
      const passInput = h('input', { type: 'password', class: 'input', value: state.password, oninput: (e) => { state.password = e.target.value; state.dbTested = false; } });
      const appUrlInput = h('input', { type: 'url', class: 'input', value: state.app_url, oninput: (e) => { state.app_url = e.target.value; } });

      workContent = h('div', { class: 'installer-content-body' },
        h('h2', {}, 'Database & Storage Engine'),
        h('p', { class: 'muted installer-lead' }, 'Connect your VPS database. For Ubuntu VPS deployments, MySQL 8.0 or MariaDB 10.11 provides maximum reliability for large video libraries and subscriber logs.'),
        field('Database Engine Architecture', driverPills),
        isSqlite
          ? field('SQLite Database File Path', dbInput, 'Relative to project root or absolute path (auto-created if missing)')
          : [
            h('div', { class: 'installer-form-row' },
              field('Database Host', hostInput, 'Usually 127.0.0.1 or localhost'),
              field('Port', portInput, isPostgres ? 'Default 5432' : 'Default 3306')),
            field('Database Name', dbInput, 'The database will be automatically created with utf8mb4 if it does not exist'),
            h('div', { class: 'installer-form-row' },
              field('Database User', userInput),
              field('Database Password', passInput)),
          ],
        field('Public Application URL (APP_URL)', appUrlInput, 'The public HTTPS domain or IP where your Creator Studio is served'),
        state.dbMessage ? h('div', { class: 'card callout ok' }, icon('check', 14), ' ', state.dbMessage) : null,
        state.dbError ? h('div', { class: 'form-error' }, state.dbError) : null);

      secondaryAction = h('button', {
        type: 'button', class: 'btn secondary', disabled: state.dbTesting, onclick: testDatabase,
      }, icon('bolt', 14), state.dbTesting ? ' Testing Connection...' : ' Test DB Connection');

      primaryAction = h('button', {
        type: 'button', class: 'btn primary', onclick: () => { step = 3; updateView(); },
      }, 'Continue to Admin Account ', icon('arrow', 14));
    }

    // STEP 3: CREATOR ADMIN & STAFF ROLES
    else if (step === 3) {
      const nameInput = h('input', { type: 'text', class: 'input', required: true, value: state.admin_name, oninput: (e) => { state.admin_name = e.target.value; } });
      const emailInput = h('input', { type: 'email', class: 'input', required: true, value: state.admin_email, oninput: (e) => { state.admin_email = e.target.value; } });
      const passInput = h('input', {
        type: 'password', class: 'input', required: true, minlength: 8, placeholder: 'At least 8 characters',
        value: state.admin_password,
        oninput: (e) => { state.admin_password = e.target.value; updateView(); },
      });
      const passConfirm = h('input', {
        type: 'password', class: 'input', required: true, placeholder: 'Confirm password',
        value: state.admin_password_confirmation,
        oninput: (e) => { state.admin_password_confirmation = e.target.value; },
      });

      const strength = getPasswordStrength(state.admin_password);

      const timezoneSelect = h('select', { class: 'input', onchange: (e) => { state.timezone = e.target.value; } },
        h('option', { value: 'UTC', selected: state.timezone === 'UTC' }, 'UTC (Universal Coordinated Time)'),
        h('option', { value: 'Europe/London', selected: state.timezone === 'Europe/London' }, 'Europe/London (GMT / BST)'),
        h('option', { value: 'Europe/Berlin', selected: state.timezone === 'Europe/Berlin' }, 'Europe/Berlin (CET / CEST)'),
        h('option', { value: 'Europe/Bucharest', selected: state.timezone === 'Europe/Bucharest' }, 'Europe/Bucharest (EET)'),
        h('option', { value: 'America/New_York', selected: state.timezone === 'America/New_York' }, 'America/New York (Eastern)'),
        h('option', { value: 'America/Los_Angeles', selected: state.timezone === 'America/Los_Angeles' }, 'America/Los Angeles (Pacific)'));

      const currencySelect = h('select', { class: 'input', onchange: (e) => { state.currency = e.target.value; } },
        h('option', { value: 'EUR', selected: state.currency === 'EUR' }, 'EUR (€) Euro'),
        h('option', { value: 'USD', selected: state.currency === 'USD' }, 'USD ($) US Dollar'),
        h('option', { value: 'GBP', selected: state.currency === 'GBP' }, 'GBP (£) British Pound'),
        h('option', { value: 'CAD', selected: state.currency === 'CAD' }, 'CAD ($) Canadian Dollar'));

      // Brand accent color picker
      const accents = [
        { id: 'rose', label: 'Rose Unicorn', color: '#e03272' },
        { id: 'ocean', label: 'Ocean Blue', color: '#0ea5e9' },
        { id: 'amber', label: 'Cyber Amber', color: '#f59e0b' },
        { id: 'emerald', label: 'Emerald Mint', color: '#10b981' },
        { id: 'violet', label: 'Deep Violet', color: '#8b5cf6' },
      ];

      const accentPicker = h('div', { class: 'installer-accent-picker' },
        accents.map((acc) => h('button', {
          type: 'button',
          class: `installer-accent-pill ${state.brand_accent === acc.id ? 'is-selected' : ''}`,
          style: `--accent-sample: ${acc.color}`,
          onclick: () => {
            state.brand_accent = acc.id;
            document.documentElement.setAttribute('data-accent', acc.id);
            updateView();
          },
        },
          h('span', { class: 'installer-accent-swatch' }),
          h('span', {}, acc.label))));

      // Staff provisioning card
      const staffCheck = h('input', {
        type: 'checkbox', checked: state.seed_staff,
        onchange: (e) => { state.seed_staff = e.target.checked; updateView(); },
      });

      const staffBox = h('div', { class: 'card installer-staff-box' },
        h('label', { class: 'installer-staff-label' },
          staffCheck,
          h('div', {},
            h('strong', {}, 'Pre-provision production team staff accounts'),
            h('p', { class: 'muted small' }, 'Automatically seeds 3 staff accounts so your video editors, thumbnail designers, and copywriters can log in immediately.'))),
        state.seed_staff ? h('div', { class: 'installer-staff-list' },
          h('span', { class: 'installer-staff-pill' }, '🎬 Alex (Video Editor)'),
          h('span', { class: 'installer-staff-pill' }, '🎨 Elena (Thumbnail Designer)'),
          h('span', { class: 'installer-staff-pill' }, '✍️ Marcus (Copywriter)')) : null);

      workContent = h('div', { class: 'installer-content-body' },
        h('h2', {}, 'Creator Identity & Team Governance'),
        h('p', { class: 'muted installer-lead' }, 'Set up Delia’s master administrator account. This account has exclusive control over studio settings, financial billing, brand partnerships, and staff task queues.'),
        field('Creator Name', nameInput, 'Public creator brand identity'),
        field('Master Administrator Email', emailInput, 'Used to log in to the studio dashboard and receive contract alerts'),
        h('div', { class: 'installer-form-row' },
          field('Master Password', passInput),
          field('Confirm Password', passConfirm)),
        state.admin_password ? h('div', { class: 'installer-strength-bar-wrap' },
          h('div', { class: 'installer-strength-bar' },
            h('div', { class: `installer-strength-fill ${strength.class}`, style: `width: ${(strength.score / 4) * 100}%` })),
          h('small', { class: `installer-strength-label ${strength.class}` }, `Password Strength: ${strength.label}`)) : null,
        h('div', { class: 'installer-form-row' },
          field('Production Timezone', timezoneSelect),
          field('Primary Studio Currency', currencySelect)),
        field('Studio Theme Accent', accentPicker),
        staffBox);

      secondaryAction = h('button', {
        type: 'button', class: 'btn secondary', onclick: () => {
          state.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
          toast(`Timezone detected: ${state.timezone}`, 'ok');
          updateView();
        },
      }, icon('clock', 14), ' Detect Timezone');

      primaryAction = h('button', {
        type: 'button', class: 'btn primary', onclick: () => {
          if (!state.admin_name || !state.admin_email || !state.admin_password) {
            toast('Please enter your name, email and password.', 'err');
            return;
          }
          if (state.admin_password !== state.admin_password_confirmation) {
            toast('Password confirmation does not match.', 'err');
            return;
          }
          step = 4;
          updateView();
        },
      }, 'Continue to AI Keys & Launch ', icon('arrow', 14));
    }

    // STEP 4: AI KEYS & PRODUCTION DEPLOY
    else {
      const geminiInput = h('input', { type: 'password', class: 'input', placeholder: 'AIzaSy...', value: state.gemini_api_key, oninput: (e) => { state.gemini_api_key = e.target.value; } });
      const claudeInput = h('input', { type: 'password', class: 'input', placeholder: 'sk-ant-...', value: state.claude_api_key, oninput: (e) => { state.claude_api_key = e.target.value; } });
      const openaiInput = h('input', { type: 'password', class: 'input', placeholder: 'sk-proj-...', value: state.openai_api_key, oninput: (e) => { state.openai_api_key = e.target.value; } });
      const youtubeInput = h('input', { type: 'password', class: 'input', placeholder: 'AIzaSy...', value: state.youtube_api_key, oninput: (e) => { state.youtube_api_key = e.target.value; } });

      const deploySummary = h('div', { class: 'card installer-deploy-card' },
        h('div', { class: 'installer-deploy-head' },
          icon('sparkle', 16),
          h('strong', {}, 'Automated Deployment Sequence')),
        h('ul', { class: 'installer-deploy-steps' },
          h('li', {}, icon('check', 12), ' Write cryptographic production .env & generate APP_KEY'),
          h('li', {}, icon('check', 12), ` Connect to ${state.driver.toUpperCase()} database and execute migrations`),
          h('li', {}, icon('check', 12), ` Provision Delia’s Administrator account (${state.admin_email})`),
          state.seed_staff ? h('li', {}, icon('check', 12), ' Provision 3 production staff accounts (Editor, Designer, Copywriter)') : null,
          h('li', {}, icon('check', 12), ' Initialize zero-CLS frontend asset bundles & auto-login session')));

      workContent = h('div', { class: 'installer-content-body' },
        h('h2', {}, 'AI Engine Integrations & 1-Click Launch'),
        h('p', { class: 'muted installer-lead' }, 'Optional: Provide your API keys now for immediate access to AI script generators and YouTube analytics, or add them anytime in Settings.'),
        field('Google Gemini API Key (Recommended)', geminiInput, 'Powers rapid YouTube script ideation, hook formulas, and 1-click video repurposing'),
        field('Anthropic Claude API Key', claudeInput, 'Used for deep architectural research, proposal drafting, and agent swarms'),
        h('div', { class: 'installer-form-row' },
          field('OpenAI API Key', openaiInput, 'Fallback for Whisper transcriptions'),
          field('YouTube Data API v3 Key', youtubeInput, 'Live views, retention analysis, and competitor breakouts')),
        deploySummary,
        state.submitting ? h('div', { class: 'card callout ok installer-deploy-progress' },
          icon('refresh', 16), ' ', state.deployStage) : null,
        state.installError ? h('div', { class: 'form-error' }, state.installError) : null);

      secondaryAction = h('button', {
        type: 'button', class: 'btn secondary', disabled: state.submitting || state.nginxLoading, onclick: loadNginxSnippet,
      }, icon('terminal', 14), state.nginxLoading ? ' Generating...' : ' View Nginx Config');

      primaryAction = h('button', {
        type: 'button', class: 'btn primary lg installer-launch-btn', disabled: state.submitting, onclick: submitInstall,
      }, icon('sparkle', 18), state.submitting ? ' Deploying Studio...' : ' Launch Creator Studio');
    }

    // WORKSPACE FOOTER ACTIONS
    const workFooter = h('footer', { class: 'installer-workspace-foot' },
      h('div', { class: 'installer-foot-left' },
        step > 1 ? h('button', {
          type: 'button', class: 'btn ghost', disabled: state.submitting,
          onclick: () => { step--; updateView(); },
        }, icon('arrow-left', 14), ' Back') : null),
      h('div', { class: 'installer-foot-right' },
        secondaryAction,
        primaryAction));

    const mainArea = h('main', { class: 'installer-main' },
      workHeader,
      workContent,
      workFooter);

    // Boxed card shell (desktop boxed layout, mobile full-width)
    const cardBox = h('div', { class: 'installer-card-box' },
      sidebar,
      mainArea);

    // ====================================================
    // BOTTOM PROPER FOOTER (Responsive)
    // ====================================================
    const bottomFooter = h('footer', { class: 'installer-footer-bottom' },
      h('div', { class: 'installer-footer-left' },
        h('span', {}, '© 2026 Tech Unicorn · Delia Lazarescu. All rights reserved.'),
        h('span', { class: 'auth-couple-badge landing-love-badge' }, icon('heart', 12), ' For Delia with love')),
      h('div', { class: 'installer-footer-right' },
        h('span', { class: 'installer-footer-stat' }, 'PHP 8.2+ • Writable Storage • Nginx / Linux Ready'),
        h('a', { href: '/impressum', class: 'installer-footer-link' }, 'Impressum'),
        h('a', { href: '/terms', class: 'installer-footer-link' }, 'Terms'),
        h('a', { href: '/privacy', class: 'installer-footer-link' }, 'GDPR Privacy')));

    container.append(topHeader, cardBox, bottomFooter);

    // NGINX MODAL IF OPEN
    if (state.showNginxModal && state.nginxConfig) {
      const conf = state.nginxConfig;
      const modal = h('div', { class: 'modal-overlay' },
        h('div', { class: 'modal-card installer-nginx-modal' },
          h('div', { class: 'modal-head' },
            h('h3', {}, icon('terminal', 16), ' Production Nginx VirtualHost Configuration'),
            h('button', { type: 'button', class: 'btn ghost sm', onclick: () => { state.showNginxModal = false; updateView(); } }, icon('x', 14))),
          h('p', { class: 'muted small' }, `Tailored reverse proxy config for ${conf.host} with SSL, gzip/brotli static asset caching, and PHP-FPM socket configuration.`),
          h('pre', { class: 'installer-code-pre' }, conf.config),
          h('div', { class: 'installer-nginx-cmd-box' },
            h('strong', {}, 'Activation command:'),
            h('code', {}, conf.enable_cmd)),
          h('div', { class: 'modal-actions' },
            h('button', {
              type: 'button', class: 'btn secondary', onclick: () => {
                navigator.clipboard?.writeText(conf.config);
                toast('Nginx configuration copied!', 'ok');
              },
            }, icon('copy', 14), ' Copy Configuration'),
            h('button', {
              type: 'button', class: 'btn primary', onclick: () => { state.showNginxModal = false; updateView(); },
            }, 'Done'))));
      container.append(modal);
    }
  }

  // Pre-load preflight check on mount
  checkPreflight();
  clear(root).append(container);
}
