// Skeleton loaders: grey placeholders in the shape of what's coming, with one shimmer
// sweeping across them all, so pages and panels never jump when content arrives.
// Screen readers hear "Loading <what>" once (role=status, aria-busy).
import { h } from './core.js';

/** One placeholder block. w/hgt are CSS lengths; round = pill or circle. */
export function sk({ w = '100%', hgt = '12px', round = false, cls = '' } = {}) {
  return h('span', { class: `sk${round ? ' sk-round' : ''}${cls ? ` ${cls}` : ''}`, style: { width: w, height: hgt }, 'aria-hidden': 'true' });
}

function wrap(label, ...children) {
  return h('div', { class: 'sk-wrap', role: 'status', 'aria-busy': 'true', 'aria-live': 'polite' },
    h('span', { class: 'sr-only' }, `Loading ${label}…`), children);
}

/** A paragraph of lines with natural, uneven widths. */
export function skLines(n = 3, label = 'content') {
  const widths = ['96%', '88%', '92%', '70%', '84%', '60%', '90%', '76%'];
  return wrap(label, h('div', { class: 'sk-stack' }, Array.from({ length: n }, (_, i) => sk({ w: i === n - 1 ? '58%' : widths[i % widths.length] }))));
}

/** Rows with a leading thumb or avatar, two lines of text and a trailing value. */
export function skList(n = 5, { thumb = 'avatar', label = 'list' } = {}) {
  return wrap(label, h('div', { class: 'sk-list' }, Array.from({ length: n }, (_, i) => h('div', { class: 'sk-row' },
    thumb === 'avatar' ? sk({ w: '36px', hgt: '36px', round: true }) : thumb === 'video' ? sk({ w: '96px', hgt: '54px', cls: 'sk-r8' }) : null,
    h('div', { class: 'sk-row-main' }, sk({ w: `${62 + ((i * 17) % 30)}%`, hgt: '13px' }), sk({ w: `${34 + ((i * 11) % 24)}%`, hgt: '10px' })),
    sk({ w: '52px', hgt: '20px', round: true })))));
}

/** A grid of cards: a chip row, a title, two lines and a footer. */
export function skCards(n = 6, label = 'cards') {
  return wrap(label, h('div', { class: 'sk-cards' }, Array.from({ length: n }, (_, i) => h('div', { class: 'sk-card' },
    h('div', { class: 'sk-inline' }, sk({ w: '74px', hgt: '20px', round: true }), sk({ w: '54px', hgt: '12px' }), h('span', { class: 'sk-spacer' }), sk({ w: '58px', hgt: '20px', round: true })),
    sk({ w: `${55 + ((i * 13) % 35)}%`, hgt: '15px' }),
    h('div', { class: 'sk-stack' }, sk({ w: '94%' }), sk({ w: '72%' })),
    sk({ w: '45%', hgt: '10px' })))));
}

function head({ actions = 2, desc = true } = {}) {
  return h('div', { class: 'sk-head' },
    h('div', { class: 'sk-stack', style: { flex: '1' } }, sk({ w: '220px', hgt: '28px', cls: 'sk-r8' }), desc ? sk({ w: 'min(520px, 90%)', hgt: '13px' }) : null),
    h('div', { class: 'sk-inline' }, Array.from({ length: actions }, (_, i) => sk({ w: i ? '120px' : '104px', hgt: '40px', cls: 'sk-r10' }))));
}

function card(...children) {
  return h('div', { class: 'card sk-card-plain' }, children);
}

function cardLines(title = true, n = 3) {
  return card(title ? sk({ w: '40%', hgt: '16px' }) : null, h('div', { class: 'sk-stack', style: { marginTop: '14px' } }, Array.from({ length: n }, (_, i) => sk({ w: ['92%', '80%', '66%', '88%'][i % 4] }))));
}

function tabs(n = 6) {
  return h('div', { class: 'sk-tabs' }, Array.from({ length: n }, (_, i) => sk({ w: `${54 + ((i * 19) % 40)}px`, hgt: '14px' })));
}

const LAYOUTS = {
  dashboard: () => [
    h('div', { class: 'sk-hero' },
      h('div', { class: 'sk-stack', style: { flex: '1' } }, sk({ w: '160px', hgt: '13px', cls: 'sk-on-hero' }), sk({ w: 'min(380px, 80%)', hgt: '26px', cls: 'sk-on-hero sk-r8' }), sk({ w: 'min(300px, 70%)', hgt: '13px', cls: 'sk-on-hero' }),
        h('div', { class: 'sk-inline', style: { marginTop: '12px' } }, sk({ w: '150px', hgt: '40px', cls: 'sk-on-hero sk-r10' }), sk({ w: '150px', hgt: '40px', cls: 'sk-on-hero sk-r10' }))),
      sk({ w: '130px', hgt: '130px', cls: 'sk-on-hero sk-r14 sk-hide-sm' })),
    card(h('div', { class: 'sk-inline' }, h('div', { class: 'sk-stack', style: { flex: '1' } }, sk({ w: '80px', hgt: '10px' }), sk({ w: '60%', hgt: '18px' }), sk({ w: '40%', hgt: '11px' })), sk({ w: '120px', hgt: '40px', cls: 'sk-r10' }))),
    h('div', { class: 'grid grid-4' }, Array.from({ length: 4 }, () => card(sk({ w: '48px', hgt: '30px', cls: 'sk-r8' }), sk({ w: '70%', hgt: '11px' })))),
    h('div', { class: 'grid grid-2', style: { marginTop: '18px' } }, Array.from({ length: 4 }, () => card(sk({ w: '35%', hgt: '16px' }), skList(4, { thumb: null })))),
  ],
  list: () => [head(), card(h('div', { class: 'sk-inline', style: { marginBottom: '14px' } }, sk({ w: '30%', hgt: '16px' }), h('span', { class: 'sk-spacer' }), sk({ w: '90px', hgt: '30px', cls: 'sk-r8' })), skList(8, { thumb: 'video' }))],
  cards: () => [head({ actions: 3 }), h('div', { class: 'grid grid-sidebar' }, h('div', {}, tabs(5), skCards(6)), h('div', {}, cardLines(true, 4), cardLines(true, 2)))],
  editor: () => [
    h('div', { class: 'sk-head' }, sk({ w: '90px', hgt: '32px', cls: 'sk-r8' }), sk({ w: 'min(420px, 50%)', hgt: '30px', cls: 'sk-r8' }), h('span', { class: 'sk-spacer' }), sk({ w: '100px', hgt: '34px', cls: 'sk-r8' }), sk({ w: '96px', hgt: '34px', cls: 'sk-r8 sk-hide-sm' })),
    tabs(8),
    h('div', { class: 'sk-editor' },
      h('div', { class: 'sk-editor-main card' }, Array.from({ length: 14 }, (_, i) => sk({ w: i % 5 === 0 ? '34%' : ['94%', '86%', '90%', '72%'][i % 4], hgt: i % 5 === 0 ? '15px' : '11px' }))),
      h('div', {}, cardLines(true, 4), cardLines(true, 3), cardLines(true, 2))),
  ],
  calendar: () => [
    head(),
    h('div', { class: 'sk-chips' }, Array.from({ length: 4 }, () => card(sk({ w: '60%', hgt: '14px' }), sk({ w: '100%', hgt: '8px', round: true })))),
    h('div', { class: 'sk-inline', style: { margin: '6px 0 12px' } }, sk({ w: '36px', hgt: '32px', cls: 'sk-r8' }), sk({ w: '96px', hgt: '32px', cls: 'sk-r8' }), sk({ w: '36px', hgt: '32px', cls: 'sk-r8' }), h('span', { class: 'sk-spacer' }), sk({ w: '170px', hgt: '16px' })),
    h('div', { class: 'sk-week' }, Array.from({ length: 7 }, (_, d) => h('div', { class: 'sk-day' },
      h('div', { class: 'sk-inline' }, sk({ w: '30px', hgt: '11px' }), h('span', { class: 'sk-spacer' }), sk({ w: '18px', hgt: '16px' })),
      Array.from({ length: [1, 2, 0, 3, 1, 2, 0][d] }, () => sk({ w: '100%', hgt: '42px', cls: 'sk-r8' }))))),
  ],
  chat: () => [h('div', { class: 'sk-chat' },
    h('div', { class: 'card sk-hide-sm' }, sk({ w: '100%', hgt: '38px', cls: 'sk-r10' }), skList(6, { thumb: null })),
    h('div', { class: 'card sk-chat-main' },
      [['62%', 'start'], ['48%', 'end'], ['70%', 'start'], ['36%', 'end'], ['58%', 'start']].map(([w, side], i) => h('div', { class: `sk-bubble ${side}` },
        sk({ w, hgt: `${36 + (i % 3) * 18}px`, cls: 'sk-r14' }))),
      sk({ w: '100%', hgt: '52px', cls: 'sk-r14 sk-composer' })))],
  settings: () => [head({ actions: 0 }), h('div', { class: 'grid grid-sidebar' },
    h('div', {}, Array.from({ length: 4 }, () => card(sk({ w: '30%', hgt: '17px' }), sk({ w: '55%', hgt: '10px' }),
      h('div', { class: 'grid grid-2 tight', style: { marginTop: '14px' } }, Array.from({ length: 4 }, () => h('div', { class: 'sk-stack' }, sk({ w: '40%', hgt: '10px' }), sk({ w: '100%', hgt: '40px', cls: 'sk-r10' }))))))),
    h('div', {}, cardLines(true, 3), cardLines(true, 5), cardLines(true, 3)))],
  form: () => [head({ actions: 1 }), Array.from({ length: 3 }, (_, i) => card(sk({ w: '28%', hgt: '18px' }),
    h('div', { class: 'sk-inline', style: { margin: '14px 0' } }, Array.from({ length: i === 1 ? 4 : 3 }, () => sk({ w: '110px', hgt: '34px', cls: 'sk-r8' }))),
    sk({ w: '100%', hgt: i === 1 ? '120px' : '44px', cls: 'sk-r10' })))],
};

const ROUTES = [
  [/^\/$/, 'dashboard', 'your dashboard'],
  [/^\/(scripts|posts)\/\d+$/, 'editor', 'the editor'],
  [/^\/calendar$/, 'calendar', 'your calendar'],
  [/^\/(linkedin|instagram|x|tiktok|threads|tools)/, 'cards', 'posts'],
  [/^\/chat/, 'chat', 'your chats'],
  [/^\/settings$/, 'settings', 'settings'],
  [/^\/(repurpose|flow)$/, 'form', 'this page'],
  [/./, 'list', 'this page'],
];

/** The skeleton for a route, shaped like its real layout. */
export function pageSkeleton(path) {
  const [, kind, label] = ROUTES.find(([re]) => re.test(path));
  return h('div', { class: `view sk-page sk-page-${kind}`, role: 'status', 'aria-busy': 'true' },
    h('span', { class: 'sr-only' }, `Loading ${label}…`), LAYOUTS[kind]());
}
