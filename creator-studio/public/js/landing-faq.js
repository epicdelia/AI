// Landing page "07: FAQ": Frequently Asked Questions about working with Delia.
// Detailed, authentic answers based on her Creator Studio business model,
// engineering background, 2-4 week sprints, IP ownership, and nomad logistics.
// Includes interactive category filters and accessible accordion items.
import { h, ensureLandingCss } from './core.js';
import { icon } from './icons.js';

ensureLandingCss(); // its styles live in landing.css

export const FAQ_ITEMS = [
  {
    id: 'faq-vibecoded',
    category: 'mvp',
    catLabel: 'MVPs & Engineering',
    q: 'What exactly does "Vibecoded" mean and how can an MVP ship in just 2 to 4 weeks?',
    a: 'Vibecoding is high-leverage modern software engineering. It combines deep systems engineering foundations (from my years writing production code at Google) with autonomous AI tools, pre-architected foundations, and rapid iteration loops. Instead of a 10-person agency spending a month in committee meetings before writing a single line of code, I work directly with you against concrete acceptance criteria. You get private GitHub commits from Day 1, recorded Loom walkthroughs every Friday, and working software deployed to live staging within days.',
    tags: ['2–4 Week Turnaround', 'Friday Loom Demos', 'Ex-Google Standards'],
    ctaLabel: 'See 4-Phase Process',
    ctaHref: '#process',
  },
  {
    id: 'faq-ownership',
    category: 'mvp',
    catLabel: 'MVPs & Engineering',
    q: 'Do I own 100% of the code, intellectual property, and cloud infrastructure?',
    a: 'Yes, 100% without exception. From Day 1, code is pushed directly to a private GitHub repository under your organization with an MIT or custom proprietary license of your choosing. All production cloud infrastructure (GCP, AWS, Supabase, Vercel) and third-party APIs are set up in your own company accounts. There are zero proprietary runtime dependencies, no licensing traps, and no hostage fees. When we launch, you hold all keys and credentials.',
    tags: ['100% IP Ownership', 'Zero Vendor Lock-In', 'Full Code Handover'],
    ctaLabel: 'View Packages',
    ctaHref: '#pricing',
  },
  {
    id: 'faq-warranty',
    category: 'mvp',
    catLabel: 'MVPs & Engineering',
    q: 'What happens after the MVP is launched? Is there a warranty or ongoing support?',
    a: 'Every MVP engagement includes a comprehensive 30-day post-launch bug warranty at zero additional cost. If anything within the agreed specification breaks or behaves unexpectedly, I patch it immediately. In addition, you receive clear developer documentation, operations runbooks, and recorded video handovers. If you want ongoing architectural guidance or priority feature development after launch, you can transition seamlessly into my monthly advisory retainer.',
    tags: ['30-Day Bug Warranty', 'Ops Runbook', 'Seamless Handover'],
    ctaLabel: 'Explore Retainers',
    ctaHref: '#pricing',
  },
  {
    id: 'faq-retainer',
    category: 'advisory',
    catLabel: 'AI & Advisory',
    q: 'How does the $3,500/month Advisory Retainer work?',
    a: 'The advisory retainer places an ex-Google systems engineer on your team without the overhead of a full-time executive hire. Each month includes two 60-minute 1-on-1 strategic architecture calls, unlimited async pull request and code reviews via private Slack or GitHub (with a guaranteed 24-hour turnaround on business days), and AI build-vs-buy evaluations. It is billed monthly via Stripe, and you can pause or cancel at any time with zero long-term commitment.',
    tags: ['Ex-Google on Call', '24h Async Reviews', 'Cancel Anytime'],
    ctaLabel: 'Book Strategy Call',
    ctaType: 'retainer',
  },
  {
    id: 'faq-tech-stack',
    category: 'advisory',
    catLabel: 'AI & Advisory',
    q: 'What tech stack and AI models do you build with?',
    a: 'I specialize in production-grade, battle-tested technologies. On the backend: TypeScript/Node.js, modern PHP/Laravel 11 REST APIs, and Python AI microservices with PostgreSQL and Redis. On the frontend: lightweight reactive JavaScript/Vue 3/React with Tailwind CSS for lightning-fast loads. For AI: state-of-the-art models (Gemini 1.5/2.0, Claude 3.5 Sonnet), custom agent tool-routing, vector search (pgvector), and deterministic guardrails. I avoid fragile hype-ware so your product remains maintainable for years.',
    tags: ['TypeScript & Python', 'Gemini & Claude', 'Production Grade'],
    ctaLabel: 'See Full Tech Stack',
    ctaHref: '#stack',
  },
  {
    id: 'faq-timezones',
    category: 'logistics',
    catLabel: 'Logistics & Payments',
    q: 'You travel across 60+ countries. How do time zones and communication work?',
    a: 'Async-first discipline is my superpower. Having worked across global teams at Google and traveled across 69 countries as a digital nomad, collaboration runs seamlessly via a private Slack or WhatsApp channel, daily commit logs, and weekly recorded Friday Loom demos. For real-time video calls, my booking engine automatically converts slots into your local timezone so we are always aligned without friction.',
    tags: ['69 Countries Nomad', 'Async-First Flow', 'Local Timezones'],
    ctaLabel: 'Book an Intro Call',
    ctaType: 'mvp_build',
  },
  {
    id: 'faq-sponsorships',
    category: 'media',
    catLabel: 'Sponsorships & Media',
    q: 'What are your criteria for brand partnerships and sponsored reviews?',
    a: 'I adhere to a strict authenticity policy: I only showcase developer tools, cloud infrastructure, AI models, and hardware that I have personally tested and genuinely use in real engineering workflows. My community of 519K+ developers and founders values high-signal, fluff-free technical depth. Engagements include custom video scripting, multi-network syndication (YouTube 4K, Instagram, LinkedIn), and transparent tracking links.',
    tags: ['519K+ Audience', 'Authentic Reviews', 'Verified Developer Reach'],
    ctaLabel: 'Request Media Kit',
    ctaType: 'brand_deal',
  },
  {
    id: 'faq-payments',
    category: 'logistics',
    catLabel: 'Logistics & Payments',
    q: 'How do payments, deposits, contracts, and invoicing work?',
    a: 'All projects are 100% fixed-price with zero surprise charges. MVP builds require a 50% upfront deposit to lock your build sprint in the studio schedule, with the remaining 50% due upon production deployment signoff. Monthly retainers are processed securely via Stripe Checkout or direct wire transfer. Standard commercial invoices with EU/US compliant VAT/tax details and signed Master Services Agreements (MSAs) with IP assignment are provided.',
    tags: ['Fixed-Price Guarantee', '50% Deposit', 'Stripe & Invoicing'],
    ctaLabel: 'Pay Deposit with Stripe',
    ctaPay: 'mvp_deposit',
  },
];

const CATEGORIES = [
  { id: 'all', label: 'All Questions' },
  { id: 'mvp', label: 'MVPs & Engineering' },
  { id: 'advisory', label: 'AI & Advisory' },
  { id: 'media', label: 'Sponsorships & Media' },
  { id: 'logistics', label: 'Logistics & Payments' },
];

/**
 * Interactive FAQ Section for the Tech Unicorn Landing Page.
 * Supports category tabs and accessible accordion items.
 */
export function faqSection({ onBookCall, onStripePay }) {
  let activeCat = 'all';

  const listEl = h('div', { class: 'landing-faq-list', role: 'region', 'aria-label': 'FAQ items' });

  // Category filter buttons
  const catButtons = CATEGORIES.map((cat) => {
    const btn = h('button', {
      type: 'button',
      class: `landing-faq-pill${cat.id === activeCat ? ' is-active' : ''}`,
      'aria-pressed': String(cat.id === activeCat),
      onclick: () => {
        activeCat = cat.id;
        catButtons.forEach((b, idx) => {
          const on = CATEGORIES[idx].id === activeCat;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-pressed', String(on));
        });
        renderItems();
      },
    }, cat.label);
    return btn;
  });

  const pillsWrap = h('div', { class: 'landing-faq-pills', role: 'tablist', 'aria-label': 'FAQ Categories' }, catButtons);

  // Still have questions CTA card
  const ctaCard = h('div', { class: 'landing-faq-cta-box' },
    h('div', { class: 'landing-faq-cta-text' },
      h('h3', {}, 'Have a unique question not listed here?'),
      h('p', { class: 'muted' }, 'Book a complimentary 30-minute intro call with Delia to discuss your specific product, architecture, or partnership.')),
    h('div', { class: 'landing-faq-cta-actions' },
      h('button', {
        type: 'button',
        class: 'btn primary',
        onclick: () => onBookCall('mvp_build', 'FAQ: Ask Delia'),
      }, icon('calendar', 14), ' Book 30-Min Call'),
      h('a', {
        href: 'mailto:hello@thetechunicorn.com?subject=Question%20about%20working%20together',
        class: 'btn ghost',
      }, icon('mail', 14), ' Email Delia Directly')));

  function renderItems() {
    const filtered = FAQ_ITEMS.filter((item) => activeCat === 'all' || item.category === activeCat);

    listEl.replaceChildren(...filtered.map((item, idx) => faqAccordionItem(item, idx, { onBookCall, onStripePay })));
  }

  renderItems();

  const section = h('section', { class: 'landing-section tinted', id: 'faq', 'aria-labelledby': 'faq-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '07: FAQ'),
        h('h2', { id: 'faq-heading' }, 'Frequently asked ', h('em', {}, 'questions')),
        h('p', { class: 'muted' }, 'Clear, transparent answers about timelines, intellectual property, pricing, and how an ex-Google nomad ships production software.')),
      h('div', { class: 'landing-faq-controls' },
        pillsWrap),
      listEl,
      ctaCard));

  return section;
}

/** Individual accessible FAQ item using <details> / <summary> */
function faqAccordionItem(item, idx, { onBookCall, onStripePay }) {
  const details = h('details', { class: 'landing-faq-item', name: 'landing-faq-accordion' });

  const summary = h('summary', { class: 'landing-faq-summary' },
    h('span', { class: 'landing-faq-q-wrap' },
      h('span', { class: 'landing-faq-cat-badge' }, item.catLabel),
      h('span', { class: 'landing-faq-q' }, item.q)),
    h('span', { class: 'landing-faq-chevron', 'aria-hidden': 'true' }, icon('chevron-down', 16)));

  const actionBtn = (() => {
    if (item.ctaPay) {
      return h('button', {
        type: 'button',
        class: 'landing-faq-action-btn',
        onclick: () => onStripePay(item.ctaPay),
      }, icon('credit-card', 13), item.ctaLabel, icon('arrow', 12));
    }
    if (item.ctaType) {
      return h('button', {
        type: 'button',
        class: 'landing-faq-action-btn',
        onclick: () => onBookCall(item.ctaType, `FAQ: ${item.q}`),
      }, icon('calendar', 13), item.ctaLabel, icon('arrow', 12));
    }
    if (item.ctaHref) {
      return h('a', {
        href: item.ctaHref,
        class: 'landing-faq-action-btn',
        onclick: (e) => {
          if (item.ctaHref.startsWith('#')) {
            const target = document.querySelector(item.ctaHref);
            if (target) {
              e.preventDefault();
              target.scrollIntoView({ behavior: 'smooth' });
            }
          }
        },
      }, item.ctaLabel, icon('arrow', 12));
    }
    return null;
  })();

  const answer = h('div', { class: 'landing-faq-answer' },
    h('p', { class: 'landing-faq-answer-text' }, item.a),
    h('div', { class: 'landing-faq-footer' },
      h('ul', { class: 'landing-faq-tags', 'aria-label': 'Key highlights' },
        item.tags.map((t) => h('li', { class: 'landing-faq-tag' }, icon('check', 11), t))),
      actionBtn));

  details.append(summary, answer);
  return details;
}

/** Dashboard Website tab card: overview of questions & status */
export function faqCard() {
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Landing Page FAQ'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `${FAQ_ITEMS.length} verified questions across ${CATEGORIES.length - 1} categories with direct booking & deposit links`)),
      h('a', { href: '/#faq', class: 'btn sm ghost' }, icon('eye', 14), 'View on Landing')),
    h('ul', { class: 'item-list' },
      FAQ_ITEMS.slice(0, 4).map((f) =>
        h('li', {},
          h('div', { class: 'item-main' },
            h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
              h('strong', { class: 'small' }, f.q),
              h('span', { class: 'badge sm' }, f.catLabel)),
            h('p', { class: 'muted small clamp-2', style: { margin: '4px 0 0' } }, f.a))))));
}
