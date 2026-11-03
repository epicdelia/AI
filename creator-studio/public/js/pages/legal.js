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

export async function renderDpa(view) {
  view.innerHTML = '<div class="card"><p class="muted">Loading Data Processing Agreement…</p></div>';
  try {
    const data = await api('GET', 'legal/dpa');
    renderLegalDoc(view, data, 'dpa');
  } catch (e) {
    view.innerHTML = `<div class="card"><p class="muted error">Failed to load DPA: ${e.message}</p></div>`;
  }
}

export async function renderSubprocessors(view) {
  view.innerHTML = '<div class="card"><p class="muted">Loading Sub-Processors Directory…</p></div>';
  try {
    const data = await api('GET', 'privacy/subprocessors');
    renderLegalDoc(view, data, 'subprocessors');
  } catch (e) {
    view.innerHTML = `<div class="card"><p class="muted error">Failed to load sub-processors: ${e.message}</p></div>`;
  }
}

function renderLegalDoc(view, data, type) {
  view.innerHTML = '';

  const isPrivacy = type === 'privacy';
  const isTerms = type === 'terms';
  const isImpressum = type === 'impressum';
  const isDpa = type === 'dpa';
  const isSubprocessors = type === 'subprocessors';

  const title = data.title || (isPrivacy ? 'Privacy Policy' : (isTerms ? 'Terms & Conditions' : (isImpressum ? 'Legal Notice (Impressum)' : (isDpa ? 'Data Processing Agreement' : 'Authorized Sub-Processors'))));
  
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
  } else if (isDpa) {
    subtitle = 'GDPR Article 28 & European Commission Standard Contractual Clauses (Decision 2021/914/EU)';
    badgeText = 'GDPR Art. 28 & EU SCCs';
  } else if (isSubprocessors) {
    subtitle = 'Transparency Register of Authorized Sub-Processors & 30-Day Advance Notification Feed';
    badgeText = 'GDPR Art. 28(4)';
  }

  const badge = h('span', { class: 'pill ok' }, badgeText);

  const navLinks = h('div', { class: 'row', style: 'gap: 0.5rem; flex-wrap: wrap;' },
    h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, icon('doc', 15), 'Print / PDF'),
    h('button', { class: 'btn', type: 'button', onclick: () => {
      copyText(data.markdown || '');
      toast('Copied legal markdown to clipboard.');
    } }, icon('copy', 15), 'Copy Text'),
    !isImpressum ? h('a', { class: 'btn', href: '#/impressum' }, icon('shield', 15), 'Legal Notice') : null,
    !isPrivacy ? h('a', { class: 'btn', href: '#/privacy' }, icon('shield', 15), 'Privacy Policy') : null,
    !isTerms ? h('a', { class: 'btn', href: '#/terms' }, icon('file-text', 15), 'Terms & Conditions') : null,
    !isDpa ? h('a', { class: 'btn', href: '#/dpa' }, icon('file', 15), 'DPA (GDPR Art. 28)') : null,
    !isSubprocessors ? h('a', { class: 'btn', href: '#/subprocessors' }, icon('users', 15), 'Sub-Processors') : null
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

  let extraCard = null;
  if (isSubprocessors) {
    const subEmail = h('input', { type: 'email', required: true, placeholder: 'compliance-team@company.com' });
    const subCompany = h('input', { type: 'text', placeholder: 'Legal Entity / Company Name' });
    const subBtn = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 14), 'Subscribe to Alerts');
    
    const subForm = h('form', {
      class: 'row',
      style: 'gap: 0.5rem; flex-wrap: wrap;',
      onsubmit: async (e) => {
        e.preventDefault();
        busy(subBtn, 'Subscribing…', async () => {
          try {
            const res = await api('POST', 'privacy/subprocessors/subscribe', {
              email: subEmail.value.trim(),
              company: subCompany.value.trim() || null,
            });
            toast(res.message);
            subEmail.value = '';
            subCompany.value = '';
          } catch (err) {
            toast(err.message, 'err');
          }
        });
      }
    }, subEmail, subCompany, subBtn);

    extraCard = h('div', { class: 'card callout', style: 'margin-top: 1rem;' },
      h('h3', { style: 'margin-top: 0;' }, icon('shield', 16), ' Subscribe to 30-Day Sub-Processor Change Notifications'),
      h('p', { class: 'muted small' }, 'Under Article 28(4) GDPR, enterprise data controllers are notified at least 30 days prior to the engagement or replacement of any third-party sub-processor, with statutory right to object.'),
      subForm
    );
  }

  view.append(
    pageHead(title, subtitle),
    headerCard,
    bodyCard,
    extraCard
  );
}
