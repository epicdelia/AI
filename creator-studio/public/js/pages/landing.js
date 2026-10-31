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
        h('h1', {}, 'Making tech ', h('em', {}, 'less scary'), ': built from beach cafés in 60+ countries.'),
        h('p', { class: 'landing-lede' }, 'I am a software engineer who left Big Tech to build apps, create content for 470K+ people, and prove you do not need a hoodie and a standing desk to ship great software.'),
        h('div', { class: 'landing-hero-actions' },
          h('button', { type: 'button', class: 'btn primary lg', onclick: () => handleBookCall('mvp_build') }, icon('calendar', 16), 'Book an Intro Call'),
          h('a', { href: '#pricing', class: 'btn ghost lg' }, 'See Packages & Pricing')),
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

  // 5. Section 01: About with single authentic Delia portrait & credentials in rows with show more / show less
  const aboutPhoto = h('div', { class: 'landing-about-showcase' },
    h('div', { class: 'landing-about-photo-card' },
      h('img', {
        src: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
        alt: 'Delia Lazarescu · Ex-Google Engineer & Founder',
        class: 'landing-about-photo',
        loading: 'eager',
      }),
      h('div', { class: 'landing-about-badge' },
        h('span', { style: { color: 'var(--accent)' } }, '✦'),
  'Delia Lazarescu · Ex-Google Engineer & Founder')));

  const credsData = [
    {
      title: '69 Countries',
      desc: 'Shipped code from beach cafes, bullet trains, and colivings.',
      icon: 'globe',
      extra: 'Tested remote workflows across Japan, Indonesia, Portugal, Brazil, and 65+ other countries while maintaining high reliability on client production apps.',
    },
    {
      title: 'Keynote Speaker',
      desc: 'Nomad Cruise, WestVisions, and Tech Summits worldwide.',
      icon: 'mic',
      extra: 'Keynote talks breaking down practical AI agents, vibecoding production software, and navigating the tech industry without losing your humanity.',
    },
    {
      title: '472K+ Community',
      desc: 'Explaining AI, software engineering, and nomadic building in plain English.',
      icon: 'sparkle',
      extra: 'Demystifying complex systems, AI tooling, and engineering truths for a community of developers, founders, and tech professionals across YouTube, Instagram, and LinkedIn.',
    },
  ];

  let credsExpanded = false;
  const credsList = h('div', { class: 'landing-about-rows' });
  const toggleCredsBtn = h('button', {
    type: 'button',
    class: 'landing-about-toggle-btn',
    'aria-expanded': 'false',
    onclick: () => {
      credsExpanded = !credsExpanded;
      renderCredsRows();
    },
  });

  function renderCredsRows() {
    toggleCredsBtn.setAttribute('aria-expanded', String(credsExpanded));
    toggleCredsBtn.replaceChildren(
      credsExpanded ? 'Show less' : 'Show more',
      icon(credsExpanded ? 'chevron-up' : 'chevron-down', 13));

    credsList.replaceChildren(
      ...credsData.map((c) =>
        h('div', { class: 'landing-about-row-item' },
          h('div', { class: 'landing-about-row-icon' }, icon(c.icon, 18)),
          h('div', { style: { flex: '1' } },
            h('h4', { class: 'landing-about-row-title' }, c.title),
            h('p', { class: 'landing-about-row-desc' }, c.desc),
            credsExpanded ? h('p', { class: 'landing-about-row-extra' }, c.extra) : null))),
      toggleCredsBtn);
  }

  renderCredsRows();

  const about = h('section', { class: 'landing-section', id: 'about' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'grid grid-2', style: { gap: 'clamp(32px, 5vw, 64px)', alignItems: 'center' } },
        h('div', {},
          h('p', { class: 'landing-section-eyebrow' }, '01: About'),
          h('h2', {}, 'Engineer first. ', h('em', {}, 'Storyteller'), ' always.'),
          h('p', {}, 'I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it was not for them.'),
          h('p', {}, 'The thesis behind everything I make: ', h('strong', {}, 'tech feels scary because it is explained badly.'), ' Whether it is a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.'),
          h('ul', { class: 'about-points', style: { listStyle: 'none', padding: 0, marginTop: '20px', display: 'grid', gap: '12px' } },
            h('li', { class: 'row', style: { gap: '10px' } }, h('span', { style: { color: 'var(--accent)' } }, '✦'), 'Self-taught engineer to Google to independent founder'),
            h('li', { class: 'row', style: { gap: '10px' } }, h('span', { style: { color: 'var(--accent)' } }, '✦'), 'Built and shipped products while living across 60+ countries'),
  h('li', { class: 'row', style: { gap: '10px' } }, h('span', { style: { color: 'var(--accent)' } }, '✦'), '472K+ community across Instagram, YouTube, LinkedIn & TikTok')),
          credsList),
        aboutPhoto)));

  // 6. Section 02: Services with dashboard composer polish
  const servicesData = [
    {
      category: 'MVP Engineering',
      badge: '2 to 4 wks',
      icon: 'zap',
      title: 'Vibecoded Apps',
      desc: 'AI-powered MVPs designed, built, and shipped in 2 to 4 weeks. From napkin sketch to live cloud deployment.',
      deliverables: [
        'Interactive prototype and design review within 5 days',
        'Full production codebase transferred to your GitHub repo',
        'Integrated Stripe billing, auth, and automated database backups',
      ],
      tags: ['React / Vite', 'Python', 'Stripe', 'Docker'],
      ctaService: 'mvp_build',
    },
    {
      category: 'Production AI',
      badge: 'Practical LLMs',
      icon: 'sparkle',
      title: 'AI Integration',
      desc: 'Practical AI features for your existing product: shipped, not slide-decked. Autonomous agents that earn their keep.',
      deliverables: [
        'Custom LLM subagent pipelines and tool-routing workflows',
        'Deterministic evaluation benchmarks and safety guardrails',
        'Optimized inference latency with streaming responses',
      ],
      tags: ['Claude Code', 'Gemini API', 'Agents', 'Vector DB'],
      ctaService: 'retainer',
    },
    {
      category: 'Ex-Google Advisory',
      badge: 'Weekly Syncs',
      icon: 'code',
      title: 'Technical Consulting',
      desc: 'An ex-Google engineer in your corner for architecture reviews, build-vs-buy decisions, and scaling strategy.',
      deliverables: [
        'Unbiased build versus buy infrastructure assessments',
        'Scalability and cloud security audits before launch',
        'Async code reviews and emergency escalation coverage',
      ],
      tags: ['Architecture', 'Cloud Security', 'Scalability'],
      ctaService: 'strategy_session',
    },
    {
      category: 'Audience & Growth',
      badge: '472K+ Audience',
      icon: 'play',
      title: 'Content Creation',
      desc: 'Short-form video, long-form YouTube deep-dives, and LinkedIn content that makes complex tech feel obvious.',
      deliverables: [
        'Full video scripting, hands-on demos, and production editing',
        'Multi-platform syndication across YouTube, Instagram & LinkedIn',
        'Native integrations that build lasting developer goodwill',
      ],
      tags: ['YouTube 4K', 'Shorts', 'LinkedIn Pulse'],
      ctaService: 'brand_deal',
    },
    {
      category: 'Keynotes & Stages',
      badge: 'Global Stages',
      icon: 'mic',
      title: 'Speaking & Workshops',
      desc: 'Keynotes and hands-on workshops on AI agents, building in public, and the future of remote engineering.',
      deliverables: [
        'In-person and virtual keynote talks tailored to your conference',
        'Live coding demos showing autonomous agents in real time',
        'Interactive Q&A and VIP speaker session engagement',
      ],
      tags: ['Nomad Cruise', 'Tech Summits', 'Live Coding'],
      ctaService: 'strategy_session',
    },
    {
      category: 'Creator Partnerships',
      badge: 'Verified Fit',
      icon: 'briefcase',
      title: 'Brand Partnerships',
      desc: 'Authentic integrations with an engaged audience of 472K+ developers, founders, and tech-curious professionals.',
      deliverables: [
        'Dedicated feature video or authentic 60-second integration',
        'Custom tracking links, social promotion, and analytics reporting',
        'Strict policy: I only endorse developer tools I genuinely use',
      ],
      tags: ['Dev Tools', 'SaaS', 'High Retention'],
      ctaService: 'brand_deal',
    },
  ];

  const services = h('section', { class: 'landing-section tinted', id: 'services' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '02: Services'),
        h('h2', {}, 'Six ways we can ', h('em', {}, 'work together')),
        h('p', { class: 'muted' }, 'Every engagement comes with direct access to Delia, transparent deliverables, and zero corporate fluff.')),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        servicesData.map((s) => h('article', { class: 'landing-service-card' },
          h('div', {},
            h('div', { class: 'landing-service-head' },
              h('span', { class: 'landing-service-cat-badge' }, icon(s.icon, 13), s.category),
              h('span', { class: 'landing-service-badge' }, s.badge)),
            h('h3', { class: 'landing-service-title' }, s.title),
            h('p', { class: 'landing-service-desc' }, s.desc),
            h('ul', { class: 'landing-service-deliverables' },
              s.deliverables.map((d) => h('li', { class: 'landing-service-deliv-item' },
                h('span', { class: 'landing-service-deliv-check' }, icon('check', 13)),
                h('span', {}, d))))),
          h('div', { class: 'landing-service-footer' },
            h('div', { class: 'landing-service-tags' },
              s.tags.map((t) => h('span', { class: 'landing-service-tag' }, t))),
            h('button', {
              type: 'button',
              class: 'landing-service-btn',
              onclick: () => handleBookCall(s.ctaService),
            }, 'Book Call', icon('arrow', 12))))))));

  // 7. Multi-Platform Social Media Showcase with Tabs (YouTube Default, Instagram Reels, TikTok, Twitter/X, Threads)
  let activePlatform = 'youtube';

  const defaultYtVideos = [
    {
      id: '5p3Mg4VEkbI',
      title: '10 Brutal Truths I Learned as a Google Software Engineer',
      desc: 'Corporate politics, manager dynamics, imposter syndrome, and learning to write production-grade code that scales.',
      duration: '14:48',
      views: '340K views',
      tag: 'Google & Career',
      color: '#EA4335',
      url: 'https://www.youtube.com/watch?v=5p3Mg4VEkbI',
      thumb: 'https://i.ytimg.com/vi/5p3Mg4VEkbI/hqdefault.jpg',
    },
    {
      id: 'DSXRUnBCYtQ',
      title: 'I am back at Google! (after I quit... here is why)',
      desc: 'The honest reasons for giving up Big Tech compensation to travel 60+ countries and build indie software.',
      duration: '12:15',
      views: '215K views',
      tag: 'Nomad Freedom',
      color: '#4285F4',
      url: 'https://www.youtube.com/watch?v=DSXRUnBCYtQ',
      thumb: 'https://i.ytimg.com/vi/DSXRUnBCYtQ/hqdefault.jpg',
    },
    {
      id: 'YJlJ12Txhkg',
      title: 'Is OpenClaw Still Worth It in 2026? (Ex-Google Engineer)',
      desc: 'A candid review of self-hosted AI agent frameworks, critical security vulnerabilities, and what to build instead.',
      duration: '16:24',
      views: '185K views',
      tag: 'AI Architecture',
      color: '#F06A9B',
      url: 'https://www.youtube.com/watch?v=YJlJ12Txhkg',
      thumb: 'https://i.ytimg.com/vi/YJlJ12Txhkg/hqdefault.jpg',
    },
    {
      id: '5jYD7ZRMJ5I',
      title: 'WTF Is Happening To The Job Market (Ex-Googler Perspective)',
      desc: 'Big tech buyouts, AI infrastructure spending shifts, and how software engineers should navigate 2026.',
      duration: '15:32',
      views: '275K views',
      tag: 'Tech Market',
      color: '#FBBC05',
      url: 'https://www.youtube.com/watch?v=5jYD7ZRMJ5I',
      thumb: 'https://i.ytimg.com/vi/5jYD7ZRMJ5I/hqdefault.jpg',
    },
    {
      id: 'iT0iE6GGuQk',
      title: 'How A.I. crosses my way as a Digital Nomad (Keynote Talk)',
      desc: 'Conference keynote on practical automation scripts, global mobility, and staying human in an automated world.',
      duration: '24:18',
      views: '98K views',
      tag: 'Keynote Talk',
      color: '#34A853',
      url: 'https://www.youtube.com/watch?v=iT0iE6GGuQk',
      thumb: 'https://i.ytimg.com/vi/iT0iE6GGuQk/hqdefault.jpg',
    },
    {
      id: 'z5lKZX9dB-4',
      title: 'On the Importance of Big Tech Clout, Especially as a Woman in Tech',
      desc: 'Key interview on landing Google engineering, using Big Tech credibility, and building independently.',
      duration: '18:50',
      views: '165K views',
      tag: 'Women in Tech',
      color: '#D97706',
      url: 'https://www.youtube.com/watch?v=z5lKZX9dB-4',
      thumb: 'https://i.ytimg.com/vi/z5lKZX9dB-4/hqdefault.jpg',
    },
  ];

  const defaultReels = [
    {
      id: 'C_aiEmail7d',
      title: 'POV: You let AI answer your emails for 7 days',
      caption: 'It archived my landlord, replied to my mum, and booked a meeting I dodged for a month. Rules beat prompts every single time.',
      views: '340K views',
      likes: '24.5K',
      comments: '890',
      audio: 'Original Audio · Tech Unicorn',
      tag: 'AI Experiment',
      gradient: 'linear-gradient(150deg, #180922 0%, #4a044e 45%, #db2777 100%)',
      url: 'https://www.instagram.com/reel/C_aiEmail7d/',
    },
    {
      id: 'C_nomadCode60',
      title: 'Shipping production code from 60 countries',
      caption: 'Bullet trains in Japan, beach cafes in Portugal, and colivings in Bali. How to build software without a permanent office.',
      views: '520K views',
      likes: '41.2K',
      comments: '1.4K',
      audio: 'Lofi Coding Beats',
      tag: 'Nomad Life',
      gradient: 'linear-gradient(150deg, #091224 0%, #0369a1 45%, #06b6d4 100%)',
      url: 'https://www.instagram.com/reel/C_nomadCode60/',
    },
    {
      id: 'C_vibecodingArch',
      title: 'Stop vibe coding without architecture reviews',
      caption: 'AI code generation is fast until state management collapses. Here is the architecture checklist I run on every MVP build.',
      views: '410K views',
      likes: '32.8K',
      comments: '980',
      audio: 'Tech Unicorn Sound',
      tag: 'Engineering',
      gradient: 'linear-gradient(150deg, #120e1f 0%, #581c87 45%, #a855f7 100%)',
      url: 'https://www.instagram.com/reel/C_vibecodingArch/',
    },
    {
      id: 'C_bigTechOfferIndie',
      title: 'Why I turned down Big Tech to stay indie',
      caption: 'Golden handcuffs are real. Giving up $400k+ compensation to own 100% of my time, IP, and freedom was terrifying and worth it.',
      views: '680K views',
      likes: '58.4K',
      comments: '2.1K',
      audio: 'Original audio · Delia',
      tag: 'Career Honesty',
      gradient: 'linear-gradient(150deg, #220808 0%, #991b1b 45%, #f97316 100%)',
      url: 'https://www.instagram.com/reel/C_bigTechOfferIndie/',
    },
    {
      id: 'C_aiDevStack2026',
      title: 'My 3-tool AI development stack in 2026',
      caption: 'The exact tools that survive my 30-day testing gauntlet. Fast LLM scaffolding, vector indexing, and asynchronous job queues.',
      views: '290K views',
      likes: '21.3K',
      comments: '750',
      audio: 'Tech Beats',
      tag: 'Tools & Stack',
      gradient: 'linear-gradient(150deg, #171206 0%, #b45309 45%, #eab308 100%)',
      url: 'https://www.instagram.com/reel/C_aiDevStack2026/',
    },
    {
      id: 'C_remoteRule1',
      title: 'The 1 rule to survive remote software engineering',
      caption: 'Asynchronous documentation beats sync meetings every single time. Here is how I protect my deep work blocks across time zones.',
      views: '380K views',
      likes: '29.1K',
      comments: '1.1K',
      audio: 'Original audio',
      tag: 'Productivity',
      gradient: 'linear-gradient(150deg, #021a14 0%, #047857 45%, #10b981 100%)',
      url: 'https://www.instagram.com/reel/C_remoteRule1/',
    },
  ];

  const defaultTikToks = [
    {
      id: 'tt_tokyoCafe',
      title: 'Day in the life: Coding an AI SaaS from a café in Tokyo',
      desc: '7:00 AM matcha, 4 hours of pure focused feature coding, sushi lunch, and evening code review.',
      views: '620K views',
      likes: '48.2K',
      shares: '1.2K',
      tag: '#TokyoTech',
      gradient: 'linear-gradient(150deg, #0d0f17 0%, #083344 50%, #06b6d4 100%)',
      url: 'https://www.tiktok.com/@tech.unicorn',
    },
    {
      id: 'tt_bigTechVsIndie',
      title: 'Things in Big Tech that give indie founders a heart attack',
      desc: '14 people in an alignment meeting to change a button color vs pushing directly to main on a Friday.',
      views: '890K views',
      likes: '76.5K',
      shares: '3.4K',
      tag: '#ExGoogle',
      gradient: 'linear-gradient(150deg, #160a12 0%, #4c0519 50%, #f43f5e 100%)',
      url: 'https://www.tiktok.com/@tech.unicorn',
    },
    {
      id: 'tt_agentTesting',
      title: 'How I test AI agents before letting them touch client databases',
      desc: 'Sandboxed SQLite replicas, dry-run mutation modes, and strict rate limits.',
      views: '340K views',
      likes: '28.9K',
      shares: '920',
      tag: '#AIArchitect',
      gradient: 'linear-gradient(150deg, #0b0f19 0%, #1e1b4b 50%, #6366f1 100%)',
      url: 'https://www.tiktok.com/@tech.unicorn',
    },
    {
      id: 'tt_backpackSetup',
      title: 'My nomadic desk setup packing list (everything in 1 backpack)',
      desc: '14-inch MacBook Pro, Roost stand, mechanical 60% keyboard, travel power bank, and noise-cancelling buds.',
      views: '510K views',
      likes: '42.7K',
      shares: '1.8K',
      tag: '#DigitalNomad',
      gradient: 'linear-gradient(150deg, #071712 0%, #064e3b 50%, #10b981 100%)',
      url: 'https://www.tiktok.com/@tech.unicorn',
    },
    {
      id: 'tt_vectorEmbeddings',
      title: 'Explaining vector embeddings to non-tech friends in 60s',
      desc: 'Imagine a library where books with similar vibes sit next to each other in 1536-dimensional space.',
      views: '430K views',
      likes: '36.1K',
      shares: '1.1K',
      tag: '#TechTok',
      gradient: 'linear-gradient(150deg, #170b18 0%, #4a044e 50%, #d946ef 100%)',
      url: 'https://www.tiktok.com/@tech.unicorn',
    },
    {
      id: 'tt_wrapperTrap',
      title: 'Do not fall into the AI wrapper trap: what to build instead',
      desc: 'Thin wrappers over basic LLM APIs get commoditized. Build proprietary workflows with private data feedback loops.',
      views: '710K views',
      likes: '61.3K',
      shares: '2.9K',
      tag: '#Startups',
      gradient: 'linear-gradient(150deg, #180f08 0%, #7c2d12 50%, #f97316 100%)',
      url: 'https://www.tiktok.com/@tech.unicorn',
    },
  ];

  const defaultTweets = [
    {
      id: 'tw_agentTeams',
      author: 'Delia Lazarescu',
      handle: '@techunicorn_',
      date: '2d ago',
      content: 'Hot take: The next generation of $10M+ software companies will be built by teams of 1 to 3 engineers leveraging specialized autonomous agents and ruthless focus. Here is the 5-layer stack we use to ship in 2 weeks: [Thread]',
      views: '142K',
      reposts: '1.8K',
      likes: '8.4K',
      url: 'https://x.com/techunicorn_',
    },
    {
      id: 'tw_latency',
      author: 'Delia Lazarescu',
      handle: '@techunicorn_',
      date: '4d ago',
      content: 'After reviewing 40+ AI startup codebases this quarter: 80% of latency bottlenecks are not the LLM API call. They are unoptimized SQL queries, missing database connection pools, and blocking async loops.',
      views: '210K',
      reposts: '2.4K',
      likes: '11.2K',
      url: 'https://x.com/techunicorn_',
    },
    {
      id: 'tw_leavingGoogle',
      author: 'Delia Lazarescu',
      handle: '@techunicorn_',
      date: '1w ago',
      content: 'The hardest part of leaving Google was not the compensation or free food. It was learning to ship imperfect v1s in 14 days instead of spending 6 months in architecture review committees.',
      views: '380K',
      reposts: '4.1K',
      likes: '19.5K',
      url: 'https://x.com/techunicorn_',
    },
    {
      id: 'tw_nomadRule',
      author: 'Delia Lazarescu',
      handle: '@techunicorn_',
      date: '2w ago',
      content: 'Building apps from 60+ countries taught me one golden engineering rule: offline-first local state and idempotent background jobs are mandatory. Wifi will betray you at the worst possible moment.',
      views: '165K',
      reposts: '1.9K',
      likes: '9.1K',
      url: 'https://x.com/techunicorn_',
    },
  ];

  const defaultThreads = [
    {
      id: 'th_validation',
      author: 'Delia Lazarescu',
      handle: '@tech.unicorn',
      date: '1d ago',
      content: 'Honest reflection: You do not need a 10-person agency to validate a software product. One ex-Big Tech engineer with a solid AI workflow can take you from napkin wireframe to paying customers in 21 days.',
      likes: '4.2K',
      replies: '380',
      url: 'https://www.threads.net/@tech.unicorn',
    },
    {
      id: 'th_lisbon',
      author: 'Delia Lazarescu',
      handle: '@tech.unicorn',
      date: '3d ago',
      content: 'Working from a seaside café in Lisbon today. The secret to nomadic engineering is brutal focus blocks and never scheduling meetings before 2 PM local time.',
      likes: '6.8K',
      replies: '490',
      url: 'https://www.threads.net/@tech.unicorn',
    },
    {
      id: 'th_monolith',
      author: 'Delia Lazarescu',
      handle: '@tech.unicorn',
      date: '5d ago',
      content: 'A lot of founders ask me: "Should we build with microservices or a monolithic stack?" Always start monolithic with clean modular domains. Complexity is easy to add later; simplicity is impossible to recover.',
      likes: '8.1K',
      replies: '610',
      url: 'https://www.threads.net/@tech.unicorn',
    },
    {
      id: 'th_favoriteTool',
      author: 'Delia Lazarescu',
      handle: '@tech.unicorn',
      date: '1w ago',
      content: 'What is the one AI tool you started using this year that genuinely saved you at least 5 hours every week? Drop your favorites below.',
      likes: '3.9K',
      replies: '520',
      url: 'https://www.threads.net/@tech.unicorn',
    },
  ];

  const platformsData = {
    youtube: defaultYtVideos,
    instagram: defaultReels,
    tiktok: defaultTikToks,
    twitter: defaultTweets,
    threads: defaultThreads,
  };

  const platformsConfig = [
    { id: 'youtube', label: 'YouTube', icon: 'brand:youtube', badge: '22K Subs', url: 'https://youtube.com/@Tech.Unicorn', actionLabel: 'Visit YouTube' },
    { id: 'instagram', label: 'Instagram Reels', icon: 'brand:instagram', badge: '384K', url: 'https://instagram.com/tech.unicorn', actionLabel: 'Follow on Instagram' },
    { id: 'tiktok', label: 'TikTok', icon: 'brand:tiktok', badge: '45K', url: 'https://tiktok.com/@tech.unicorn', actionLabel: 'Follow on TikTok' },
    { id: 'twitter', label: 'Twitter / X', icon: 'brand:x', badge: '28K', url: 'https://x.com/techunicorn_', actionLabel: 'Follow on X' },
    { id: 'threads', label: 'Threads', icon: 'brand:threads', badge: '15K', url: 'https://threads.net/@tech.unicorn', actionLabel: 'Follow on Threads' },
  ];

  const ytTrack = h('div', { class: 'landing-yt-track', id: 'landing-social-track' });

  function openVideoModal({ title, videoId, url, platform }) {
    const existing = document.getElementById('landing-video-modal');
    if (existing) existing.remove();

    if (!videoId) {
      if (url) window.open(url, '_blank', 'noopener');
      return;
    }

    const platformLabels = {
      youtube: 'YouTube · @Tech.Unicorn',
      instagram: 'Instagram Reel · @tech.unicorn',
      tiktok: 'TikTok · @tech.unicorn',
    };

    const platformIcons = {
      youtube: 'brand:youtube',
      instagram: 'brand:instagram',
      tiktok: 'brand:tiktok',
    };

    const isVertical = platform === 'instagram' || platform === 'tiktok';

    const modal = h('div', {
      id: 'landing-video-modal',
      class: 'landing-video-modal-backdrop',
      onclick: (e) => {
        if (e.target === modal) modal.remove();
      },
    },
      h('div', { class: `landing-video-modal-dialog ${isVertical ? 'vertical' : 'landscape'}` },
        h('button', {
          type: 'button',
          class: 'landing-video-modal-close',
          'aria-label': 'Close player',
          onclick: () => modal.remove(),
        }, icon('x', 18)),
        h('div', { class: `landing-video-modal-player ${isVertical ? 'vertical' : 'landscape'}` },
          h('iframe', {
            src: `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1`,
            allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
            allowfullscreen: true,
            title: title || 'Video preview',
          })),
        h('div', { class: 'landing-video-modal-foot' },
          h('div', { style: { minWidth: 0 } },
            h('strong', { style: { display: 'block', fontSize: '0.92rem', color: '#fff', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, title),
            h('span', { style: { fontSize: '0.78rem', color: 'rgba(255,255,255,0.7)' } }, platformLabels[platform] || 'Video')),
          h('a', {
            href: url || '#',
            target: '_blank',
            rel: 'noopener',
            class: 'btn primary btn-sm',
            style: { flexShrink: 0 },
          }, icon(platformIcons[platform] || 'play', 14), platform === 'instagram' ? 'View on IG' : (platform === 'tiktok' ? 'View on TikTok' : 'Watch on YouTube')))));

    document.body.appendChild(modal);
  }

  function renderYtCard(vid) {
    const thumb = vid.thumb || vid.thumbnail || `https://i.ytimg.com/vi/${vid.id}/hqdefault.jpg`;
    return h('a', {
      href: vid.url || `https://www.youtube.com/watch?v=${vid.id}`,
      target: '_blank',
      rel: 'noopener',
      class: 'landing-yt-card',
      title: `Watch "${vid.title}" on YouTube`,
      onclick: (e) => {
        if (!e.ctrlKey && !e.metaKey && !e.shiftKey) {
          e.preventDefault();
          openVideoModal({ title: vid.title, videoId: vid.id, url: vid.url, platform: 'youtube' });
        }
      },
    },
      h('div', { class: 'landing-yt-thumb-wrap' },
        h('img', {
          src: thumb,
          alt: vid.title,
          class: 'landing-yt-thumb',
          loading: 'lazy',
          referrerpolicy: 'no-referrer',
          onerror: (e) => {
            e.currentTarget.src = `https://img.youtube.com/vi/${vid.id}/mqdefault.jpg`;
          },
        }),
        h('div', { class: 'landing-yt-play' },
          h('div', { class: 'landing-yt-play-circle' }, icon('play', 20))),
        h('span', { class: 'landing-yt-duration' }, vid.duration || 'Video')),
      h('div', { class: 'landing-yt-info' },
        h('div', { class: 'landing-yt-meta' },
          h('span', {}, vid.tag || 'YouTube'),
          h('span', { class: 'dot' }, '·'),
          h('span', { class: 'muted' }, vid.views || 'YouTube')),
        h('h3', { class: 'landing-yt-title' }, vid.title),
        h('p', { class: 'landing-yt-desc' }, vid.desc)));
  }

  function renderReelCard(reel) {
    const thumb = reel.thumb || (reel.video_id ? `https://i.ytimg.com/vi/${reel.video_id}/hqdefault.jpg` : '/images/549290582_18080889986298812_7772569637968987998_n.jpg');
    return h('a', {
      href: reel.url || 'https://instagram.com/tech.unicorn',
      target: '_blank',
      rel: 'noopener',
      class: 'landing-reel-card',
      title: `Watch Reel "${reel.title}" on Instagram`,
      onclick: (e) => {
        if (reel.video_id && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
          e.preventDefault();
          openVideoModal({ title: reel.title, videoId: reel.video_id, url: reel.url, platform: 'instagram' });
        }
      },
    },
      h('img', {
        src: thumb,
        alt: reel.title,
        class: 'landing-reel-bg',
        loading: 'lazy',
        referrerpolicy: 'no-referrer',
        onerror: (e) => {
          if (reel.video_id) {
            e.currentTarget.src = `https://img.youtube.com/vi/${reel.video_id}/hqdefault.jpg`;
          }
        },
      }),
      h('div', { class: 'landing-reel-overlay' }),
      h('div', { class: 'landing-reel-top' },
        h('span', { class: 'landing-reel-badge' },
          icon('brand:instagram', 13),
          h('span', {}, reel.tag || 'Reels')),
        h('span', { class: 'landing-reel-badge' }, icon('play', 11), h('span', {}, reel.views || 'Reels'))),
      h('div', { class: 'landing-reel-center-play' }, icon('play', 18)),
      h('div', { class: 'landing-reel-bottom' },
        h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' } },
          h('img', {
            src: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
            alt: 'Delia Lazarescu',
            style: { width: '22px', height: '22px', borderRadius: '50%', objectFit: 'cover', border: '1px solid rgba(255,255,255,0.4)', flexShrink: '0' },
          }),
          h('span', { style: { fontSize: '0.72rem', fontWeight: '700', color: 'rgba(255, 255, 255, 0.95)', textShadow: '0 1px 4px rgba(0,0,0,0.8)' } }, '@tech.unicorn')),
        h('h3', { class: 'landing-reel-title' }, reel.title),
        h('p', { class: 'landing-reel-caption' }, reel.caption),
        h('div', { class: 'landing-reel-stats' },
          h('span', { style: { display: 'inline-flex', alignItems: 'center', gap: '4px' } },
            icon('heart', 12),
            h('span', {}, reel.likes || '54K')),
          h('span', { style: { display: 'inline-flex', alignItems: 'center', gap: '4px', opacity: '0.9' } },
            icon('music', 11),
            h('span', {}, reel.audio || 'Tech Unicorn Audio')))));
  }

  function renderTikTokCard(item) {
    const thumb = item.thumb || (item.video_id ? `https://i.ytimg.com/vi/${item.video_id}/hqdefault.jpg` : '/images/549290582_18080889986298812_7772569637968987998_n.jpg');
    return h('a', {
      href: item.url || 'https://tiktok.com/@tech.unicorn',
      target: '_blank',
      rel: 'noopener',
      class: 'landing-tiktok-card',
      title: `Watch "${item.title}" on TikTok`,
      onclick: (e) => {
        if (item.video_id && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
          e.preventDefault();
          openVideoModal({ title: item.title, videoId: item.video_id, url: item.url, platform: 'tiktok' });
        }
      },
    },
      h('img', {
        src: thumb,
        alt: item.title,
        class: 'landing-reel-bg',
        loading: 'lazy',
        referrerpolicy: 'no-referrer',
        onerror: (e) => {
          if (item.video_id) {
            e.currentTarget.src = `https://img.youtube.com/vi/${item.video_id}/hqdefault.jpg`;
          }
        },
      }),
      h('div', { class: 'landing-reel-overlay' }),
      h('div', { class: 'landing-reel-top' },
        h('span', { class: 'landing-reel-badge' },
          icon('brand:tiktok', 13),
          h('span', {}, item.tag || '#TechTok')),
        h('span', { class: 'landing-reel-badge' }, icon('eye', 12), h('span', {}, item.views || 'TikTok'))),
      h('div', { class: 'landing-reel-center-play', style: { background: 'rgba(37, 244, 238, 0.95)', color: '#000', boxShadow: '0 4px 18px rgba(37, 244, 238, 0.5)' } },
        icon('play', 18)),
      h('div', { class: 'landing-reel-bottom' },
        h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' } },
          h('img', {
            src: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
            alt: 'Delia Lazarescu',
            style: { width: '22px', height: '22px', borderRadius: '50%', objectFit: 'cover', border: '1px solid rgba(255,255,255,0.4)', flexShrink: '0' },
          }),
          h('span', { style: { fontSize: '0.72rem', fontWeight: '700', color: 'rgba(255, 255, 255, 0.95)', textShadow: '0 1px 4px rgba(0,0,0,0.8)' } }, '@tech.unicorn')),
        h('h3', { class: 'landing-reel-title' }, item.title),
        h('p', { class: 'landing-reel-caption' }, item.desc),
        h('div', { class: 'landing-reel-stats' },
          h('span', { style: { display: 'inline-flex', alignItems: 'center', gap: '4px' } },
            icon('heart', 12),
            h('span', {}, item.likes || '48K')),
          h('span', { style: { display: 'inline-flex', alignItems: 'center', gap: '4px', opacity: '0.9' } },
            icon('arrow', 11),
            h('span', {}, item.shares ? `${item.shares} shares` : 'Viral')))));
  }

  function renderTweetCard(item) {
    return h('a', {
      href: item.url || 'https://x.com/techunicorn_',
      target: '_blank',
      rel: 'noopener',
      class: 'landing-tweet-card',
      style: {
        minWidth: '300px',
        maxWidth: '360px',
        flex: '0 0 330px',
        background: 'var(--panel)',
        border: '1px solid var(--line)',
        borderRadius: '18px',
        padding: '20px',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
        gap: '14px',
        textDecoration: 'none',
        color: 'inherit',
      },
      title: `View post on X by ${item.author}`,
    },
      h('div', { class: 'landing-tweet-head' },
        h('div', { class: 'landing-tweet-user' },
          h('img', {
            src: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
            alt: item.author,
            class: 'landing-tweet-avatar',
            style: { width: '40px', height: '40px', borderRadius: '50%', objectFit: 'cover', flexShrink: '0', border: '1px solid var(--line)' },
          }),
          h('div', { class: 'landing-tweet-meta' },
            h('span', { class: 'landing-tweet-name' },
              item.author,
              h('span', { style: { color: 'var(--accent)', fontSize: '0.75rem', display: 'inline-flex' } }, icon('check', 12))),
            h('span', { class: 'landing-tweet-handle' }, `${item.handle} · ${item.date}`))),
        icon('brand:x', 18)),
      h('p', { class: 'landing-tweet-body' }, item.content),
      h('div', { class: 'landing-tweet-footer' },
        h('span', { class: 'landing-tweet-metric' }, icon('eye', 13), h('span', {}, item.views)),
        h('span', { class: 'landing-tweet-metric' }, icon('repeat', 13), h('span', {}, item.reposts)),
        h('span', { class: 'landing-tweet-metric' }, icon('heart', 13), h('span', {}, item.likes))));
  }

  function renderThreadCard(item) {
    return h('a', {
      href: item.url || 'https://threads.net/@tech.unicorn',
      target: '_blank',
      rel: 'noopener',
      class: 'landing-thread-card',
      style: {
        minWidth: '300px',
        maxWidth: '360px',
        flex: '0 0 330px',
        background: 'var(--panel)',
        border: '1px solid var(--line)',
        borderRadius: '18px',
        padding: '20px',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
        gap: '14px',
        textDecoration: 'none',
        color: 'inherit',
      },
      title: `View thread by ${item.author}`,
    },
      h('div', { class: 'landing-tweet-head' },
        h('div', { class: 'landing-tweet-user' },
          h('img', {
            src: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
            alt: item.author,
            class: 'landing-tweet-avatar',
            style: { width: '40px', height: '40px', borderRadius: '50%', objectFit: 'cover', flexShrink: '0', border: '1px solid var(--line)' },
          }),
          h('div', { class: 'landing-tweet-meta' },
            h('span', { class: 'landing-tweet-name' }, item.author),
            h('span', { class: 'landing-tweet-handle' }, `${item.handle} · ${item.date}`))),
        icon('brand:threads', 18)),
      h('p', { class: 'landing-tweet-body' }, item.content),
      h('div', { class: 'landing-tweet-footer' },
        h('span', { class: 'landing-tweet-metric' }, icon('heart', 13), h('span', {}, `${item.likes} likes`)),
        h('span', { class: 'landing-tweet-metric' }, icon('chat', 13), h('span', {}, `${item.replies} replies`))));
  }

  const visitChannelBtn = h('a', {
    href: 'https://youtube.com/@Tech.Unicorn',
    target: '_blank',
    rel: 'noopener',
    class: 'landing-process-btn no-underline',
  }, 'Visit YouTube', icon('external', 13));

  const tabButtons = [];

  function updateSocialTrack() {
    const list = platformsData[activePlatform] || [];
    let cards = [];
    if (activePlatform === 'youtube') {
      cards = list.map(renderYtCard);
    } else if (activePlatform === 'instagram') {
      cards = list.map(renderReelCard);
    } else if (activePlatform === 'tiktok') {
      cards = list.map(renderTikTokCard);
    } else if (activePlatform === 'twitter') {
      cards = list.map(renderTweetCard);
    } else if (activePlatform === 'threads') {
      cards = list.map(renderThreadCard);
    }
    ytTrack.replaceChildren(...cards);
    ytTrack.scrollTo({ left: 0, behavior: 'smooth' });

    // Update active tab buttons
    tabButtons.forEach(({ id, btn }) => {
      btn.classList.toggle('is-active', id === activePlatform);
    });

    // Update channel link button
    const cfg = platformsConfig.find((p) => p.id === activePlatform);
    if (cfg) {
      visitChannelBtn.href = cfg.url;
      visitChannelBtn.replaceChildren(cfg.actionLabel, icon('external', 13));
    }
  }

  const tabsContainer = h('div', { class: 'landing-media-tabs', role: 'tablist' },
    platformsConfig.map((cfg) => {
      const btn = h('button', {
        type: 'button',
        class: `landing-media-tab ${cfg.id === activePlatform ? 'is-active' : ''}`,
        role: 'tab',
        'aria-selected': cfg.id === activePlatform ? 'true' : 'false',
        onclick: () => {
          activePlatform = cfg.id;
          updateSocialTrack();
        },
      },
        icon(cfg.icon, 16),
        h('span', {}, cfg.label),
        h('span', { class: 'landing-media-tab-badge' }, cfg.badge));
      tabButtons.push({ id: cfg.id, btn });
      return btn;
    }));

  // Initial population with default YouTube videos
  updateSocialTrack();

  // Fetch updated multi-platform feeds from backend
  api('GET', 'landing/social-feed').then((res) => {
    if (res && res.platforms) {
      if (Array.isArray(res.platforms.youtube) && res.platforms.youtube.length) {
        platformsData.youtube = res.platforms.youtube;
      }
      if (Array.isArray(res.platforms.instagram) && res.platforms.instagram.length) {
        platformsData.instagram = res.platforms.instagram;
      }
      if (Array.isArray(res.platforms.tiktok) && res.platforms.tiktok.length) {
        platformsData.tiktok = res.platforms.tiktok;
      }
      if (Array.isArray(res.platforms.twitter) && res.platforms.twitter.length) {
        platformsData.twitter = res.platforms.twitter;
      }
      if (Array.isArray(res.platforms.threads) && res.platforms.threads.length) {
        platformsData.threads = res.platforms.threads;
      }
      updateSocialTrack();
    }
  }).catch((err) => {
    console.warn('Could not load dynamic social feed:', err);
  });

  const prevBtn = h('button', {
    type: 'button',
    class: 'landing-yt-nav-btn',
    'aria-label': 'Scroll left',
    onclick: () => ytTrack.scrollBy({ left: -340, behavior: 'smooth' }),
  }, icon('arrow-left', 16));

  const nextBtn = h('button', {
    type: 'button',
    class: 'landing-yt-nav-btn',
    'aria-label': 'Scroll right',
    onclick: () => ytTrack.scrollBy({ left: 340, behavior: 'smooth' }),
  }, icon('arrow', 16));

  const ytSection = h('section', { class: 'landing-section landing-yt-section', id: 'youtube' },
    h('div', { class: 'landing-container' },
      h('span', { id: 'content', style: { position: 'relative', top: '-40px', display: 'block', visibility: 'hidden' } }),
      h('div', { class: 'landing-yt-head' },
        h('div', {},
          h('p', { class: 'landing-section-eyebrow' }, '02b: Content & Social Channels'),
          h('h2', { style: { margin: 0 } }, 'Watch, listen, and follow across ', h('em', {}, 'platforms')),
          h('p', { class: 'muted', style: { margin: '6px 0 0', fontSize: '0.86rem' } },
            'From 15-minute architectural deep dives on YouTube to 60-second reels on Instagram and raw engineering insights on X and Threads.')),
        h('div', { class: 'landing-yt-controls' }, visitChannelBtn, prevBtn, nextBtn)),
      tabsContainer,
      ytTrack));

  // 8. Section 03: Process - 4-Phase Engineering Sprint Lifecycle
  const processSteps = [
    {
      num: '01',
      phase: 'Phase 01 · Diagnostic',
      time: 'Day 0 · 30 Min',
      title: 'Scoping & Architecture Audit',
      desc: 'Direct technical consultation with Delia. Bring your product concept, codebase, or bottleneck for an honest build viability assessment and architecture blueprint.',
      deliverables: [
        'Direct consultation with Delia (zero sales reps)',
        'Build-vs-buy engineering assessment',
        'High-level technical architecture plan',
        'Honest feasibility & next steps appraisal',
      ],
      tag: 'Zero Commitment',
      actionLabel: 'Book Intro Call',
      onAction: () => handleBookCall('audit'),
    },
    {
      num: '02',
      phase: 'Phase 02 · Blueprint',
      time: 'Within 48h',
      title: 'Fixed Scope & Milestone Spec',
      desc: 'A transparent, binding one-page blueprint with concrete deliverables, weekly sprint milestones, modern tech stack selection, and a fixed-price guarantee.',
      deliverables: [
        'Comprehensive component & API architecture',
        'Firm fixed-price quote & contract scope',
        'Precise week-by-week delivery roadmap',
        'Clear acceptance criteria & performance targets',
      ],
      tag: '100% Fixed Price',
      actionLabel: 'View Packages',
      href: '#pricing',
    },
    {
      num: '03',
      phase: 'Phase 03 · Execution',
      time: 'Weeks 1 to 3',
      title: 'Agile Sprints & Friday Demos',
      desc: 'Rapid iterative engineering with complete transparency. Clean, production-grade codebases with private GitHub commits and recorded Loom demos every Friday.',
      deliverables: [
        'Private GitHub repository access from day 1',
        'Weekly Friday recorded Loom walkthroughs',
        'Live password-protected staging environment',
        'Direct async Slack or WhatsApp channel',
      ],
      tag: 'Full Transparency',
      actionLabel: 'See Tech Stack',
      href: '#services',
    },
    {
      num: '04',
      phase: 'Phase 04 · Deployment',
      time: 'Week 4 + 30 Days',
      title: 'Production Ship & Warranty',
      desc: 'Turnkey deployment to production cloud infrastructure (GCP, AWS, Vercel). Complete code handover, DNS setup, environment secrets, and 30-day bug warranty.',
      deliverables: [
        'Production launch & DNS/domain configuration',
        'Clean codebase handover & 100% IP ownership',
        'Documented developer README & ops runbook',
        '30-day post-launch bug fix warranty',
      ],
      tag: '30-Day Warranty',
      actionLabel: 'Explore Retainers',
      href: '#pricing',
    },
  ];

  const process = h('section', { class: 'landing-section tinted', id: 'process' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '03: How it works'),
        h('h2', {}, 'From first call to ', h('em', {}, 'shipped'), ' in weeks'),
        h('p', { class: 'muted', style: { maxWidth: '640px', margin: '8px auto 0' } },
          'No bloatware, no endless agency meetings, and no junior handoffs. A battle-tested engineering sprint lifecycle refined across 60+ shipped projects.')),
      h('div', { class: 'landing-process-grid' },
        processSteps.map((step) => {
          const actionBtn = step.href
            ? h('a', {
                href: step.href,
                class: 'landing-process-btn no-underline',
                onclick: (e) => {
                  if (step.href.startsWith('#')) {
                    const el = document.querySelector(step.href);
                    if (el) {
                      e.preventDefault();
                      el.scrollIntoView({ behavior: 'smooth' });
                    }
                  }
                },
              }, step.actionLabel, icon('arrow', 13))
            : h('button', {
                type: 'button',
                class: 'landing-process-btn',
                onclick: step.onAction,
              }, step.actionLabel, icon('arrow', 13));

          return h('div', { class: 'landing-process-card' },
            h('div', {},
              h('div', { class: 'landing-process-head' },
                h('span', { class: 'landing-process-num-badge' }, step.num),
                h('span', { class: 'landing-process-time-pill' }, step.time)),
              h('div', { class: 'landing-process-phase' }, step.phase),
              h('h3', { class: 'landing-process-title' }, step.title),
              h('p', { class: 'landing-process-desc' }, step.desc),
              h('ul', { class: 'landing-process-deliverables' },
                step.deliverables.map((item) =>
                  h('li', { class: 'landing-process-deliv-item' },
                    h('span', { class: 'landing-process-deliv-check' }, icon('check', 14)),
                    h('span', {}, item))))),
            h('div', { class: 'landing-process-footer' },
              h('span', { class: 'landing-process-tag' }, step.tag),
              actionBtn));
        }))));

  // 9. Section 04: Packages & Pricing with exact ag-pt structure
  const pricing = h('section', { class: 'landing-section', id: 'pricing' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '04: Packages & Pricing'),
        h('h2', {}, 'Packages that ', h('em', {}, 'pay for themselves')),
        h('p', { class: 'muted' }, 'Every engagement starts with a free scoping call or instant Stripe checkout. Transparent scopes and zero surprise invoices.')),
      h('div', { class: 'ag-pt-wrap', role: 'region', tabindex: '0', 'aria-label': 'Plans' },
        h('div', { class: 'ag-pt-grid', style: { '--ag-pt-cols': '4' } },
          // Card 1: Strategy Session
          h('div', { class: 'ag-pt-card' },
            h('span', { class: 'ag-pt-flag is-empty', 'aria-hidden': 'true' }),
            h('div', { class: 'ag-pt-head' },
              h('div', { class: 'ag-pt-title' },
                h('span', { class: 'ag-pt-voxel', 'aria-hidden': 'true' }, brandMark(18)),
                h('h3', { class: 'ag-pt-name' }, 'Strategy Call')),
              h('div', { class: 'ag-pt-price' },
                h('span', { class: 'ag-pt-cur' }, '$'),
                h('span', { class: 'ag-pt-price-now' }, '500'),
                h('span', { class: 'ag-pt-per' }, '/session')),
              h('p', { class: 'ag-pt-clarify' }, 'one-time private call'),
              h('button', { type: 'button', class: 'ag-pt-yearly', onclick: () => handleBookCall('consulting') }, '90 minutes private session'),
              h('div', { class: 'ag-pt-desc-block' },
                h('p', { class: 'ag-pt-desc' }, 'Private 90-minute architecture review, code audit, and AI product roadmap.'),
                h('button', { type: 'button', class: 'ag-pt-desc-more', onclick: () => handleBookCall('consulting') }, 'Read details'))),
            h('div', { class: 'ag-pt-actions' },
              h('div', { class: 'ag-pt-cta' },
                h('button', { type: 'button', class: 'vs-btn vs-btn-secondary vs-btn-sm', onclick: () => handleStripePay('advisory_session') }, 'Book session ($500)'),
                h('p', { class: 'ag-pt-trust' }, '100% satisfaction guarantee'))),
            h('div', { class: 'ag-pt-body' },
              h('div', { class: 'ag-pt-slot' },
                h('div', { class: 'ag-pt-hosting-strip' },
                  h('div', { class: 'ag-pt-hosting-head' },
                    h('span', { class: 'ag-pt-hbadge', title: 'Private 1:1 Video Room' }, icon('play', 14), ' Google Meet 1:1')),
                  h('div', { class: 'ag-pt-hosting-grid' },
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Duration'), h('strong', { class: 'ag-pt-hm-val' }, '90 min'), h('span', { class: 'ag-pt-hm-sub' }, 'private session')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Seats'), h('strong', { class: 'ag-pt-hm-val' }, '1:1'), h('span', { class: 'ag-pt-hm-sub' }, 'your team')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Turnaround'), h('strong', { class: 'ag-pt-hm-val' }, '24h'), h('span', { class: 'ag-pt-hm-sub' }, 'booking')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Support'), h('strong', { class: 'ag-pt-hm-val' }, '7 days'), h('span', { class: 'ag-pt-hm-sub' }, 'follow-up async'))),
                  h('div', { class: 'ag-pt-hosting-perks' },
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Full recording & transcript'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Architecture summary report'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Follow-up Q&A for 7 days')))),
              h('p', { class: 'ag-pt-inherits is-empty', 'aria-hidden': 'true' }),
              h('ul', { class: 'ag-pt-benefits', role: 'list' },
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, '90 minutes private strategy call')),
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Full code & architecture audit')),
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'AI model selection & roadmap')),
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, '7-day follow-up async support')),
                h('li', { class: 'ag-pt-benefit is-absent' }, h('span', { class: 'ag-pt-bicon is-absent' }, icon('x', 14)), h('span', { class: 'ag-pt-btext' }, 'Ongoing weekly on-call access')),
                h('li', { class: 'ag-pt-benefit is-absent' }, h('span', { class: 'ag-pt-bicon is-absent' }, icon('x', 14)), h('span', { class: 'ag-pt-btext' }, 'Full production MVP builds'))))),

          // Card 2: Advisory Retainer
          h('div', { class: 'ag-pt-card' },
            h('span', { class: 'ag-pt-flag is-empty', 'aria-hidden': 'true' }),
            h('div', { class: 'ag-pt-head' },
              h('div', { class: 'ag-pt-title' },
                h('span', { class: 'ag-pt-voxel', 'aria-hidden': 'true' }, brandMark(18)),
                h('h3', { class: 'ag-pt-name' }, 'Retainer')),
              h('div', { class: 'ag-pt-price' },
                h('span', { class: 'ag-pt-cur' }, '$'),
                h('span', { class: 'ag-pt-price-now' }, '3,500'),
                h('span', { class: 'ag-pt-per' }, '/mo')),
              h('p', { class: 'ag-pt-clarify' }, 'billed monthly'),
              h('button', { type: 'button', class: 'ag-pt-yearly', onclick: () => handleStripePay('retainer') }, 'Save 15% with quarterly'),
              h('div', { class: 'ag-pt-desc-block' },
                h('p', { class: 'ag-pt-desc' }, 'An ex-Google engineer on call for your product and engineering team.'),
                h('button', { type: 'button', class: 'ag-pt-desc-more', onclick: () => handleBookCall('retainer') }, 'Read details'))),
            h('div', { class: 'ag-pt-actions' },
              h('div', { class: 'ag-pt-cta' },
                h('button', { type: 'button', class: 'vs-btn vs-btn-secondary vs-btn-sm', onclick: () => handleStripePay('retainer') }, 'Start retainer ($3,500)'),
                h('p', { class: 'ag-pt-trust' }, 'Cancel anytime, no lock-in'))),
            h('div', { class: 'ag-pt-body' },
              h('div', { class: 'ag-pt-slot' },
                h('div', { class: 'ag-pt-hosting-strip' },
                  h('div', { class: 'ag-pt-hosting-head' },
                    h('span', { class: 'ag-pt-hbadge', title: 'Advisory Infrastructure' }, icon('sparkle', 14), ' Google Cloud & AI Advisory')),
                  h('div', { class: 'ag-pt-hosting-grid' },
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Strategy Calls'), h('strong', { class: 'ag-pt-hm-val' }, '2 calls'), h('span', { class: 'ag-pt-hm-sub' }, 'monthly')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Async Reviews'), h('strong', { class: 'ag-pt-hm-val' }, 'Unlimited'), h('span', { class: 'ag-pt-hm-sub' }, 'code & PRs')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Response SLA'), h('strong', { class: 'ag-pt-hm-val' }, '24h'), h('span', { class: 'ag-pt-hm-sub' }, 'guaranteed')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Direct Line'), h('strong', { class: 'ag-pt-hm-val' }, 'Slack / TG'), h('span', { class: 'ag-pt-hm-sub' }, 'priority access'))),
                  h('div', { class: 'ag-pt-hosting-perks' },
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Bi-weekly deep-dive strategy calls'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Architecture & PR code reviews'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Engineering hiring vetting')))),
              h('p', { class: 'ag-pt-inherits' }, 'Everything in Strategy Call, and:'),
              h('ul', { class: 'ag-pt-benefits', role: 'list' },
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, '2 deep-dive strategy calls every month')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Unlimited async PR & code reviews')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Direct Slack/Telegram access with 24h SLA')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Build-vs-buy and AI model evaluations')),
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Cancel anytime with no minimum lock-in')),
                h('li', { class: 'ag-pt-benefit is-absent' }, h('span', { class: 'ag-pt-bicon is-absent' }, icon('x', 14)), h('span', { class: 'ag-pt-btext' }, 'Dedicated 2-4 week full MVP builds'))))),

          // Card 3: MVP Build (Popular)
          h('div', { class: 'ag-pt-card is-popular' },
            h('span', { class: 'ag-pt-ribbon' }, 'Most popular'),
            h('span', { class: 'ag-pt-flag is-empty', 'aria-hidden': 'true' }),
            h('div', { class: 'ag-pt-head' },
              h('div', { class: 'ag-pt-title' },
                h('span', { class: 'ag-pt-voxel', 'aria-hidden': 'true' }, brandMark(18)),
                h('h3', { class: 'ag-pt-name' }, 'MVP Build')),
              h('div', { class: 'ag-pt-price' },
                h('span', { class: 'ag-pt-cur' }, '$'),
                h('span', { class: 'ag-pt-price-now' }, '12,000'),
                h('span', { class: 'ag-pt-per' }, 'fixed')),
              h('p', { class: 'ag-pt-clarify' }, 'fixed scope delivery'),
              h('button', { type: 'button', class: 'ag-pt-yearly', onclick: () => handleStripePay('mvp_deposit') }, 'Pay 50% deposit to start ($6,000)'),
              h('div', { class: 'ag-pt-desc-block' },
                h('p', { class: 'ag-pt-desc' }, 'Your idea, live in 2 to 4 weeks: UX design, AI build, and cloud deploy.'),
                h('button', { type: 'button', class: 'ag-pt-desc-more', onclick: () => handleBookCall('mvp_build') }, 'Read details'))),
            h('div', { class: 'ag-pt-actions' },
              h('div', { class: 'ag-pt-cta' },
                h('button', { type: 'button', class: 'vs-btn vs-btn-primary vs-btn-sm', onclick: () => handleStripePay('mvp_deposit') }, 'Pay 50% deposit ($6,000)'),
                h('p', { class: 'ag-pt-trust' }, '30 days post-launch warranty included'))),
            h('div', { class: 'ag-pt-body' },
              h('div', { class: 'ag-pt-slot' },
                h('div', { class: 'ag-pt-hosting-strip' },
                  h('div', { class: 'ag-pt-hosting-head' },
                    h('span', { class: 'ag-pt-hbadge', title: 'Production Cloud Deployment' }, icon('zap', 14), ' Google Cloud Production')),
                  h('div', { class: 'ag-pt-hosting-grid' },
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Timeline'), h('strong', { class: 'ag-pt-hm-val' }, '2 to 4 wks'), h('span', { class: 'ag-pt-hm-sub' }, 'production ready')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Live Demos'), h('strong', { class: 'ag-pt-hm-val' }, 'Weekly'), h('span', { class: 'ag-pt-hm-sub' }, 'Friday live reviews')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'IP Ownership'), h('strong', { class: 'ag-pt-hm-val' }, '100%'), h('span', { class: 'ag-pt-hm-sub' }, 'all source & code')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Warranty'), h('strong', { class: 'ag-pt-hm-val' }, '30 days'), h('span', { class: 'ag-pt-hm-sub' }, 'post-launch support'))),
                  h('div', { class: 'ag-pt-hosting-perks' },
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Full UX design, database & responsive app'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Cloud deployment with custom domain & SSL'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), '30 days of post-launch engineering support')))),
              h('p', { class: 'ag-pt-inherits' }, 'Everything in Retainer, and:'),
              h('ul', { class: 'ag-pt-benefits', role: 'list' },
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Complete product: UX, AI build & cloud deployment')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Weekly Friday demos to see real live progress')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Production codebase with clean documentation')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, '100% ownership of all source code, IP & accounts')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, '30 days of dedicated post-launch bug warranty')),
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Fixed price scope with zero surprise invoices'))))),

          // Card 4: Brand Partnership
          h('div', { class: 'ag-pt-card' },
            h('span', { class: 'ag-pt-flag is-empty', 'aria-hidden': 'true' }),
            h('div', { class: 'ag-pt-head' },
              h('div', { class: 'ag-pt-title' },
                h('span', { class: 'ag-pt-voxel', 'aria-hidden': 'true' }, brandMark(18)),
                h('h3', { class: 'ag-pt-name' }, 'Brand Deal')),
              h('div', { class: 'ag-pt-price' },
                h('span', { class: 'ag-pt-cur' }, '$'),
                h('span', { class: 'ag-pt-price-now' }, '5,000'),
                h('span', { class: 'ag-pt-per' }, '/campaign')),
              h('p', { class: 'ag-pt-clarify' }, 'multi-platform campaign'),
              h('button', { type: 'button', class: 'ag-pt-yearly', onclick: () => handleStripePay('brand_partnership') }, 'Save 20% on 3-episode package'),
              h('div', { class: 'ag-pt-desc-block' },
                h('p', { class: 'ag-pt-desc' }, 'Your product explained to an engaged audience of 472K+ tech professionals.'),
                h('button', { type: 'button', class: 'ag-pt-desc-more', onclick: () => navigate('/mediakit') }, 'View Media Kit'))),
            h('div', { class: 'ag-pt-actions' },
              h('div', { class: 'ag-pt-cta' },
                h('button', { type: 'button', class: 'vs-btn vs-btn-secondary vs-btn-sm', onclick: () => handleStripePay('brand_partnership') }, 'Book campaign ($5,000)'),
                h('p', { class: 'ag-pt-trust' }, 'Full digital ad rights included'))),
            h('div', { class: 'ag-pt-body' },
              h('div', { class: 'ag-pt-slot' },
                h('div', { class: 'ag-pt-hosting-strip' },
                  h('div', { class: 'ag-pt-hosting-head' },
                    h('span', { class: 'ag-pt-hbadge', title: 'Verified Audience Metrics' }, icon('users', 14), ' 472K+ Audience Reach')),
                  h('div', { class: 'ag-pt-hosting-grid' },
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Instagram'), h('strong', { class: 'ag-pt-hm-val' }, '384K'), h('span', { class: 'ag-pt-hm-sub' }, 'followers')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'YouTube'), h('strong', { class: 'ag-pt-hm-val' }, '22K'), h('span', { class: 'ag-pt-hm-sub' }, 'subscribers')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'LinkedIn'), h('strong', { class: 'ag-pt-hm-val' }, '25K'), h('span', { class: 'ag-pt-hm-sub' }, 'tech pros')),
                    h('div', { class: 'ag-pt-hmetric' }, h('span', { class: 'ag-pt-hm-label' }, 'Lifetime Views'), h('strong', { class: 'ag-pt-hm-val' }, '54M+'), h('span', { class: 'ag-pt-hm-sub' }, 'impressions'))),
                  h('div', { class: 'ag-pt-hosting-perks' },
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Concept, script & high-retention video'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Native cross-platform distribution'),
                    h('span', { class: 'ag-pt-hperk' }, icon('check', 11), 'Perpetual digital advertising usage rights')))),
              h('p', { class: 'ag-pt-inherits' }, 'Dedicated sponsor production, including:'),
              h('ul', { class: 'ag-pt-benefits', role: 'list' },
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'High-retention technical integration & video')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, '384K Instagram, 22K YouTube & 25K LinkedIn reach')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Full digital ad commercial rights included')),
                h('li', { class: 'ag-pt-benefit is-added' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Executive post-campaign performance report')),
                h('li', { class: 'ag-pt-benefit is-carried' }, h('span', { class: 'ag-pt-bicon' }, icon('check', 15)), h('span', { class: 'ag-pt-btext' }, 'Direct link tracking in bio & descriptions')))))))));

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

  // 12. Multi-column Refined Footer with Voxel Logo and Platform Columns
  const footer = h('footer', { class: 'landing-footer' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-footer-grid' },
        // Brand Column with Voxel Logo (Span 2)
        h('div', { class: 'landing-footer-brand' },
          h('a', {
            href: '#top',
            class: 'landing-footer-brand-logo',
            'aria-label': 'Tech Unicorn Top',
          },
            h('span', { class: 'landing-footer-brand-icon' }, brandMark(20)),
            h('strong', {}, 'Tech Unicorn')),
          h('p', { style: { fontSize: '0.84rem', fontWeight: '700', color: 'var(--accent)', margin: '0 0 6px' } },
            'Delia Lazarescu · Ex-Google Engineer · Digital Nomad'),
          h('p', { style: { fontSize: '0.82rem', color: 'var(--muted)', lineHeight: '1.6', margin: 0, maxWidth: '380px' } },
            'AI-powered software development, technical advisory, and high-impact media. Shipped from beach cafés and bullet trains across 60+ countries.')),

        // Column 1: Core Engineering
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Core Engineering'),
          h('a', { href: '#services', class: 'landing-footer-link' }, 'Vibecoded Apps (MVPs)'),
          h('a', { href: '#services', class: 'landing-footer-link' }, 'AI Integration & Agents'),
          h('a', { href: '#services', class: 'landing-footer-link' }, 'Technical Consulting'),
          h('a', { href: '#pricing', class: 'landing-footer-link' }, 'Packages & Retainers'),
          h('a', { href: '#process', class: 'landing-footer-link' }, 'Delivery in 2-4 Weeks')),

        // Column 2: Content & Media
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Content & Channels'),
          h('a', { href: 'https://youtube.com/@Tech.Unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'YouTube Deep-Dives'),
          h('a', { href: 'https://instagram.com/tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'Instagram & Reels'),
          h('a', { href: 'https://linkedin.com/in/delialazarescu', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'LinkedIn Articles'),
          h('a', { href: 'https://tiktok.com/@tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'TikTok Shorts'),
          link('/mediakit', { class: 'landing-footer-link' }, 'Media Kit & Demographics')),

        // Column 3: Platform & Connect
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Studio & Connect'),
          link('/auth', { class: 'landing-footer-link', style: { color: 'var(--accent)', fontWeight: '700' } }, 'Studio Suite Sign In'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleBookCall('mvp_build') }, 'Book an Intro Call'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleStripePay('retainer') }, 'Pay with Stripe'),
          link('/links', { class: 'landing-footer-link' }, 'Link in Bio Hub'),
          h('a', { href: 'mailto:hello@thetechunicorn.com', class: 'landing-footer-link' }, 'hello@thetechunicorn.com'))),

      // Subfooter Bar
      h('div', { class: 'landing-footer-sub' },
        h('div', {},
          h('span', {}, '© 2026 Tech Unicorn · Delia Lazarescu. All rights reserved.')),
        h('div', { class: 'landing-footer-sub-right' },
          h('a', { href: '#top', class: 'landing-footer-sub-link' }, 'Back to top'),
          link('/links', { class: 'landing-footer-sub-link' }, 'Links'),
  link('/mediakit', { class: 'landing-footer-sub-link' }, 'Media Kit')))));

  const landingBox = h('div', { class: 'landing-box' },
    nav,
    hero,
    marquee,
    trusted,
    about,
    services,
    ytSection,
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
