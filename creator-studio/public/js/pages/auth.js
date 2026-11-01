// Sign in, Register and First-run Setup with Boxed Layout, Header & In-App Password Reset.
// Personalised for Delia (@Tech.Unicorn) with love from Dempsey.
import { h, api, clear, field, formData } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';
import { noteOfTheDay, setLove } from '../love.js';

const GOOGLE = '<svg width="20" height="20" viewBox="0 0 25 24" aria-hidden="true"><path d="M23 12.23c0-.86-.07-1.49-.23-2.14H12.72v3.9h5.9c-.12.97-.76 2.42-2.19 3.4l-.02.13 3.18 2.42.22.02C21.83 18.12 23 15.43 23 12.23Z" fill="#4285F4"/><path d="M12.71 22.5c2.9 0 5.32-.93 7.1-2.54l-3.38-2.57c-.9.62-2.12 1.05-3.72 1.05a6.45 6.45 0 0 1-6.09-4.36l-.13.01-3.3 2.51-.05.12a10.7 10.7 0 0 0 9.57 5.78Z" fill="#34A853"/><path d="M6.62 14.08A6.63 6.63 0 0 1 6.26 12c0-.72.13-1.42.35-2.08v-.14L3.25 7.24l-.1.05a10.5 10.5 0 0 0 0 9.42l3.47-2.63Z" fill="#FBBC05"/><path d="M12.71 5.56c2.01 0 3.37.85 4.15 1.56l3.02-2.89A10.36 10.36 0 0 0 12.71 1.5 10.7 10.7 0 0 0 3.14 7.29l3.47 2.63a6.48 6.48 0 0 1 6.1-4.36Z" fill="#EB4335"/></svg>';

function passwordField(name, label, autocomplete, extra = {}) {
  const input = h('input', { type: 'password', name, required: true, autocomplete, placeholder: 'Your password', ...extra });
  const eye = h('button', {
    type: 'button', class: 'auth-eye', 'aria-label': 'Show password', 'aria-pressed': 'false',
    onclick: () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      eye.setAttribute('aria-pressed', String(show));
      eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      eye.replaceChildren(icon(show ? 'x' : 'eye', 18));
    },
  }, icon('eye', 18));
  return { container: field(label, h('div', { class: 'auth-pass' }, input, eye)), input };
}

function passwordMeter(input) {
  const fill = h('div', { class: 'auth-strength-fill' });
  const bar = h('div', { class: 'auth-strength-bar' }, fill);
  const text = h('div', { class: 'auth-strength-text' }, '');
  const wrap = h('div', { class: 'auth-strength' }, bar, text);

  input.addEventListener('input', () => {
    const v = input.value;
    if (!v) {
      wrap.className = 'auth-strength';
      text.textContent = '';
      return;
    }
    let score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    let level = 'weak';
    let label = 'Weak';
    if (score >= 4) {
      level = 'strong';
      label = 'Strong';
    } else if (score >= 2) {
      level = 'good';
      label = 'Good';
    } else {
      level = 'weak';
      label = 'Weak (at least 8 chars, mix letters & numbers)';
    }

    wrap.className = `auth-strength auth-strength-${level}`;
    text.textContent = label;
  });

  return wrap;
}

function renderGuidedInstall() {
  let open = false;
  const statusContainer = h('div', { class: 'auth-install-status' });
  const messageArea = h('div', { class: 'form-error', hidden: true });
  const outputArea = h('pre', { class: 'auth-install-output', hidden: true });

  const driverSelect = h('select', { name: 'driver', class: 'input' },
    h('option', { value: 'sqlite' }, 'SQLite (Zero setup, local file)'),
    h('option', { value: 'mysql' }, 'MySQL / MariaDB (Recommended for server)'),
    h('option', { value: 'pgsql' }, 'PostgreSQL'));

  const hostInput = h('input', { type: 'text', name: 'host', value: '127.0.0.1', placeholder: '127.0.0.1' });
  const portInput = h('input', { type: 'number', name: 'port', value: '3306', placeholder: '3306' });
  const dbInput = h('input', { type: 'text', name: 'database', value: 'database/database.sqlite', placeholder: 'database/database.sqlite or db name' });
  const userInput = h('input', { type: 'text', name: 'username', placeholder: 'Database username' });
  const passInput = h('input', { type: 'password', name: 'password', placeholder: 'Database password' });
  const appUrlInput = h('input', { type: 'url', name: 'app_url', value: window.location.origin, placeholder: window.location.origin });

  const hostField = field('Host', hostInput);
  const portField = field('Port', portInput);
  const userField = field('Username', userInput);
  const passField = field('Password', passInput);

  function syncDriverFields() {
    const isSqlite = driverSelect.value === 'sqlite';
    hostField.style.display = isSqlite ? 'none' : 'block';
    portField.style.display = isSqlite ? 'none' : 'block';
    userField.style.display = isSqlite ? 'none' : 'block';
    passField.style.display = isSqlite ? 'none' : 'block';
    if (isSqlite) {
      dbInput.value = 'database/database.sqlite';
      dbInput.placeholder = 'database/database.sqlite';
    } else {
      if (dbInput.value === 'database/database.sqlite') dbInput.value = 'creator_studio';
      portInput.value = driverSelect.value === 'pgsql' ? '5432' : '3306';
    }
  }
  driverSelect.addEventListener('change', syncDriverFields);
  syncDriverFields();

  async function checkServerEnv() {
    statusContainer.replaceChildren(h('span', { class: 'auth-install-status-pill' }, icon('refresh', 14), ' Checking environment...'));
    try {
      const res = await api('GET', 'install/status');
      const s = res.server;
      const db = res.database;
      statusContainer.replaceChildren(
        h('span', { class: `auth-install-status-pill ${s.php_ok ? 'ok' : 'warn'}` }, `PHP ${s.php_version}`),
        h('span', { class: `auth-install-status-pill ${s.storage_writable ? 'ok' : 'warn'}` }, s.storage_writable ? 'Storage ✓' : 'Storage read-only'),
        h('span', { class: `auth-install-status-pill ${s.extensions?.pdo_mysql ? 'ok' : ''}` }, 'MySQL PDO'),
        h('span', { class: `auth-install-status-pill ${s.extensions?.pdo_sqlite ? 'ok' : ''}` }, 'SQLite PDO'),
        h('span', { class: `auth-install-status-pill ${db.connected ? 'ok' : 'warn'}` }, db.connected ? 'DB Connected ✓' : 'DB Not Connected')
      );
    } catch (e) {
      statusContainer.replaceChildren(h('span', { class: 'auth-install-status-pill warn' }, 'Status check unavailable'));
    }
  }

  const testBtn = h('button', {
    type: 'button', class: 'btn secondary small',
    onclick: async () => {
      messageArea.hidden = true;
      testBtn.disabled = true;
      try {
        const payload = {
          driver: driverSelect.value,
          host: hostInput.value,
          port: portInput.value,
          database: dbInput.value,
          username: userInput.value,
          password: passInput.value,
        };
        const res = await api('POST', 'install/test-db', payload);
        messageArea.className = 'form-error';
        messageArea.style.borderColor = 'var(--good)';
        messageArea.style.color = 'var(--good)';
        messageArea.style.background = 'var(--good-soft)';
        messageArea.textContent = res.message || 'Connection successful!';
        messageArea.hidden = false;
      } catch (err) {
        messageArea.className = 'form-error';
        messageArea.style.borderColor = '';
        messageArea.style.color = '';
        messageArea.style.background = '';
        messageArea.textContent = err.message;
        messageArea.hidden = false;
      } finally {
        testBtn.disabled = false;
      }
    },
  }, icon('check', 14), ' Test Connection');

  const saveBtn = h('button', {
    type: 'button', class: 'btn primary small',
    onclick: async () => {
      messageArea.hidden = true;
      outputArea.hidden = true;
      saveBtn.disabled = true;
      try {
        const payload = {
          driver: driverSelect.value,
          host: hostInput.value,
          port: portInput.value,
          database: dbInput.value,
          username: userInput.value,
          password: passInput.value,
          app_url: appUrlInput.value,
        };
        const res = await api('POST', 'install/setup-db', payload);
        messageArea.className = 'form-error';
        messageArea.style.borderColor = 'var(--good)';
        messageArea.style.color = 'var(--good)';
        messageArea.style.background = 'var(--good-soft)';
        messageArea.textContent = res.message;
        messageArea.hidden = false;
        if (res.output) {
          outputArea.textContent = res.output;
          outputArea.hidden = false;
        }
        checkServerEnv();
      } catch (err) {
        messageArea.className = 'form-error';
        messageArea.style.borderColor = '';
        messageArea.style.color = '';
        messageArea.style.background = '';
        messageArea.textContent = err.message;
        messageArea.hidden = false;
      } finally {
        saveBtn.disabled = false;
      }
    },
  }, icon('gear', 14), ' Save & Migrate');

  const body = h('div', { class: 'auth-install-body', hidden: true },
    h('p', { class: 'auth-install-desc' }, 'Optional server & database setup. If you already configured your database in .env or via CLI, you can skip this section and create your administrator account directly.'),
    statusContainer,
    field('Database Driver', driverSelect),
    h('div', { class: 'auth-install-grid' }, hostField, portField),
    field('Database Name / File Path', dbInput),
    h('div', { class: 'auth-install-grid' }, userField, passField),
    field('App URL', appUrlInput),
    h('div', { class: 'auth-install-actions' }, testBtn, saveBtn),
    messageArea,
    outputArea);

  const toggleBtn = h('button', {
    type: 'button', class: 'auth-install-toggle',
    onclick: () => {
      open = !open;
      body.hidden = !open;
      toggleBtn.setAttribute('aria-expanded', String(open));
      if (open && !statusContainer.children.length) {
        checkServerEnv();
      }
    },
  },
    h('span', { class: 'auth-install-title' }, icon('gear', 16), ' Server & Database Setup'),
    h('span', { class: 'auth-install-badge' }, 'Optional'));

  return h('div', { class: 'auth-install-card' }, toggleBtn, body);
}

export function renderAuth(root, initialized, welcome, onDone, onBackToLanding = null) {
  const params = new URLSearchParams(location.search);
  const errorMsg = params.get('login_error') || '';
  if (params.get('login_error')) history.replaceState({}, '', '/');

  // Personalised for Delia
  let creatorName = welcome?.name || 'Delia';
  if (creatorName.toLowerCase() === 'dempsey') {
    creatorName = 'Delia';
  }

  // Sign-ups close once the studio has its account (nothing is scoped per user).
  let mode = initialized ? 'login' : 'setup'; // 'login' | 'setup' | 'forgot'
  let codeSent = false;
  let resetEmail = '';
  let resetNote = '';

  const container = h('div', { class: 'auth-inner' });

  function updateView() {
    clear(container);
    const error = h('div', { class: 'form-error', role: 'alert', hidden: !errorMsg }, errorMsg);
    const remember = h('input', { type: 'checkbox', name: 'remember', checked: true });

    let titleText = 'Sign in';
    let subText = `Welcome back, ${creatorName}. Pick up right where you left off.`;
    let submitLabel = 'Sign in';

    if (mode === 'setup') {
      titleText = 'Set up your studio';
      subText = `Welcome, ${creatorName}! Create your administrator account to launch your creator studio.`;
      submitLabel = 'Create my studio';
    } else if (mode === 'forgot') {
      titleText = 'Reset password';
      subText = codeSent
        ? resetNote
        : 'Enter your email address and we’ll email you a 6-digit code.';
      submitLabel = codeSent ? 'Reset & Sign In' : 'Send Reset Code';
    }

    const backLink = mode === 'forgot' ? h('button', {
      type: 'button', class: 'auth-back-link',
      onclick: () => { mode = 'login'; codeSent = false; updateView(); },
    }, icon('back', 16), ' Back to sign in') : null;

    const formFields = [];

    if (mode === 'forgot') {
      const emailInput = h('input', { type: 'email', name: 'email', required: true, value: resetEmail, placeholder: 'your@email.com' });
      formFields.push(field('Email address', emailInput));

      if (codeSent) {
        const codeInput = h('input', { type: 'text', name: 'code', required: true, inputmode: 'numeric', autocomplete: 'one-time-code', maxlength: 6, placeholder: '6-digit code' });
        formFields.push(field('Verification Code', codeInput));

        const pass = passwordField('password', 'New Password', 'new-password', { minlength: 8, placeholder: 'At least 8 characters' });
        formFields.push(pass.container);
        formFields.push(passwordMeter(pass.input));
        formFields.push(passwordField('password_confirmation', 'Confirm New Password', 'new-password', { minlength: 8, placeholder: 'Once more' }).container);
      }
    } else if (mode === 'setup') {
      formFields.push(
        field('Your name', h('input', { type: 'text', name: 'name', value: mode === 'setup' ? creatorName : '', required: true, maxlength: 100, autocomplete: 'name', placeholder: 'Delia Lazarescu' }), 'used on your dashboard and in AI prompts'),
        field('Email address', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email', placeholder: 'your@email.com' }))
      );
      const pass = passwordField('password', 'Password', 'new-password', { minlength: 8, placeholder: 'At least 8 characters' });
      formFields.push(pass.container);
      formFields.push(passwordMeter(pass.input));
      formFields.push(passwordField('password_confirmation', 'Confirm password', 'new-password', { minlength: 8, placeholder: 'Once more' }).container);
    } else {
      // Login mode
      formFields.push(
        field('Email address', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email', placeholder: 'your@email.com' })),
        passwordField('password', 'Password', 'current-password').container,
        h('div', { class: 'auth-row' },
          h('label', { class: 'auth-remember' }, remember, ' Remember me'),
          h('button', {
            type: 'button', class: 'auth-link',
            onclick: () => { mode = 'forgot'; codeSent = false; updateView(); },
          }, 'Forgot password?'))
      );
    }

    const submit = h('button', { class: 'btn primary block auth-submit', type: 'submit' }, submitLabel);

    const form = h('form', {
      class: 'auth-form', novalidate: false,
      onsubmit: async (e) => {
        e.preventDefault();
        error.hidden = true;
        submit.disabled = true;
        try {
          const body = formData(form);
          if (mode === 'forgot') {
            if (!codeSent) {
              // Request code: it arrives by email, never in this response
              const res = await api('POST', 'auth/forgot-password', { email: body.email });
              resetEmail = body.email;
              resetNote = res.message;
              codeSent = true;
              updateView();
              return;
            } else {
              // Reset password
              const res = await api('POST', 'auth/reset-password', body);
              onDone(res.user);
              return;
            }
          }

          const res = await api('POST', mode === 'setup' ? 'auth/setup' : 'auth/login', body);
          onDone(res.user);
        } catch (err) {
          error.textContent = err.message;
          error.hidden = false;
          submit.disabled = false;
        }
      },
    }, formFields, error, submit);

    const google = (mode === 'login' && initialized && welcome?.google) ? [
      h('a', { class: 'auth-social', href: '/auth/google/redirect', onclick: (e) => { e.currentTarget.href = `/auth/google/redirect?remember=${remember.checked ? 1 : 0}`; } },
        h('span', { class: 'auth-social-ico', html: GOOGLE }), 'Login with Google'),
      h('div', { class: 'auth-or' }, h('span'), 'or', h('span')),
    ] : null;

    const guidedInstall = mode === 'setup' ? renderGuidedInstall() : null;

    container.append(
      backLink,
      h('h1', {}, titleText),
      h('p', { class: 'muted auth-sub' }, subText),
      google,
      form,
      guidedInstall
    );

    const firstInput = form.querySelector('input');
    if (firstInput) firstInput.focus();
  }

  updateView();

  setLove({ name: creatorName });
  const note = welcome?.love !== false ? noteOfTheDay() : null;

  // Header with brand logo and light/dark switch
  const authHeader = h('header', { class: 'auth-header' },
    h('div', { class: 'auth-header-brand' },
      brandMark(28),
      onBackToLanding ? h('button', {
        type: 'button',
        class: 'btn ghost sm',
        style: { marginLeft: '12px' },
        onclick: onBackToLanding,
        title: 'Return to Home',
      }, icon('home', 14), 'Home') : null
    ),
    h('div', { class: 'auth-header-actions' },
      themeSwitch()
    ));

  // Right side: Full-height vertical Unsplash studio photography + anchored note of the day
  const authArt = h('aside', { class: 'auth-art', 'aria-hidden': 'true' },
    h('img', {
      class: 'auth-hero-img',
      src: 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1000&h=1400&q=80',
      alt: 'Creator Studio Workspace',
      loading: 'eager',
    }),
    note ? h('figure', { class: 'auth-note' },
      h('span', { class: 'auth-note-heart' }, icon('mail', 18)),
      h('blockquote', {}, note),
      h('figcaption', {}, 'Daily note from Dempsey')) : null);

  const authBody = h('div', { class: 'auth-body' },
    h('main', { class: 'auth-side' },
      container,
      note ? h('p', { class: 'auth-note-mobile' }, icon('heart', 14), ' ', note) : null,
      h('div', { class: 'auth-foot-wrap' },
        h('span', { class: 'auth-couple-badge' }, icon('heart', 12), ' For Delia with love'),
        h('p', { class: 'auth-foot muted small' }, 'Your studio runs on this server. Made with love for Delia.'))),
    authArt);

  const authBox = h('div', { class: 'auth-box' },
    authHeader,
    authBody);

  const authPage = h('div', { class: 'auth-page' }, authBox);

  clear(root).append(authPage);
}
