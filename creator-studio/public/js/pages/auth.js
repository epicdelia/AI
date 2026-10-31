// First-run setup (create the one account) and login.
import { h, api, clear, field, formData } from '../core.js';

export function renderAuth(root, initialized, onDone) {
  const error = h('div', { class: 'form-error', role: 'alert', hidden: true });
  const submit = h('button', { class: 'btn primary block', type: 'submit' }, initialized ? 'Log in' : 'Create my studio');

  const fields = initialized
    ? [
        field('Email', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email' })),
        field('Password', h('input', { type: 'password', name: 'password', required: true, autocomplete: 'current-password' })),
      ]
    : [
        field('Your name', h('input', { type: 'text', name: 'name', required: true, maxlength: 100, autocomplete: 'name' }), 'shown on your dashboard and used in the AI prompts'),
        field('Email', h('input', { type: 'email', name: 'email', required: true, autocomplete: 'email' })),
        field('Password', h('input', { type: 'password', name: 'password', required: true, minlength: 8, autocomplete: 'new-password' }), 'at least 8 characters'),
        field('Confirm password', h('input', { type: 'password', name: 'password_confirmation', required: true, minlength: 8, autocomplete: 'new-password' })),
      ];

  const form = h('form', {
    onsubmit: async (e) => {
      e.preventDefault();
      error.hidden = true;
      submit.disabled = true;
      try {
        const res = await api('POST', initialized ? 'auth/login' : 'auth/setup', formData(form));
        onDone(res.user);
      } catch (err) {
        error.textContent = err.message;
        error.hidden = false;
        submit.disabled = false;
      }
    },
  }, fields, error, submit);

  clear(root).append(h('div', { class: 'auth-wrapper' },
    h('div', { class: 'auth-card' },
      h('div', { class: 'brand', style: { padding: '0 0 18px' } }, h('div', { class: 'logo' }, '🎬'), h('span', {}, 'Creator Studio')),
      h('h1', {}, initialized ? 'Welcome back' : 'Set up your studio'),
      h('p', { class: 'muted' }, initialized
        ? 'Log in to pick up where you left off.'
        : 'Breakout research, ideas, scripts, dictation and a weekly mentor in one place. This creates the only account for this studio.'),
      form)));

  form.querySelector('input').focus();
}
