// Sign in (and first-run setup): a split screen, the form on the left and a peek at her studio
// on the right with today's love note. Google sign-in appears once the Google app is set up
// (Connections → YouTube) and only lets her own account in.
import { h, api, clear, field, formData, modal } from '../core.js';
import { icon } from '../icons.js';
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
  return field(label, h('div', { class: 'auth-pass' }, input, eye));
}

function forgotHelp() {
  modal('Forgot your password?', () => h('div', { class: 'auth-forgot' },
    h('p', {}, 'The studio runs on your computer and doesn’t send emails, so the password is reset there:'),
    h('ol', {},
      h('li', {}, 'Open a terminal in the creator-studio folder.'),
      h('li', {}, 'Run ', h('code', {}, 'php artisan studio:password'), ' and type the new password twice.'),
      h('li', {}, 'Sign in here with it.')),
    h('p', { class: 'muted small' }, 'If Google sign-in is set up, “Login with Google” works without the password too.')));
}

export function renderAuth(root, initialized, welcome, onDone) {
  const params = new URLSearchParams(location.search);
  const error = h('div', { class: 'form-error', role: 'alert', hidden: !params.get('login_error') }, params.get('login_error') || '');
  if (params.get('login_error')) history.replaceState({}, '', '/');
  const submit = h('button', { class: 'btn primary block auth-submit', type: 'submit' }, initialized ? 'Sign in' : 'Create my studio');
  const remember = h('input', { type: 'checkbox', name: 'remember', checked: true });
  const name = welcome?.name || '';

  const fields = initialized
    ? [
        field('Email address', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email', placeholder: 'your@email.com' })),
        passwordField('password', 'Password', 'current-password'),
        h('div', { class: 'auth-row' },
          h('label', { class: 'auth-remember' }, remember, ' Remember me'),
          h('button', { type: 'button', class: 'auth-link', onclick: forgotHelp }, 'Forgot password?')),
      ]
    : [
        field('Your name', h('input', { type: 'text', name: 'name', required: true, maxlength: 100, autocomplete: 'name' }), 'shown on your dashboard and used in the AI prompts'),
        field('Email address', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email', placeholder: 'your@email.com' })),
        passwordField('password', 'Password', 'new-password', { minlength: 8, placeholder: 'At least 8 characters' }),
        passwordField('password_confirmation', 'Confirm password', 'new-password', { minlength: 8, placeholder: 'Once more' }),
      ];

  const form = h('form', {
    class: 'auth-form', novalidate: false,
    onsubmit: async (e) => {
      e.preventDefault();
      error.hidden = true;
      submit.disabled = true;
      try {
        const body = formData(form);
        const res = await api('POST', initialized ? 'auth/login' : 'auth/setup', body);
        onDone(res.user);
      } catch (err) {
        error.textContent = err.message;
        error.hidden = false;
        submit.disabled = false;
      }
    },
  }, fields, error, submit);

  const google = initialized && welcome?.google ? [
    h('a', { class: 'auth-social', href: '/auth/google/redirect', onclick: (e) => { e.currentTarget.href = `/auth/google/redirect?remember=${remember.checked ? 1 : 0}`; } },
      h('span', { class: 'auth-social-ico', html: GOOGLE }), 'Login with Google'),
    h('div', { class: 'auth-or' }, h('span'), 'or', h('span')),
  ] : null;

  if (name) setLove({ name }); // so a note with {name} greets her by name
  const note = welcome?.love !== false ? noteOfTheDay() : null;

  clear(root).append(h('div', { class: 'auth-split' },
    h('main', { class: 'auth-side' },
      h('div', { class: 'auth-top' },
        h('div', { class: 'auth-brand' }, h('span', { class: 'logo' }, '🎬'), 'Creator Studio'),
        h('div', { class: 'auth-theme' }, themeSwitch())),
      h('div', { class: 'auth-inner' },
        h('h1', {}, initialized ? 'Sign in' : 'Set up your studio'),
        h('p', { class: 'muted auth-sub' }, initialized
          ? (name ? `Welcome back, ${name} 💕 Pick up right where you left off.` : 'Welcome back. Pick up right where you left off.')
          : 'Ideas, scripts, every social network, agents and a weekly mentor in one place. This creates the only account for this studio.'),
        google, form),
      note ? h('p', { class: 'auth-note-mobile' }, '💌 ', note) : null,
      h('p', { class: 'auth-foot muted small' }, 'Your studio runs on this computer. Your data stays here.')),
    h('aside', { class: 'auth-art', 'aria-hidden': 'true' },
      h('div', { class: 'auth-glow' }),
      h('img', { class: 'auth-mockup', src: '/images/auth-mockup.png', alt: '', loading: 'eager', decoding: 'async' }),
      note ? h('figure', { class: 'auth-note' },
        h('span', { class: 'auth-note-heart' }, '💌'),
        h('blockquote', {}, note),
        h('figcaption', {}, 'Note of the day')) : null)));

  form.querySelector('input').focus();
}
