// Sign in (her and her clients), client sign-up, first-run setup, two-factor and password reset.
// Clients use this page too, so it stays neutral: her love notes live in her dashboard.
import { h, api, clear, field, formData, botTrap } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';
import { setLove } from '../love.js';

const GOOGLE = '<svg width="20" height="20" viewBox="0 0 25 24" aria-hidden="true"><path d="M23 12.23c0-.86-.07-1.49-.23-2.14H12.72v3.9h5.9c-.12.97-.76 2.42-2.19 3.4l-.02.13 3.18 2.42.22.02C21.83 18.12 23 15.43 23 12.23Z" fill="#4285F4"/><path d="M12.71 22.5c2.9 0 5.32-.93 7.1-2.54l-3.38-2.57c-.9.62-2.12 1.05-3.72 1.05a6.45 6.45 0 0 1-6.09-4.36l-.13.01-3.3 2.51-.05.12a10.7 10.7 0 0 0 9.57 5.78Z" fill="#34A853"/><path d="M6.62 14.08A6.63 6.63 0 0 1 6.26 12c0-.72.13-1.42.35-2.08v-.14L3.25 7.24l-.1.05a10.5 10.5 0 0 0 0 9.42l3.47-2.63Z" fill="#FBBC05"/><path d="M12.71 5.56c2.01 0 3.37.85 4.15 1.56l3.02-2.89A10.36 10.36 0 0 0 12.71 1.5 10.7 10.7 0 0 0 3.14 7.29l3.47 2.63a6.48 6.48 0 0 1 6.1-4.36Z" fill="#EB4335"/></svg>';
const LINKEDIN = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>';

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
      label = 'Weak (at least 10 characters, letters and numbers)';
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

/**
 * Sign in for everyone: her (the admin) and her clients. Modes: 'login', 'signup' (client
 * accounts only: the role is decided on the server), 'setup' (first run: the admin), 'forgot'
 * and 'two_factor' (the authenticator code after a correct password).
 */
export function renderAuth(root, initialized, welcome, onDone, onBackToLanding = null) {
  const params = new URLSearchParams(location.search);
  let errorMsg = params.get('login_error') || '';
  // ?verified=1 after the email link, ?signup=1&email=… from her invite, ?two_factor=1 after Google.
  let notice = params.get('verified') ? 'Your email is confirmed. Sign in to open your dashboard.' : '';
  const invitedEmail = params.get('email') || '';
  if (params.toString()) history.replaceState({}, '', '/login');

  // Personalised for Delia
  let creatorName = welcome?.name || 'Delia';
  if (creatorName.toLowerCase() === 'dempsey') {
    creatorName = 'Delia';
  }

  let mode = !initialized ? 'setup' : params.get('two_factor') ? 'two_factor' : params.get('signup') ? 'signup' : 'login';
  let selectedRole = params.get('role') === 'client' ? 'client' : 'member';
  let codeSent = false;
  let resetEmail = '';
  let resetNote = '';

  const container = h('div', { class: 'auth-inner' });

  function go(next, message = '', role = null) {
    mode = next;
    if (role) selectedRole = role;
    codeSent = false;
    notice = message;
    errorMsg = '';
    updateView();
  }

  function updateView() {
    clear(container);
    const error = h('div', { class: 'form-error', role: 'alert', hidden: !errorMsg }, errorMsg);
    const info = notice ? h('div', { class: 'card callout', role: 'status' }, icon('check', 15), ' ', notice) : null;
    const remember = h('input', { type: 'checkbox', name: 'remember', checked: true });

    let titleText = 'Sign in';
    let subText = `Welcome back. Sign in to ${creatorName}’s studio, community hub or client portal.`;
    let submitLabel = 'Sign in';

    if (mode === 'setup') {
      titleText = 'Set up your studio';
      subText = `Welcome, ${creatorName}! Create your administrator account to launch your creator studio.`;
      submitLabel = 'Create my studio';
    } else if (mode === 'signup') {
      if (selectedRole === 'client') {
        titleText = 'Create your client account';
        subText = `Follow your projects with ${creatorName}: proposals, deliverables, invoices and messages in one place.`;
        submitLabel = 'Create Client Account';
      } else {
        titleText = 'Join Delia’s Community';
        subText = `Free account for her 519K+ followers: open-source starter kits, architecture blueprints, AMA question board and partner tool perks.`;
        submitLabel = 'Join Community (Free)';
      }
    } else if (mode === 'forgot') {
      titleText = 'Reset password';
      subText = codeSent ? resetNote : 'Enter your email address and we’ll email you a 6-digit code.';
      submitLabel = codeSent ? 'Change password' : 'Send Reset Code';
    } else if (mode === 'two_factor') {
      titleText = 'Two-factor sign-in';
      subText = 'Enter the 6-digit code from your authenticator app. Lost your phone? Use one of your recovery codes.';
      submitLabel = 'Verify';
    }

    const backLink = ['forgot', 'two_factor', 'signup'].includes(mode) && initialized ? h('button', {
      type: 'button', class: 'auth-back-link',
      onclick: () => go('login'),
    }, icon('back', 16), ' Back to sign in') : null;

    const roleGrid = mode === 'signup' ? h('div', { class: 'auth-role-grid', role: 'radiogroup', 'aria-label': 'Account type' },
      h('button', {
        type: 'button',
        class: `auth-role-card ${selectedRole === 'member' ? 'is-active' : ''}`,
        role: 'radio',
        'aria-checked': String(selectedRole === 'member'),
        onclick: () => { selectedRole = 'member'; updateView(); },
      },
        h('div', { class: 'auth-role-badge' }, icon('sparkle', 12), 'Community (Free)'),
        h('div', { class: 'auth-role-title' }, 'Follower / Engineer'),
        h('div', { class: 'auth-role-desc' }, 'Starter kits, AI templates, AMA question board, and $350 in partner credits.')),
      h('button', {
        type: 'button',
        class: `auth-role-card ${selectedRole === 'client' ? 'is-active' : ''}`,
        role: 'radio',
        'aria-checked': String(selectedRole === 'client'),
        onclick: () => { selectedRole = 'client'; updateView(); },
      },
        h('div', { class: 'auth-role-badge' }, icon('briefcase', 12), 'Commercial Partner'),
        h('div', { class: 'auth-role-title' }, 'Client Account'),
        h('div', { class: 'auth-role-desc' }, 'Proposals, deliverables, engineering retainers, and invoices.'))) : null;

    const formFields = [];
    const newPassword = (label = 'Password') => {
      const pass = passwordField('password', label, 'new-password', { minlength: 10, placeholder: 'At least 10 characters, letters and numbers' });
      formFields.push(pass.container, passwordMeter(pass.input),
        passwordField('password_confirmation', `Confirm ${label.toLowerCase()}`, 'new-password', { minlength: 10, placeholder: 'Once more' }).container);
    };

    if (mode === 'forgot') {
      formFields.push(field('Email address', h('input', { type: 'email', name: 'email', required: true, value: resetEmail, autocomplete: 'email', placeholder: 'your@email.com' })));
      if (codeSent) {
        formFields.push(field('Code from the email', h('input', { type: 'text', name: 'code', required: true, inputmode: 'numeric', autocomplete: 'one-time-code', maxlength: 6, placeholder: '6-digit code' })));
        newPassword('New password');
      }
    } else if (mode === 'two_factor') {
      formFields.push(field('Code', h('input', { type: 'text', name: 'code', required: true, autocomplete: 'one-time-code', maxlength: 20, placeholder: '123 456 or a recovery code' })));
    } else if (mode === 'setup') {
      formFields.push(
        field('Your name', h('input', { type: 'text', name: 'name', value: creatorName, required: true, maxlength: 100, autocomplete: 'name', placeholder: 'Delia Lazarescu' }), 'used on your dashboard and in AI prompts'),
        field('Email address', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email', placeholder: 'your@email.com' })));
      newPassword();
    } else if (mode === 'signup') {
      formFields.push(h('input', { type: 'hidden', name: 'role', value: selectedRole }));
      formFields.push(field('Your name', h('input', { type: 'text', name: 'name', required: true, maxlength: 100, autocomplete: 'name', placeholder: 'Your name or handle' })));
      if (selectedRole === 'client') {
        formFields.push(field('Company', h('input', { type: 'text', name: 'company', maxlength: 150, autocomplete: 'organization', placeholder: 'Acme Corp' }), 'optional'));
      }
      formFields.push(field('Email address', h('input', { type: 'email', name: 'email', required: true, value: invitedEmail, autocomplete: 'email', placeholder: selectedRole === 'client' ? 'you@company.com' : 'you@domain.com' }), selectedRole === 'client' ? 'the one you used on the website, so your requests show up' : 'for AMA replies, kit updates, and partner credit codes'));
      newPassword();
      const termsText = selectedRole === 'client'
        ? ' I agree that my details are stored to manage my projects.'
        : ' I agree that my details are stored to manage my community perks and updates.';
      formFields.push(h('label', { class: 'auth-remember' }, h('input', { type: 'checkbox', name: 'terms', required: true }), termsText), botTrap());
    } else {
      formFields.push(
        field('Email address', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email', placeholder: 'your@email.com' })),
        passwordField('password', 'Password', 'current-password').container,
        h('div', { class: 'auth-row' },
          h('label', { class: 'auth-remember' }, remember, ' Remember me'),
          h('button', { type: 'button', class: 'auth-link', onclick: () => go('forgot') }, 'Forgot password?')));
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
              const res = await api('POST', 'auth/forgot-password', { email: body.email });
              resetEmail = body.email;
              resetNote = res.message;
              codeSent = true;
              updateView();
            } else {
              const res = await api('POST', 'auth/reset-password', body);
              go('login', res.message);
            }
            return;
          }
          if (mode === 'signup') {
            const res = await api('POST', 'auth/register', body);
            go('login', res.message);
            return;
          }
          if (mode === 'two_factor') {
            const res = await api('POST', 'auth/two-factor', { code: body.code });
            onDone(res.user);
            return;
          }
          const res = await api('POST', mode === 'setup' ? 'auth/setup' : 'auth/login', body);
          if (res.two_factor) { go('two_factor'); return; }
          onDone(res.user);
        } catch (err) {
          if (mode === 'two_factor' && err.status === 401) { go('login'); errorMsg = err.message; updateView(); return; }
          error.textContent = err.message;
          error.hidden = false;
          submit.disabled = false;
        }
      },
    }, formFields, error, submit);

    const hasOAuth = initialized && (mode === 'login' || mode === 'signup');
    const socialButtons = hasOAuth ? [
      h('div', { class: 'auth-social-grid' },
        h('a', {
          class: 'auth-social-btn google-btn',
          href: `/auth/google/redirect?remember=${remember?.checked ? 1 : 1}${mode === 'signup' ? `&role=${selectedRole}` : ''}`,
          onclick: (e) => {
            const rem = remember ? (remember.checked ? 1 : 0) : 1;
            e.currentTarget.href = `/auth/google/redirect?remember=${rem}${mode === 'signup' ? `&role=${selectedRole}` : ''}`;
          },
        }, h('span', { class: 'auth-social-ico', html: GOOGLE }), mode === 'signup' ? 'Google' : 'Continue with Google'),
        h('a', {
          class: 'auth-social-btn linkedin-btn',
          href: `/auth/linkedin/redirect?remember=${remember?.checked ? 1 : 1}${mode === 'signup' ? `&role=${selectedRole}` : ''}`,
          onclick: (e) => {
            const rem = remember ? (remember.checked ? 1 : 0) : 1;
            e.currentTarget.href = `/auth/linkedin/redirect?remember=${rem}${mode === 'signup' ? `&role=${selectedRole}` : ''}`;
          },
        }, h('span', { class: 'auth-social-ico linkedin-ico', html: LINKEDIN }), mode === 'signup' ? 'LinkedIn' : 'Continue with LinkedIn'),
      ),
      h('div', { class: 'auth-or' }, h('span'), 'or continue with email', h('span')),
    ] : null;

    // Dev/testing only (DEMO_CLIENT_LOGIN=true): straight into the demo client's dashboard.
    const demo = mode === 'login' && initialized && welcome?.demo_client ? h('div', { class: 'card callout' },
      h('p', { class: 'small' }, h('strong', {}, 'Testing? '), 'Open the client dashboard as a demo client with sample requests.'),
      h('button', { class: 'btn block', type: 'button', onclick: async (e) => {
        e.currentTarget.disabled = true;
        try { onDone((await api('POST', 'auth/demo-client')).user); } catch (err) { errorMsg = err.message; updateView(); }
      } }, icon('user', 16), 'Sign in as demo client')) : null;

    const switchRow = initialized && mode === 'login'
      ? h('div', { class: 'auth-switch-box' },
          h('p', { class: 'auth-foot muted small' }, 'Follower & Engineer? ', h('button', { type: 'button', class: 'auth-link', onclick: () => go('signup', '', 'member') }, 'Join Delia’s Community (Free)')),
          h('p', { class: 'auth-foot muted small' }, 'Working with us on a project? ', h('button', { type: 'button', class: 'auth-link', onclick: () => go('signup', '', 'client') }, 'Create a client account')))
      : initialized && mode === 'signup'
        ? h('p', { class: 'auth-foot muted small' }, 'Already have an account? ', h('button', { type: 'button', class: 'auth-link', onclick: () => go('login') }, 'Sign in'))
        : null;

    container.append(
      backLink,
      h('h1', {}, titleText),
      h('p', { class: 'muted auth-sub' }, subText),
      info,
      roleGrid,
      socialButtons,
      form,
      demo,
      switchRow,
      mode === 'setup' ? renderGuidedInstall() : null);

    const firstInput = form.querySelector('input:not([value]), input[value=""]') || form.querySelector('input');
    if (firstInput) firstInput.focus();
  }

  updateView();
  setLove({ name: creatorName });
  authLayout(root, container, { onBackToLanding });
}

/** A client who hasn't opened the confirmation link yet: resend it, or sign out. */
export function renderVerifyEmail(root, user, onLogout) {
  const status = h('p', { class: 'muted', role: 'status' });
  const resend = h('button', { class: 'btn primary block', type: 'button', onclick: async () => {
    resend.disabled = true;
    try { status.textContent = (await api('POST', 'auth/email/resend')).message; } catch (err) { status.textContent = err.message; }
    setTimeout(() => { resend.disabled = false; }, 30000);
  } }, icon('mail', 16), 'Send the link again');
  authLayout(root, h('div', { class: 'auth-inner' },
    h('h1', {}, 'Confirm your email'),
    h('p', { class: 'muted auth-sub' }, `We sent a link to ${user.email}. Open it to see your requests: it proves the email is yours, so nobody else can see your projects.`),
    resend, status,
    h('p', { class: 'auth-foot muted small' }, 'Wrong email or another account? ', h('button', { type: 'button', class: 'auth-link', onclick: onLogout }, 'Sign out'))));
}

/** Admins turn on two-factor sign-in before anything else (Role::requiresTwoFactor). */
export function renderTwoFactorSetup(root, user, { onDone, onLogout }) {
  const box = h('div', { class: 'auth-inner' },
    h('h1', {}, 'Turn on two-factor sign-in'),
    h('p', { class: 'muted auth-sub' }, 'Your studio holds every key, client and payment, so signing in needs your password and a code from an authenticator app (Google Authenticator, 1Password, Authy…).'));
  const slot = h('div', {}, h('p', { class: 'muted' }, 'Loading…'));
  box.append(slot, h('p', { class: 'auth-foot muted small' }, h('button', { type: 'button', class: 'auth-link', onclick: onLogout }, 'Sign out')));
  authLayout(root, box);
  import('./account.js').then((m) => clear(slot).append(m.twoFactorEnroll({ onEnabled: onDone, doneLabel: 'Open my studio' })));
}

/** The boxed sign-in layout: header, the form side and the photo side. */
function authLayout(root, content, { onBackToLanding = null } = {}) {

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

  // Right side: Full-height vertical Unsplash studio photography
  const authArt = h('aside', { class: 'auth-art', 'aria-hidden': 'true' },
    h('img', {
      class: 'auth-hero-img',
      src: 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1000&h=1400&q=80',
      alt: 'Creator Studio Workspace',
      loading: 'eager',
    }));

  const authBody = h('div', { class: 'auth-body' },
    h('main', { class: 'auth-side' },
      content),
    authArt);

  clear(root).append(h('div', { class: 'auth-page' }, h('div', { class: 'auth-box' }, authHeader, authBody)));
}
