// Account & security, for everyone signed in (Role ability `account`): profile, password, email,
// two-factor sign-in (required for admins, optional for clients) and, for clients, deleting
// the account. Anything sensitive asks for the current password again.
import { h, api, busy, toast, pageHead, field, formData, clear, confirmDialog, copyText } from '../core.js';
import { icon } from '../icons.js';

export async function renderAccount(view) {
  let { user } = await api('GET', 'account');
  const twoFactorSlot = h('div');

  const profile = h('form', { class: 'card', onsubmit: (e) => { e.preventDefault(); busy(saveProfile, 'Saving…', async () => {
    user = { ...user, ...(await api('PATCH', 'account', formData(profile))).user };
    toast('Saved.');
  }); } },
  h('h2', {}, 'Profile'),
  field('Name', h('input', { name: 'name', value: user.name, required: true, maxlength: 100, autocomplete: 'name' })),
  field('Company', h('input', { name: 'company', value: user.company || '', maxlength: 150, autocomplete: 'organization' })),
  field('Phone', h('input', { name: 'phone', type: 'tel', value: user.phone || '', maxlength: 40, autocomplete: 'tel' })));
  const saveProfile = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Save');
  profile.append(saveProfile);

  const password = h('form', { class: 'card', onsubmit: (e) => { e.preventDefault(); busy(savePassword, 'Saving…', async () => {
    toast((await api('PUT', 'account/password', formData(password))).message);
    password.reset();
  }); } },
  h('h2', {}, 'Password'),
  field('Current password', h('input', { name: 'current_password', type: 'password', required: true, autocomplete: 'current-password' })),
  field('New password', h('input', { name: 'password', type: 'password', required: true, minlength: 10, autocomplete: 'new-password' }), 'at least 10 characters, letters and numbers'),
  field('Confirm new password', h('input', { name: 'password_confirmation', type: 'password', required: true, minlength: 10, autocomplete: 'new-password' })));
  const savePassword = h('button', { class: 'btn primary', type: 'submit' }, icon('lock', 15), 'Change password');
  password.append(savePassword, h('p', { class: 'muted small' }, 'Changing it signs you out on every other device.'));

  const email = h('form', { class: 'card', onsubmit: (e) => { e.preventDefault(); busy(saveEmail, 'Saving…', async () => {
    const r = await api('PUT', 'account/email', formData(email));
    toast(r.message);
    if (!r.user.email_verified) location.reload(); // a client confirms the new address first
    user = { ...user, ...r.user };
  }); } },
  h('h2', {}, 'Email'),
  field('Email address', h('input', { name: 'email', type: 'email', value: user.email, required: true, autocomplete: 'email' })),
  field('Current password', h('input', { name: 'current_password', type: 'password', required: true, autocomplete: 'current-password' })));
  const saveEmail = h('button', { class: 'btn', type: 'submit' }, icon('mail', 15), 'Change email');
  email.append(saveEmail, user.role === 'client' ? h('p', { class: 'muted small' }, 'You’ll confirm the new address before you see your projects again.') : null);

  function drawTwoFactor() {
    const tf = user.two_factor || {};
    const card = h('div', { class: 'card' }, h('div', { class: 'card-header' },
      h('h2', {}, 'Two-factor sign-in'),
      h('span', { class: `pill ${tf.enabled ? 'ok' : ''}` }, tf.enabled ? 'On' : 'Off')));
    if (!tf.enabled) {
      card.append(h('p', { class: 'muted' }, 'Sign in with your password and a code from an authenticator app, so a leaked password alone can’t open your account.'),
        twoFactorEnroll({ onEnabled: async () => { user = (await api('GET', 'account')).user; drawTwoFactor(); } }));
    } else {
      const pw = h('input', { type: 'password', autocomplete: 'current-password', placeholder: 'Current password' });
      const codesOut = h('div');
      card.append(
        h('p', { class: 'muted' }, `${user.recovery_codes_left ?? 0} recovery code(s) left.`, tf.required ? ' Admins always sign in with two-factor.' : ''),
        field('Current password', pw, 'to make changes'),
        h('div', { class: 'row' },
          h('button', { class: 'btn', type: 'button', onclick: (e) => busy(e.currentTarget, 'Making…', async () => {
            const r = await api('POST', 'account/two-factor/recovery-codes', { current_password: pw.value });
            clear(codesOut).append(recoveryCodes(r.recovery_codes));
            user.recovery_codes_left = r.recovery_codes.length;
          }) }, icon('refresh', 15), 'New recovery codes'),
          h('button', { class: 'btn', type: 'button', onclick: (e) => busy(e.currentTarget, 'Starting…', async () => {
            const r = await api('POST', 'account/two-factor/start', { current_password: pw.value });
            user.two_factor = { ...tf, enabled: false };
            clear(card).append(h('h2', {}, 'Set up your new phone'), twoFactorEnroll({ started: r, onEnabled: async () => { user = (await api('GET', 'account')).user; drawTwoFactor(); } }));
          }) }, icon('phone', 15), 'New phone'),
          tf.required ? null : h('button', { class: 'btn danger', type: 'button', onclick: (e) => busy(e.currentTarget, 'Turning off…', async () => {
            user = { ...user, ...(await api('DELETE', 'account/two-factor', { current_password: pw.value })).user };
            toast('Two-factor sign-in is off.');
            drawTwoFactor();
          }) }, 'Turn off')),
        codesOut);
    }
    clear(twoFactorSlot).append(card);
  }
  drawTwoFactor();

  const danger = user.can_delete ? h('div', { class: 'card' },
    h('h2', {}, 'Delete account'),
    h('p', { class: 'muted' }, 'Removes your login. Records of work already done (proposals, invoices) stay with the studio.'),
    (() => {
      const pw = h('input', { type: 'password', autocomplete: 'current-password', placeholder: 'Current password' });
      return [field('Current password', pw), h('button', { class: 'btn danger', type: 'button', onclick: async (e) => {
        if (!(await confirmDialog('Delete your account? This can’t be undone.'))) return;
        busy(e.currentTarget, 'Deleting…', async () => {
          await api('DELETE', 'account', { current_password: pw.value });
          location.href = '/';
        });
      } }, icon('trash', 15), 'Delete my account')];
    })()) : null;

  view.append(
    pageHead('Account & security', user.role === 'admin' ? 'Your studio login.' : 'Your login for this client dashboard.'),
    h('div', { class: 'grid grid-2' }, h('div', {}, profile, email), h('div', {}, twoFactorSlot, password)),
    danger);
}

/**
 * Turning on two-factor: scan or paste the key into an authenticator app, type its first code,
 * then keep the recovery codes. `started` reuses a secret already made (new phone).
 */
export function twoFactorEnroll({ onEnabled, started = null, doneLabel = 'Done' } = {}) {
  const box = h('div', { class: 'stack' });
  const startBtn = h('button', { class: 'btn primary', type: 'button', onclick: () => busy(startBtn, 'Starting…', async () => show(await api('POST', 'account/two-factor/start'))) }, icon('lock', 15), 'Set up two-factor');

  function show({ secret, uri }) {
    const code = h('input', { type: 'text', inputmode: 'numeric', autocomplete: 'one-time-code', maxlength: 7, placeholder: '123456', required: true });
    const confirm = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Turn it on');
    const form = h('form', { onsubmit: (e) => { e.preventDefault(); busy(confirm, 'Checking…', async () => {
      const r = await api('POST', 'account/two-factor/confirm', { code: code.value });
      clear(box).append(
        h('p', {}, h('strong', {}, 'Two-factor sign-in is on.'), ' Keep these recovery codes somewhere safe (a password manager). Each one works once if you lose your phone.'),
        recoveryCodes(r.recovery_codes),
        h('button', { class: 'btn primary', type: 'button', onclick: () => onEnabled?.() }, doneLabel));
    }); } },
    field('Code from the app', code), confirm);
    clear(box).append(
      h('ol', { class: 'stack' },
        h('li', {}, 'Open your authenticator app and add an account. On this phone? ', h('a', { href: uri }, 'Open it in the app'), '.'),
        h('li', {}, 'Or type this key: ', h('code', { class: 'nowrap' }, secret), ' ',
          h('button', { class: 'btn ghost sm', type: 'button', onclick: () => copyText(secret.replace(/\s/g, ''), 'Key copied.') }, icon('copy', 13), 'Copy')),
        h('li', {}, 'Type the 6-digit code it shows:')),
      form);
    code.focus();
  }

  if (started) show(started); else box.append(startBtn);
  return box;
}

function recoveryCodes(codes) {
  return h('div', { class: 'stack' },
    h('pre', { class: 'card', style: { userSelect: 'all' } }, codes.join('\n')),
    h('button', { class: 'btn sm', type: 'button', onclick: () => copyText(codes.join('\n'), 'Recovery codes copied.') }, icon('copy', 13), 'Copy codes'));
}
