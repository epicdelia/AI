// Delia Lazarescu (Tech Unicorn) Public Landing Page
// Fully implemented in pure JavaScript using the Creator Studio dashboard design system.
import { h, link, navigate } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { themeSwitch } from '../theme.js';

export function renderLanding(view, options = {}) {
  const onAuthClick = options.onAuthClick || (() => navigate('/login'));
  const isAuth = !!options.user;

  // 1. Navigation Header
  const nav = h('header', { class: 'landing-nav', id: 'top' },
    h('div', { class: 'landing-container landing-nav-inner' },
      h('a', { href: '#top', class: 'landing-logo' },
        h('span', { style: { fontSize: '1.4rem' } }, '🦄'),
        h('span', {}, 'Tech Unicorn'),
        h('span', { class: 'brand-badge' }, 'PRO')),
      h('nav', { class: 'landing-links' },
        h('a', { href: '#about' }, 'About'),
        h('a', { href: '#services' }, 'Services'),
        h('a', { href: '#process' }, 'Process'),
        h('a', { href: '#pricing' }, 'Pricing'),
        h('a', { href: '#content' }, 'Content'),
        h('a', { href: '#speaking' }, 'Speaking'),
        link('/links', { class: 'btn btn-ghost sm' }, icon('link', 14), 'Link in Bio'),
        h('a', { href: 'mailto:hello@thetechunicorn.com?subject=Intro%20Call%20Booking', class: 'btn primary sm' }, 'Book an Intro Call'),
        h('button', {
          type: 'button',
          class: 'btn ghost sm',
          onclick: onAuthClick,
          title: isAuth ? 'Open Creator Studio Dashboard' : 'Sign in to Creator Studio',
        }, icon('sparkle', 14), isAuth ? 'Open Studio' : 'Studio Login'),
        themeSwitch())));

  // 2. Hero Section
  const hero = h('section', { class: 'landing-hero' },
    h('div', { class: 'landing-container landing-hero-grid' },
      h('div', { class: 'landing-hero-copy' },
        h('span', { class: 'landing-eyebrow' }, 'Delia Lazarescu · Ex-Google Engineer · Digital Nomad'),
        h('h1', {}, 'Making tech ', h('em', {}, 'less scary'), ' — from beach cafés in 60+ countries.'),
        h('p', { class: 'landing-lede' }, 'I’m a software engineer who left Big Tech to build apps, create content for 470K+ people, and prove you don’t need a hoodie and a standing desk to ship great software.'),
        h('div', { class: 'landing-hero-actions' },
          h('a', { href: 'mailto:hello@thetechunicorn.com?subject=Intro%20Call%20Booking', class: 'btn primary lg' }, 'Book an Intro Call'),
          h('a', { href: '#pricing', class: 'btn ghost lg' }, 'See Packages & Pricing'),
          h('button', { type: 'button', class: 'btn ghost lg', onclick: onAuthClick }, icon('sparkle', 15), isAuth ? 'Dashboard' : 'Sign In')),
        h('p', { class: 'muted small' }, 'Free 30-minute call · fixed-scope proposal within 48 hours · no surprise invoices')),
      h('div', { class: 'landing-stats-grid' },
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, '472K+'),
          h('div', { class: 'landing-stat-lbl' }, 'Combined audience across platforms')),
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, '69'),
          h('div', { class: 'landing-stat-lbl' }, 'Countries traveled and worked from')),
        h('div', { class: 'landing-stat-box' },
          h('div', { class: 'landing-stat-num' }, '2–4', h('span', { style: { fontSize: '0.5em', color: 'var(--accent)' } }, 'wks')),
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
      marqueeItems.flatMap((item, i) => [
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
        h('p', { class: 'landing-section-eyebrow' }, '01 — About')),
      h('div', {},
        h('h2', {}, 'Engineer first. ', h('em', {}, 'Storyteller'), ' always.'),
        h('p', {}, 'I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it wasn’t for them.'),
        h('p', {}, 'The thesis behind everything I make: ', h('strong', {}, 'tech feels scary because it’s explained badly.'), ' Whether it’s a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same — take something intimidating and make it feel obvious.'),
        h('ul', { class: 'about-points', style: { listStyle: 'none', padding: 0, marginTop: '20px', display: 'grid', gap: '12px' } },
          h('li', { class: 'row' }, h('span', { style: { color: 'var(--accent)' } }, '✦'), 'Self-taught engineer → Google → independent founder'),
          h('li', { class: 'row' }, h('span', { style: { color: 'var(--accent)' } }, '✦'), 'Built and shipped products while living across 60+ countries'),
          h('li', { class: 'row' }, h('span', { style: { color: 'var(--accent)' } }, '✦'), '470K+ community across Instagram, YouTube, LinkedIn & TikTok')))));

  // 6. Section 02: Services
  const servicesData = [
    { icon: '⚡️', title: 'Vibecoded Apps', desc: 'AI-powered MVPs designed, built, and shipped in 2–4 weeks. From napkin sketch to live product — no six-month roadmap required.' },
    { icon: '🎥', title: 'Content Creation', desc: 'Short-form video, long-form YouTube, and LinkedIn content that makes your product make sense to real humans.' },
    { icon: '🎤', title: 'Speaking & Workshops', desc: 'Keynotes and hands-on workshops on AI, building in public, and the future of work — for conferences, companies, and communities.' },
    { icon: '🤝', title: 'Brand Partnerships', desc: 'Authentic integrations with an engaged, tech-curious audience of 470K+. I only partner with products I’d actually use.' },
    { icon: '🧭', title: 'Technical Consulting', desc: 'An ex-Google engineer in your corner — architecture reviews, build-vs-buy decisions, and honest answers about what your team actually needs.' },
    { icon: '✨', title: 'AI Integration', desc: 'Practical AI features for your existing product — shipped, not slide-decked. LLMs, automation, and agents that earn their keep.' },
  ];
  const services = h('section', { class: 'landing-section tinted', id: 'services' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '02 — Services'),
        h('h2', {}, 'Six ways we can ', h('em', {}, 'work together'))),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        servicesData.map((s) => h('article', { class: 'landing-card' },
          h('div', { class: 'landing-card-icon' }, s.icon),
          h('h3', {}, s.title),
          h('p', { class: 'muted' }, s.desc))))));

  // 7. Section 03: Process
  const stepsData = [
    { num: '1', title: 'Intro call', desc: 'A free 30-minute call. You bring the idea or problem; I’ll tell you honestly whether it’s a 2-week build, an advisory retainer, or not worth your money.' },
    { num: '2', title: 'Fixed-scope proposal', desc: 'Within 48 hours you get a one-page proposal: deliverables, timeline, and a fixed price. No surprise invoices or scope creep.' },
    { num: '3', title: 'Build, demo, ship', desc: 'Weekly demos every Friday while I build, a live product at the end, and 30 days of post-launch support included on every build.' },
  ];
  const process = h('section', { class: 'landing-section', id: 'process' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '03 — How it works'),
        h('h2', {}, 'From first call to ', h('em', {}, 'shipped'), ' in weeks')),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        stepsData.map((st) => h('div', { class: 'landing-card' },
          h('div', { style: { width: '40px', height: '40px', borderRadius: '50%', background: 'var(--grad)', color: '#fff', display: 'grid', placeItems: 'center', fontWeight: 'bold', marginBottom: '16px' } }, st.num),
          h('h3', {}, st.title),
          h('p', { class: 'muted' }, st.desc))))));

  // 8. Section 04: Pricing & Packages
  const pricing = h('section', { class: 'landing-section tinted', id: 'pricing' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '04 — Packages & Pricing'),
        h('h2', {}, 'Packages that ', h('em', {}, 'pay for themselves')),
        h('p', { class: 'muted' }, 'Every engagement starts with a free intro call and a fixed quote. Prices below are transparent starting points for typical scope.')),
      h('div', { class: 'grid grid-3', style: { gap: '24px', alignItems: 'start' } },
        h('article', { class: 'landing-price-card' },
          h('h3', {}, 'Advisory Retainer'),
          h('div', { class: 'row', style: { alignItems: 'baseline' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.2rem', fontWeight: '600' } }, '$3,500'),
            h('span', { class: 'muted small' }, '/month')),
          h('p', { class: 'muted' }, 'An ex-Google engineer on call for your team.'),
          h('ul', { style: { listStyle: 'none', padding: '12px 0 0', margin: '0', display: 'grid', gap: '8px', borderTop: '1px solid var(--line)' } },
            h('li', { class: 'small' }, '✦ 2 deep-dive strategy calls per month'),
            h('li', { class: 'small' }, '✦ Unlimited async architecture reviews'),
            h('li', { class: 'small' }, '✦ Direct access with 24h response time'),
            h('li', { class: 'small' }, '✦ Cancel anytime, no minimum lock-in')),
          h('a', { href: 'mailto:hello@thetechunicorn.com?subject=Advisory%20Retainer%20Inquiry', class: 'btn ghost', style: { marginTop: '12px' } }, 'Start a Retainer')),
        h('article', { class: 'landing-price-card featured' },
          h('span', { class: 'landing-price-badge' }, 'Most popular'),
          h('h3', {}, 'MVP Build'),
          h('div', { class: 'row', style: { alignItems: 'baseline' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.2rem', fontWeight: '600' } }, 'from $12,000'),
            h('span', { class: 'muted small' }, 'fixed')),
          h('p', { class: 'muted' }, 'Your idea, live in 2–4 weeks — design, build, deploy.'),
          h('ul', { style: { listStyle: 'none', padding: '12px 0 0', margin: '0', display: 'grid', gap: '8px', borderTop: '1px solid var(--line)' } },
            h('li', { class: 'small' }, '✦ Full UX design, AI build & cloud deployment'),
            h('li', { class: 'small' }, '✦ Weekly Friday demos — see live progress'),
            h('li', { class: 'small' }, '✦ 30 days of post-launch support included'),
            h('li', { class: 'small' }, '✦ You own 100% of all code, IP & accounts')),
          h('a', { href: 'mailto:hello@thetechunicorn.com?subject=MVP%20Build%20Inquiry', class: 'btn primary', style: { marginTop: '12px' } }, 'Get a Fixed Quote')),
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
          link('/mediakit', { class: 'btn ghost', style: { marginTop: '12px' } }, 'Request Media Kit')))));

  // 9. Section 05: Content & Community
  const platforms = h('section', { class: 'landing-section', id: 'content' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '05 — Reach & Content'),
        h('h2', {}, 'Where the ', h('em', {}, 'internet'), ' finds me')),
      h('div', { class: 'grid grid-3', style: { gap: '20px' } },
        h('a', { href: 'https://instagram.com/tech.unicorn', target: '_blank', rel: 'noopener', class: 'landing-card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, 'Instagram'),
            h('span', { style: { color: 'var(--accent)' } }, '↗')),
          h('div', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.8rem', fontWeight: '600', color: 'var(--accent)', margin: '10px 0' } }, '384K'),
          h('p', { class: 'muted small' }, 'Daily tech news, AI experiments, and nomad engineering life in 60 seconds.'),
          h('span', { class: 'small', style: { color: 'var(--accent)', fontWeight: '600' } }, '@tech.unicorn')),
        h('a', { href: 'https://youtube.com/@Tech.Unicorn', target: '_blank', rel: 'noopener', class: 'landing-card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, 'YouTube'),
            h('span', { style: { color: 'var(--accent)' } }, '↗')),
          h('div', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.8rem', fontWeight: '600', color: 'var(--accent)', margin: '10px 0' } }, '22K'),
          h('p', { class: 'muted small' }, 'Deep dives, build-alongs, and coding challenges from beach cafés.'),
          h('span', { class: 'small', style: { color: 'var(--accent)', fontWeight: '600' } }, '@Tech.Unicorn')),
        h('a', { href: 'https://linkedin.com/in/delialazarescu', target: '_blank', rel: 'noopener', class: 'landing-card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, 'LinkedIn'),
            h('span', { style: { color: 'var(--accent)' } }, '↗')),
          h('div', { style: { fontFamily: 'Fraunces, serif', fontSize: '2.8rem', fontWeight: '600', color: 'var(--accent)', margin: '10px 0' } }, '25K'),
          h('p', { class: 'muted small' }, 'Career honesty, industry architecture takes, and lessons from Big Tech.'),
          h('span', { class: 'small', style: { color: 'var(--accent)', fontWeight: '600' } }, 'Delia Lazarescu')))));

  // 10. Section 06: Speaking
  const speaking = h('section', { class: 'landing-section tinted', id: 'speaking' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '06 — On Stage'),
        h('h2', {}, 'Recently ', h('em', {}, 'keynoting'))),
      h('div', { style: { display: 'grid', gap: '8px' } },
        h('div', { class: 'row', style: { justifyContent: 'space-between', padding: '16px 14px', borderBottom: '1px solid var(--line)' } },
          h('div', { class: 'row', style: { gap: '18px' } },
            h('span', { style: { fontFamily: 'Fraunces, serif', fontSize: '1.2rem', color: 'var(--accent)', fontWeight: '600' } }, '01'),
            h('div', {},
              h('h3', {}, 'Nomad Cruise'),
              h('p', { class: 'muted small' }, 'Keynote on building a tech career without an address — engineering, content, and revenue.'))),
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
              h('p', { class: 'muted small' }, 'Workshop on practical AI for non-technical teams — making the intimidating feel obvious.'))),
          h('span', { class: 'pf-chip' }, 'Workshop')))));

  // 11. Conversion CTA Banner
  const cta = h('section', { class: 'landing-container' },
    h('div', { class: 'landing-cta-banner' },
      h('p', { class: 'landing-section-eyebrow' }, 'Let’s build something'),
      h('h2', { style: { fontSize: 'clamp(2rem, 4.5vw, 3.4rem)', marginBottom: '12px' } }, 'Have an idea that needs ', h('em', {}, 'shipping?')),
      h('p', { style: { fontSize: '1.15rem', maxWidth: '48ch', marginInline: 'auto', marginBottom: '28px' } }, 'App, campaign, keynote, or something bolder — if it makes tech less scary, let’s talk.'),
      h('a', { href: 'mailto:hello@thetechunicorn.com?subject=Project%20Inquiry', class: 'btn primary lg' }, 'hello@thetechunicorn.com')));

  // 12. Signature Typographic Footer
  const footer = h('footer', { class: 'landing-footer' },
    h('div', { class: 'landing-footer-wordmark', 'aria-hidden': 'true' }, 'TECH UNICORN'),
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-footer-links' },
        h('a', { href: 'https://instagram.com/tech.unicorn', target: '_blank', rel: 'noopener' }, 'Instagram'),
        h('a', { href: 'https://youtube.com/@Tech.Unicorn', target: '_blank', rel: 'noopener' }, 'YouTube'),
        h('a', { href: 'https://linkedin.com/in/delialazarescu', target: '_blank', rel: 'noopener' }, 'LinkedIn'),
        h('a', { href: 'https://tiktok.com/@tech.unicorn', target: '_blank', rel: 'noopener' }, 'TikTok'),
        link('/links', {}, 'Link in Bio'),
        link('/mediakit', {}, 'Media Kit'),
        h('a', { href: 'mailto:hello@thetechunicorn.com' }, 'Email')),
      h('p', { class: 'muted small' }, '© 2026 Tech Unicorn · Software development, technical advisory & media services · Made somewhere with good wifi 🌴')));

  const landingWrapper = h('div', { class: 'landing-page' },
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

  view.replaceChildren(landingWrapper);
  return () => landingWrapper.remove();
}
