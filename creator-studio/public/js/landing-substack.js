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
      id: 1,
      title: 'My new favourite AI has my credit card. Here are the rules I make it follow.',
      excerpt: 'I’m in Bali, texting an AI on WhatsApp to fix my macbook, book my massages, order my coffee… Here are the exact sandbox verification rules.',
      url: 'https://techunicorn.substack.com/p/my-new-favourite-ai-has-my-credit',
      category: 'AI & Agents',
      read_time: '12 min read',
      date: 'Oct 2026',
      cover: 'https://substackcdn.com/image/youtube/w_728,c_limit/ik8cyqLfLDE',
      popular: true,
    },
    {
      id: 2,
      title: 'How to set up a 24/7 AI employee (ClawdBot managed my business while I partied in Davos🤖)',
      excerpt: 'I was in Davos at the World Economic Forum surrounded by billionaires… meanwhile my 24/7 autonomous agent handled client leads and operations.',
      url: 'https://techunicorn.substack.com/p/how-to-set-up-a-247-ai-employee-my',
      category: 'AI & Agents',
      read_time: '11 min read',
      date: 'Jan 2026',
      cover: 'https://substackcdn.com/image/fetch/$s_!c5-b!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2Fdcd9a088-2c27-4744-adf8-4164177f0e97_1200x1600.jpeg',
      popular: true,
    },
    {
      id: 3,
      title: 'Is Your CS Degree About to Become Worthless?..',
      excerpt: 'Remember when a CS degree was your golden ticket to Silicon Valley? The industry shifted: architecture and systems debugging are the real defensible moats.',
      url: 'https://techunicorn.substack.com/p/is-your-cs-degree-about-to-become',
      category: 'Career & Tech',
      read_time: '4 min read',
      date: 'Jul 2025',
      cover: 'https://substackcdn.com/image/fetch/$s_!UT14!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F3d19a0cb-a5df-467b-b5fc-246ae2ff2043_1280x720.png',
      popular: false,
    },
    {
      id: 4,
      title: '$40/Hour to $400K at Google WITHOUT a Degree - The Roadmap Nobody Talks About 🚀',
      excerpt: 'The roadmap from $40/hour intern to $68K to $130K to $230K to $400K+ at Google without a computer science degree, completely deconstructed.',
      url: 'https://techunicorn.substack.com/p/40hour-to-400k-at-google-without',
      category: 'Career & Tech',
      read_time: '4 min read',
      date: 'Jun 2025',
      cover: 'https://substackcdn.com/image/fetch/$s_!MkHW!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F40238c89-04be-4b28-b822-be1a7d7cfcab_2240x1260.png',
      popular: false,
    },
    {
      id: 5,
      title: 'From $150K at Google to $400K at Airbnb (Real Interview Secrets Inside!) 💰',
      excerpt: 'Real interview transcripts, distributed systems architecture rounds, and negotiation tactics for top-of-market software compensation.',
      url: 'https://techunicorn.substack.com/p/from-150k-at-google-to-400k-at-airbnb',
      category: 'Career & Tech',
      read_time: '4 min read',
      date: 'May 2025',
      cover: 'https://substackcdn.com/image/fetch/$s_!fVox!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F399d5dcb-9bbf-48f8-9abb-ac981a42d411_1280x720.png',
      popular: false,
    },
    {
      id: 6,
      title: '21 Hackathon Wins at 21 Years Old? 🤯🔮',
      excerpt: 'There is a secret formula to winning hackathons and turning 48-hour prototypes into venture-backed companies and viral products.',
      url: 'https://techunicorn.substack.com/p/21-hackathon-wins-at-21-years-old',
      category: 'Hackathons',
      read_time: '4 min read',
      date: 'May 2025',
      cover: 'https://substackcdn.com/image/fetch/$s_!v0NQ!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2Ff035f3e3-4e6e-4613-9c27-9d125f288b27_2048x1365.jpeg',
      popular: false,
    },
    {
      id: 7,
      title: 'Top 10 AI Tools That 10x My Life 🚀',
      excerpt: 'Giving an AI keynote on a cruise to Japan created using AI. Here are the 10 production tools that automate my engineering and content workflow.',
      url: 'https://techunicorn.substack.com/p/top-10-ai-tools-that-10x-my-life',
      category: 'AI & Agents',
      read_time: '6 min read',
      date: 'Nov 2024',
      cover: 'https://substackcdn.com/image/fetch/$s_!3lH_!,f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2Fc6c62933-91c6-44a1-8fc1-30f72c088939_4032x3024.jpeg',
      popular: false,
    },
    {
      id: 8,
      title: 'Welcome to the Digital Nomad Life :)',
      excerpt: 'I’m Delia, your favorite always-traveling ex-Google engineer. Lessons from shipping code across 60+ countries and living out of a carry-on.',
      url: 'https://techunicorn.substack.com/p/digital-nomad-tech-bro-life',
      category: 'Nomad Life',
      read_time: '4 min read',
      date: 'Sep 2024',
      cover: 'https://substackcdn.com/image/fetch/f_auto,q_auto:good,fl_progressive:steep/https%3A%2F%2Fsubstack-post-media.s3.amazonaws.com%2Fpublic%2Fimages%2F3f3284ff-5438-4673-a458-a586fffd88ed_3927x2208.png',
      popular: false,
    },
  ],
};

/**
 * 08: Substack Section (4-column cards grid with large headline and category filters).
 */
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

    // Section Header with prominent large title matching the landing design system
    const header = h('div', { class: 'landing-substack-header' },
      h('div', { class: 'landing-substack-header-text' },
        h('p', { class: 'landing-section-eyebrow' }, icon('sparkle', 13), '08: The Tech Unicorn Dispatch'),
        h('h2', { id: 'landing-substack-title', class: 'landing-substack-title' },
          'Engineering, AI & ',
          h('em', {}, 'Nomad Essays')
        ),
        h('p', { class: 'landing-section-sub' },
          'Deep-dive architectural essays, autonomous agent playbooks, and digital nomad dispatches by Delia Lazarescu (',
          h('a', {
            href: pub.profile_url || 'https://substack.com/@techunicorn',
            target: '_blank',
            rel: 'noopener noreferrer',
            class: 'landing-substack-author-link'
          }, pub.handle || '@techunicorn'),
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

    // Multi-Column Articles Grid (4 columns on desktop, 3 on tablet/laptop, never locked to 2)
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
            h('div', { class: 'landing-substack-cover-overlay' }),
            h('span', { class: 'landing-substack-card-category' }, post.category),
            post.popular ? h('span', { class: 'landing-substack-card-popular' }, icon('sparkle', 10), ' Popular') : null
          ),
          h('div', { class: 'landing-substack-card-body' },
            h('div', { class: 'landing-substack-card-meta' },
              h('span', { class: 'landing-substack-badge' },
                h('span', { class: 'landing-substack-icon', 'aria-hidden': 'true' }, '✦'),
                'Tech Unicorns'
              ),
              h('span', { class: 'landing-substack-dot' }, '·'),
              h('span', { class: 'landing-substack-date' }, post.date),
              h('span', { class: 'landing-substack-dot' }, '·'),
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

    section.replaceChildren(
      h('div', { class: 'landing-container' },
        header,
        filterTabs,
        grid
      )
    );
  };

  render(DEFAULTS);
  api('GET', 'landing/substack')
    .then((data) => render(data))
    .catch(() => {});

  return section;
}

/**
 * Polished Sunday Deep-Dive Newsletter Banner:
 * Positioned in the footer zone above the marquee.
 */
export function sundayDeepDiveBanner() {
  const container = h('section', {
    class: 'landing-sunday-dispatch-section',
    'aria-label': 'Sunday Deep-Dive Newsletter',
  });

  const emailInput = h('input', {
    type: 'email',
    placeholder: 'Enter your email for Sunday deep-dives...',
    class: 'landing-sunday-dispatch-input',
    required: true,
    'aria-label': 'Email address for Sunday deep-dives newsletter',
  });

  const form = h('form', {
    class: 'landing-sunday-dispatch-form',
    onsubmit: (e) => {
      e.preventDefault();
      const email = emailInput.value.trim();
      const subUrl = `https://techunicorn.substack.com/subscribe?email=${encodeURIComponent(email)}`;
      window.open(subUrl, '_blank', 'noopener,noreferrer');
    },
  },
    emailInput,
    h('button', { type: 'submit', class: 'landing-sunday-dispatch-submit-btn' },
      icon('sparkle', 14),
      'Subscribe Free'
    )
  );

  const banner = h('div', { class: 'landing-sunday-dispatch-banner' },
    h('div', { class: 'landing-sunday-dispatch-content' },
      h('span', { class: 'landing-sunday-dispatch-badge' }, icon('sparkle', 12), 'Sunday Deep-Dive'),
      h('h3', {}, 'Get Delia’s Engineering & AI Dispatches in your inbox'),
      h('p', {}, 'Every Sunday: one production architecture breakdown, zero fluff, and raw notes from building around the world.')
    ),
    h('div', { class: 'landing-sunday-dispatch-action' },
      form,
      h('p', { class: 'landing-sunday-dispatch-trust' },
        h('span', { class: 'landing-pulse-dot', 'aria-hidden': 'true' }),
        'Free weekly essay · Join 24,000+ engineers & founders · Unsubscribe anytime'
      )
    )
  );

  container.appendChild(h('div', { class: 'landing-container' }, banner));
  return container;
}
