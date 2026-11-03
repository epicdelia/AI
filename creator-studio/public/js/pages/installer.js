// Comprehensive Step-by-Step VPS Installation Wizard
// Launched when visiting /install or ?install=1 on the main URL.
import { h, api, clear, field } from '../core.js';
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
    dbTested: false,
    dbTesting: false,
    dbMessage: '',
    dbError: '',
    // Admin Account
    admin_name: 'Delia Lazarescu',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
    timezone: 'UTC',
    currency: 'EUR',
    // Optional API keys
    gemini_api_key: '',
    openai_api_key: '',
    youtube_api_key: '',
    // Submission
    submitting: false,
    installError: '',
  };

  const container = h('div', { class: 'installer-page' });

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
    } catch (err) {
      state.dbTested = false;
      state.dbError = err.message || 'Connection failed';
    } finally {
      state.dbTesting = false;
      updateView();
    }
  }

  async function submitInstall() {
    state.submitting = true;
    state.installError = '';
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
        openai_api_key: state.openai_api_key || null,
        youtube_api_key: state.youtube_api_key || null,
        timezone: state.timezone,
        currency: state.currency,
      };

      const res = await api('POST', 'install/vps', payload);
      onDone(res.user);
    } catch (err) {
      state.installError = err.message || 'Installation failed';
      state.submitting = false;
      updateView();
    }
  }

  function updateView() {
    clear(container);

    // Header
    const header = h('header', { class: 'installer-header' },
      h('div', { class: 'installer-header-brand' },
        brandMark(28),
        h('div', { class: 'installer-header-text' },
          h('strong', {}, 'Creator Studio VPS Installer'),
          h('span', { class: 'muted small' }, 'Automated Production Setup'))),
      h('div', { class: 'installer-header-actions' },
        themeSwitch()));

    // Progress stepper
    const stepsData = [
      { num: 1, title: 'Pre-flight', desc: 'Environment & extensions' },
      { num: 2, title: 'Database', desc: 'VPS storage & connection' },
      { num: 3, title: 'Admin Account', desc: 'Creator credentials' },
      { num: 4, title: 'Integrations & Deploy', desc: 'API keys & launch' },
    ];

    const stepper = h('div', { class: 'installer-stepper' },
      stepsData.map((s) => {
        const isCurrent = step === s.num;
        const isDone = step > s.num;
        return h('div', { class: `installer-step ${isCurrent ? 'current' : ''} ${isDone ? 'done' : ''}` },
          h('span', { class: 'installer-step-num' }, isDone ? icon('check', 14) : s.num),
          h('div', { class: 'installer-step-info' },
            h('strong', {}, s.title),
            h('small', {}, s.desc)));
      }));

    // Step content
    let stepContent = null;

    if (step === 1) {
      // Step 1: Pre-flight check
      const p = state.preflight;
      const s = p?.server;
      const ext = s?.extensions || {};

      const checkItems = [
        { label: 'PHP Version >= 8.2', ok: s?.php_ok, value: s ? `PHP ${s.php_version}` : '...' },
        { label: 'Writable storage/ Directory', ok: s?.storage_writable, value: s?.storage_writable ? 'Writable' : 'Read-only' },
        { label: 'Writable bootstrap/cache/', ok: s?.cache_writable, value: s?.cache_writable ? 'Writable' : 'Read-only' },
        { label: 'PDO MySQL Extension', ok: ext.pdo_mysql, value: ext.pdo_mysql ? 'Installed' : 'Missing' },
        { label: 'PDO SQLite Extension', ok: ext.pdo_sqlite, value: ext.pdo_sqlite ? 'Installed' : 'Missing' },
        { label: 'OpenSSL & cURL', ok: ext.openssl && ext.curl, value: (ext.openssl && ext.curl) ? 'Enabled' : 'Missing' },
        { label: 'Mbstring & Fileinfo', ok: ext.mbstring && ext.fileinfo, value: (ext.mbstring && ext.fileinfo) ? 'Enabled' : 'Missing' },
      ];

      const checkGrid = h('div', { class: 'installer-checks-grid' },
        checkItems.map((ci) => h('div', { class: `installer-check-card ${ci.ok ? 'ok' : 'warn'}` },
          h('span', { class: 'installer-check-icon' }, icon(ci.ok ? 'check' : 'alert-circle', 16)),
          h('div', { class: 'installer-check-text' },
            h('strong', {}, ci.label),
            h('small', {}, ci.value)))));

      stepContent = h('div', { class: 'installer-body' },
        h('h2', {}, 'Step 1: VPS Environment & Pre-Flight Checks'),
        h('p', { class: 'muted installer-lead' }, 'We verify that your live Linux/Ubuntu/Debian VPS meets all system requirements before running database migrations.'),
        state.preflightLoading ? h('div', { class: 'card callout' }, icon('refresh', 14), ' Checking environment...') : checkGrid,
        h('div', { class: 'installer-actions' },
          h('button', {
            type: 'button', class: 'btn secondary', onclick: checkPreflight,
          }, icon('refresh', 14), ' Re-check Environment'),
          h('button', {
            type: 'button', class: 'btn primary', onclick: () => { step = 2; updateView(); },
          }, 'Continue to Database Setup ', icon('arrow', 14))));
    } else if (step === 2) {
      // Step 2: Database Configuration
      const driverSelect = h('select', { class: 'input', onchange: (e) => { state.driver = e.target.value; state.dbTested = false; updateView(); } },
        h('option', { value: 'mysql', selected: state.driver === 'mysql' }, 'MySQL / MariaDB (Recommended for production VPS)'),
        h('option', { value: 'sqlite', selected: state.driver === 'sqlite' }, 'SQLite (Zero-config local file)'),
        h('option', { value: 'pgsql', selected: state.driver === 'pgsql' }, 'PostgreSQL'));

      const isSqlite = state.driver === 'sqlite';

      const hostInput = h('input', { type: 'text', class: 'input', value: state.host, oninput: (e) => { state.host = e.target.value; state.dbTested = false; } });
      const portInput = h('input', { type: 'number', class: 'input', value: state.port, oninput: (e) => { state.port = e.target.value; state.dbTested = false; } });
      const dbInput = h('input', { type: 'text', class: 'input', value: state.database, oninput: (e) => { state.database = e.target.value; state.dbTested = false; } });
      const userInput = h('input', { type: 'text', class: 'input', value: state.username, oninput: (e) => { state.username = e.target.value; state.dbTested = false; } });
      const passInput = h('input', { type: 'password', class: 'input', value: state.password, oninput: (e) => { state.password = e.target.value; state.dbTested = false; } });
      const appUrlInput = h('input', { type: 'url', class: 'input', value: state.app_url, oninput: (e) => { state.app_url = e.target.value; } });

      stepContent = h('div', { class: 'installer-body' },
        h('h2', {}, 'Step 2: VPS Database & Application URL'),
        h('p', { class: 'muted installer-lead' }, 'Configure your database connection. For Ubuntu/Debian VPS deployments, MySQL or MariaDB provides optimal performance for heavy video scripts and creator analytics.'),
        field('Database Driver', driverSelect),
        isSqlite ? field('SQLite Database File Path', dbInput, 'Relative or absolute path to database.sqlite') : [
          h('div', { class: 'installer-form-row' }, field('Host', hostInput), field('Port', portInput)),
          field('Database Name', dbInput, 'If database does not exist, installer will attempt auto-creation'),
          h('div', { class: 'installer-form-row' }, field('Username', userInput), field('Password', passInput)),
        ],
        field('Application URL (APP_URL)', appUrlInput, 'The public HTTPS domain or IP where your studio is hosted'),
        state.dbMessage ? h('div', { class: 'card callout ok' }, icon('check', 14), ' ', state.dbMessage) : null,
        state.dbError ? h('div', { class: 'form-error' }, state.dbError) : null,
        h('div', { class: 'installer-actions' },
          h('button', { type: 'button', class: 'btn secondary', onclick: () => { step = 1; updateView(); } }, icon('back', 14), ' Back'),
          h('button', {
            type: 'button', class: 'btn secondary', disabled: state.dbTesting, onclick: testDatabase,
          }, icon('check', 14), state.dbTesting ? ' Testing Connection...' : ' Test Connection'),
          h('button', {
            type: 'button', class: 'btn primary', onclick: () => { step = 3; updateView(); },
          }, 'Continue to Admin Account ', icon('arrow', 14))));
    } else if (step === 3) {
      // Step 3: Admin Account
      const nameInput = h('input', { type: 'text', class: 'input', required: true, value: state.admin_name, oninput: (e) => { state.admin_name = e.target.value; } });
      const emailInput = h('input', { type: 'email', class: 'input', required: true, value: state.admin_email, placeholder: 'delia@thetechunicorn.com', oninput: (e) => { state.admin_email = e.target.value; } });
      const passInput = h('input', { type: 'password', class: 'input', required: true, minlength: 8, placeholder: 'At least 8 characters', oninput: (e) => { state.admin_password = e.target.value; } });
      const passConfirm = h('input', { type: 'password', class: 'input', required: true, placeholder: 'Confirm password', oninput: (e) => { state.admin_password_confirmation = e.target.value; } });

      const timezoneSelect = h('select', { class: 'input', onchange: (e) => { state.timezone = e.target.value; } },
        h('option', { value: 'UTC' }, 'UTC (Coordinated Universal Time)'),
        h('option', { value: 'Europe/London' }, 'Europe/London (GMT / BST)'),
        h('option', { value: 'Europe/Berlin' }, 'Europe/Berlin (CET / CEST)'),
        h('option', { value: 'America/New_York' }, 'America/New York (Eastern)'),
        h('option', { value: 'America/Los_Angeles' }, 'America/Los Angeles (Pacific)'));

      const currencySelect = h('select', { class: 'input', onchange: (e) => { state.currency = e.target.value; } },
        h('option', { value: 'EUR' }, 'EUR (€) Euro'),
        h('option', { value: 'USD' }, 'USD ($) US Dollar'),
        h('option', { value: 'GBP' }, 'GBP (£) British Pound'));

      stepContent = h('div', { class: 'installer-body' },
        h('h2', {}, 'Step 3: Creator Identity & Admin Account'),
        h('p', { class: 'muted installer-lead' }, 'Create Delia’s primary administrator account. This account has full authority over YouTube scripts, brand deals, staff assignments, and client portals.'),
        field('Creator Name', nameInput, 'Displayed across studio and AI prompts'),
        field('Administrator Email', emailInput, 'Used for sign in, client communications and system alerts'),
        h('div', { class: 'installer-form-row' },
          field('Master Password', passInput),
          field('Confirm Password', passConfirm)),
        h('div', { class: 'installer-form-row' },
          field('Studio Timezone', timezoneSelect),
          field('Primary Currency', currencySelect)),
        h('div', { class: 'installer-actions' },
          h('button', { type: 'button', class: 'btn secondary', onclick: () => { step = 2; updateView(); } }, icon('back', 14), ' Back'),
          h('button', {
            type: 'button', class: 'btn primary', onclick: () => {
              if (!state.admin_name || !state.admin_email || !state.admin_password) {
                alert('Please enter your name, email, and password.');
                return;
              }
              if (state.admin_password !== state.admin_password_confirmation) {
                alert('Passwords do not match.');
                return;
              }
              step = 4;
              updateView();
            },
          }, 'Continue to Integrations & Launch ', icon('arrow', 14))));
    } else {
      // Step 4: Optional Keys & Final Launch
      const geminiInput = h('input', { type: 'password', class: 'input', placeholder: 'AIzaSy...', value: state.gemini_api_key, oninput: (e) => { state.gemini_api_key = e.target.value; } });
      const openaiInput = h('input', { type: 'password', class: 'input', placeholder: 'sk-proj-...', value: state.openai_api_key, oninput: (e) => { state.openai_api_key = e.target.value; } });
      const youtubeInput = h('input', { type: 'password', class: 'input', placeholder: 'AIzaSy...', value: state.youtube_api_key, oninput: (e) => { state.youtube_api_key = e.target.value; } });

      stepContent = h('div', { class: 'installer-body' },
        h('h2', {}, 'Step 4: AI Keys & Automated Launch'),
        h('p', { class: 'muted installer-lead' }, 'Optional: Add your AI and YouTube API keys now, or configure them later in Studio Settings. Clicking "Launch Creator Studio" will write .env, run migrations, provision your admin account, and sign you in.'),
        field('Google Gemini API Key', geminiInput, 'Powers rapid YouTube script ideation, breakout analysis, and copilot prompts'),
        field('OpenAI API Key', openaiInput, 'Optional fallback for transcription and embeddings'),
        field('YouTube Data API v3 Key', youtubeInput, 'Enables live video metrics and competitor channel breakout auditing'),
        state.installError ? h('div', { class: 'form-error' }, state.installError) : null,
        h('div', { class: 'installer-actions' },
          h('button', { type: 'button', class: 'btn secondary', disabled: state.submitting, onclick: () => { step = 3; updateView(); } }, icon('back', 14), ' Back'),
          h('button', {
            type: 'button', class: 'btn primary lg', disabled: state.submitting, onclick: submitInstall,
          }, icon('sparkle', 16), state.submitting ? ' Provisioning Studio & Migrating DB...' : ' Launch Creator Studio')));
    }

    const box = h('div', { class: 'installer-box' },
      header,
      stepper,
      stepContent);

    container.append(box);
  }

  // Load preflight checks immediately on mount
  checkPreflight();
  clear(root).append(container);
}
