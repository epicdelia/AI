// Landing page "01: About": her words from the dashboard's "About you" card plus facts the studio
// already keeps (audience from Public reach, latest talk from Speaking, build-slot availability
// from Client journey), so this section always agrees with the rest of the page. It renders at once
// with her current copy, then updates from /api/landing/about.
import { h, api, ensureLandingCss } from './core.js';
import { icon } from './icons.js';

ensureLandingCss(); // its styles live in landing.css

const DEFAULTS = {
  headline: 'Engineer first. Storyteller always.',
  bio: ['I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it was not for them.'],
  thesis: 'Tech feels scary because it is explained badly.',
  thesis_detail: 'Whether it is a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.',
  now: 'Building AI-powered apps and explaining them on camera',
  location: '',
  story_url: 'https://linkedin.com/in/delialazarescu',
  milestones: [
    { label: 'Self-taught engineer', detail: 'Learned to code on her own and got hired as a software engineer.' },
    { label: 'Google', detail: 'Wrote production code at Google.' },
    { label: 'Independent founder', detail: 'Left Big Tech to build AI-powered products for clients, from anywhere.' },
    { label: 'Creator & speaker', detail: 'Explains tech to a large community and on stage.' },
  ],
  portrait_url: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
  facts: { countries: 69, audience: '472K+', networks: 6, talks: 0, latest_talk: null, availability: null },
};

/** "Engineer first. Storyteller always." → the second sentence's first word in the accent serif. */
function headline(text) {
  const m = String(text).match(/^(.*?[.!?]\s+)(\S+)(.*)$/);
  return m ? [m[1], h('em', {}, m[2]), m[3]] : [text];
}

const jump = (id) => (e) => {
  const el = document.getElementById(id);
  if (!el) return;
  e.preventDefault();
  el.scrollIntoView({ behavior: 'smooth' });
};

function factTiles(f) {
  const talk = f.latest_talk;
  const tiles = [
    f.countries ? { icon: 'globe', num: String(f.countries), label: 'countries', sub: 'worked and shipped from', href: null } : null,
    f.audience ? { icon: 'users', num: f.audience, label: 'people', sub: `follow along across ${f.networks} networks`, href: 'reach' } : null,
    f.talks ? { icon: 'mic', num: String(f.talks), label: f.talks === 1 ? 'talk' : 'talks', sub: talk ? `${talk.upcoming ? 'Next' : 'Latest'}: ${talk.event}` : 'on stages worldwide', href: 'speaking' } : null,
  ].filter(Boolean);
  return h('div', { class: 'landing-about-facts' }, tiles.map((t) => {
    const body = [
      h('span', { class: 'landing-about-fact-icon', 'aria-hidden': 'true' }, icon(t.icon, 16)),
      h('span', { class: 'landing-about-fact-num' }, t.num, h('small', {}, ` ${t.label}`)),
      h('span', { class: 'landing-about-fact-sub' }, t.sub),
    ];
    return t.href
      ? h('a', { class: 'landing-about-fact', href: `#${t.href}`, onclick: jump(t.href) }, ...body)
      : h('div', { class: 'landing-about-fact' }, ...body);
  }));
}

function journey(milestones) {
  if (!milestones.length) return null;
  const list = h('ol', {
    class: 'landing-about-journey show-details',
    id: 'landing-about-journey',
    style: { '--steps': String(milestones.length) }
  },
    milestones.map((m, i) => h('li', { class: i === milestones.length - 1 ? 'is-now' : '' },
      h('span', { class: 'landing-about-step-dot', 'aria-hidden': 'true' },
        i === milestones.length - 1 ? icon('sparkle', 12) : String(i + 1)
      ),
      h('div', { class: 'landing-about-step-content' },
        h('strong', { class: 'landing-about-step-title' }, m.label),
        m.detail ? h('span', { class: 'landing-about-step-detail' }, m.detail) : null
      )
    ))
  );

  return h('div', { class: 'landing-about-journey-wrap' },
    h('div', { class: 'landing-about-journey-head' },
      h('span', {}, 'The path so far'),
      h('span', { class: 'landing-about-journey-badge' }, `${milestones.length} milestones`)
    ),
    list
  );
}

function portrait(d, { onBookCall }) {
  const f = d.facts || {};
  const avail = f.availability;

  // Curated showcase slides for Delia's portfolio
  const SLIDES = [
    {
      src: d.portrait_url || '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
      alt: 'Delia Lazarescu, ex-Google engineer and founder',
      tag: 'Ex-Google Engineer & Founder',
      icon: 'code',
      ctaType: 'consulting',
      ctaLabel: 'Book an intro call',
      ctaService: 'About: Intro call',
      statusLabel: avail ? avail.label : '1 build slot open',
      statusClass: avail ? ('is-' + avail.status) : 'is-open',
      statusPrompt: 'Advisory & AI Architecture'
    },
    {
      src: '/images/delia_hyperagent.jpg',
      alt: 'Delia Lazarescu building AI HyperAgents & autonomous engineering systems',
      tag: 'AI Systems & HyperAgents',
      icon: 'sparkle',
      ctaType: 'mvp_build',
      ctaLabel: 'Build with Delia',
      ctaService: 'AI HyperAgents & Systems',
      statusLabel: 'Production AI Sprints',
      statusClass: 'is-open',
      statusPrompt: 'Autonomous LLM Pipelines'
    },
    {
      src: '/images/delia_tech_ai.jpg',
      alt: 'Delia Lazarescu, keynote speaker and content creator for 519K+ builders',
      tag: 'Tech Creator & Keynote Speaker',
      icon: 'mic',
      ctaType: 'speaking',
      ctaLabel: 'Book Keynote / Talk',
      ctaService: 'Keynote & Tech Talks',
      statusLabel: 'Global Stages & Studio',
      statusClass: 'is-open',
      statusPrompt: '519K+ Builders Worldwide'
    }
  ];

  let currentIndex = 0;
  let timer = null;
  let isPaused = false;

  const showcase = h('div', { class: 'landing-about-showcase landing-slider-showcase' });
  const card = h('figure', {
    class: 'landing-about-photo-card landing-about-slider-card',
    tabIndex: 0,
    role: 'region',
    'aria-label': 'Delia Lazarescu Photo Showcase Slider',
    'aria-roledescription': 'carousel'
  });

  // Track of images with smooth cross-fade & zoom
  const track = h('div', { class: 'landing-slider-track' });
  const slideEls = SLIDES.map((slide, idx) => {
    return h('div', {
      class: 'landing-slider-slide' + (idx === 0 ? ' is-active' : ''),
      'aria-hidden': String(idx !== 0)
    },
      h('img', {
        src: slide.src,
        alt: slide.alt,
        class: 'landing-about-photo',
        width: '480',
        height: '480',
        loading: idx === 0 ? 'eager' : 'lazy',
        decoding: 'async',
        style: { aspectRatio: '1 / 1', objectFit: 'cover' }
      })
    );
  });
  track.append(...slideEls);

  // Indicators / Dots (defined before topOverlay which embeds dotsWrap)
  const dotsWrap = h('div', { class: 'landing-slider-dots', role: 'tablist', 'aria-label': 'Choose photo' });
  const dotEls = SLIDES.map((slideItem, idx) => h('button', {
    type: 'button',
    class: 'landing-slider-dot' + (idx === 0 ? ' is-active' : ''),
    role: 'tab',
    'aria-selected': String(idx === 0),
    'aria-label': 'Go to slide ' + (idx + 1),
    onclick: (e) => { e.stopPropagation(); goToSlide(idx); }
  }));
  dotsWrap.append(...dotEls);

  // Top header overlay: Tag pill on left, dots in center, counter badge on right
  const tagEl = h('span', { class: 'landing-about-photo-tag' },
    h('span', { class: 'icon', 'aria-hidden': 'true' }, icon(SLIDES[0].icon, 12)),
    h('span', { class: 'landing-tag-label' }, SLIDES[0].tag)
  );

  const counterEl = h('span', { class: 'landing-slider-counter' }, '1 / ' + SLIDES.length);
  const topOverlay = h('div', { class: 'landing-slider-top-bar' }, tagEl, dotsWrap, counterEl);

  // Navigation Arrows
  const prevBtn = h('button', {
    type: 'button',
    class: 'landing-slider-nav-btn prev',
    'aria-label': 'Previous photo',
    onclick: (e) => { e.stopPropagation(); goToSlide(currentIndex - 1); }
  }, icon('chevron-left', 16));

  const nextBtn = h('button', {
    type: 'button',
    class: 'landing-slider-nav-btn next',
    'aria-label': 'Next photo',
    onclick: (e) => { e.stopPropagation(); goToSlide(currentIndex + 1); }
  }, icon('chevron-right', 16));

  // Interactive Client Action Card at bottom of slider
  const ctaBtnText = h('span', {}, SLIDES[0].ctaLabel);
  const primaryCtaBtn = h('button', {
    type: 'button',
    class: 'landing-slider-cta-btn',
    onclick: (e) => {
      e.stopPropagation();
      const s = SLIDES[currentIndex];
      if (typeof onBookCall === 'function') {
        onBookCall(s.ctaType || 'consulting', s.ctaService || 'About Slider CTA');
      }
    }
  }, icon('calendar', 14), ctaBtnText);

  const storyCtaBtn = h('a', {
    class: 'landing-slider-story-btn',
    href: d.story_url || 'https://linkedin.com/in/delialazarescu',
    target: '_blank',
    rel: 'noopener',
    onclick: (e) => e.stopPropagation()
  }, 'Read my story', icon('external', 13));

  const statusTextEl = h('span', { class: 'landing-slider-status-text ' + SLIDES[0].statusClass }, SLIDES[0].statusLabel);
  const statusPromptEl = h('span', { class: 'landing-slider-prompt' }, SLIDES[0].statusPrompt);

  const actionCard = h('figcaption', { class: 'landing-slider-action-card' },
    h('div', { class: 'landing-slider-action-header' },
      h('div', { class: 'landing-slider-status-pill' },
        h('i', { class: 'landing-pulse-dot', 'aria-hidden': 'true' }),
        statusTextEl
      ),
      statusPromptEl
    ),
    h('div', { class: 'landing-slider-action-buttons' },
      primaryCtaBtn,
      storyCtaBtn
    )
  );

  // Progress Bar
  const progressBar = h('div', { class: 'landing-slider-progress-bar' });
  const progressInner = h('div', { class: 'landing-slider-progress-inner' });
  progressBar.appendChild(progressInner);

  function goToSlide(newIndex) {
    if (newIndex < 0) newIndex = SLIDES.length - 1;
    if (newIndex >= SLIDES.length) newIndex = 0;
    currentIndex = newIndex;

    const s = SLIDES[currentIndex];

    // Update slides visibility
    slideEls.forEach((el, idx) => {
      const active = idx === currentIndex;
      el.classList.toggle('is-active', active);
      el.setAttribute('aria-hidden', String(!active));
    });

    // Update dots
    dotEls.forEach((el, idx) => {
      const active = idx === currentIndex;
      el.classList.toggle('is-active', active);
      el.setAttribute('aria-selected', String(active));
    });

    // Update counter
    counterEl.textContent = (currentIndex + 1) + ' / ' + SLIDES.length;

    // Update Tag
    tagEl.replaceChildren(
      h('span', { class: 'icon', 'aria-hidden': 'true' }, icon(s.icon, 12)),
      h('span', { class: 'landing-tag-label' }, s.tag)
    );

    // Update Interactive Action Card
    ctaBtnText.textContent = s.ctaLabel;
    statusTextEl.textContent = s.statusLabel;
    statusTextEl.className = 'landing-slider-status-text ' + s.statusClass;
    statusPromptEl.textContent = s.statusPrompt;

    // Reset progress animation
    resetProgress();
  }

  function resetProgress() {
    progressInner.style.animation = 'none';
    void progressInner.offsetWidth;
    if (!isPaused) {
      progressInner.style.animation = 'landingSliderProgress 5.5s linear infinite';
    }
  }

  function startAutoPlay() {
    stopAutoPlay();
    timer = setInterval(() => {
      if (!isPaused) goToSlide(currentIndex + 1);
    }, 5500);
    resetProgress();
  }

  function stopAutoPlay() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  card.addEventListener('mouseenter', () => { isPaused = true; progressInner.style.animationPlayState = 'paused'; });
  card.addEventListener('mouseleave', () => { isPaused = false; progressInner.style.animationPlayState = 'running'; });

  // Touch Swipe Handling for Mobile
  let touchStartX = 0;
  let touchEndX = 0;
  card.addEventListener('touchstart', (e) => { touchStartX = e.changedTouches[0].screenX; isPaused = true; }, { passive: true });
  card.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    isPaused = false;
    const diff = touchEndX - touchStartX;
    if (diff > 45) goToSlide(currentIndex - 1);
    else if (diff < -45) goToSlide(currentIndex + 1);
  }, { passive: true });

  // Keyboard accessibility
  card.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') { e.preventDefault(); goToSlide(currentIndex - 1); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); goToSlide(currentIndex + 1); }
  });

  startAutoPlay();

  card.append(track, topOverlay, prevBtn, nextBtn, actionCard, progressBar);
  showcase.appendChild(card);
  return showcase;
}

/** @param {{ onBookCall: (type: string, service?: string) => void }} options */
export function aboutSection({ onBookCall }) {
  const section = h('section', { class: 'landing-section landing-about', id: 'about', 'aria-labelledby': 'landing-about-title' });

  const render = (d) => {
    section.replaceChildren(h('div', { class: 'landing-container' },
      h('div', { class: 'landing-about-grid' },
        h('div', { class: 'landing-about-text' },
          h('p', { class: 'landing-section-eyebrow' }, '01: About'),
          h('h2', { id: 'landing-about-title' }, ...headline(d.headline)),
          ...(d.bio || []).map((p) => h('p', { class: 'landing-about-bio' }, p)),
          d.thesis ? h('blockquote', { class: 'landing-about-thesis' },
            h('p', {}, h('strong', {}, d.thesis)),
            d.thesis_detail ? h('p', { class: 'muted' }, d.thesis_detail) : null) : null,
          factTiles(d.facts || {}),
          journey(d.milestones || [])
        ),
        portrait(d, { onBookCall })
      )));
  };

  render(DEFAULTS);
  api('GET', 'landing/about').then((d) => render({ ...DEFAULTS, ...d, facts: { ...DEFAULTS.facts, ...(d.facts || {}) } })).catch(() => {});
  return section;
}
