// EU GDPR Privacy Policy and B2B Commercial Terms & Conditions Pages
import { h, api, pageHead, mdBlock, busy, toast, copyText } from '../core.js';
import { icon } from '../icons.js';

export async function renderPrivacy(view) {
  view.innerHTML = '<div class="card"><p class="muted">Loading Privacy Policy…</p></div>';
  try {
    const data = await api('GET', 'legal/privacy');
    renderLegalDoc(view, data, 'privacy');
  } catch (e) {
    view.innerHTML = `<div class="card"><p class="muted error">Failed to load privacy policy: ${e.message}</p></div>`;
  }
}

export async function renderTerms(view) {
  view.innerHTML = '<div class="card"><p class="muted">Loading Terms & Conditions…</p></div>';
  try {
    const data = await api('GET', 'legal/terms');
    renderLegalDoc(view, data, 'terms');
  } catch (e) {
    view.innerHTML = `<div class="card"><p class="muted error">Failed to load terms: ${e.message}</p></div>`;
  }
}

function renderLegalDoc(view, data, type) {
  view.innerHTML = '';

  const isPrivacy = type === 'privacy';
  const title = data.title || (isPrivacy ? 'Privacy Policy' : 'Terms & Conditions');
  const subtitle = isPrivacy 
    ? 'GDPR (Regulation EU 2016/679) & ePrivacy Directive Compliance'
    : 'B2B Creator Sponsorship Agreement, EU VAT Reverse-Charge & Payment Terms';

  const badge = h('span', { class: 'pill ok' }, isPrivacy ? 'GDPR Compliant' : 'EU Directive 2006/112/EC & 2011/7/EU');

  const actions = h('div', { class: 'row', style: 'gap: 0.5rem; flex-wrap: wrap;' },
    h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, icon('doc', 15), 'Print / PDF'),
    h('button', { class: 'btn', type: 'button', onclick: () => {
      copyText(data.markdown || '');
      toast('Copied legal markdown to clipboard.');
    } }, icon('copy', 15), 'Copy Text'),
    isPrivacy 
      ? h('a', { class: 'btn', href: '#/terms' }, icon('file-text', 15), 'View Commercial Terms')
      : h('a', { class: 'btn', href: '#/privacy' }, icon('shield', 15), 'View Privacy Policy')
  );

  const headerCard = h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h1', { style: 'font-size: 1.5rem; margin-bottom: 0.25rem;' }, title),
        h('p', { class: 'muted', style: 'margin: 0;' }, subtitle)
      ),
      badge
    ),
    h('div', { style: 'margin-top: 1rem;' }, actions)
  );

  const bodyCard = h('div', { class: 'card', style: 'padding: 2.5rem 2rem;' },
    mdBlock(data.markdown || '')
  );

  view.append(
    pageHead(title, subtitle),
    headerCard,
    bodyCard
  );
}

