// Delia Lazarescu (Tech Unicorn) Public Landing Page
// Fully implemented in pure JavaScript using the Creator Studio dashboard design system.
// Zero build step, reactive DOM helper h(), native SVG icons, dark/light theme tokens.
import { h, link, navigate, api, modal, toast, field, botTrap, ensureLandingCss } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';
import { contentSection, loadFeed } from '../landing-content.js';
import { reachSection } from '../landing-reach.js';
import { speakingSection } from '../landing-speaking.js';
import { ctaSection, emailPill, socialIconsBar } from '../landing-cta.js';
import { aboutSection } from '../landing-about.js';
import { pricingSection } from '../landing-pricing.js';
import { partnersSection } from '../landing-partners.js';
import { marqueeSection } from '../landing-marquee.js';
import { heroSection } from '../landing-hero.js';
import { attribution, countEvent, icsFile, downloadIcs } from '../landing-tracking.js';
import { testimonialsSection, leadMagnetSection } from '../landing-proof.js';
import { faqSection } from '../landing-faq.js';
import { stackSection } from '../landing-stack.js';
import { comparisonSection } from '../landing-compare.js';
import { estimatorSection } from '../landing-estimator.js';
import { buildsSection } from '../landing-builds.js';
import { guaranteesSection } from '../landing-guarantees.js';
import { nomadSection } from '../landing-nomad.js';

ensureLandingCss(); // its styles live in landing.css

/**
 * Interactive Booking Modal for 30-minute Intro Calls.
 * Logs the lead into the Creator Studio deals pipeline; Delia confirms the time by email.
 */
export function openBookingModal(initialType = 'mvp_build', service = '') {
  countEvent('cta');
  return modal('Book an Intro Call with Delia', (close) => {
    let submitting = false;
    const today = new Date().toISOString().slice(0, 10);
    let slot = null; // an open time from her booking hours, when she has them on

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

    // Real open times (her working hours, minus booked calls), shown in the visitor's own time.
    // Without them the form keeps "preferred date and time" and she confirms by email.
    const whenRow = h('div', { class: 'grid grid-2', style: { gap: '14px' } },
      field('Preferred Date', dateInput),
      field('Preferred Time', timeSelect));
    const slotBox = h('div', { class: 'slot-picker', hidden: true });
    const localTz = (() => { try { return Intl.DateTimeFormat().resolvedOptions().timeZone; } catch { return ''; } })();
    api('GET', 'booking/slots').then((open) => {
      if (!open.enabled || !open.days?.length || submitting) return;
      let day = open.days[0];
      const times = h('div', { class: 'slot-times', role: 'group', 'aria-label': 'Open times' });
      const days = h('div', { class: 'slot-days', role: 'group', 'aria-label': 'Days' });
      const drawTimes = () => {
        times.replaceChildren(...day.slots.map((iso) => h('button', {
          type: 'button',
          class: `slot-btn${slot === iso ? ' on' : ''}`,
          'aria-pressed': String(slot === iso),
          onclick: () => { slot = iso; drawTimes(); submitBtn.replaceChildren(icon('calendar', 14), ` Book ${new Date(iso).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' })}`); },
        }, new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }))));
      };
      const drawDays = () => {
        days.replaceChildren(...open.days.map((d) => h('button', {
          type: 'button',
          class: `slot-day${d === day ? ' on' : ''}`,
          'aria-pressed': String(d === day),
          onclick: () => { day = d; drawDays(); drawTimes(); },
        }, new Date(`${d.date}T12:00:00Z`).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' }))));
      };
      drawDays();
      drawTimes();
      slotBox.replaceChildren(
        h('label', { class: 'field-label' }, 'Pick a time', h('span', { class: 'hint' }, ` ${open.minutes} min · times in your timezone${localTz ? ` (${localTz})` : ''}`)),
        days, times,
        h('button', { type: 'button', class: 'btn ghost sm', onclick: () => { slot = null; slotBox.hidden = true; whenRow.hidden = false; submitBtn.replaceChildren(icon('calendar', 14), ' Request 30-Min Call'); } }, 'None of these work? Suggest a time'));
      slotBox.hidden = false;
      whenRow.hidden = true;
    }).catch(() => { });

    const form = h('form', {
      class: 'booking-form',
      onsubmit: async (e) => {
        e.preventDefault();
        if (submitting) return;
        submitting = true;
        submitBtn.disabled = true;
        submitBtn.replaceChildren(icon('sparkle', 14), ' Sending request...');

        try {
          if (whenRow.hidden && !slot) throw new Error('Pick a time first.');
          const payload = {
            name: nameInput.value.trim(),
            website: form.querySelector('.hp-field')?.value || undefined,
            email: emailInput.value.trim(),
            company: companyInput.value.trim(),
            project_type: typeSelect.value,
            notes: notesInput.value.trim(),
            service: service || undefined,
            attribution: attribution(),
            ...(slot ? { slot } : { preferred_date: dateInput.value, preferred_time: timeSelect.value }),
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
        field('Your Name', nameInput, 'Required'), botTrap(),
        field('Work Email', emailInput, 'Delia confirms the time here')),
      h('div', { class: 'grid grid-2', style: { gap: '14px' } },
        field('Company or Brand', companyInput, 'Optional'),
        field('Project Type', typeSelect)),
      slotBox,
      whenRow,
      field('Project Notes or Goals', notesInput, 'Tell Delia what you need built'),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '10px', marginTop: '16px' } },
        cancelBtn,
        submitBtn));

    form.addEventListener('focusin', () => countEvent('form_start'), { once: true });

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
            h('span', { class: 'small muted' }, appt.start ? 'Booked for:' : 'Requested time:'),
            h('strong', { class: 'small' }, appt.start ? new Date(appt.start).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : `${appt.date} at ${appt.time}`)),
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('span', { class: 'small muted' }, 'Service:'),
            h('strong', { class: 'small' }, appt.service || 'Intro Consultation'))),
        h('div', { class: 'row', style: { justifyContent: 'center', gap: '12px' } },
          appt.start ? h('button', {
            type: 'button',
            class: 'btn',
            onclick: () => downloadIcs('intro-call-delia.ics', icsFile({
              start: appt.start, minutes: appt.minutes || 30, title: 'Intro call with Delia (Tech Unicorn)',
              description: `${appt.service || 'Intro call'}. Delia will send the meeting link by email. Ref ${data.booking_ref || ''}`,
            })),
          }, icon('calendar', 14), 'Add to calendar') : null,
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
    brand_partnership: { id: 'brand_partnership', name: 'Brand Partnership Campaign', price: '$5,000', desc: 'A dedicated technical integration across her biggest networks, with full digital ad rights.' },
    media_kit_sponsor: { id: 'media_kit_sponsor', name: 'Verified Media Kit Feature', price: '$1,500', desc: 'Featured sponsor placement in Delia’s live media kit and rate card deck.' },
    advisory_session: { id: 'advisory_session', name: '90-Minute Architecture & Strategy Session', price: '$500', desc: '90-minute 1-on-1 architecture review, code audit and AI product roadmap.' },
  };

  const selected = PACKAGES[pkgId] || PACKAGES.retainer;
  countEvent('cta');

  return modal(`Stripe Secure Checkout: ${selected.name}`, (close) => {
    let submitting = false;
    const nameInput = h('input', { type: 'text', class: 'input', name: 'customer_name', placeholder: 'Jane Doe', required: true });
    const emailInput = h('input', { type: 'email', class: 'input', name: 'customer_email', placeholder: 'jane@company.example.com', required: true });
    const companyInput = h('input', { type: 'text', class: 'input', name: 'company', placeholder: 'Acme Corporation' });
    const notesInput = h('textarea', { class: 'input', name: 'notes', rows: 2, placeholder: 'Project details, billing instructions, or VAT number...' });

    const payBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('credit-card', 14), ` Pay ${selected.price} with Stripe`);
    const cancelBtn = h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Cancel');
    let payLabel = ` Pay ${selected.price} with Stripe`;
    const priceEl = h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '1.4rem', fontWeight: 700, color: 'var(--accent)' } }, selected.price);
    // The live price (her dashboard can change it) and whether card payment is set up.
    api('GET', 'stripe/packages').then((p) => {
      const live = p.packages?.[selected.id];
      if (live && !submitting) {
        const price = `$${Math.round(live.amount / 100).toLocaleString('en-US')}${live.interval === 'month' ? '/mo' : ''}`;
        priceEl.textContent = price;
        payLabel = ` Pay ${price} with Stripe`;
        payBtn.replaceChildren(icon('credit-card', 14), payLabel);
      }
      if (p.stripe_configured || submitting) return;
      payLabel = ' Request an invoice';
      payBtn.replaceChildren(icon('file-text', 14), payLabel);
    }).catch(() => { });

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
            website: form.querySelector('.hp-field')?.value || undefined,
            customer_email: emailInput.value.trim(),
            company: companyInput.value.trim(),
            notes: notesInput.value.trim(),
            attribution: attribution(),
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
          priceEl),
        h('p', { class: 'muted small', style: { margin: '8px 0 0' } }, selected.desc)),
      h('div', { class: 'grid grid-2', style: { gap: '14px' } },
        field('Cardholder or Client Name', nameInput, 'Required'), botTrap(),
        field('Billing Email', emailInput, 'Receipt or invoice sent here')),
      field('Company or Brand Name', companyInput, 'For invoice heading'),
      field('Notes & Project Requirements', notesInput, 'Optional details'),
      h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginTop: '18px' } },
        h('span', { class: 'small muted' }, icon('lock', 12), ' 256-bit encrypted Stripe checkout'),
        h('div', { class: 'row', style: { gap: '10px' } }, cancelBtn, payBtn)));

    form.addEventListener('focusin', () => countEvent('form_start'), { once: true });

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
 * Live details for the menus, fetched once on first open: her current package prices, whether a
 * build slot is open, her audience headline and profile links. The menus work without them.
 */
let liveNav = null;
function loadNavLive() {
  liveNav ||= Promise.all([
    api('GET', 'landing/pricing').catch(() => null),
    api('GET', 'landing/availability').catch(() => null),
    loadFeed().catch(() => null),
  ]).then(([pricing, availability, feed]) => {
    const price = {};
    for (const p of pricing?.plans || []) price[p.key] = p;
    return { price, availability, audience: feed?.headline || '519K+', urls: feed?.urls || {} };
  });
  return liveNav;
}
const money = (n) => `$${Math.round(Number(n) || 0).toLocaleString('en-US')}`;

/**
 * Global Navigation Configuration for both Mega Menus and Mobile Side Drawer.
 * `live` (optional) fills in her current prices and audience; badges are short facts.
 */
function getLandingNavConfig({ onBookCall, onStripePay, isAuth }, live = null) {
  const p = (key, fallback) => (live?.price?.[key] ? money(live.price[key].price) : fallback);
  const audience = live?.audience || '519K+';
  return [
    {
      id: 'services',
      label: 'Services',
      lede: 'What we can build together, from a two-week MVP to a sponsored series.',
      groups: [
        {
          title: 'Core Engineering',
          items: [
            { label: 'Vibecoded Apps', desc: 'AI-powered MVPs designed, built and shipped.', badge: '2–4 wks', icon: 'zap', href: '#services' },
            { label: 'AI Integration', desc: 'Practical LLM features and agents that earn their keep.', badge: 'Agents', icon: 'sparkle', href: '#services' },
            { label: 'Technical Consulting', desc: 'Architecture reviews and build-vs-buy calls.', badge: 'Ex-Google', icon: 'code', href: '#services' },
            { label: 'Tech Stack', desc: 'Production AI, modern APIs, and zero bloat.', badge: 'Stack', icon: 'sliders', href: '#stack' },
            { label: 'Shipped Builds', desc: 'Real architectures shipped in 2–4 weeks.', badge: 'Case Studies', icon: 'rocket', href: '#builds' },
            { label: 'Why Delia', desc: 'Ex-Google principal vs traditional agency.', badge: 'Compare', icon: 'star', href: '#why-delia' },
          ],
        },
        {
          title: 'Audience & Growth',
          items: [
            { label: 'Content Creation', desc: `Reels and YouTube deep dives for ${audience} people.`, badge: 'Weekly', icon: 'play', href: '#youtube' },
            { label: 'Brand Partnerships', desc: 'High-retention technical integrations for your product.', badge: p('brand', '$5,000'), icon: 'briefcase', href: '#pricing' },
            { label: 'Speaking & Workshops', desc: 'Keynotes and hands-on AI workshops worldwide.', badge: 'Global', icon: 'mic', href: '#speaking' },
          ],
        },
      ],
      feature: 'availability',
      actions: [
        { label: 'Book an Intro Call', icon: 'calendar', onClick: () => onBookCall('mvp_build'), primary: true },
        { label: 'Packages & Pricing', icon: 'tag', href: '#pricing' },
        { label: 'How it works', icon: 'flow', href: '#process' },
        { label: 'Tech Stack', icon: 'sliders', href: '#stack' },
        { label: 'Client Guarantees', icon: 'lock', href: '#guarantees' },
        { label: 'Project Estimator', icon: 'target', href: '#estimator' },
        { label: 'FAQ', icon: 'chat', href: '#faq' },
      ],
    },
    {
      id: 'pricing',
      label: 'Pricing & Retainers',
      lede: 'Transparent scopes and fixed prices. Pay by card, or ask for an invoice.',
      groups: [
        {
          title: 'Fixed Engagements',
          items: [
            { label: 'MVP Build', desc: 'A complete product with weekly Friday demos.', badge: p('mvp', '$12,000'), icon: 'rocket', href: '#pricing' },
            { label: 'Advisory Retainer', desc: 'An engineer on call: 2 strategy calls a month, 24h replies.', badge: `${p('retainer', '$3,500')}/mo`, icon: 'clock', href: '#pricing' },
            { label: 'Brand Partnership', desc: 'Concept, production and multi-network distribution.', badge: p('brand', '$5,000'), icon: 'star', href: '#pricing' },
          ],
        },
        {
          title: 'Start Today',
          items: [
            { label: 'Pay the MVP deposit', desc: 'Lock your build slot with a 50% deposit.', badge: 'Stripe', icon: 'credit-card', onClick: () => onStripePay('mvp_deposit') },
            { label: 'Start the retainer', desc: 'Monthly advisory and async architecture reviews.', badge: 'Stripe', icon: 'repeat', onClick: () => onStripePay('retainer') },
            { label: 'Strategy session', desc: 'A private 90-minute review and AI roadmap.', badge: p('strategy', '$500'), icon: 'target', onClick: () => onStripePay('advisory_session') },
          ],
        },
      ],
      feature: 'prices',
      actions: [
        { label: 'Pay with Stripe', icon: 'credit-card', onClick: () => onStripePay('retainer'), primary: true },
        { label: 'Client Guarantees', icon: 'lock', href: '#guarantees' },
        { label: 'Project Estimator', icon: 'target', href: '#estimator' },
        { label: isAuth ? 'Open Media Kit' : 'Request Media Kit', icon: 'file-text', onClick: () => (isAuth ? navigate('/mediakit') : onBookCall('brand_deal')) },
      ],
    },
    {
      id: 'about',
      label: 'About Delia',
      lede: 'Engineer first, storyteller always: who you’d be working with.',
      groups: [
        {
          title: 'Background',
          items: [
            { label: 'Ex-Google Engineer', desc: 'Production systems engineer turned founder and creator.', badge: 'Ex-G', icon: 'briefcase', href: '#about' },
            { label: 'Digital Nomad', desc: 'Shipping software from beach cafés worldwide.', badge: '69 countries', icon: 'globe', href: '#about' },
            { label: `${audience} Community`, desc: 'Demystifying tech across every network.', badge: 'Live', icon: 'users', href: '#reach' },
          ],
        },
        {
          title: 'On Stage',
          items: [
            { label: 'Keynotes & Panels', desc: 'Careers in tech without a permanent address.', badge: 'Talks', icon: 'mic', href: '#speaking' },
            { label: 'Workshops', desc: 'Hands-on AI workflows for product teams.', badge: 'Hands-on', icon: 'sparkle', href: '#speaking' },
            { label: 'Client stories', desc: 'What working together looks like, in their words.', badge: 'Reviews', icon: 'chat', href: '#testimonials' },
          ],
        },
      ],
      feature: 'facts',
      actions: [
        { label: 'Link in bio', icon: 'link', href: '/links', primary: true },
        { label: 'FAQ', icon: 'chat', href: '#faq' },
        { label: 'Instagram @tech.unicorn', icon: 'brand:instagram', href: live?.urls?.instagram || 'https://instagram.com/tech.unicorn', external: true },
      ],
    },
  ];
}

/** Follow a menu item: run its action, or smooth-scroll to its section. */
function followNavItem(it, e, close) {
  close();
  if (it.onClick) {
    e.preventDefault();
    it.onClick();
  } else if (it.href?.startsWith('#')) {
    const target = document.querySelector(it.href);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
  }
}

/** One compact menu row: icon tile, label, one-line description, a short fact on the right. */
function navRow(it, cls, close) {
  return h('a', {
    href: it.href || '#',
    target: it.external ? '_blank' : undefined,
    rel: it.external ? 'noopener' : undefined,
    class: cls,
    onclick: (e) => followNavItem(it, e, close),
  },
    h('span', { class: 'ln-ico' }, icon(it.icon, 15)),
    h('span', { class: 'ln-text' }, h('strong', {}, it.label), it.desc ? h('span', {}, it.desc) : null),
    it.badge ? h('span', { class: 'ln-badge' }, it.badge) : null);
}

/** The card beside each menu: build slots, live prices, or quick facts. */
function navFeature(kind, live, { onBookCall }, close) {
  if (kind === 'availability') {
    const a = live?.availability;
    const open = a?.show && a.status === 'open';
    return h('div', { class: 'ln-feature' },
      h('span', { class: `ln-feature-pill${a?.show ? (open ? ' is-open' : ' is-wait') : ''}` }, h('i'), a?.show ? a.label : 'Taking on new projects'),
      h('strong', {}, 'Idea to production in 2–4 weeks'),
      h('p', {}, 'Weekly Friday demos, you own all the code, and a 30-day warranty after launch.'),
      h('button', { type: 'button', class: 'ln-feature-cta', onclick: () => { close(); onBookCall('mvp_build', 'Menu: build slot'); } }, open ? 'Claim a slot' : 'Join the list', icon('arrow', 12)));
  }
  if (kind === 'prices') {
    const rows = [['strategy', 'Strategy call'], ['retainer', 'Retainer'], ['mvp', 'MVP build'], ['brand', 'Brand deal']]
      .map(([k, l]) => [l, live?.price?.[k] ? `${money(live.price[k].price)}${live.price[k].per && live.price[k].per !== 'fixed' ? `<small>${live.price[k].per}</small>` : ''}` : null])
      .filter(([, v]) => v);
    return h('div', { class: 'ln-feature' },
      h('strong', {}, 'Current prices'),
      rows.length ? h('dl', { class: 'ln-prices' }, rows.map(([l, v]) => [h('dt', {}, l), h('dd', { html: v })]))
        : h('p', {}, 'Fixed scopes for every package, shown in full below.'),
      h('p', { class: 'ln-feature-note' }, icon('lock', 11), ' Stripe checkout, or an invoice'));
  }
  return h('div', { class: 'ln-feature' },
    h('div', { class: 'ln-facts' },
      h('div', {}, h('b', {}, live?.audience || '519K+'), h('small', {}, 'community')),
      h('div', {}, h('b', {}, '69'), h('small', {}, 'countries')),
      h('div', {}, h('b', {}, 'Ex-G'), h('small', {}, 'Google engineer'))),
    h('a', { class: 'ln-feature-cta', href: '#about', onclick: (e) => followNavItem({ href: '#about' }, e, close) }, 'Read her story', icon('arrow', 12)));
}

/**
 * Multi-Column Mega Menus for the Landing Page Header: a short intro, compact rows with a fact
 * each, a feature card (slots, live prices or quick facts), quick actions and a footer strip.
 * Hover or click a tab; ↓ opens it and moves into the panel, ↑/↓ move between rows, Esc closes.
 */
function landingMegaMenus(handlers) {
  const panel = h('div', { class: 'mega landing-mega', id: 'landing-mega-panel', hidden: true, role: 'region' });
  let openSec = null;
  let timer = null;
  let live = null;
  const tabs = [];

  const close = () => {
    clearTimeout(timer);
    if (!openSec) return;
    tabs.find((t) => t.id === openSec)?.btn.setAttribute('aria-expanded', 'false');
    panel.classList.remove('open');
    panel.hidden = true;
    openSec = null;
  };

  function fill(id) {
    const sec = getLandingNavConfig(handlers, live).find((s) => s.id === id);
    const tab = tabs.find((t) => t.id === id);
    panel.setAttribute('aria-label', sec.label);
    panel.replaceChildren(h('div', { class: 'ln-panel' },
      h('div', { class: 'ln-main' },
        h('p', { class: 'ln-lede' }, sec.lede),
        h('div', { class: `ln-cols cols-${sec.groups.length}` },
          sec.groups.map((g) => h('div', { class: 'ln-col' },
            h('p', { class: 'ln-title' }, g.title),
            h('div', { class: 'ln-list' }, g.items.map((it) => navRow(it, 'ln-row', close))))))),
      h('aside', { class: 'ln-side' },
        navFeature(sec.feature, live, handlers, close),
        h('div', { class: 'ln-actions' }, sec.actions.map((a) => h('a', {
          href: a.href || '#', target: a.external ? '_blank' : undefined, rel: a.external ? 'noopener' : undefined,
          class: `ln-action${a.primary ? ' is-primary' : ''}`, onclick: (e) => followNavItem(a, e, close),
        }, icon(a.icon, 13), h('span', {}, a.label), icon('arrow', 11))))),
      h('div', { class: 'ln-foot' },
        h('span', {}, icon('calendar', 12), ' Free 30-minute intro call'),
        h('span', {}, icon('clock', 12), ' Replies within one business day'),
        h('span', {}, icon('check', 12), ' Fixed scopes, no surprise invoices'))));
    tab?.btn.classList.add('is-active');
  }

  function show(id, focusFirst = false) {
    clearTimeout(timer);
    if (openSec !== id) {
      tabs.forEach((t) => { t.btn.setAttribute('aria-expanded', String(t.id === id)); t.btn.classList.toggle('is-active', t.id === id); });
      openSec = id;
      fill(id);
      panel.hidden = false;
      requestAnimationFrame(() => panel.classList.add('open'));
      // Prices, slots and audience arrive once; the open panel refreshes with them.
      if (!live) loadNavLive().then((l) => { live = l; if (openSec) fill(openSec); });
    }
    if (focusFirst) requestAnimationFrame(() => panel.querySelector('.ln-row')?.focus());
  }

  for (const sec of getLandingNavConfig(handlers)) {
    const btn = h('button', { class: 'mega-tab', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'landing-mega-panel' }, sec.label, icon('chevron-down', 13));
    btn.addEventListener('mouseenter', () => { clearTimeout(timer); timer = setTimeout(() => show(sec.id), openSec ? 0 : 100); });
    btn.addEventListener('mouseleave', () => { clearTimeout(timer); timer = setTimeout(close, 220); });
    btn.addEventListener('click', (e) => { e.stopPropagation(); openSec === sec.id ? close() : show(sec.id); });
    btn.addEventListener('keydown', (e) => { if (e.key === 'ArrowDown') { e.preventDefault(); show(sec.id, true); } });
    tabs.push({ id: sec.id, btn });
  }

  panel.addEventListener('mouseenter', () => clearTimeout(timer));
  panel.addEventListener('mouseleave', () => { timer = setTimeout(close, 220); });
  panel.addEventListener('click', (e) => e.stopPropagation());
  panel.addEventListener('keydown', (e) => {
    const rows = [...panel.querySelectorAll('.ln-row, .ln-action, .ln-feature-cta')];
    const i = rows.indexOf(document.activeElement);
    if (e.key === 'Escape') {
      const tab = tabs.find((t) => t.id === openSec)?.btn;
      close();
      tab?.focus();
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      rows[(i + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length]?.focus();
    }
  });
  document.addEventListener('click', close);

  return h('div', { class: 'mega-wrap' },
    h('nav', { class: 'mega-tabs', 'aria-label': 'Landing navigation' }, tabs.map((t) => t.btn)),
    panel);
}

/**
 * Mobile & Tablet Off-Canvas Side Drawer: the build-slot status and main call to action first,
 * then each menu as a collapsible group of compact rows, her profiles and the theme switch.
 * Focus stays inside while it's open; Esc or the backdrop closes it.
 */
function landingSideDrawer({ onBookCall, onStripePay, onAuthClick, isAuth }) {
  const handlers = { onBookCall, onStripePay, isAuth };
  const backdrop = h('div', { class: 'landing-drawer-backdrop' });
  const closeBtn = h('button', { type: 'button', class: 'landing-drawer-close', 'aria-label': 'Close menu' }, icon('x', 16));
  const status = h('span', { class: 'ln-feature-pill' }, h('i'), 'Taking on new projects');
  const sectionsBox = h('div', { class: 'ld-sections' });
  const socials = h('div', { class: 'ld-socials' });

  const drawSections = (live = null) => {
    sectionsBox.replaceChildren(...getLandingNavConfig(handlers, live).map((sec) => h('details', { class: 'ld-group', open: true },
      h('summary', {}, h('span', {}, sec.label), h('small', {}, sec.groups.reduce((n, g) => n + g.items.length, 0)), icon('chevron-down', 13)),
      // close is defined further down: look it up when a row is clicked, not while building.
      h('div', { class: 'ld-list' }, sec.groups.flatMap((g) => g.items).map((it) => navRow(it, 'ln-row is-drawer', () => close()))))));
  };
  drawSections();

  const drawerPanel = h('aside', { class: 'landing-drawer ld', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Navigation menu' },
    h('div', { class: 'ld-head' },
      h('div', { class: 'ld-brand' }, brandMark(22), h('span', {}, h('strong', {}, 'Tech Unicorn'), h('small', {}, 'Delia Lazarescu'))),
      closeBtn),
    h('div', { class: 'ld-cta' },
      status,
      h('button', { type: 'button', class: 'ld-primary', onclick: () => { close(); navigate('/login?signup=1&role=member'); } }, icon('sparkle', 14), 'Join Community Hub (Free)', icon('arrow', 12)),
      h('div', { class: 'ld-secondary' },
        h('button', { type: 'button', onclick: () => { close(); onBookCall('mvp_build'); } }, icon('calendar', 13), 'Book Intro Call'),
        h('button', { type: 'button', onclick: () => { close(); onAuthClick(); } }, icon('user', 13), isAuth ? 'Dashboard' : 'Sign in'))),
    sectionsBox,
    h('div', { class: 'ld-foot' },
      socials,
      h('div', { class: 'ld-theme' }, h('span', {}, 'Appearance'), themeSwitch()),
      h('p', {}, '© 2026 Tech Unicorn · @Tech.Unicorn')));

  const drawerWrap = h('div', { class: 'landing-drawer-wrap', 'aria-hidden': 'true' }, backdrop, drawerPanel);

  let lastFocus = null;
  const close = () => {
    drawerWrap.classList.remove('open');
    drawerWrap.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('landing-drawer-open');
    lastFocus?.focus?.();
  };
  const open = () => {
    lastFocus = document.activeElement;
    drawerWrap.classList.add('open');
    drawerWrap.setAttribute('aria-hidden', 'false');
    document.body.classList.add('landing-drawer-open');
    closeBtn.focus();
    loadNavLive().then((live) => {
      drawSections(live);
      const a = live.availability;
      if (a?.show) {
        status.className = `ln-feature-pill ${a.status === 'open' ? 'is-open' : 'is-wait'}`;
        status.replaceChildren(h('i'), a.label);
      }
      const nets = [['youtube', 'brand:youtube'], ['instagram', 'brand:instagram'], ['linkedin', 'brand:linkedin'], ['tiktok', 'brand:tiktok'], ['twitter', 'brand:x']]
        .filter(([k]) => live.urls?.[k]);
      socials.replaceChildren(...nets.map(([k, ic]) => h('a', { href: live.urls[k], target: '_blank', rel: 'noopener', 'aria-label': k }, icon(ic, 15))));
    });
  };

  closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  drawerPanel.addEventListener('click', (e) => e.stopPropagation());
  const onKey = (e) => {
    if (!drawerWrap.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'Tab') {
      // Keep focus inside the drawer while it's open.
      const items = [...drawerPanel.querySelectorAll('a[href], button, summary, [tabindex]:not([tabindex="-1"])')].filter((el) => el.offsetParent !== null);
      if (!items.length) return;
      if (e.shiftKey && document.activeElement === items[0]) { e.preventDefault(); items[items.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === items[items.length - 1]) { e.preventDefault(); items[0].focus(); }
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
  // Count real visitors only: her own preview inside the studio isn't a visit.
  if (!isAuth) { attribution(); countEvent('visit'); }

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
        isAuth ? null : h('button', {
          type: 'button',
          class: 'btn primary sm landing-join-community',
          title: 'Join Delia’s Community: Starter kits, AMA board, perks',
          onclick: () => navigate('/login?signup=1&role=member'),
        }, icon('sparkle', 14), h('span', {}, 'Join Community')),
        isAuth ? null : h('button', {
          type: 'button',
          class: 'btn ghost sm landing-register',
          title: 'Sign in to studio or client portal',
          onclick: () => onAuthClick(),
        }, icon('user', 14), h('span', { class: 'landing-register-label' }, 'Sign in')),
        h('button', {
          type: 'button',
          class: 'landing-drawer-toggle',
          'aria-label': 'Open navigation side drawer',
          title: 'Open Side Drawer',
          onclick: () => drawer.openDrawer(),
        }, icon('menu', 18)),
        h('div', { class: 'hdr-avatar-wrap' }, avatar))));

  // 2. Hero: her copy from the dashboard's "Hero" card, real numbers, status pill and live stats.
  const hero = heroSection({ onBookCall: handleBookCall });

  // 3. Marquee: her phrases and live facts from the dashboard, shown just above the footer.
  const marquee = marqueeSection();

  // 4. Brand Partners & Stages: brands from her dashboard plus the stages from her talks.
  const trusted = partnersSection();

  // 5. Section 01: About: her words from the dashboard's "About you" card, plus live facts.
  const about = aboutSection({ onBookCall: handleBookCall });

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
  }).catch(() => { });

  const process = h('section', { class: 'landing-section tinted', id: 'process' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '03: How it works'),
        h('h2', {}, 'From first call to ', h('em', {}, 'shipped'), ' in weeks'),
        h('p', { class: 'muted', style: { maxWidth: '640px', margin: '8px auto 0' } },
          'No bloatware, no endless agency meetings, and no junior handoffs. A battle-tested engineering sprint lifecycle refined across 60+ shipped projects.'),
        availability),
      h('ol', { class: 'landing-process-grid', tabindex: '0', 'aria-label': 'Four phases, from first call to launch' }, processSteps.map(processCard)),
      h('p', { class: 'landing-swipe-hint muted', 'aria-hidden': 'true' }, icon('arrow', 13), 'Swipe through the four phases'),
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

  // 9. Section 04: Packages & Pricing: prices, visibility and "Most popular" from her dashboard.
  const pricing = pricingSection({ onBookCall: handleBookCall, onStripePay: handleStripePay });

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
            'AI-powered software development, technical advisory, and high-impact media. Shipped from beach cafés and bullet trains across 60+ countries.'),
          h('div', { class: 'landing-footer-avail-pill' },
            h('span', { class: 'landing-nomad-live-dot' }),
            h('span', {}, 'Taking on 1 MVP build sprint for next month')),
          h('div', { class: 'landing-footer-stats-strip' },
            h('span', {}, '519K+ Community'),
            h('span', { class: 'muted' }, '·'),
            h('span', {}, '69 Countries'),
            h('span', { class: 'muted' }, '·'),
            h('span', {}, 'Ex-Google')),
          emailPill(),
          socialIconsBar()),

        // Column 1: Core Engineering
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Core Engineering'),
          h('a', { href: '#services', class: 'landing-footer-link' }, 'Vibecoded Apps (MVPs)'),
          h('a', { href: '#services', class: 'landing-footer-link' }, 'AI Integration & Agents'),
          h('a', { href: '#services', class: 'landing-footer-link' }, 'Technical Consulting'),
          h('a', { href: '#stack', class: 'landing-footer-link' }, 'Tech Stack & Architecture'),
          h('a', { href: '#why-delia', class: 'landing-footer-link' }, 'Why Delia Lazarescu'),
          h('a', { href: '#guarantees', class: 'landing-footer-link' }, 'Client Guarantees & Code Quality'),
          h('a', { href: '#builds', class: 'landing-footer-link' }, 'Shipped Products & Case Studies'),
          h('a', { href: '#nomad', class: 'landing-footer-link' }, 'Shipped from 69 Countries'),
          h('a', { href: '#pricing', class: 'landing-footer-link' }, 'Packages & Retainers'),
          h('a', { href: '#estimator', class: 'landing-footer-link' }, 'Interactive Project Estimator'),
          h('a', { href: '#faq', class: 'landing-footer-link' }, 'Frequently Asked Questions (FAQ)'),
          h('a', { href: '#process', class: 'landing-footer-link' }, 'Delivery in 2-4 Weeks')),

        // Column 2: Content & Media
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Content & Channels'),
          h('a', { href: 'https://youtube.com/@Tech.Unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'YouTube Deep-Dives'),
          h('a', { href: 'https://instagram.com/tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'Instagram & Reels'),
          h('a', { href: 'https://linkedin.com/in/delialazarescu', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'LinkedIn Articles'),
          h('a', { href: 'https://tiktok.com/@tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'TikTok Shorts'),
          h('a', { href: 'https://threads.net/@tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'Threads Daily Dispatches'),
          h('a', { href: 'https://x.com/tech_unicorn', target: '_blank', rel: 'noopener', class: 'landing-footer-link' }, 'X / Twitter Engineering Takes'),
          h('a', { href: '#speaking', class: 'landing-footer-link' }, 'Keynote Speaking & Panels'),
          h('a', { href: '#speaking', class: 'landing-footer-link' }, 'Hands-on AI Workshops'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: openMediaKit }, 'Media Kit & Demographics'),
          h('a', { href: '#pricing', class: 'landing-footer-link' }, 'Brand Partnerships & Rates'),
          h('a', { href: '#download', class: 'landing-footer-link' }, 'Free Architecture Blueprint'),
          h('a', { href: '#about', class: 'landing-footer-link' }, 'Delia’s Story & Background')),

        // Column 3: Platform & Connect
        h('div', { class: 'landing-footer-col' },
          h('h3', { class: 'landing-footer-col-title' }, 'Studio & Connect'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', style: { color: 'var(--accent)', fontWeight: '700' }, onclick: () => onAuthClick() }, isAuth ? 'Open Studio Dashboard' : 'Studio Suite Sign In'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleBookCall('mvp_build') }, 'Book an Intro Call'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleStripePay('retainer') }, 'Pay Retainer ($3,500/mo)'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleStripePay('mvp_deposit') }, 'Pay 50% MVP Deposit'),
          h('button', { type: 'button', class: 'landing-footer-link-btn', onclick: () => handleStripePay('advisory_session') }, 'Strategy Session ($500)'),
          h('a', { href: '#process', class: 'landing-footer-link' }, 'Check Sprint Capacity'),
          h('a', { href: '#estimator', class: 'landing-footer-link' }, 'Project Scope Estimator'),
          h('a', { href: '#testimonials', class: 'landing-footer-link' }, 'Client Stories & Reviews'),
          h('a', { href: '/links', class: 'landing-footer-link' }, 'Link in Bio Hub'),
          h('a', { href: 'mailto:hello@thetechunicorn.com', class: 'landing-footer-link' }, 'hello@thetechunicorn.com'),
          h('a', { href: '#pricing', class: 'landing-footer-link' }, 'Stripe Checkout Security'),
          h('a', { href: '#guarantees', class: 'landing-footer-link' }, '24h Response Promise'))),

      // Subfooter Bar
      h('div', { class: 'landing-footer-sub' },
        h('div', { class: 'landing-footer-sub-left' },
          h('span', {}, '© 2026 Tech Unicorn · Delia Lazarescu. All rights reserved.'),
          h('span', { class: 'auth-couple-badge landing-love-badge' }, icon('heart', 12), ' For Delia with love')),
        h('div', { class: 'landing-footer-sub-right' },
          h('a', { href: '/impressum', class: 'landing-footer-sub-link' }, 'Impressum'),
          h('a', { href: '/terms', class: 'landing-footer-sub-link' }, 'Commercial Terms'),
          h('a', { href: '/privacy', class: 'landing-footer-sub-link' }, 'GDPR Privacy'),
          h('a', { href: '/links', class: 'landing-footer-sub-link' }, 'Links Hub'),
          h('a', { href: 'mailto:hello@thetechunicorn.com', class: 'landing-footer-sub-link' }, 'Contact'),
          h('button', {
            type: 'button',
            class: 'landing-back-to-top-btn',
            'aria-label': 'Scroll back to top of page',
            onclick: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
          }, icon('arrow-up', 13), h('span', {}, 'Back to top')),
          h('button', { type: 'button', class: 'landing-footer-link-btn landing-footer-sub-link', onclick: openMediaKit }, 'Media Kit')))));

  const stack = stackSection({ onBookCall: handleBookCall });
  const comparison = comparisonSection({ onBookCall: handleBookCall });
  const guarantees = guaranteesSection({ onBookCall: handleBookCall });
  const builds = buildsSection({ onBookCall: handleBookCall });
  const nomad = nomadSection({ onBookCall: handleBookCall });
  const estimator = estimatorSection({ onBookCall: handleBookCall, onStripePay: handleStripePay });
  const faq = faqSection({ onBookCall: handleBookCall, onStripePay: handleStripePay });

  const landingBox = h('div', { class: 'landing-box' },
    nav,
    hero,
    trusted,
    about,
    services,
    ytSection,
    process,
    guarantees,
    stack,
    comparison,
    builds,
    nomad,
    testimonialsSection(),
    pricing,
    estimator,
    faq,
    platforms,
    speaking,
    leadMagnetSection(),
    cta,
    marquee,
    footer);

  // Floating back to top button
  const floatingTop = h('button', {
    type: 'button',
    class: 'landing-floating-top',
    'aria-label': 'Back to top',
    onclick: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
  }, icon('arrow-up', 14), h('span', {}, 'Top'));

  const onScroll = () => {
    if (window.scrollY > 600) {
      floatingTop.classList.add('is-visible');
    } else {
      floatingTop.classList.remove('is-visible');
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });

  const landingWrapper = h('div', { class: 'landing-page' },
    drawer.drawerWrap,
    landingBox,
    floatingTop);

  view.replaceChildren(landingWrapper);
  if (!isAuth) document.title = 'Tech Unicorn · Delia Lazarescu, Engineer & Creator';
  handleLandingLinks({ onBookCall: handleBookCall, onStripePay: handleStripePay });
  return () => {
    window.removeEventListener('scroll', onScroll);
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
