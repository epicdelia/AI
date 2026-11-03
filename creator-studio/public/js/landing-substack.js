// Landing page "08: The Tech Unicorn Dispatch": latest Substack essays, engineering breakdowns,
// and digital nomad dispatches. Reads live from /api/landing/substack with instant fallback.
import { h, api, ensureLandingCss } from './core.js';
import { icon } from './icons.js';

ensureLandingCss();

const DEFAULTS = {
  publication: {
    name: 'Tech Unicorns',
    author: 'Delia Lazarescu',
    handle: '@techunicorn',
    profile_url: 'https://substack.com/@techunicorn',
    url: 'https://techunicorn.substack.com',
    subscribers: '24K+',
    tagline: 'Coding, AI experiments, digital nomadism, and building software around the world.',
  },
  posts: [
    {
      id: 101,
      title: 'My new favourite AI has my credit card. Here are the rules I make it follow.',
      excerpt: 'Giving an autonomous agent spend authority sounds terrifying until you build deterministic verification guardrails. Here is the exact sandbox architecture.',
      url: 'https://techunicorn.substack.com/p/my-new-favourite-ai-has-my-credit-card',
      category: 'AI Agents',
      read_time: '6 min read',
      date: 'Oct 2026',
      cover: '/images/delia_hyperagent.jpg',
      popular: true,
    },
    {
      id: 102,
      title: 'How to set up a 24/7 AI employee (ClawdBot managed my business while I partied in Davos)',
      excerpt: 'A practical, unvarnished walkthrough of building background autonomous workflows that triage client leads, schedule tasks, and keep the business running autonomously.',
      url: 'https://techunicorn.substack.com/p/how-to-set-up-a-24-7-ai-employee',
      category: 'Autonomous Systems',
      read_time: '8 min read',
      date: 'Jan 2026',
      cover: '/images/delia_tech_ai.jpg',
      popular: true,
    },
    {
      id: 103,
      title: 'Is Your CS Degree About to Become Worthless?',
      excerpt: 'The software industry shifted under our feet. Syntax memorization is dead; architecture, prompt harness design, and system debugging are the real defensible moats.',
      url: 'https://techunicorn.substack.com/p/is-your-cs-degree-about-to-become-worthless',
      category: 'Engineering',
      read_time: '7 min read',
      date: 'Jul 2025',
      cover: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
      popular: false,
    },
    {
      id: 104,
      title: '$40/Hour to $400K at Google WITHOUT a Degree - The Roadmap Nobody Talks About',
      excerpt: 'The raw, unfiltered blueprint of learning to code independently, mastering production systems, passing Google technical interviews, and trading the campus for a laptop bag.',
      url: 'https://techunicorn.substack.com/p/40-hour-to-400k-at-google-without-a-degree',
      category: 'Career & Nomad',
      read_time: '10 min read',
      date: 'Jun 2025',
      cover: '/images/delia_portrait_round.jpg',
      popular: true,
    },
  ],
};

/** "Engineering, AI & Nomad Essays" → the second sentence's first word in accent serif. */
function headline(text) {
  const m = String(text).match(/^(.*?[,&]\s+)(\S+)(.*)$/);
  return m ? [m[1], h('em', {}, m[2]), m[3]] : [text];
}

export function substackSection() {
  const section = h('section', {
    class: 'landing-section landing-substack',
    id: 'substack',
    'aria-labelledby': 'landing-substack-title',
  });

  let currentCategory = 'all';

  const render = (data) => {
    const pub = data.publication || DEFAULTS.publication;
    const allPosts = data.posts || DEFAULTS.posts;

    // Collect unique categories
    const categories = ['all', ...new Set(allPosts.map((p) => p.category))];

    const filteredPosts = currentCategory === 'all'
      ? allPosts
      : allPosts.filter((p) => p.category === currentCategory);

    // Section Header
    const header = h('div', { class: 'landing-substack-header' },
      h('div', { class: 'landing-substack-header-text' },
        h('p', { class: 'landing-section-eyebrow' }, icon('sparkle', 13), '08: The Tech Unicorn Dispatch'),
        h('h2', { id: 'landing-substack-title' }, ...headline('Engineering, AI & Nomad Essays')),
        h('p', { class: 'landing-section-sub' },
          'Deep-dive architectural essays, autonomous agent playbooks, and digital nomad dispatches by Delia Lazarescu (',
          h('a', { href: pub.profile_url || 'https://substack.com/@techunicorn', target: '_blank', rel: 'noopener noreferrer', style: { color: 'var(--accent)', textDecoration: 'underline' } }, pub.handle || '@techunicorn'),
          ') read by ',
          h('strong', {}, `${pub.subscribers || '24K+'} builders`),
          ' worldwide.'
        )
      ),
      h('div', { class: 'landing-substack-header-actions' },
        h('a', {
          class: 'btn primary landing-substack-sub-btn',
          href: pub.profile_url || 'https://substack.com/@techunicorn',
          target: '_blank',
          rel: 'noopener noreferrer',
          title: 'Read and subscribe on Substack (@techunicorn)',
        },
          icon('external', 14),
          h('span', {}, 'Substack @techunicorn'),
          h('span', { class: 'landing-substack-pill' }, pub.subscribers || '24K+')
        )
      )
    );

    // Filter Tabs
    const filterTabs = h('div', { class: 'landing-substack-tabs', role: 'tablist', 'aria-label': 'Filter articles by category' },
      categories.map((cat) => h('button', {
        type: 'button',
        class: `landing-substack-tab ${cat === currentCategory ? 'is-active' : ''}`,
        role: 'tab',
        'aria-selected': String(cat === currentCategory),
        onclick: () => {
          currentCategory = cat;
          render(data);
        },
      }, cat === 'all' ? 'All Articles' : cat))
    );

    // Articles Grid
    const grid = h('div', { class: 'landing-substack-grid' },
      filteredPosts.map((post) => {
        return h('article', { class: 'landing-substack-card' },
          h('a', {
            href: post.url,
            target: '_blank',
            rel: 'noopener noreferrer',
            class: 'landing-substack-card-cover-wrap',
            'aria-label': post.title,
          },
            h('img', {
              src: post.cover || '/images/delia_hyperagent.jpg',
              alt: post.title,
              class: 'landing-substack-card-cover',
              loading: 'lazy',
              decoding: 'async',
            }),
            h('span', { class: 'landing-substack-card-category' }, post.category),
            post.popular ? h('span', { class: 'landing-substack-card-popular' }, 'Popular') : null
          ),
          h('div', { class: 'landing-substack-card-body' },
            h('div', { class: 'landing-substack-card-meta' },
              h('span', { class: 'landing-substack-badge' },
                h('span', { class: 'landing-substack-icon', 'aria-hidden': 'true' }, '✦'),
                'Tech Unicorns'
              ),
              h('span', { class: 'muted' }, '·'),
              h('span', { class: 'landing-substack-date' }, post.date),
              h('span', { class: 'muted' }, '·'),
              h('span', { class: 'landing-substack-read-time' }, post.read_time)
            ),
            h('h3', { class: 'landing-substack-card-title' },
              h('a', {
                href: post.url,
                target: '_blank',
                rel: 'noopener noreferrer',
              }, post.title)
            ),
            h('p', { class: 'landing-substack-card-excerpt' }, post.excerpt),
            h('div', { class: 'landing-substack-card-footer' },
              h('a', {
                href: post.url,
                target: '_blank',
                rel: 'noopener noreferrer',
                class: 'landing-substack-read-link',
              },
                'Read on Substack',
                icon('arrow', 13)
              )
            )
          )
        );
      })
    );

    // Newsletter Subscribe Banner
    const newsletterEmail = h('input', {
      type: 'email',
      placeholder: 'Enter your email for Sunday deep-dives...',
      class: 'landing-substack-input',
      required: true,
      'aria-label': 'Email address for Substack newsletter',
    });

    const newsletterForm = h('form', {
      class: 'landing-substack-form',
      onsubmit: (e) => {
        e.preventDefault();
        const email = newsletterEmail.value.trim();
        const subUrl = pub.url
          ? `${pub.url}/subscribe?email=${encodeURIComponent(email)}`
          : `https://techunicorn.substack.com/subscribe?email=${encodeURIComponent(email)}`;
        window.open(subUrl, '_blank', 'noopener,noreferrer');
      },
    },
      newsletterEmail,
      h('button', { type: 'submit', class: 'btn primary landing-substack-submit-btn' },
        icon('sparkle', 14),
        'Subscribe Free'
      )
    );

    const subscribeBox = h('div', { class: 'landing-substack-newsletter-box' },
      h('div', { class: 'landing-substack-newsletter-content' },
        h('span', { class: 'landing-substack-newsletter-badge' }, icon('sparkle', 12), 'Sunday Deep-Dive'),
        h('h3', {}, 'Get Delia’s Engineering & AI Dispatches in your inbox'),
        h('p', {}, 'Every Sunday: one production architecture breakdown, zero fluff, and raw notes from building around the world.')
      ),
      h('div', { class: 'landing-substack-newsletter-action' },
        newsletterForm,
        h('p', { class: 'landing-substack-newsletter-trust' },
          h('span', { class: 'landing-pulse-dot', 'aria-hidden': 'true' }),
          'Free weekly essay · Join 24,000+ engineers & founders · Unsubscribe anytime'
        )
      )
    );

    section.replaceChildren(
      h('div', { class: 'landing-container' },
        header,
        filterTabs,
        grid,
        subscribeBox
      )
    );
  };

  render(DEFAULTS);
  api('GET', 'landing/substack')
    .then((data) => render(data))
    .catch(() => {});

  return section;
}
