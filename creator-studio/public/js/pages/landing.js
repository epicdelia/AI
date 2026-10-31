// Delia Lazarescu (Tech Unicorn) Public Landing Page
// Fully implemented in pure JavaScript using the Creator Studio dashboard design system.
// Zero build step, reactive DOM helper h(), native SVG icons, dark/light theme tokens.
import { h, link, navigate, api, modal, toast, field } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';
import { contentSection } from '../landing-content.js';
import { reachSection } from '../landing-reach.js';
import { speakingSection } from '../landing-speaking.js';
import { ctaSection } from '../landing-cta.js';

/**
 * Interactive Booking Modal for 30-minute Intro Calls.
 * Logs the lead into the Creator Studio deals pipeline; Delia confirms the time by email.
 */
export function openBookingModal(initialType = 'mvp_build', service = '') {
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

    const submitBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('calendar', 14), ' Request 30-Min Call');
    const cancelBtn = h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Cancel');

    const form = h('form', {
      class: 'booking-form',
      onsubmit: async (e) => {
        e.preventDefault();
        if (submitting) return;
        submitting = true;
        submitBtn.disabled = true;
        submitBtn.replaceChildren(icon('sparkle', 14), ' Sending request...');

        try {
          const payload = {
            name: nameInput.value.trim(),
            email: emailInput.value.trim(),
            company: companyInput.value.trim(),
            project_type: typeSelect.value,
            preferred_date: dateInput.value,
            preferred_time: timeSelect.value,
            notes: notesInput.value.trim(),
            service: service || undefined,
          };

          const res = await api('POST', 'booking', payload);
          showSuccess(res);
        } catch (err) {
          submitting = false;
          submitBtn.disabled = false;
          submitBtn.replaceChildren(icon('calendar', 14), ' Request 30-Min Call');
          toast(err.message || 'Could not send your request. Please try again.', 'err');
        }
      },
    },
      h('div', { class: 'grid grid-2', style: { gap: '14px' } },
        field('Your Name', nameInput, 'Required'),
        field('Work Email', emailInput, 'Delia confirms the time here')),
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
          h('p', { class: 'muted small', style: { margin: 0 } }, service ? `About: ${service}` : 'Discuss your MVP, retainer, or brand partnership with Delia.'))),
      form);

    function showSuccess(data) {
      const appt = data.appointment || {};
      const successBox = h('div', { class: 'booking-confirmed-card', style: { textAlign: 'center', padding: '16px 8px' } },
        h('div', { style: { width: '56px', height: '56px', borderRadius: '50%', background: 'rgba(34, 197, 94, 0.15)', color: '#22c55e', display: 'grid', placeItems: 'center', margin: '0 auto 16px' } },
          icon('check', 28)),
        h('h3', { style: { margin: '0 0 6px', fontSize: '1.4rem' } }, 'Request received!'),
        h('p', { class: 'muted', style: { margin: '0 0 20px' } }, data.message || `Delia will confirm your time at ${emailInput.value}.`),
        h('div', { class: 'card', style: { textAlign: 'left', padding: '16px', marginBottom: '20px', background: 'var(--panel-2)', border: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Booking Reference:'),
            h('strong', { class: 'small' }, data.booking_ref || '-')),
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Requested time:'),
            h('strong', { class: 'small' }, `${appt.date} at ${appt.time}`)),
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('span', { class: 'small muted' }, 'Service:'),
            h('strong', { class: 'small' }, appt.service || 'Intro Consultation'))),
        h('div', { class: 'row', style: { justifyContent: 'center', gap: '12px' } },
          h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Done')));

      contentBox.replaceChildren(successBox);
    }

    return contentBox;
  }, { wide: true });
}

/**
 * Interactive Stripe Checkout Modal.
 * Redirects to Stripe Checkout when it's set up; otherwise logs an invoice request. Nothing is marked paid here.
 */
export function openStripeModal(pkgId = 'retainer') {
  const PACKAGES = {
    retainer: { id: 'retainer', name: 'Advisory Retainer (Monthly)', price: '$3,500/mo', desc: 'Ex-Google engineer on call: 2 strategy calls, unlimited async reviews, 24h response. Billed monthly, cancel anytime.' },
    mvp_deposit: { id: 'mvp_deposit', name: 'MVP Build: 50% Project Deposit', price: '$6,000', desc: '50% upfront project deposit for 2-4 week fixed MVP build with Friday live demos.' },
    mvp_full: { id: 'mvp_full', name: 'MVP Build: Full Fixed Package', price: '$12,000', desc: 'Complete fixed-scope MVP: UX design, AI build, cloud deployment, and 30-day post-launch support.' },
    brand_partnership: { id: 'brand_partnership', name: 'Brand Partnership Campaign', price: '$5,000', desc: 'Dedicated technical integration across Instagram (384K), YouTube (22K) & LinkedIn (25K).' },
    media_kit_sponsor: { id: 'media_kit_sponsor', name: 'Verified Media Kit Feature', price: '$1,500', desc: 'Featured sponsor placement in Delia’s live media kit and rate card deck.' },
    advisory_session: { id: 'advisory_session', name: '1-Hour Architecture & Strategy Session', price: '$500', desc: '60-minute 1-on-1 architecture review, build-vs-buy analysis, or technical roadmap consultation.' },
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
    let payLabel = ` Pay ${selected.price} with Stripe`;
    api('GET', 'stripe/packages').then((p) => {
      if (p.stripe_configured || submitting) return;
      payLabel = ' Request an invoice';
      payBtn.replaceChildren(icon('file-text', 14), payLabel);
    }).catch(() => {});

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

          showRequested(res);
        } catch (err) {
          submitting = false;
          payBtn.disabled = false;
          payBtn.replaceChildren(icon('credit-card', 14), payLabel);
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
        field('Billing Email', emailInput, 'Receipt or invoice sent here')),
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

    // Only reached when Stripe isn't available: a request for an invoice, not a payment.
    function showRequested(data) {
      const receipt = h('div', { style: { textAlign: 'center', padding: '16px 8px' } },
        h('div', { style: { width: '56px', height: '56px', borderRadius: '50%', background: 'rgba(34, 197, 94, 0.15)', color: '#22c55e', display: 'grid', placeItems: 'center', margin: '0 auto 16px' } },
          icon('check', 28)),
        h('h3', { style: { margin: '0 0 6px', fontSize: '1.4rem' } }, 'Request received'),
        h('p', { class: 'muted', style: { margin: '0 0 18px' } }, data.message || `Nothing was charged. You’ll get an invoice for ${selected.name} by email.`),
        h('div', { class: 'card', style: { textAlign: 'left', padding: '16px', marginBottom: '20px', background: 'var(--panel-2)', border: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
            h('span', { class: 'small muted' }, 'Amount due:'),
            h('strong', { class: 'small', style: { color: 'var(--accent)' } }, `$${(data.amount || 0).toLocaleString()} USD`)),
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('span', { class: 'small muted' }, 'Package:'),
            h('strong', { class: 'small' }, selected.name))),
        h('div', { class: 'row', style: { justifyContent: 'center', gap: '12px' } },
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
        { label: isAuth ? 'Open Media Kit' : 'Request Media Kit', icon: 'file-text', onClick: () => (isAuth ? navigate('/mediakit') : onBookCall('brand_deal')) },
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
            { label: '472K+ Community', desc: 'Demystifying technology for hundreds of thousands across social platforms.', icon: 'users', href: '#reach' },
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
        { label: 'Open Public Bio Page', icon: 'link', href: '/links' },
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

  const handleBookCall = (type = 'mvp_build', service = '') => openBookingModal(type, service);
  const handleStripePay = (pkg = 'retainer') => openStripeModal(pkg);
  // The media kit lives inside the studio; visitors ask for it through the brand-deal form.
  const openMediaKit = () => (isAuth ? navigate('/mediakit') : handleBookCall('brand_deal'));

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
      h('a', { href: '/links', class: 'hdr-menu-item', role: 'menuitem' },
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
      ctaService: 'consulting',
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
      ctaService: 'consulting',
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
      ctaService: 'speaking',
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

  // The first three services show; "Show all services" under the grid reveals the rest.
  const SERVICES_SHOWN = 3;
  const serviceGridEl = h('div', { class: 'landing-service-grid', id: 'landing-service-grid' }, servicesData.map(serviceCard));
  const hiddenCount = Math.max(0, servicesData.length - SERVICES_SHOWN);
  const allLabel = (open) => (open ? 'Show fewer services' : `Show all ${servicesData.length} services`);
  const allBtn = hiddenCount ? h('button', {
    type: 'button',
    class: 'landing-service-all-btn',
    'aria-expanded': 'false',
    'aria-controls': 'landing-service-grid',
    onclick: (e) => {
      const open = serviceGridEl.classList.toggle('show-all');
      allBtn.setAttribute('aria-expanded', String(open));
      allBtn.replaceChildren(allLabel(open), icon('chevron-down', 14));
      // Keyboard users (click detail 0) jump to the first revealed card; mouse users stay put.
      if (open && e.detail === 0) serviceGridEl.querySelector('.is-more .landing-service-more')?.focus({ preventScroll: true });
      else serviceGridEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    },
  }, allLabel(false), icon('chevron-down', 14)) : null;
  const serviceGrid = [serviceGridEl, allBtn ? h('div', { class: 'landing-service-all' }, allBtn) : null];

  const services = h('section', { class: 'landing-section tinted', id: 'services' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '02: Services'),
        h('h2', {}, 'Six ways we can ', h('em', {}, 'work together')),
        h('p', { class: 'muted' }, 'Every engagement comes with direct access to Delia, transparent deliverables, and zero corporate fluff.')),
      serviceGrid));

  // One service: the description is clamped and only the first deliverable shows until "Show more";
  // tags stay on one row and the actions sit on the card's bottom edge so every card lines up.
  function serviceCard(s, i) {
    const VISIBLE = 1;
    const id = `svc-more-${i}`;
    const card = h('article', { class: `landing-service-card${i >= SERVICES_SHOWN ? ' is-more' : ''}`, 'aria-labelledby': `${id}-title` });
    const extra = s.deliverables.slice(VISIBLE);
    const toggle = h('button', {
      type: 'button',
      class: 'landing-service-more',
      'aria-expanded': 'false',
      'aria-controls': id,
      onclick: () => {
        const open = card.classList.toggle('expanded');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.replaceChildren(open ? 'Show less' : `Show more${extra.length ? ` (+${extra.length})` : ''}`, icon('chevron-down', 12));
      },
    }, `Show more${extra.length ? ` (+${extra.length})` : ''}`, icon('chevron-down', 12));

    card.append(
      h('div', { class: 'landing-service-head' },
        h('span', { class: 'landing-service-cat-badge' }, icon(s.icon, 13), s.category),
        h('span', { class: 'landing-service-badge' }, s.badge)),
      h('h3', { class: 'landing-service-title', id: `${id}-title` }, s.title),
      h('div', { class: 'landing-service-body', id },
        h('p', { class: 'landing-service-desc' }, s.desc),
        h('ul', { class: 'landing-service-deliverables' },
          s.deliverables.map((d, n) => h('li', { class: `landing-service-deliv-item${n >= VISIBLE ? ' is-extra' : ''}` },
            h('span', { class: 'landing-service-deliv-check' }, icon('check', 13)),
            h('span', {}, d))))),
      h('ul', { class: 'landing-service-tags', 'aria-label': `${s.title} focus areas` },
        s.tags.map((t) => h('li', { class: 'landing-service-tag' }, t))),
      h('div', { class: 'landing-service-footer' },
        toggle,
        h('button', {
          type: 'button',
          class: 'landing-service-btn',
          'aria-label': `Book a call about ${s.title}`,
          onclick: () => handleBookCall(s.ctaService, s.title),
        }, icon('calendar', 13), 'Book a call', icon('arrow', 12))));
    return card;
  }

  // 7. Content & Social Channels: her real videos and posts, refreshed hourly (landing-content.js).
  // The "472K+" written across the page becomes the total of the networks shown in Reach & Content.
  const content = contentSection({ onFeed: (feed) => applyAudienceHeadline(view, feed.headline) });
  const ytSection = content.el;

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
      onAction: () => handleBookCall('consulting', 'Scoping & Architecture Audit'),
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

  // How it works: availability comes from her dashboard (build capacity vs. builds in progress).
  const availability = h('div', { class: 'landing-process-avail', hidden: true, 'aria-live': 'polite' });
  api('GET', 'landing/availability').then((a) => {
    if (!a?.show) return;
    availability.hidden = false;
    availability.classList.add(`is-${a.status}`);
    availability.replaceChildren(
      h('span', { class: 'landing-process-avail-dot', 'aria-hidden': 'true' }),
      h('span', {}, a.label),
      h('button', {
        type: 'button',
        class: 'landing-process-avail-btn',
        onclick: () => handleBookCall('mvp_build', a.status === 'open' ? 'Build slot' : 'Build waitlist'),
      }, a.status === 'open' ? 'Book your slot' : 'Join the waitlist', icon('arrow', 12)));
  }).catch(() => {});

  const process = h('section', { class: 'landing-section tinted', id: 'process' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '03: How it works'),
        h('h2', {}, 'From first call to ', h('em', {}, 'shipped'), ' in weeks'),
        h('p', { class: 'muted', style: { maxWidth: '640px', margin: '8px auto 0' } },
          'No bloatware, no endless agency meetings, and no junior handoffs. A battle-tested engineering sprint lifecycle refined across 60+ shipped projects.'),
        availability),
      h('ol', { class: 'landing-process-grid', 'aria-label': 'Four phases, from first call to launch' }, processSteps.map(processCard)),
      h('div', { class: 'landing-process-cta' },
        h('p', { class: 'muted' }, 'Not sure which phase you are in? Start with the free 30-minute call.'),
        h('button', { type: 'button', class: 'landing-service-btn', onclick: () => handleBookCall('consulting', 'How it works') },
          icon('calendar', 13), 'Book a free call', icon('arrow', 12)))));

  // One phase, built like a service card: two deliverables until "Show more", the tag on its own
  // row and the actions on the bottom edge so all four cards line up.
  function processCard(step, i) {
    const VISIBLE = 2;
    const id = `process-more-${i}`;
    const extra = step.deliverables.length - VISIBLE;
    const card = h('li', { class: 'landing-process-card', 'aria-labelledby': `${id}-title` });
    const moreLabel = (open) => (open ? 'Show less' : `+${extra} more`);
    const toggle = extra > 0 ? h('button', {
      type: 'button',
      class: 'landing-service-more',
      'aria-expanded': 'false',
      'aria-controls': id,
      'aria-label': `Show all ${step.deliverables.length} deliverables of ${step.title}`,
      onclick: () => {
        const open = card.classList.toggle('expanded');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.replaceChildren(moreLabel(open), icon('chevron-down', 12));
      },
    }, moreLabel(false), icon('chevron-down', 12)) : h('span');
    const action = step.href
      ? h('a', {
        href: step.href,
        class: 'landing-process-btn no-underline',
        onclick: (e) => {
          const el = step.href.startsWith('#') && document.querySelector(step.href);
          if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth' }); }
        },
      }, step.actionLabel, icon('arrow', 13))
      : h('button', { type: 'button', class: 'landing-process-btn is-primary', onclick: step.onAction }, icon('calendar', 13), step.actionLabel, icon('arrow', 13));

    card.append(
      h('div', { class: 'landing-process-head' },
        h('span', { class: 'landing-process-num-badge', 'aria-hidden': 'true' }, step.num),
        h('span', { class: 'landing-process-time-pill' }, icon('clock', 12), step.time)),
      h('p', { class: 'landing-process-phase' }, step.phase),
      h('h3', { class: 'landing-process-title', id: `${id}-title` }, step.title),
      h('div', { class: 'landing-process-body', id },
        h('p', { class: 'landing-process-desc' }, step.desc),
        h('ul', { class: 'landing-process-deliverables' },
          step.deliverables.map((item, n) => h('li', { class: `landing-process-deliv-item${n >= VISIBLE ? ' is-extra' : ''}` },
            h('span', { class: 'landing-process-deliv-check' }, icon('check', 13)),
            h('span', {}, item))))),
      h('div', { class: 'landing-process-tags' }, h('span', { class: 'landing-process-tag' }, icon('star', 12), step.tag)),
      h('div', { class: 'landing-process-footer' }, toggle, action));
    return card;
  }

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
                h('button', { type: 'button', class: 'ag-pt-desc-more', onclick: openMediaKit }, isAuth ? 'View Media Kit' : 'Request Media Kit'))),
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

  // 9. Section 05: Reach & Content: all six networks, live numbers, shown/hidden from her dashboard.
  const platforms = reachSection();

  // 10. Section 06: On Stage: her talks, managed from the dashboard's Speaking card.
  const speaking = speakingSection({ onBookCall: handleBookCall });

  // 11. Closing section above the footer; its leads and availability come from her dashboard.
  const cta = ctaSection({ onBookCall: handleBookCall, onStripePay: handleStripePay });

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
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: openMediaKit }, 'Media Kit & Demographics')),

        // Column 3: Platform & Connect
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Studio & Connect'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', style: { color: 'var(--accent)', fontWeight: '700' }, onclick: () => onAuthClick() }, isAuth ? 'Open Studio Dashboard' : 'Studio Suite Sign In'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleBookCall('mvp_build') }, 'Book an Intro Call'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleStripePay('retainer') }, 'Pay with Stripe'),
          h('a', { href: '/links', class: 'landing-footer-link' }, 'Link in Bio Hub'),
          h('a', { href: 'mailto:hello@thetechunicorn.com', class: 'landing-footer-link' }, 'hello@thetechunicorn.com'))),

      // Subfooter Bar
      h('div', { class: 'landing-footer-sub' },
        h('div', {},
          h('span', {}, '© 2026 Tech Unicorn · Delia Lazarescu. All rights reserved.')),
        h('div', { class: 'landing-footer-sub-right' },
          h('a', { href: '#top', class: 'landing-footer-sub-link' }, 'Back to top'),
          h('a', { href: '/links', class: 'landing-footer-sub-link' }, 'Links'),
  h('button', { type: 'button', class: 'landing-footer-link-btn landing-footer-sub-link', onclick: openMediaKit }, 'Media Kit')))));

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
  if (!isAuth) document.title = 'Tech Unicorn · Delia Lazarescu, Engineer & Creator';
  handleLandingLinks({ onBookCall: handleBookCall, onStripePay: handleStripePay });
  return () => {
    document.removeEventListener('click', closeProfileMenu);
    document.removeEventListener('keydown', onEsc);
    drawer.destroy();
    content.destroy();
    landingWrapper.remove();
  };
}

/**
 * Links into the landing page from outside it: /?book=<type> and /?pay=<package> (from /techunicorn
 * and the bio page) open the form, and /?payment=success|cancelled is where Stripe Checkout returns.
 * The query is removed afterwards so a refresh doesn't open it again.
 */
function handleLandingLinks({ onBookCall, onStripePay }) {
  const q = new URLSearchParams(location.search);
  const book = q.get('book');
  const pay = q.get('pay');
  const payment = q.get('payment');
  if (!book && !pay && !payment) return;
  history.replaceState(history.state, '', location.pathname + location.hash);
  if (payment === 'success') toast('Payment received. Thank you! Delia will be in touch by email.', 'ok');
  else if (payment === 'cancelled') toast('Checkout cancelled. Nothing was charged.');
  else if (pay) onStripePay(pay);
  else onBookCall(book, q.get('service') || '');
}

/** The copy says "472K+"; swap in the server's total (synced counts, else public figures) so it adds up. */
function applyAudienceHeadline(root, headline) {
  if (!headline || headline === '472K+') return;
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    if (/47[02]K\+/.test(node.nodeValue)) node.nodeValue = node.nodeValue.replace(/47[02]K\+/g, headline);
  }
}
