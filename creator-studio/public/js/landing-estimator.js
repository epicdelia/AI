// Landing page "08: Project Estimator": Interactive MVP Scope & Timeline Estimator.
// Allows founders to toggle project type, feature capabilities, and cloud targets
// to calculate an immediate, honest scope, timeline, and pricing breakdown.
import { h } from './core.js';
import { icon } from './icons.js';

const PROJECT_TYPES = [
  { id: 'mvp', label: 'Greenfield MVP', badge: '2–4 Weeks', desc: 'From zero to production launch. Weekly Friday Loom walkthroughs.', baseDays: 14, basePrice: 12000, depositPrice: 6000 },
  { id: 'ai', label: 'AI Agent & LLM Integration', badge: '1–2 Weeks', desc: 'Practical subagents, tool routing, and RAG into your existing product.', baseDays: 10, basePrice: 8500, depositPrice: 4250 },
  { id: 'retainer', label: 'Architecture & Retainer', badge: 'Monthly', desc: 'Ex-Google engineer on call: 2 strategy calls and 24h async code reviews.', baseDays: 0, basePrice: 3500, depositPrice: 3500 },
];

const FEATURES = [
  { id: 'auth', label: 'User Auth & RBAC Roles', days: 2, icon: 'lock' },
  { id: 'stripe', label: 'Stripe Billing & Subscriptions', days: 3, icon: 'credit-card' },
  { id: 'agents', label: 'Autonomous AI Agents & Tool Routing', days: 4, icon: 'sparkle' },
  { id: 'rag', label: 'Vector DB & Semantic RAG Search', days: 3, icon: 'search' },
  { id: 'cicd', label: 'Staging Environment & CI/CD Pipelines', days: 2, icon: 'flow' },
  { id: 'admin', label: 'Admin Dashboard & Real-Time Telemetry', days: 3, icon: 'sliders' },
];

const CLOUDS = [
  { id: 'gcp', label: 'Google Cloud (Cloud Run)', desc: 'Serverless containers, auto-scaling & secrets' },
  { id: 'aws', label: 'AWS Serverless', desc: 'Lambda, S3, RDS & CloudFront CDN' },
  { id: 'vercel', label: 'Vercel / Supabase', desc: 'Next-gen edge runtime & PostgreSQL' },
];

export function estimatorSection({ onBookCall, onStripePay }) {
  let selectedType = 'mvp';
  const selectedFeatures = new Set(['auth', 'stripe', 'agents', 'cicd']);
  let selectedCloud = 'gcp';

  const resultBox = h('div', { class: 'landing-est-summary', 'aria-live': 'polite' });

  function calculate() {
    const typeObj = PROJECT_TYPES.find((t) => t.id === selectedType) || PROJECT_TYPES[0];
    let totalDays = typeObj.baseDays;
    selectedFeatures.forEach((fid) => {
      const feat = FEATURES.find((f) => f.id === fid);
      if (feat) totalDays += feat.days;
    });

    const weeks = typeObj.id === 'retainer' ? 'Ongoing' : `${Math.max(2, Math.round(totalDays / 7))} to ${Math.max(3, Math.ceil(totalDays / 6))} Weeks`;
    const priceText = typeObj.id === 'retainer' ? '$3,500/mo' : `$${typeObj.basePrice.toLocaleString()}`;
    const depositText = typeObj.id === 'retainer' ? '$3,500' : `$${typeObj.depositPrice.toLocaleString()}`;
    const cloudObj = CLOUDS.find((c) => c.id === selectedCloud) || CLOUDS[0];

    resultBox.replaceChildren(
      h('div', { class: 'landing-est-result-card' },
        h('div', { class: 'landing-est-result-head' },
          h('div', {},
            h('span', { class: 'landing-est-badge' }, icon('clock', 12), 'Estimated Turnaround'),
            h('div', { class: 'landing-est-weeks' }, weeks)),
          h('div', { class: 'landing-est-price-box' },
            h('span', { class: 'landing-est-price-label' }, typeObj.id === 'retainer' ? 'Monthly Retainer' : 'Fixed-Price Package'),
            h('div', { class: 'landing-est-price' }, priceText),
            typeObj.id !== 'retainer' ? h('span', { class: 'landing-est-deposit' }, `Or ${depositText} (50% deposit to lock slot)`) : null)),
        h('div', { class: 'landing-est-specs' },
          h('div', { class: 'landing-est-spec-item' },
            h('span', { class: 'muted small' }, 'Model:'),
            h('strong', { class: 'small' }, typeObj.label)),
          h('div', { class: 'landing-est-spec-item' },
            h('span', { class: 'muted small' }, 'Capabilities:'),
            h('strong', { class: 'small' }, `${selectedFeatures.size} of ${FEATURES.length} Selected`)),
          h('div', { class: 'landing-est-spec-item' },
            h('span', { class: 'muted small' }, 'Infrastructure:'),
            h('strong', { class: 'small' }, cloudObj.label))),
        h('ul', { class: 'landing-est-deliverables' },
          h('li', {}, icon('check', 13), h('span', {}, '100% Client Code & IP Ownership from Day 1')),
          h('li', {}, icon('check', 13), h('span', {}, 'Weekly Friday Recorded Loom Video Walkthroughs')),
          h('li', {}, icon('check', 13), h('span', {}, 'Live Password-Protected Staging Deployment')),
          h('li', {}, icon('check', 13), h('span', {}, '30-Day Post-Launch Bug Warranty Included'))),
        h('div', { class: 'landing-est-actions' },
          h('button', {
            type: 'button',
            class: 'btn primary',
            onclick: () => onBookCall(typeObj.id === 'retainer' ? 'retainer' : 'mvp_build', `Estimated Project: ${typeObj.label} (${weeks})`),
          }, icon('calendar', 14), ' Book 30-Min Scoping Call', icon('arrow', 12)),
          typeObj.id !== 'retainer' ? h('button', {
            type: 'button',
            class: 'btn ghost',
            onclick: () => onStripePay('mvp_deposit'),
          }, icon('credit-card', 14), ` Lock Sprint (${depositText} deposit)`) : null),
        h('div', { class: 'landing-est-trust' },
          icon('check', 12),
          h('span', { class: 'small muted' }, 'Fixed-Price Guarantee · Zero Hidden Fees · Direct Line with Delia'))));
  }

  // Type Selector
  const typePills = h('div', { class: 'landing-est-type-grid', role: 'radiogroup', 'aria-label': 'Project Type' },
    PROJECT_TYPES.map((t) => {
      const btn = h('button', {
        type: 'button',
        role: 'radio',
        class: `landing-est-type-btn${t.id === selectedType ? ' on' : ''}`,
        'aria-checked': String(t.id === selectedType),
        onclick: () => {
          selectedType = t.id;
          typePills.querySelectorAll('.landing-est-type-btn').forEach((b, idx) => {
            const on = PROJECT_TYPES[idx].id === selectedType;
            b.classList.toggle('on', on);
            b.setAttribute('aria-checked', String(on));
          });
          calculate();
        },
      },
      h('div', { class: 'landing-est-type-top' },
        h('strong', {}, t.label),
        h('span', { class: 'landing-est-pill-sm' }, t.badge)),
      h('p', { class: 'muted small' }, t.desc));
      return btn;
    }));

  // Feature Toggles
  const featureGrid = h('div', { class: 'landing-est-feat-grid', 'aria-label': 'Included Capabilities' },
    FEATURES.map((f) => {
      const btn = h('button', {
        type: 'button',
        class: `landing-est-feat-btn${selectedFeatures.has(f.id) ? ' on' : ''}`,
        'aria-pressed': String(selectedFeatures.has(f.id)),
        onclick: () => {
          if (selectedFeatures.has(f.id)) selectedFeatures.delete(f.id);
          else selectedFeatures.add(f.id);
          btn.classList.toggle('on', selectedFeatures.has(f.id));
          btn.setAttribute('aria-pressed', String(selectedFeatures.has(f.id)));
          calculate();
        },
      },
      h('span', { class: 'landing-est-feat-icon', 'aria-hidden': 'true' }, icon(f.icon, 14)),
      h('span', { class: 'landing-est-feat-label' }, f.label),
      h('span', { class: 'landing-est-check', 'aria-hidden': 'true' }, icon('check', 12)));
      return btn;
    }));

  // Cloud Selector
  const cloudGrid = h('div', { class: 'landing-est-cloud-grid', role: 'radiogroup', 'aria-label': 'Cloud Target' },
    CLOUDS.map((c) => {
      const btn = h('button', {
        type: 'button',
        role: 'radio',
        class: `landing-est-cloud-btn${c.id === selectedCloud ? ' on' : ''}`,
        'aria-checked': String(c.id === selectedCloud),
        onclick: () => {
          selectedCloud = c.id;
          cloudGrid.querySelectorAll('.landing-est-cloud-btn').forEach((b, idx) => {
            const on = CLOUDS[idx].id === selectedCloud;
            b.classList.toggle('on', on);
            b.setAttribute('aria-checked', String(on));
          });
          calculate();
        },
      },
      h('strong', { class: 'small' }, c.label),
      h('p', { class: 'muted small' }, c.desc));
      return btn;
    }));

  calculate();

  const section = h('section', { class: 'landing-section tinted', id: 'estimator', 'aria-labelledby': 'est-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '08: Project Estimator'),
        h('h2', { id: 'est-heading' }, 'Estimate your MVP, ', h('em', {}, 'in seconds')),
        h('p', { class: 'muted' },
          'No ambiguous hourly quotes. Select your project parameters to get a realistic turnaround timeline and fixed investment blueprint.')),
      h('div', { class: 'landing-est-layout' },
        h('div', { class: 'landing-est-controls' },
          h('div', { class: 'landing-est-group' },
            h('label', { class: 'landing-est-group-label' }, '1. Choose Engagement Model'),
            typePills),
          h('div', { class: 'landing-est-group' },
            h('label', { class: 'landing-est-group-label' }, '2. Core Components & Capabilities'),
            featureGrid),
          h('div', { class: 'landing-est-group' },
            h('label', { class: 'landing-est-group-label' }, '3. Target Cloud Infrastructure'),
            cloudGrid)),
        resultBox)));

  return section;
}

