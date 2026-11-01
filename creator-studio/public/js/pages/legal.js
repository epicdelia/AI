// EU GDPR Privacy Policy, Commercial Terms & Conditions, and Impressum (Legal Notice)
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

export async function renderImpressum(view) {
  view.innerHTML = '<div class="card"><p class="muted">Loading Legal Notice (Impressum)…</p></div>';
  try {
    const data = await api('GET', 'legal/impressum');
    renderLegalDoc(view, data, 'impressum');
  } catch (e) {
    view.innerHTML = `<div class="card"><p class="muted error">Failed to load legal notice: ${e.message}</p></div>`;
  }
}

function renderLegalDoc(view, data, type) {
  view.innerHTML = '';

  const isPrivacy = type === 'privacy';
  const isTerms = type === 'terms';
  const isImpressum = type === 'impressum';

  const title = data.title || (isPrivacy ? 'Privacy Policy' : (isTerms ? 'Terms & Conditions' : 'Legal Notice (Impressum)'));
  
  let subtitle = 'EU Legal Compliance Framework';
  let badgeText = 'EU Compliant';

  if (isPrivacy) {
    subtitle = 'GDPR (Regulation EU 2016/679) & ePrivacy Directive Compliance';
    badgeText = 'GDPR Compliant';
  } else if (isTerms) {
    subtitle = 'B2B Creator Sponsorship Agreement, EU VAT Reverse-Charge & Directive 2011/7/EU';
    badgeText = 'EU Directive 2006/112/EC & 2011/7/EU';
  } else if (isImpressum) {
    subtitle = 'Information pursuant to § 5 DDG (Germany), Directive 2000/31/EC & EU AI Act (Art. 50)';
    badgeText = '§ 5 DDG & EU AI Act (Art. 50)';
  }

  const badge = h('span', { class: 'pill ok' }, badgeText);

  const navLinks = h('div', { class: 'row', style: 'gap: 0.5rem; flex-wrap: wrap;' },
    h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, icon('doc', 15), 'Print / PDF'),
    h('button', { class: 'btn', type: 'button', onclick: () => {
      copyText(data.markdown || '');
      toast('Copied legal markdown to clipboard.');
    } }, icon('copy', 15), 'Copy Text'),
    !isImpressum ? h('a', { class: 'btn', href: '#/impressum' }, icon('shield', 15), 'Legal Notice (Impressum)') : null,
    !isPrivacy ? h('a', { class: 'btn', href: '#/privacy' }, icon('shield', 15), 'Privacy Policy') : null,
    !isTerms ? h('a', { class: 'btn', href: '#/terms' }, icon('file-text', 15), 'Commercial Terms') : null
  );

  const headerCard = h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h1', { style: 'font-size: 1.5rem; margin-bottom: 0.25rem;' }, title),
        h('p', { class: 'muted', style: 'margin: 0;' }, subtitle)
      ),
      badge
    ),
    h('div', { style: 'margin-top: 1rem;' }, navLinks)
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
