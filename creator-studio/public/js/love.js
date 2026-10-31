// Little love notes for her, sprinkled around the studio 💕
// One note of the day (same all day, new tomorrow), a hello the first time she opens the
// studio each day, and cheers at her big moments. Signed and extended from Settings → Love notes.
import { h, modal } from './core.js';

let cfg = { enabled: true, name: 'you', from: '', custom: [] };

export function setLove(config) {
  if (config) cfg = { ...cfg, ...config, name: (config.name || 'you').split(' ')[0] };
}

export const loveOn = () => cfg.enabled !== false;

const NOTES = [
  'Good morning, {name} ☀️ The best part of my day is still you.',
  'Reminder: you are ridiculously talented. Also ridiculously cute. 😍',
  'Whatever you film today, I’m already your biggest fan. 💕',
  'Hey {name}, I love you more than you love a good thumbnail. And that’s a lot. 🖼️❤️',
  'You + this camera = magic. You + me = my favourite thing ever. 💘',
  'If nobody told you today: you’re doing amazing, sweetheart. 🥰',
  'Fun fact: my heart does a little breakout every time I see you. 📈❤️',
  'Drink some water, stretch, and remember someone is very, very proud of you. 💧💕',
  'You’re my favourite creator. Sorry, everyone else. 😌💖',
  'One script at a time, one video a week, and one person who’s crazy about you. 😘',
  '{name}, you make everything look easy. Especially making me smile. 😊',
  'Plot twist: the real algorithm is how much I love you. 💞',
  'Take a deep breath. You’ve got this, and you’ve got me. 🤍',
  'Your voice is my favourite sound, on camera and off. 🎙️❤️',
  'I’d subscribe to you a thousand times. With notifications on. 🔔💕',
  'Today’s goal: be proud of yourself. Bonus goal: let me take you out for dinner. 🍝😏',
  'You are the cold open of my life: you had me from the first second. 🎬💘',
  'Every view you get, I count as a little cheer from me too. 📣❤️',
  'You’re not just a creator, you’re my favourite person. 💗',
  'Breaking news: local girl is incredible. More at 11. 📰😍',
  'Even your rough drafts are better than most people’s finals. ✍️💕',
  'Missed a week? Who cares. I’d still pick you every single week. 💞',
  'Zero em dashes, a hundred kisses. 😘😘😘',
  'You’re the thumbnail I’d click every single time. 👀❤️',
  'Remember to eat something nice today. Chef’s orders. 👨‍🍳💕',
  'I love the way your eyes light up when an idea clicks. 💡😍',
  'You’re my daily reminder that hard work and kindness look really good together. ✨',
  '{name}, if I could, I’d give you a 100× outlier score in being loved. 📈💖',
  'Proud of you. Always. No conditions. 🫶',
  'Your hustle is cute. You are cuter. 🥹',
  'Small steps still count. Every one of them gets a kiss from me. 💋',
  'Can’t wait to watch your next video. Can wait even less to see you tonight. 🌙💕',
  'You make the world a little brighter, one upload at a time. 🌈',
  'Somewhere out there, your future fans are waiting. Right here, I’m already obsessed. 😍',
  'Rest is part of the process. So are cuddles. Just saying. 🛋️❤️',
  'You’re the best thing I ever found without a search bar. 🔎💘',
  'Reminder from your favourite person: you are loved. A lot. Like, a LOT. 💕💕💕',
  'Hey you. Yes, you. I love you. That’s it, that’s the note. ❤️',
];

const CHEERS = {
  shipped: ['YOU SHIPPED IT! 🎉 So proud of you, {name}! ❤️', 'Another week, another video. You’re unstoppable, babe. 🚀💕'],
  published: ['Published! Go celebrate, superstar. 🌟💖', 'It’s live! The internet is lucky to have you. 🥳❤️'],
  picked: ['Ooh, good pick! 💡💕', 'Great taste, as always. (I would know, you picked me.) 😏❤️'],
  draft: ['Draft done! Read it out loud, it already sounds like you. 🎙️💕'],
  idea: ['Saved! Your brain is my favourite. 🧠❤️'],
  wizard: ['Planned like a pro. Now go be brilliant. ✨💖'],
  morning: ['Good morning, beautiful ☀️', 'Hey gorgeous 💕', 'Hi {name}! 🥰', 'Welcome back, superstar 🌟'],
};

const fill = (s) => s.replaceAll('{name}', cfg.name);
const pool = () => [...NOTES, ...(cfg.custom || [])];
const sign = () => (cfg.from ? `— ${cfg.from} 💘` : '— you know who 💘');

function dayIndex(d = new Date()) {
  const start = new Date(d.getFullYear(), 0, 0);
  return Math.floor((d - start) / 86400000) + d.getFullYear() * 400;
}

/** Same note all day, a different one tomorrow. */
export function noteOfTheDay() {
  const p = pool();
  return fill(p[dayIndex() % p.length]);
}

export function randomNote(except = '') {
  const p = pool().map(fill).filter((n) => n !== except);
  return p[Math.floor(Math.random() * p.length)] || noteOfTheDay();
}

export function cheer(kind) {
  const list = CHEERS[kind];
  return list ? fill(list[Math.floor(Math.random() * list.length)]) : null;
}

/** Floating hearts, for the moments that deserve it. */
export function hearts(count = 14) {
  if (!loveOn() || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
  const box = h('div', { class: 'love-hearts', 'aria-hidden': 'true' });
  for (let i = 0; i < count; i++) {
    const s = h('span', { class: 'love-heart' }, ['❤️', '💕', '💖', '💗', '💘'][i % 5]);
    s.style.left = `${Math.random() * 100}%`;
    s.style.animationDelay = `${Math.random() * 0.8}s`;
    s.style.fontSize = `${16 + Math.random() * 18}px`;
    box.append(s);
  }
  document.body.append(box);
  setTimeout(() => box.remove(), 3200);
}

/** Toast-style love message (used for cheers). */
export function loveToast(text) {
  if (!loveOn() || !text) return;
  const box = document.getElementById('toast-container');
  const el = h('div', { class: 'toast love', role: 'status' }, text);
  el.addEventListener('click', () => el.remove());
  box?.append(el);
  setTimeout(() => el.remove(), 5000);
}

export function celebrate(kind) {
  if (!loveOn()) return;
  loveToast(cheer(kind));
  hearts();
}

/** The first time she opens the studio each day: a little hello. */
export function dailyHello() {
  if (!loveOn()) return;
  const key = `love-hello-${new Date().toDateString()}`;
  try {
    if (localStorage.getItem(key)) return;
    localStorage.setItem(key, '1');
  } catch { /* private mode: just show it */ }
  setTimeout(() => {
    modal(cheer('morning'), (close) => h('div', { class: 'love-card' },
      h('div', { class: 'love-big' }, '💌'),
      h('p', { class: 'love-text' }, noteOfTheDay()),
      h('p', { class: 'love-sign' }, sign()),
      h('div', { class: 'row', style: { justifyContent: 'center' } },
        h('button', { class: 'btn primary', onclick: () => { close(); hearts(20); } }, 'Love you too ❤️'))));
  }, 600);
}

/** Card for the dashboard: today's note, with "another one" and a heart burst. */
export function loveCard() {
  if (!loveOn()) return null;
  const text = h('p', { class: 'love-text' }, noteOfTheDay());
  return h('div', { class: 'card love-note' },
    h('div', { class: 'love-note-head' }, h('span', { class: 'love-big sm' }, '💌'), h('strong', {}, 'Today’s note for you')),
    text,
    h('div', { class: 'row-between' },
      h('span', { class: 'love-sign' }, sign()),
      h('button', {
        class: 'btn ghost sm', type: 'button',
        onclick: () => { text.textContent = randomNote(text.textContent); hearts(8); },
      }, 'Another one 💕')));
}

/** Sidebar heart: tap for a random note. */
export function heartButton() {
  if (!loveOn()) return null;
  return h('button', {
    class: 'love-heart-btn', type: 'button', title: 'A little note for you', 'aria-label': 'A love note',
    onclick: () => { loveToast(randomNote()); hearts(10); },
  }, '❤️');
}

export const loveSignature = sign;
