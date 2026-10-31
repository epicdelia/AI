// Delia Lazarescu (Tech Unicorn) Public Landing Page
// Fully implemented in pure JavaScript using the Creator Studio dashboard design system.
// Zero build step, reactive DOM helper h(), native SVG icons, dark/light theme tokens.
import { h, link, navigate, api, modal, toast, field } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';

/**
 * Interactive Booking Modal for 30-minute Intro Calls.
 * Logs lead directly into Creator Studio CRM deals pipeline and confirms meeting.
 */
export function openBookingModal(initialType = 'mvp_build') {
  return modal('Book an Intro Call with Delia', (close) => {
    let submitting = false;
    const today = new Date().toISOString().slice(0, 10);

    const typeSelect = h('select', { class: 'input', name: 'project_type' },
      h('option', { value: 'mvp_build', selected: initialType === 'mvp_build' }, 'MVP Build (2 to 4 Weeks)'),
      h('option', { value: 'retainer', selected: initialType === 'retainer' }, 'Advisory Retainer ($3,500/mo)'),
      h('option', { value: 'brand_deal', selected: initialType === 'brand_deal' }, 'Brand Partnership & Sponsorship'),
      h('option', { value: 'speaking', selected: initialType === 'speaking' }, 'Conference Keynote or Workshop'),
      h('option', { value: 'consulting', selected: initialType === 'consulting' }, 'AI & Architecture Technical Consulting'),
      h('option', { value: 'other', selected: initialType === 'other' }, 'Other Collaboration'));

    const nameInput = h('input', { type: 'text', class: 'input', name: 'name', placeholder: 'Sarah Connor', required: true });
    const emailInput = h('input', { type: 'email', class: 'input', name: 'email', placeholder: 'sarah@skynet.example.com', required: true });
    const companyInput = h('input', { type: 'text', class: 'input', name: 'company', placeholder: 'Cyberdyne Systems (optional)' });
    const dateInput = h('input', { type: 'date', class: 'input', name: 'preferred_date', value: today, min: today, required: true });
    const timeSelect = h('select', { class: 'input', name: 'preferred_time' },
      ['14:00 UTC (10:00 AM EST)', '15:00 UTC (11:00 AM EST)', '16:00 UTC (12:00 PM EST)', '17:00 UTC (01:00 PM EST)', '18:00 UTC (02:00 PM EST)', '19:00 UTC (03:00 PM EST)'].map((slot) =>
        h('option', { value: slot }, slot)));
    const notesInput = h('textarea', {
      class: 'input',
      name: 'notes',
      rows: 3,
      placeholder: 'Briefly share what you want to build or achieve...',
    });

    const submitBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('calendar', 14), ' Confirm 30-Min Call');
    const cancelBtn = h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Cancel');

    const form = h('form', {
      class: 'booking-form',
      onsubmit: async (e) => {
        e.preventDefault();
        if (submitting) return;
        submitting = true;
        submitBtn.disabled = true;
        submitBtn.replaceChildren(icon('sparkle', 14), ' Scheduling call...');

        try {
          const payload = {
            name: nameInput.value.trim(),
            email: emailInput.value.trim(),
            company: companyInput.value.trim(),
            project_type: typeSelect.value,
            preferred_date: dateInput.value,
            preferred_time: timeSelect.value,
            notes: notesInput.value.trim(),
          };

          const res = await api('POST', 'booking', payload);
          showSuccess(res);
        } catch (err) {
          submitting = false;
          submitBtn.disabled = false;
          submitBtn.replaceChildren(icon('calendar', 14), ' Confirm 30-Min Call');
          toast(err.message || 'Could not schedule call. Please try again.', 'err');
        }
      },
    },
    h('div', { class: 'grid grid-2', style: { gap: '14px' } },
      field('Your Name', nameInput, 'Required'),
      field('Work Email', emailInput, 'Calendar invite sent here')),
    h('div', { class: 'grid grid-2', style: { gap: '14px' } },
      field('Company or Brand', companyInput, 'Optional'),
      field('Project Type', typeSelect)),
    h('div', { class: 'grid grid-2', style: { gap: '14px' } },
      field('Preferred Date', dateInput),
      field('Preferred Time', timeSelect)),
    field('Project Notes or Goals', notesInput, 'Tell Delia what you need built'),
    h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '10px', marginTop: '16px' } },
      cancelBtn,
      submitBtn));

    const contentBox = h('div', { class: 'booking-modal-body' },
      h('div', { class: 'row', style: { gap: '12px', alignItems: 'center', marginBottom: '18px', paddingBottom: '14px', borderBottom: '1px solid var(--line)' } },
        brandMark(32),
        h('div', {},
          h('h3', { style: { margin: 0, fontSize: '1.15rem' } }, 'Free 30-Minute Intro Call'),
          h('p', { class: 'muted small', style: { margin: 0 } }, 'Discuss your MVP, retainer, or brand partnership with Delia.'))),
      form);

    function showSuccess(data) {
      const appt = data.appointment || {};
      const successBox = h('div', { class: 'booking-confirmed-card', style: { textAlign: 'center', padding: '16px 8px' } },
        h('div', { style: { width: '56px', height: '56px', borderRadius: '50%', background: 'rgba(34, 197, 94, 0.15)', color: '#22c55e', display: 'grid', placeItems: 'center', margin: '0 auto 16px' } },
          icon('check', 28)),
        h('h3', { style: { margin: '0 0 6px', fontSize: '1.4rem' } }, 'Intro Call Confirmed!'),
        h('p', { class: 'muted', style: { margin: '0 0 20px' } }, data.message || `Invite sent to ${emailInput.value}.`),
        h('div', { class: 'card', style: { textAlign: 'left', padding: '16px', marginBottom: '20px', background: 'var(--panel-2)', border: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Booking Reference:'),
            h('strong', { class: 'small' }, data.booking_ref || 'CONFIRMED')),
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Scheduled Date:'),
            h('strong', { class: 'small' }, `${appt.date} at ${appt.time}`)),
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Service:'),
            h('strong', { class: 'small' }, appt.service || 'Intro Consultation')),
          appt.calendar_link ? h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('span', { class: 'small muted' }, 'Meeting Room:'),
            h('a', { href: appt.calendar_link, target: '_blank', rel: 'noopener', style: { color: 'var(--accent)', fontWeight: 'bold', display: 'inline-flex', alignItems: 'center', gap: '4px' } }, 'Google Meet Link', icon('arrow', 12))) : null),
        h('div', { class: 'row', style: { justifyContent: 'center', gap: '12px' } },
          appt.calendar_link ? h('a', { href: appt.calendar_link, target: '_blank', rel: 'noopener', class: 'btn primary' }, icon('play', 14), ' Open Google Meet') : null,
          h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Done')));

      contentBox.replaceChildren(successBox);
    }

    return contentBox;
  }, { wide: true });
}

/**
 * Interactive Stripe Checkout Modal.
 * Generates verified Stripe checkout session or verified local receipt and logs into CRM.
 */
export function openStripeModal(pkgId = 'retainer') {
  const PACKAGES = {
    retainer: { id: 'retainer', name: 'Advisory Retainer (1 Month)', price: '$3,500', desc: 'Ex-Google engineer on call: 2 strategy calls, unlimited async reviews, 24h response.' },
    mvp_deposit: { id: 'mvp_deposit', name: 'MVP Build: 50% Project Deposit', price: '$6,000', desc: '50% upfront project deposit for 2-4 week fixed MVP build with Friday live demos.' },
    mvp_full: { id: 'mvp_full', name: 'MVP Build: Full Fixed Package', price: '$12,000', desc: 'Complete fixed-scope MVP: UX design, AI build, cloud deployment, and 30-day post-launch support.' },
    brand_partnership: { id: 'brand_partnership', name: 'Brand Partnership Campaign', price: '$5,000', desc: 'Dedicated technical integration across Instagram (384K), YouTube (22K) & LinkedIn (25K).' },
    media_kit_sponsor: { id: 'media_kit_sponsor', name: 'Verified Media Kit Feature', price: '$1,500', desc: 'Featured sponsor placement in Delia’s live media kit and rate card deck.' },
    advisory_session: { id: 'advisory_session', name: 'Strategy & Architecture Session', price: '$500', desc: '90-minute private architectural deep-dive and code review call.' },
  };

  const selected = PACKAGES[pkgId] || PACKAGES.retainer;

  return modal(`Stripe Secure Checkout: ${selected.name}`, (close) => {
    let submitting = false;
    const nameInput = h('input', { type: 'text', class: 'input', name: 'customer_name', placeholder: 'Jane Doe', required: true });
    const emailInput = h('input', { type: 'email', class: 'input', name: 'customer_email', placeholder: 'jane@company.example.com', required: true });
    const companyInput = h('input', { type: 'text', class: 'input', name: 'company', placeholder: 'Acme Corporation' });
    const notesInput = h('textarea', { class: 'input', name: 'notes', rows: 2, placeholder: 'Project details, billing instructions, or VAT number...' });

    const payBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('credit-card', 14), ` Pay ${selected.price} with Stripe`);
    const cancelBtn = h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Cancel');

    const form = h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        if (submitting) return;
        submitting = true;
        payBtn.disabled = true;
        payBtn.replaceChildren(icon('sparkle', 14), ' Connecting to Stripe...');

        try {
          const payload = {
            package_id: selected.id,
            customer_name: nameInput.value.trim(),
            customer_email: emailInput.value.trim(),
            company: companyInput.value.trim(),
            notes: notesInput.value.trim(),
          };

          const res = await api('POST', 'stripe/checkout-session', payload);

          if (res.checkout_url) {
            window.location.href = res.checkout_url;
            return;
          }

          showPaymentSuccess(res);
        } catch (err) {
          submitting = false;
          payBtn.disabled = false;
          payBtn.replaceChildren(icon('credit-card', 14), ` Pay ${selected.price} with Stripe`);
          toast(err.message || 'Stripe payment error. Please try again.', 'err');
        }
      },
    },
    h('div', { class: 'card', style: { padding: '16px', background: 'var(--panel-2)', border: '1px solid var(--line)', marginBottom: '16px' } },
      h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
        h('strong', {}, selected.name),
        h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '1.4rem', fontWeight: 700, color: 'var(--accent)' } }, selected.price)),
      h('p', { class: 'muted small', style: { margin: '8px 0 0' } }, selected.desc)),
    h('div', { class: 'grid grid-2', style: { gap: '14px' } },
      field('Cardholder or Client Name', nameInput, 'Required'),
      field('Billing Email', emailInput, 'Invoice receipt sent here')),
    field('Company or Brand Name', companyInput, 'For invoice heading'),
    field('Notes & Project Requirements', notesInput, 'Optional details'),
    h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginTop: '18px' } },
      h('span', { class: 'small muted' }, icon('lock', 12), ' 256-bit encrypted Stripe checkout'),
      h('div', { class: 'row', style: { gap: '10px' } }, cancelBtn, payBtn)));

    const contentBox = h('div', { class: 'stripe-modal-body' },
      h('div', { class: 'row', style: { gap: '12px', alignItems: 'center', marginBottom: '16px', paddingBottom: '14px', borderBottom: '1px solid var(--line)' } },
        brandMark(28),
        h('div', {},
          h('h3', { style: { margin: 0, fontSize: '1.15rem' } }, 'Tech Unicorn · Secure Checkout'),
          h('p', { class: 'muted small', style: { margin: 0 } }, 'Stripe Payments partner with instant invoice and contract logging.'))),
      form);

    function showPaymentSuccess(data) {
      const receipt = h('div', { style: { textAlign: 'center', padding: '16px 8px' } },
        h('div', { style: { width: '56px', height: '56px', borderRadius: '50%', background: 'rgba(34, 197, 94, 0.15)', color: '#22c55e', display: 'grid', placeItems: 'center', margin: '0 auto 16px' } },
          icon('check', 28)),
        h('h3', { style: { margin: '0 0 6px', fontSize: '1.4rem' } }, 'Payment Successful!'),
        h('p', { class: 'muted', style: { margin: '0 0 18px' } }, data.message || `Payment for ${selected.name} has been processed.`),
        h('div', { class: 'card', style: { textAlign: 'left', padding: '16px', marginBottom: '20px', background: 'var(--panel-2)', border: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Receipt Reference:'),
            h('strong', { class: 'small' }, data.receipt_id || 'REC-VERIFIED')),
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Amount Paid:'),
            h('strong', { class: 'small', style: { color: 'var(--accent)' } }, `$${(data.amount || 0).toLocaleString()} USD`)),
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Package:'),
            h('strong', { class: 'small' }, selected.name)),
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('span', { class: 'small muted' }, 'Creator Studio CRM:'),
            h('span', { class: 'pill ok small' }, 'Contract Registered'))),
        h('div', { class: 'row', style: { justifyContent: 'center', gap: '12px' } },
          h('button', { type: 'button', class: 'btn primary', onclick: () => window.print() }, icon('file-text', 14), ' Print Receipt'),
          h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Done')));

      contentBox.replaceChildren(receipt);
    }

    return contentBox;
  }, { wide: true });
}

/**
 * Global Navigation Configuration for both Mega Menus and Mobile Side Drawer.
 */
function getLandingNavConfig({ onBookCall, onStripePay, onAuthClick, isAuth }) {
  return [
    {
      id: 'services',
      label: 'Services',
      groups: [
        {
          title: 'Core Engineering',
          items: [
            { label: 'Vibecoded Apps', desc: 'AI-powered MVPs designed, built, and shipped in 2 to 4 weeks.', icon: 'zap', href: '#services' },
            { label: 'AI Integration', desc: 'Practical LLMs and autonomous agents that earn their keep.', icon: 'sparkle', href: '#services' },
            { label: 'Technical Consulting', desc: 'Ex-Google architecture reviews and build-vs-buy decisions.', icon: 'code', href: '#services' },
          ],
        },
        {
          title: 'Audience & Growth',
          items: [
            { label: 'Content Creation', desc: 'Short-form reels and YouTube deep-dives for 472K+ followers.', icon: 'play', href: '#content' },
            { label: 'Brand Partnerships', desc: 'High-retention technical integrations and sponsor features.', icon: 'briefcase', href: '#pricing' },
            { label: 'Speaking & Workshops', desc: 'Conference keynotes and hands-on AI workshops worldwide.', icon: 'mic', href: '#speaking' },
          ],
        },
      ],
      actions: [
        { label: 'Book an Intro Call', icon: 'calendar', onClick: () => onBookCall('mvp_build') },
        { label: 'Explore Packages & Pricing', icon: 'tag', href: '#pricing' },
      ],
    },
    {
      id: 'pricing',
      label: 'Pricing & Retainers',
      groups: [
        {
          title: 'Fixed Engagements',
          items: [
            { label: 'MVP Build (from $12,000)', desc: 'Complete product in 2 to 4 weeks with weekly Friday live demos.', icon: 'rocket', href: '#pricing' },
            { label: 'Advisory Retainer ($3,500/mo)', desc: 'Ex-Google engineer on call, 2 monthly strategy calls, 24h response.', icon: 'clock', href: '#pricing' },
            { label: 'Brand Partnership ($5,000)', desc: 'Concept, video production, and multi-network sponsor distribution.', icon: 'star', href: '#pricing' },
          ],
        },
        {
          title: 'Instant Stripe Checkout',
          items: [
            { label: 'Pay 50% MVP Deposit ($6,000)', desc: 'Lock in your upcoming build slot with instant Stripe confirmation.', icon: 'credit-card', onClick: () => onStripePay('mvp_deposit') },
            { label: 'Start Monthly Retainer ($3,500)', desc: 'Begin immediate monthly advisory and asynchronous architectural reviews.', icon: 'repeat', onClick: () => onStripePay('retainer') },
            { label: 'Verified Media Kit Feature ($1,500)', desc: 'Featured partner placement in Delia’s live media kit rate card.', icon: 'file-text', onClick: () => onStripePay('media_kit_sponsor') },
          ],
        },
      ],
      actions: [
        { label: 'Pay with Stripe', icon: 'credit-card', onClick: () => onStripePay('retainer') },
        { label: 'Request Media Kit', icon: 'file-text', to: '/mediakit' },
      ],
    },
    {
      id: 'about',
      label: 'About Delia',
      groups: [
        {
          title: 'Background & Ethos',
          items: [
            { label: 'Ex-Google Software Engineer', desc: 'Production systems engineer turned independent creator and founder.', icon: 'briefcase', href: '#about' },
            { label: '69 Countries Digital Nomad', desc: 'Building products and shipping software from beach cafés globally.', icon: 'globe', href: '#about' },
            { label: '472K+ Community', desc: 'Demystifying technology for hundreds of thousands across social platforms.', icon: 'users', href: '#content' },
          ],
        },
        {
          title: 'Stages & Keynotes',
          items: [
            { label: 'Nomad Cruise Keynote', desc: 'Building an international career in technology without a permanent address.', icon: 'mic', href: '#speaking' },
            { label: 'Vently Panel', desc: 'Creator-led software businesses and distribution for engineers.', icon: 'chat', href: '#speaking' },
            { label: 'Digital Garage Tokyo', desc: 'Hands-on AI workflows and generative tools for product teams.', icon: 'sparkle', href: '#speaking' },
          ],
        },
      ],
      actions: [
        { label: 'Open Public Bio Page', icon: 'link', to: '/links' },
        { label: 'Instagram @tech.unicorn', icon: 'brand:instagram', href: 'https://instagram.com/tech.unicorn', external: true },
      ],
    },
    {
      id: 'studio',
      label: 'Studio Suite',
      groups: [
        {
          title: 'Creator Studio Platform',
          items: [
            { label: 'Creator Studio Pro', desc: 'AI script wizard, audience retention analytics, and video pipelines.', icon: 'sparkle', onClick: onAuthClick },
            { label: 'Link in Bio Builder', desc: 'Fast, high-converting bio pages with click tracking and analytics.', icon: 'link', to: '/links' },
            { label: 'Visual Code Cards', desc: 'Generate high-res code cards and terminal syntax snippets.', icon: 'code', to: '/snippets' },
          ],
        },
        {
          title: 'Business & CRM',
          items: [
            { label: 'Brand Deals CRM', desc: 'Manage inbound sponsorships, deliverables, and rate cards.', icon: 'briefcase', to: '/deals' },
            { label: 'Media Kit Deck', desc: 'Live audience reach, verified demographics, and pricing tiers.', icon: 'file-text', to: '/mediakit' },
            { label: 'Omnichannel Distribution', desc: 'Repurpose one idea into multi-network native social posts.', icon: 'repeat', to: '/repurpose' },
          ],
        },
      ],
      actions: [
        { label: isAuth ? 'Open Studio Dashboard' : 'Sign in to Creator Studio', icon: 'sliders', onClick: onAuthClick },
        { label: 'Quick Search (Omnibar)', icon: 'search', onClick: () => window.dispatchEvent(new CustomEvent('open-palette')) },
      ],
    },
  ];
}

/**
 * Multi-Column Mega Menus for the Landing Page Header.
 * Matches Creator Studio dashboard megaMenus() styling and interactive structure.
 */
function landingMegaMenus({ onBookCall, onStripePay, onAuthClick, isAuth }) {
  const MEGA_CONFIG = getLandingNavConfig({ onBookCall, onStripePay, onAuthClick, isAuth });
  const panel = h('div', { class: 'mega', id: 'landing-mega-panel', hidden: true, role: 'region' });
  let openSec = null;
  let timer = null;
  const tabs = [];

  const close = () => {
    clearTimeout(timer);
    if (!openSec) return;
    const tab = tabs.find((t) => t.sec === openSec);
    tab?.btn.setAttribute('aria-expanded', 'false');
    panel.classList.remove('open');
    panel.hidden = true;
    openSec = null;
  };

  function fill(sec) {
    const itemElem = (it) => {
      const clickHandler = (e) => {
        close();
        if (it.onClick) {
          e.preventDefault();
          it.onClick();
        } else if (it.href && it.href.startsWith('#')) {
          // Smooth scroll to anchor
          const target = document.querySelector(it.href);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
          }
        }
      };

      if (it.to) {
        return link(it.to, { class: 'mega-link', onclick: () => close() },
          h('span', { class: 'mega-ico' }, icon(it.icon, 18)),
          h('span', { class: 'mega-text' },
            h('strong', {}, it.label),
            h('span', { class: 'muted small' }, it.desc)));
      }

      return h('a', {
        href: it.href || '#',
        target: it.external ? '_blank' : undefined,
        rel: it.external ? 'noopener' : undefined,
        class: 'mega-link',
        onclick: clickHandler,
      },
      h('span', { class: 'mega-ico' }, icon(it.icon, 18)),
      h('span', { class: 'mega-text' },
        h('strong', {}, it.label),
        h('span', { class: 'muted small' }, it.desc)));
    };

    panel.replaceChildren(h('div', { class: 'mega-inner' },
      h('div', { class: `mega-cols cols-${sec.groups.length}` },
        sec.groups.map((g) => h('div', { class: 'mega-col' },
          h('p', { class: 'mega-title' }, g.title),
          h('div', { class: 'mega-list' }, g.items.map(itemElem))))),
      h('aside', { class: 'mega-side' },
        h('p', { class: 'mega-title' }, 'Quick actions'),
        sec.actions.map((act) => {
          const actClick = (e) => {
            close();
            if (act.onClick) {
              e.preventDefault();
              act.onClick();
            }
          };

          if (act.to) {
            return link(act.to, { class: 'mega-action', onclick: () => close() },
              icon(act.icon, 15),
              act.label,
              icon('arrow', 13));
          }

          return h('a', {
            href: act.href || '#',
            target: act.external ? '_blank' : undefined,
            rel: act.external ? 'noopener' : undefined,
            class: 'mega-action',
            onclick: actClick,
          },
          icon(act.icon, 15),
          act.label,
          icon('arrow', 13));
        }))));
  }

  function show(sec) {
    clearTimeout(timer);
    if (openSec !== sec) {
      tabs.forEach((t) => t.btn.setAttribute('aria-expanded', String(t.sec === sec)));
      openSec = sec;
      fill(sec);
      panel.hidden = false;
      requestAnimationFrame(() => panel.classList.add('open'));
    }
  }

  for (const sec of MEGA_CONFIG) {
    const btn = h('button', {
      class: 'mega-tab',
      type: 'button',
      'aria-expanded': 'false',
      'aria-controls': 'landing-mega-panel',
    }, sec.label, icon('chevron-down', 14));

    btn.addEventListener('mouseenter', () => {
      clearTimeout(timer);
      timer = setTimeout(() => show(sec), openSec ? 0 : 100);
    });
    btn.addEventListener('mouseleave', () => {
      clearTimeout(timer);
      timer = setTimeout(() => close(), 200);
    });
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openSec === sec ? close() : show(sec);
    });

    tabs.push({ sec, btn });
  }

  panel.addEventListener('mouseenter', () => clearTimeout(timer));
  panel.addEventListener('mouseleave', () => {
    timer = setTimeout(() => close(), 200);
  });
  panel.addEventListener('click', (e) => e.stopPropagation());
  document.addEventListener('click', () => close());

  return h('div', { class: 'mega-wrap' },
    h('nav', { class: 'mega-tabs', 'aria-label': 'Landing navigation' }, tabs.map((t) => t.btn)),
    panel);
}

/**
 * Mobile & Tablet Off-Canvas Side Drawer.
 * Faithfully mirrors the Creator Studio dashboard mobile drawer navigation.
 */
function landingSideDrawer({ onBookCall, onStripePay, onAuthClick, isAuth }) {
  const MEGA_CONFIG = getLandingNavConfig({ onBookCall, onStripePay, onAuthClick, isAuth });
  const backdrop = h('div', { class: 'landing-drawer-backdrop' });

  const closeBtn = h('button', {
    type: 'button',
    class: 'landing-drawer-close',
    'aria-label': 'Close menu',
  }, icon('x', 18));

  const drawerHead = h('div', { class: 'landing-drawer-head' },
    h('div', { class: 'row', style: { gap: '10px', alignItems: 'center' } },
      brandMark(24),
      h('strong', { style: { fontSize: '1.05rem', letterSpacing: '0.02em' } }, 'Tech Unicorn')),
    closeBtn);

  const actionsBox = h('div', { class: 'landing-drawer-actions' },
    h('button', {
      type: 'button',
      class: 'btn primary',
      onclick: () => { close(); onBookCall('mvp_build'); },
    }, icon('calendar', 14), 'Book an Intro Call'),
    h('button', {
      type: 'button',
      class: 'btn ghost',
      onclick: () => { close(); onStripePay('retainer'); },
    }, icon('credit-card', 14), 'Pay with Stripe'),
    h('button', {
      type: 'button',
      class: 'btn ghost',
      onclick: () => { close(); onAuthClick(); },
    }, icon('sparkle', 14), isAuth ? 'Open Dashboard' : 'Studio Login'));

  const sectionsBox = h('div', { class: 'landing-drawer-sections' },
    MEGA_CONFIG.map((sec) =>
      h('div', { class: 'landing-drawer-group' },
        h('p', { class: 'landing-drawer-group-title' }, sec.label),
        h('div', { class: 'landing-drawer-group-list', style: { display: 'grid', gap: '4px' } },
          sec.groups.flatMap((g) => g.items).map((item) => {
            const clickHandler = (e) => {
              close();
              if (item.onClick) {
                e.preventDefault();
                item.onClick();
              } else if (item.href && item.href.startsWith('#')) {
                const target = document.querySelector(item.href);
                if (target) {
                  e.preventDefault();
                  target.scrollIntoView({ behavior: 'smooth' });
                }
              }
            };

            if (item.to) {
              return link(item.to, { class: 'landing-drawer-link', onclick: () => close() },
                icon(item.icon, 16),
                h('span', {}, item.label));
            }

            return h('a', {
              href: item.href || '#',
              target: item.external ? '_blank' : undefined,
              rel: item.external ? 'noopener' : undefined,
              class: 'landing-drawer-link',
              onclick: clickHandler,
            },
            icon(item.icon, 16),
            h('span', {}, item.label));
          })))));

  const drawerFoot = h('div', { class: 'landing-drawer-footer' },
    h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
      h('span', { class: 'muted small' }, 'Appearance'),
      themeSwitch()),
    h('p', { class: 'muted small', style: { margin: 0 } }, 'Delia Lazarescu · @Tech.Unicorn'));

  const drawerPanel = h('aside', { class: 'landing-drawer', role: 'dialog', 'aria-label': 'Navigation menu' },
    drawerHead,
    actionsBox,
    sectionsBox,
    drawerFoot);

  const drawerWrap = h('div', { class: 'landing-drawer-wrap', 'aria-hidden': 'true' },
    backdrop,
    drawerPanel);

  const close = () => {
    drawerWrap.classList.remove('open');
    drawerWrap.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('landing-drawer-open');
  };

  const open = () => {
    drawerWrap.classList.add('open');
    drawerWrap.setAttribute('aria-hidden', 'false');
    document.body.classList.add('landing-drawer-open');
    closeBtn.focus();
  };

  closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  drawerPanel.addEventListener('click', (e) => e.stopPropagation());

  const onKey = (e) => {
    if (e.key === 'Escape' && drawerWrap.classList.contains('open')) {
      close();
    }
  };
  document.addEventListener('keydown', onKey);

  return {
    drawerWrap,
    openDrawer: open,
    closeDrawer: close,
    destroy: () => {
      document.removeEventListener('keydown', onKey);
      drawerWrap.remove();
    },
  };
}

/**
 * Main Pure-JavaScript Landing Page Component.
 * Faithfully adapts https://www.thetechunicorn.com in the Creator Studio design system.
 */
export function renderLanding(view, options = {}) {
  const onAuthClick = options.onAuthClick || (() => navigate('/login'));
  const isAuth = !!options.user;

  const handleBookCall = (type = 'mvp_build') => openBookingModal(type);
  const handleStripePay = (pkg = 'retainer') => openStripeModal(pkg);

  // 1. Navigation Header with official SVG brand logo, grouped actions, and side drawer
  const megaNav = landingMegaMenus({
    onBookCall: handleBookCall,
    onStripePay: handleStripePay,
    onAuthClick,
    isAuth,
  });

  const drawer = landingSideDrawer({
    onBookCall: handleBookCall,
    onStripePay: handleStripePay,
    onAuthClick,
    isAuth,
  });

  // Single profile/actions button containing Bio, Studio, Book Intro Call, and Light/Dark Switch
  const avatar = h('button', {
    class: 'hdr-avatar',
    type: 'button',
    'aria-haspopup': 'true',
    'aria-expanded': 'false',
    'aria-label': 'Tech Unicorn menu',
    title: 'Tech Unicorn · Delia Lazarescu',
  }, 'D');

  let profileMenu = null;
  const closeProfileMenu = () => {
    profileMenu?.remove();
    profileMenu = null;
    avatar.setAttribute('aria-expanded', 'false');
  };

  avatar.addEventListener('click', (e) => {
    e.stopPropagation();
    if (profileMenu) { closeProfileMenu(); return; }
    profileMenu = h('div', { class: 'hdr-menu', role: 'menu', onclick: (ev) => ev.stopPropagation() },
      h('div', { class: 'hdr-menu-head' },
        h('strong', {}, 'Delia Lazarescu'),
        h('span', { class: 'muted small' }, '@Tech.Unicorn · Ex-Google Engineer')),
      themeSwitch(),
      link('/links', { class: 'hdr-menu-item', role: 'menuitem', onclick: closeProfileMenu },
        icon('link', 15), 'Link in Bio'),
      h('button', {
        class: 'hdr-menu-item',
        role: 'menuitem',
        type: 'button',
        onclick: () => { closeProfileMenu(); handleBookCall('mvp_build'); },
      }, icon('calendar', 15), 'Book an Intro Call'),
      h('button', {
        class: 'hdr-menu-item',
        role: 'menuitem',
        type: 'button',
        onclick: () => { closeProfileMenu(); handleStripePay('retainer'); },
      }, icon('credit-card', 15), 'Pay with Stripe'),
      h('button', {
        class: 'hdr-menu-item',
        role: 'menuitem',
        type: 'button',
        onclick: () => { closeProfileMenu(); onAuthClick(); },
      }, icon('sparkle', 15), isAuth ? 'Open Studio Dashboard' : 'Studio Login'));

    avatar.parentElement.append(profileMenu);
    avatar.setAttribute('aria-expanded', 'true');
    profileMenu.querySelector('a, button.hdr-menu-item')?.focus();
  });

  document.addEventListener('click', closeProfileMenu);
  const onEsc = (e) => {
    if (e.key === 'Escape' && profileMenu) {
      closeProfileMenu();
      avatar.focus();
    }
  };
  document.addEventListener('keydown', onEsc);

  const nav = h('header', { class: 'landing-nav', id: 'top' },
    h('div', { class: 'landing-container landing-nav-inner' },
      h('a', {
        href: '#top',
        class: 'landing-logo',
        'aria-label': 'Tech Unicorn Home',
        title: 'Tech Unicorn',
      }, brandMark(28)),
      megaNav,
      h('div', { class: 'landing-header-actions', style: { display: 'flex', alignItems: 'center', gap: '10px' } },
        h('button', {
          type: 'button',
          class: 'landing-drawer-toggle',
          'aria-label': 'Open navigation side drawer',
          title: 'Open Side Drawer',
          onclick: () => drawer.openDrawer(),
        }, icon('menu', 18)),
        h('div', { class: 'hdr-avatar-wrap' }, avatar))));

  // 2. Hero Section
  const hero = h('section', { class: 'landing-hero' },
    h('div', { class: 'landing-container landing-hero-grid' },
      h('div', { class: 'landing-hero-copy' },
        h('div', { class: 'row', style: { gap: '8px', alignItems: 'center', marginBottom: '14px' } },
          brandMark(20),
          h('span', { class: 'landing-eyebrow' }, 'Delia Lazarescu · Ex-Google Engineer · Digital Nomad')),
        h('h1', {}, 'Making tech ', h('em', {}, 'less scary'), ': built from beach cafés in 60+ countries.'),
        h('p', { class: 'landing-lede' }, 'I am a software engineer who left Big Tech to build apps, create content for 470K+ people, and prove you do not need a hoodie and a standing desk to ship great software.'),
        h('div', { class: 'landing-hero-actions' },
          h('button', { type: 'button', class: 'btn primary lg', onclick: () => handleBookCall('mvp_build') }, icon('calendar', 16), 'Book an Intro Call'),
          h('a', { href: '#pricing', class: 'btn ghost lg' }, 'See Packages & Pricing'),
          h('button', { type: 'button', class: 'btn ghost lg', onclick: () => handleStripePay('retainer') }, icon('credit-card', 15), 'Stripe Checkout')),
        h('p', { class: 'muted small' }, 'Free 30-minute call · fixed-scope proposal within 48 hours · no surprise invoices')),
      h('div', { class: 'landing-stats-grid' },
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, '472K+'),
          h('div', { class: 'landing-stat-lbl' }, 'Combined audience across platforms')),
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, '69'),
          h('div', { class: 'landing-stat-lbl' }, 'Countries traveled and worked from')),
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, '2-4', h('span', { style: { fontSize: '0.5em', color: 'var(--accent)' } }, 'wks')),
          h('div', { class: 'landing-stat-lbl' }, 'Idea to shipped production MVP')),
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, 'ex-G'),
          h('div', { class: 'landing-stat-lbl' }, 'Google engineering background')))));

  // 3. Live Animated Capabilities Marquee
  const marqueeItems = [
    'Vibecoded Apps', 'Content Creation', 'Speaking & Keynotes', 'AI Integration', 'Brand Partnerships', 'Technical Advisory',
    'Vibecoded Apps', 'Content Creation', 'Speaking & Keynotes', 'AI Integration', 'Brand Partnerships', 'Technical Advisory',
  ];
  const marquee = h('div', { class: 'landing-marquee', 'aria-hidden': 'true' },
    h('div', { class: 'landing-marquee-track' },
      marqueeItems.flatMap((item) => [
        h('span', {}, item),
        h('span', { class: 'dot' }, '✦'),
      ])));

  // 4. Trusted By Strip
  const trusted = h('section', { class: 'landing-trusted' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-trusted-label' }, 'Brand Partners & Stages'),
      h('ul', { class: 'landing-trusted-list' },
        ['IBM', 'Adobe', 'Airalo', 'Temporal', 'Nomad Cruise', 'Digital Garage Tokyo', 'Vently'].map((name) =>
          h('li', {}, name)))));

  // 5. Section 01: About
  const about = h('section', { class: 'landing-section', id: 'about' },
    h('div', { class: 'landing-container grid grid-sidebar' },
      h('div', {},
        h('p', { class: 'landing-section-eyebrow' }, '01: About'),
        h('div', { style: { marginTop: '24px' } }, brandMark(48))),
      h('div', {},
        h('h2', {}, 'Engineer first. ', h('em', {}, 'Storyteller'), ' always.'),
        h('p', {}, 'I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it was not for them.'),
        h('p', {}, 'The thesis behind everything I make: ', h('strong', {}, 'tech feels scary because it is explained badly.'), ' Whether it is a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.'),
        h('ul', { class: 'about-points', style: { listStyle: 'none', padding: 0, marginTop: '20px', display: 'grid', gap: '12px' } },
          h('li', { class: 'row', style: { gap: '10px' } }, h('span', { style: { color: 'var(--accent)' } }, '✦'), 'Self-taught engineer to Google to independent founder'),
          h('li', { class: 'row', style: { gap: '10px' } }, h('span', { style: { color: 'var(--accent)' } }, '✦'), 'Built and shipped products while living across 60+ countries'),
          h('li', { class: 'row', style: { gap: '10px' } }, h('span', { style: { color: 'var(--accent)' } }, '✦'), '472K+ community across Instagram, YouTube, LinkedIn & TikTok')))));

  // 6. Section 02: Services
  const servicesData = [
    { icon: 'zap', title: 'Vibecoded Apps', desc: 'AI-powered MVPs designed, built, and shipped in 2 to 4 weeks. From napkin sketch to live product with no six-month roadmap required.' },
    { icon: 'play', title: 'Content Creation', desc: 'Short-form video, long-form YouTube, and LinkedIn content that makes your product make sense to real humans.' },
    { icon: 'mic', title: 'Speaking & Workshops', desc: 'Keynotes and hands-on workshops on AI, building in public, and the future of work for conferences, companies, and communities.' },
    { icon: 'briefcase', title: 'Brand Partnerships', desc: 'Authentic integrations with an engaged, tech-curious audience of 470K+. I only partner with products I actually use.' },
    { icon: 'code', title: 'Technical Consulting', desc: 'An ex-Google engineer in your corner for architecture reviews, build-vs-buy decisions, and honest answers about what your team actually needs.' },
    { icon: 'sparkle', title: 'AI Integration', desc: 'Practical AI features for your existing product: shipped, not slide-decked. LLMs, automation, and agents that earn their keep.' },
  ];
  const services = h('section', { class: 'landing-section tinted', id: 'services' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '02: Services'),
        h('h2', {}, 'Six ways we can ', h('em', {}, 'work together'))),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        servicesData.map((s) => h('article', { class: 'landing-card' },
          h('div', { class: 'landing-card-icon', style: { marginBottom: '14px', color: 'var(--accent)' } }, icon(s.icon, 24)),
          h('h3', {}, s.title),
          h('p', { class: 'muted' }, s.desc))))));

  // 7. Section 03: Process
  const stepsData = [
    { num: '1', title: 'Intro call', desc: 'A free 30-minute call. You bring the idea or problem; I will tell you honestly whether it is a 2-week build, an advisory retainer, or not worth your money.' },
    { num: '2', title: 'Fixed-scope proposal', desc: 'Within 48 hours you get a one-page proposal: deliverables, timeline, and a fixed price. No surprise invoices or scope creep.' },
    { num: '3', title: 'Build, demo, ship', desc: 'Weekly demos every Friday while I build, a live product at the end, and 30 days of post-launch support included on every build.' },
  ];
  const process = h('section', { class: 'landing-section', id: 'process' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '03: How it works'),
        h('h2', {}, 'From first call to ', h('em', {}, 'shipped'), ' in weeks')),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        stepsData.map((st) => h('div', { class: 'landing-card' },
          h('div', { style: { width: '40px', height: '40px', borderRadius: '50%', background: 'var(--grad)', color: '#fff', display: 'grid', placeItems: 'center', fontWeight: 'bold', marginBottom: '16px' } }, st.num),
          h('h3', {}, st.title),
          h('p', { class: 'muted' }, st.desc))))));

  // 8. Section 04: Pricing & Packages with integrated Stripe payment triggers
  const pricing = h('section', { class: 'landing-section tinted', id: 'pricing' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '04: Packages & Pricing'),
        h('h2', {}, 'Packages that ', h('em', {}, 'pay for themselves')),
        h('p', { class: 'muted' }, 'Every engagement starts with a free intro call or instant Stripe checkout. Prices below are transparent starting points for typical scope.')),
      h('div', { class: 'grid grid-3', style: { gap: '24px', alignItems: 'start' } },
        // Package 1: Advisory Retainer
        h('article', { class: 'landing-price-card' },
          h('h3', {}, 'Advisory Retainer'),
          h('div', { class: 'row', style: { alignItems: 'baseline' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.2rem', fontWeight: '600' } }, '$3,500'),
            h('span', { class: 'muted small' }, '/month')),
          h('p', { class: 'muted' }, 'An ex-Google engineer on call for your product team.'),
          h('ul', { style: { listStyle: 'none', padding: '12px 0 0', margin: '0', display: 'grid', gap: '8px', borderTop: '1px solid var(--line)' } },
            h('li', { class: 'small' }, '✦ 2 deep-dive strategy calls per month'),
            h('li', { class: 'small' }, '✦ Unlimited async architecture reviews'),
            h('li', { class: 'small' }, '✦ Direct access with 24h response time'),
            h('li', { class: 'small' }, '✦ Cancel anytime, no minimum lock-in')),
          h('div', { style: { display: 'grid', gap: '8px', marginTop: '16px' } },
            h('button', { type: 'button', class: 'btn primary', onclick: () => handleStripePay('retainer') }, icon('credit-card', 14), ' Pay with Stripe ($3,500)'),
            h('button', { type: 'button', class: 'btn ghost', onclick: () => handleBookCall('retainer') }, icon('calendar', 13), 'Book Intro Call'))),

        // Package 2: MVP Build (Featured)
        h('article', { class: 'landing-price-card featured' },
          h('span', { class: 'landing-price-badge' }, 'Most popular'),
          h('h3', {}, 'MVP Build'),
          h('div', { class: 'row', style: { alignItems: 'baseline' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.2rem', fontWeight: '600' } }, 'from $12,000'),
            h('span', { class: 'muted small' }, 'fixed')),
          h('p', { class: 'muted' }, 'Your idea, live in 2 to 4 weeks: UX design, build, and deploy.'),
          h('ul', { style: { listStyle: 'none', padding: '12px 0 0', margin: '0', display: 'grid', gap: '8px', borderTop: '1px solid var(--line)' } },
            h('li', { class: 'small' }, '✦ Full UX design, AI build & cloud deployment'),
            h('li', { class: 'small' }, '✦ Weekly Friday demos to see live progress'),
            h('li', { class: 'small' }, '✦ 30 days of post-launch support included'),
            h('li', { class: 'small' }, '✦ You own 100% of all code, IP & accounts')),
          h('div', { style: { display: 'grid', gap: '8px', marginTop: '16px' } },
            h('button', { type: 'button', class: 'btn primary', onclick: () => handleStripePay('mvp_deposit') }, icon('credit-card', 14), ' Pay 50% Deposit ($6,000)'),
            h('button', { type: 'button', class: 'btn ghost', onclick: () => handleBookCall('mvp_build') }, icon('calendar', 13), 'Book a Free Scoping Call'))),

        // Package 3: Brand Partnership
        h('article', { class: 'landing-price-card' },
          h('h3', {}, 'Brand Partnership'),
          h('div', { class: 'row', style: { alignItems: 'baseline' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.2rem', fontWeight: '600' } }, 'from $5,000'),
            h('span', { class: 'muted small' }, '/campaign')),
          h('p', { class: 'muted' }, 'Your product, explained to 470K+ tech-curious people.'),
          h('ul', { style: { listStyle: 'none', padding: '12px 0 0', margin: '0', display: 'grid', gap: '8px', borderTop: '1px solid var(--line)' } },
            h('li', { class: 'small' }, '✦ Concept, script, high-retention video production'),
            h('li', { class: 'small' }, '✦ 384K Instagram + YouTube & LinkedIn distribution'),
            h('li', { class: 'small' }, '✦ 54M+ lifetime reach track record'),
            h('li', { class: 'small' }, '✦ Full digital ad usage rights included')),
          h('div', { style: { display: 'grid', gap: '8px', marginTop: '16px' } },
            h('button', { type: 'button', class: 'btn primary', onclick: () => handleStripePay('brand_partnership') }, icon('credit-card', 14), ' Book Campaign ($5,000)'),
            link('/mediakit', { class: 'btn ghost' }, icon('file-text', 13), 'Request Media Kit'))))));

  // 9. Section 05: Content & Community
  const platforms = h('section', { class: 'landing-section', id: 'content' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '05: Reach & Content'),
        h('h2', {}, 'Where the ', h('em', {}, 'internet'), ' finds me')),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        h('a', { href: 'https://instagram.com/tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, 'Instagram'),
            h('span', { style: { color: 'var(--accent)' } }, icon('arrow', 13))),
          h('div', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.8rem', fontWeight: '600', color: 'var(--accent)', margin: '10px 0' } }, '384K'),
          h('p', { class: 'muted small' }, 'Daily tech news, AI experiments, and nomad engineering life in 60 seconds.'),
          h('span', { class: 'small', style: { color: 'var(--accent)', fontWeight: '600' } }, '@tech.unicorn')),
        h('a', { href: 'https://youtube.com/@Tech.Unicorn', target: '_blank', rel: 'noopener', class: 'landing-card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, 'YouTube'),
            h('span', { style: { color: 'var(--accent)' } }, icon('arrow', 13))),
          h('div', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.8rem', fontWeight: '600', color: 'var(--accent)', margin: '10px 0' } }, '22K'),
          h('p', { class: 'muted small' }, 'Deep dives, build-alongs, and coding challenges from beach cafés.'),
          h('span', { class: 'small', style: { color: 'var(--accent)', fontWeight: '600' } }, '@Tech.Unicorn')),
        h('a', { href: 'https://linkedin.com/in/delialazarescu', target: '_blank', rel: 'noopener', class: 'landing-card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, 'LinkedIn'),
            h('span', { style: { color: 'var(--accent)' } }, icon('arrow', 13))),
          h('div', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.8rem', fontWeight: '600', color: 'var(--accent)', margin: '10px 0' } }, '25K'),
          h('p', { class: 'muted small' }, 'Career honesty, industry architecture takes, and lessons from Big Tech.'),
          h('span', { class: 'small', style: { color: 'var(--accent)', fontWeight: '600' } }, 'Delia Lazarescu')))));

  // 10. Section 06: Speaking
  const speaking = h('section', { class: 'landing-section tinted', id: 'speaking' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '06: On Stage'),
        h('h2', {}, 'Recently ', h('em', {}, 'keynoting'))),
      h('div', { style: { display: 'grid', gap: '8px' } },
        h('div', { class: 'row', style: { justifyContent: 'space-between', padding: '16px 14px', borderBottom: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { gap: '18px' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '1.2rem', color: 'var(--accent)', fontWeight: '600' } }, '01'),
            h('div', {},
              h('h3', {}, 'Nomad Cruise'),
              h('p', { class: 'muted small' }, 'Keynote on building a tech career without a fixed address: engineering, content, and revenue.'))),
          h('span', { class: 'pf-chip' }, 'Keynote')),
        h('div', { class: 'row', style: { justifyContent: 'space-between', padding: '16px 14px', borderBottom: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { gap: '18px' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '1.2rem', color: 'var(--accent)', fontWeight: '600' } }, '02'),
            h('div', {},
              h('h3', {}, 'Vently Panel'),
              h('p', { class: 'muted small' }, 'Panel on creator-led businesses and what happens when engineers learn distribution.'))),
          h('span', { class: 'pf-chip' }, 'Panel')),
        h('div', { class: 'row', style: { justifyContent: 'space-between', padding: '16px 14px', borderBottom: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { gap: '18px' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '1.2rem', color: 'var(--accent)', fontWeight: '600' } }, '03'),
            h('div', {},
              h('h3', {}, 'Digital Garage Tokyo'),
              h('p', { class: 'muted small' }, 'Workshop on practical AI for non-technical teams: making the intimidating feel obvious.'))),
          h('span', { class: 'pf-chip' }, 'Workshop')))));

  // 11. Conversion CTA Banner
  const cta = h('section', { class: 'landing-container' },
    h('div', { class: 'landing-cta-banner' },
      h('p', { class: 'landing-section-eyebrow' }, 'Let us build something'),
      h('h2', { style: { fontSize: 'clamp(2rem, 4.5vw, 3.4rem)', marginBottom: '12px' } }, 'Have an idea that needs ', h('em', {}, 'shipping?')),
      h('p', { style: { fontSize: '1.15rem', maxWidth: '48ch', marginInline: 'auto', marginBottom: '28px' } }, 'App, campaign, keynote, or something bolder. If it makes tech less scary, let us talk.'),
      h('div', { class: 'row', style: { justifyContent: 'center', gap: '14px', flexWrap: 'wrap' } },
        h('button', { type: 'button', class: 'btn primary lg', onclick: () => handleBookCall('mvp_build') }, icon('calendar', 16), 'Book an Intro Call'),
        h('button', { type: 'button', class: 'btn ghost lg', onclick: () => handleStripePay('retainer') }, icon('credit-card', 16), 'Pay with Stripe'),
        h('a', { href: 'mailto:hello@thetechunicorn.com?subject=Project%20Inquiry', class: 'btn ghost lg' }, 'hello@thetechunicorn.com'))));

  // 12. Signature Typographic Footer with official SVG brand logo
  const footer = h('footer', { class: 'landing-footer' },
    h('div', { class: 'landing-footer-wordmark', 'aria-hidden': 'true' }, 'TECH UNICORN'),
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-footer-top', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px', marginBottom: '24px' } },
        h('div', { class: 'row', style: { gap: '12px', alignItems: 'center' } },
          brandMark(28),
          h('strong', { style: { fontSize: '1.1rem', letterSpacing: '0.04em' } }, 'Tech Unicorn · Delia Lazarescu')),
        h('div', { class: 'row', style: { gap: '12px' } },
          h('button', { type: 'button', class: 'btn sm primary', onclick: () => handleBookCall('mvp_build') }, 'Book Call'),
          h('button', { type: 'button', class: 'btn sm ghost', onclick: () => handleStripePay('retainer') }, 'Stripe Checkout'))),
      h('div', { class: 'landing-footer-links' },
        h('a', { href: 'https://instagram.com/tech.unicorn', target: '_blank', rel: 'noopener' }, 'Instagram'),
        h('a', { href: 'https://youtube.com/@Tech.Unicorn', target: '_blank', rel: 'noopener' }, 'YouTube'),
        h('a', { href: 'https://linkedin.com/in/delialazarescu', target: '_blank', rel: 'noopener' }, 'LinkedIn'),
        h('a', { href: 'https://tiktok.com/@tech.unicorn', target: '_blank', rel: 'noopener' }, 'TikTok'),
        link('/links', {}, 'Link in Bio'),
        link('/mediakit', {}, 'Media Kit'),
        h('a', { href: 'mailto:hello@thetechunicorn.com' }, 'Email')),
      h('p', { class: 'muted small' }, '© 2026 Tech Unicorn · Software development, technical advisory & media services · Made somewhere with good wifi 🌴')));

  const landingBox = h('div', { class: 'landing-box' },
    nav,
    hero,
    marquee,
    trusted,
    about,
    services,
    process,
    pricing,
    platforms,
    speaking,
    cta,
    footer);

  const landingWrapper = h('div', { class: 'landing-page' },
    drawer.drawerWrap,
    landingBox);

  view.replaceChildren(landingWrapper);
  return () => {
    document.removeEventListener('click', closeProfileMenu);
    document.removeEventListener('keydown', onEsc);
    drawer.destroy();
    landingWrapper.remove();
  };
}
