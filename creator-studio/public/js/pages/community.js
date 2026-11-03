import { h, api, toast, clear, modal, confirmDialog, pageHead, emptyState, loading, field, copyText, timeAgo, navigate } from '../core.js';
import { icon } from '../icons.js';

// The Community Hub (members, clients and her) and her side of it (/community/manage).
// Everything shown is real: what she publishes, members' questions and approved projects.

const TYPE_LABEL = { dispatch: 'Posts', kit: 'Starter kits', blueprint: 'Blueprints', showcase: 'Showcase', question: 'Ask', perk: 'Perks' };
const money = (n, cur) => `${cur === 'EUR' ? '€' : cur === 'GBP' ? '£' : '$'}${Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}`;

export async function renderCommunityHub(view) {
  clear(view);
  view.append(loading('Loading the community…'));
  let data;
  try {
    data = await api('GET', 'portal/community/hub');
  } catch (err) {
    clear(view);
    view.append(h('div', { class: 'card error' }, h('h2', {}, 'Could not load the community'), h('p', { class: 'muted' }, err.message || 'Please sign in again.')));
    return;
  }
  const params = new URLSearchParams(location.search);
  if (params.get('pro') === 'success') toast('Welcome to Pro! It can take a minute to unlock.');
  if (params.get('pro') === 'cancelled') toast('No problem: nothing was charged.', 'warn');

  let active = data.dispatches.length ? 'dispatch' : 'question';
  const content = h('div', { class: 'community-tab-content' });

  const tabs = [
    ['dispatch', 'Posts', 'doc', data.dispatches],
    ['kit', 'Starter kits', 'code', data.kits],
    ['blueprint', 'Blueprints', 'sliders', data.blueprints],
    ['showcase', 'Showcase', 'sparkle', data.showcase],
    ['question', 'Ask', 'chat', data.ama],
    ['perk', 'Perks', 'tag', data.perks],
    ['leaderboard', 'Leaderboard', 'trend', data.leaderboard],
  ];
  const tabNav = h('div', { class: 'community-tabs', role: 'tablist' }, tabs.map(([id, label, ic, list]) => h('button', {
    type: 'button', role: 'tab', class: `community-tab-btn${id === active ? ' is-active' : ''}`, 'aria-selected': String(id === active),
    onclick: (e) => {
      active = id;
      tabNav.querySelectorAll('.community-tab-btn').forEach((b) => { b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
      e.currentTarget.classList.add('is-active');
      e.currentTarget.setAttribute('aria-selected', 'true');
      draw();
    },
  }, icon(ic, 14), h('span', {}, label), h('span', { class: 'community-tab-badge' }, String(list?.length || 0)))));

  function upgradeButton(label = `Join ${data.pro.name}`) {
    if (!data.pro.available || data.pro.active) return null;
    return h('button', {
      type: 'button', class: 'btn primary sm',
      onclick: async (e) => {
        e.currentTarget.disabled = true;
        try { location.href = (await api('POST', 'portal/community/pro/checkout')).url; } catch (err) { toast(err.message, 'err'); e.currentTarget.disabled = false; }
      },
    }, icon('sparkle', 13), ` ${label} · ${money(data.pro.amount, data.pro.currency)}/${data.pro.interval}`);
  }

  function locked(item) {
    return h('div', { class: 'community-kit-card is-locked' },
      h('div', { class: 'community-kit-head' }, h('span', { class: 'tag' }, icon('lock', 10), ` ${data.pro.name}`)),
      h('h3', { class: 'community-kit-title' }, item.title),
      h('p', { class: 'muted small' }, `For ${data.pro.name} members.`),
      h('div', { class: 'community-kit-foot' }, upgradeButton('Unlock') || h('span', { class: 'muted small' }, 'Not open to new members right now.')));
  }

  function voteButton(item) {
    const count = h('span', { class: 'community-upvote-count' }, String(item.upvotes || 0));
    const btn = h('button', {
      type: 'button', class: `community-upvote-btn${item.voted ? ' is-active' : ''}`, 'aria-pressed': String(!!item.voted),
      'aria-label': item.voted ? 'Remove your vote' : 'Vote', disabled: item.mine || item.status !== 'published',
      title: item.mine ? 'Your own post' : '',
      onclick: async () => {
        btn.disabled = true;
        try {
          const r = await api('POST', `portal/community/items/${item.id}/vote`);
          item.voted = r.voted; item.upvotes = r.upvotes;
          count.textContent = String(r.upvotes);
          btn.classList.toggle('is-active', r.voted);
          btn.setAttribute('aria-pressed', String(r.voted));
        } catch (err) { toast(err.message, 'err'); } finally { btn.disabled = false; }
      },
    }, icon('chevron-up', 14));
    return h('div', { class: 'community-ama-vote' }, btn, count);
  }

  function cardsFor(list, render, empty) {
    if (!list.length) return emptyState(icon('sparkle', 28), 'Nothing here yet', empty);
    return h('div', { class: 'community-kits-grid' }, list.map((i) => (i.locked ? locked(i) : render(i))));
  }

  function draw() {
    clear(content);
    if (active === 'dispatch') {
      content.append(data.dispatches.length ? h('div', { class: 'community-dispatches-list' }, data.dispatches.map((d) => (d.locked ? locked(d) : h('article', { class: 'community-dispatch-card' },
        h('div', { class: 'community-dispatch-head' },
          h('span', { class: 'muted small' }, timeAgo(d.published_at), d.tier === 'pro' ? ` · ${data.pro.name}` : ''),
          h('div', { class: 'community-dispatch-tags' }, d.tags.map((t) => h('span', { class: 'tag' }, `#${t}`)))),
        h('h3', { class: 'community-dispatch-title' }, d.title),
        h('p', { class: 'community-dispatch-summary', style: { whiteSpace: 'pre-wrap' } }, d.body || ''),
        d.url ? h('a', { href: d.url, target: '_blank', rel: 'noopener', class: 'btn ghost sm' }, 'Read more ', icon('external', 12)) : null)))) : emptyState(icon('doc', 28), 'No posts yet', 'New posts show up here, and in your weekly digest email.'));
    } else if (active === 'kit') {
      content.append(cardsFor(data.kits, (k) => h('div', { class: 'community-kit-card' },
        h('div', { class: 'community-kit-head' }, k.meta.category ? h('span', { class: 'community-kit-cat' }, k.meta.category) : null),
        h('h3', { class: 'community-kit-title' }, k.title),
        h('p', { class: 'community-kit-desc muted small' }, k.body || ''),
        h('ul', { class: 'community-kit-feats' }, (k.meta.features || []).map((f) => h('li', {}, icon('check', 11), h('span', {}, f)))),
        h('div', { class: 'community-kit-foot' },
          k.code ? h('button', { type: 'button', class: 'btn sm primary', onclick: () => copyText(k.code, 'Copied.') }, icon('copy', 13), ' Copy command') : null,
          k.url ? h('a', { href: k.url, target: '_blank', rel: 'noopener', class: 'btn sm ghost' }, icon('external', 13), ' Open') : null)),
      'Starter kits she shares will appear here.'));
    } else if (active === 'blueprint') {
      content.append(cardsFor(data.blueprints, (bp) => h('div', { class: 'community-kit-card' },
        h('div', { class: 'community-kit-head' }, bp.meta.category ? h('span', { class: 'community-kit-cat' }, bp.meta.category) : null),
        h('h3', { class: 'community-kit-title' }, bp.title),
        h('p', { class: 'community-kit-desc muted small' }, bp.body || ''),
        h('ul', { class: 'community-kit-feats' }, (bp.meta.components || []).map((c) => h('li', {}, icon('check', 11), h('span', {}, c)))),
        h('div', { class: 'community-kit-foot' }, h('button', { type: 'button', class: 'btn sm primary', onclick: () => scaffold(bp) }, icon('sliders', 13), ' Get starter files'))),
      'Architecture blueprints will appear here.'));
    } else if (active === 'showcase') {
      content.append(
        h('div', { class: 'card callout community-showcase-banner' },
          h('div', {}, h('h3', {}, 'Built something you’re proud of?'), h('p', { class: 'muted small' }, 'Share it. Projects appear once they’re reviewed; approved and featured projects earn points.')),
          h('button', { type: 'button', class: 'btn primary', onclick: submitProject }, icon('plus', 14), ' Submit a project')),
        cardsFor(data.showcase, (p) => h('div', { class: `community-kit-card${p.featured ? ' is-featured' : ''}` },
          h('div', { class: 'community-kit-head' },
            h('span', { class: 'community-kit-cat' }, p.author ? `By ${p.author}` : ''),
            p.status === 'pending' ? h('span', { class: 'tag warn' }, 'Waiting for review') : p.featured ? h('span', { class: 'tag ok' }, icon('sparkle', 10), ' Featured') : null),
          h('h3', { class: 'community-kit-title' }, p.title),
          h('p', { class: 'community-kit-desc muted small' }, p.body || ''),
          h('div', { class: 'community-dispatch-tags', style: { marginBottom: '12px' } }, p.tags.map((t) => h('span', { class: 'tag' }, `#${t}`))),
          h('div', { class: 'community-kit-foot' }, voteButton(p),
            h('div', { class: 'row' },
              p.url2 ? h('a', { href: p.url2, target: '_blank', rel: 'noopener', class: 'btn sm primary' }, icon('external', 12), ' Demo') : null,
              p.url ? h('a', { href: p.url, target: '_blank', rel: 'noopener', class: 'btn sm ghost' }, icon('code', 12), ' Code') : null))),
        'No projects yet. Be the first to share one.'));
    } else if (active === 'question') {
      const input = h('textarea', { class: 'input', rows: 3, maxlength: 500, placeholder: 'Ask about anything she covers: questions with the most votes get answered first.' });
      const ask = h('button', { type: 'button', class: 'btn primary' }, icon('chat', 14), ' Post question');
      ask.onclick = async () => {
        const q = input.value.trim();
        if (q.length < 10) return toast('Please write at least 10 characters.', 'err');
        ask.disabled = true;
        try {
          const r = await api('POST', 'portal/community/ask', { question: q });
          toast(r.message);
          data.ama = [r.item, ...data.ama];
          draw();
        } catch (err) { toast(err.message, 'err'); } finally { ask.disabled = false; }
      };
      content.append(
        h('div', { class: 'community-ask-box' }, h('h3', {}, 'Ask a question'), h('p', { class: 'muted small' }, 'You’ll get an email when it’s answered.'), input, h('div', { class: 'row-end', style: { marginTop: '10px' } }, ask)),
        data.ama.length ? h('div', { class: 'community-ama-list' }, data.ama.map((q) => h('div', { class: 'community-ama-card' },
          voteButton(q),
          h('div', { class: 'community-ama-body' },
            h('div', { class: 'community-ama-head' }, h('strong', {}, q.author || 'Member'), h('span', { class: 'muted small' }, ` · ${timeAgo(q.published_at)}`),
              q.answered_at ? h('span', { class: 'tag ok' }, icon('check', 10), ' Answered') : null),
            h('p', { class: 'community-ama-q' }, q.title),
            q.answer ? h('blockquote', { class: 'community-ama-answer' }, h('p', { style: { whiteSpace: 'pre-wrap' } }, q.answer)) : null)))) : emptyState(icon('chat', 28), 'No questions yet', 'Ask the first one.'));
    } else if (active === 'perk') {
      content.append(data.perks.length ? h('div', { class: 'community-perks-grid' }, data.perks.map((p) => (p.locked ? locked(p) : h('div', { class: 'community-perk-card' },
        h('div', { class: 'community-perk-head' }, h('strong', {}, p.meta.brand || ''), p.meta.badge ? h('span', { class: 'community-perk-badge' }, p.meta.badge) : null),
        h('h3', { class: 'community-perk-offer' }, p.title),
        p.body ? h('p', { class: 'muted small' }, p.body) : null,
        p.code ? h('div', { class: 'community-perk-code-box' }, h('code', {}, p.code), h('button', { type: 'button', class: 'btn ghost sm', onclick: () => copyText(p.code, 'Code copied.') }, icon('copy', 12), ' Copy')) : null,
        p.url ? h('a', { href: p.url, target: '_blank', rel: 'noopener', class: 'btn primary block sm', style: { marginTop: '12px' } }, 'Redeem ', icon('external', 12)) : null)))) : emptyState(icon('tag', 28), 'No perks yet', 'Partner offers will appear here.'));
    } else if (active === 'leaderboard') {
      content.append(h('div', { class: 'card' },
        h('h2', {}, 'Most active this month'),
        h('p', { class: 'muted small' }, 'Points: asking +5 (3 a day), each vote you receive +2, an answered question +10, an approved project +15, a featured one +25.'),
        data.leaderboard.length ? h('ol', { class: 'community-leaderboard' }, data.leaderboard.map((r) => h('li', { class: 'row-between' },
          h('span', {}, h('strong', {}, r.name), h('span', { class: 'muted small' }, ` · ${r.level}`)), h('span', { class: 'tag' }, `${r.points} pts`)))) : h('p', { class: 'muted' }, 'No points yet this month.')));
    }
  }

  async function scaffold(bp) {
    const name = h('input', { class: 'input', value: `${bp.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 30)}-app` });
    const db = h('select', { class: 'input' }, h('option', { value: 'postgres' }, 'PostgreSQL + pgvector'), h('option', { value: 'sqlite' }, 'SQLite'));
    const cache = h('select', { class: 'input' }, h('option', { value: 'redis' }, 'Redis'), h('option', { value: 'memory' }, 'In-memory'));
    const out = h('pre', { class: 'auth-install-output', style: { maxHeight: '240px' }, hidden: true });
    await modal(`Starter files: ${bp.title}`, () => h('div', {},
      field('Project name', name), field('Database', db), field('Cache', cache),
      h('div', { class: 'row-end', style: { marginTop: '12px' } }, h('button', {
        type: 'button', class: 'btn primary',
        onclick: async () => {
          try {
            const r = await api('POST', 'portal/community/blueprints/scaffold', { blueprint_id: bp.id, project_name: name.value, database: db.value, cache: cache.value });
            out.textContent = `# docker-compose.yml\n${r.files['docker-compose.yml']}\n# README.md\n${r.files['README.md']}`;
            out.hidden = false;
            copyText(r.files['docker-compose.yml'], 'docker-compose.yml copied.');
          } catch (err) { toast(err.message, 'err'); }
        },
      }, 'Generate')), out), { wide: true });
  }

  async function submitProject() {
    const title = h('input', { class: 'input', maxlength: 100, required: true });
    const desc = h('textarea', { class: 'input', rows: 3, maxlength: 500 });
    const repo = h('input', { class: 'input', type: 'url', placeholder: 'https://github.com/…', required: true });
    const demo = h('input', { class: 'input', type: 'url', placeholder: 'https://… (optional)' });
    const tags = h('input', { class: 'input', placeholder: 'ai, vite, laravel' });
    await modal('Submit a project', (close) => h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        try {
          const r = await api('POST', 'portal/community/showcase', { title: title.value, description: desc.value || null, repo_url: repo.value, demo_url: demo.value || null, tags: tags.value });
          toast(r.message);
          data.showcase = [r.item, ...data.showcase];
          close();
          draw();
        } catch (err) { toast(err.message, 'err'); }
      },
    }, field('Title', title), field('What it does', desc), field('Code link', repo), field('Demo link', demo), field('Tags (comma separated)', tags),
    h('div', { class: 'row-end', style: { marginTop: '12px' } }, h('button', { type: 'submit', class: 'btn primary' }, 'Submit for review'))));
  }

  const me = data.me;
  const progress = me.next_at ? Math.min(100, Math.round((me.points / me.next_at) * 100)) : 100;
  const digest = h('input', { type: 'checkbox', checked: data.digest, onchange: async (e) => {
    try { await api('PUT', 'portal/community/digest', { on: e.target.checked }); toast(e.target.checked ? 'Weekly digest on.' : 'Weekly digest off.'); } catch (err) { toast(err.message, 'err'); }
  } });

  const hero = h('div', { class: 'community-hero' },
    h('div', { class: 'community-hero-content' },
      h('h1', { class: 'community-hero-title' }, 'Community Hub'),
      h('p', { class: 'community-hero-sub muted' }, `Welcome, ${data.member.name}. Posts, starter kits and answers, plus a place to ask and share what you build.`),
      h('label', { class: 'row small muted', style: { gap: '6px', marginTop: '8px' } }, digest, 'Email me a weekly digest')),
    h('div', { class: 'community-hero-card' },
      h('span', { class: 'community-hero-card-role' }, data.pro.active ? `${data.pro.name} member` : data.member.role),
      h('strong', {}, `Level ${me.level} · ${me.name}`),
      h('span', { class: 'muted small' }, `${me.points} points${me.rank ? ` · #${me.rank}` : ''}`),
      h('div', { class: 'meter', role: 'progressbar', 'aria-valuenow': progress, 'aria-valuemin': 0, 'aria-valuemax': 100, style: { marginTop: '6px' } },
        h('span', { style: { width: `${progress}%` } })),
      me.next_name ? h('span', { class: 'muted small' }, `${me.next_at - me.points} to ${me.next_name}`) : null,
      data.pro.active && data.pro.can_manage ? h('button', {
        type: 'button', class: 'btn ghost sm', style: { marginTop: '8px' },
        onclick: async () => { try { location.href = (await api('POST', 'portal/community/pro/manage')).url; } catch (err) { toast(err.message, 'err'); } },
      }, 'Manage membership') : upgradeButton()));

  const proPitch = data.pro.available && !data.pro.active && (data.pro.pitch || data.pro.perks.length) ? h('div', { class: 'card callout' },
    h('h3', {}, `${data.pro.name} membership`),
    data.pro.pitch ? h('p', {}, data.pro.pitch) : null,
    data.pro.perks.length ? h('ul', {}, data.pro.perks.map((p) => h('li', {}, p))) : null,
    upgradeButton()) : null;

  const hire = data.hire ? h('div', { class: 'card callout community-hire' },
    h('div', {}, h('h3', {}, data.hire.headline), h('p', { class: 'muted small' }, data.hire.body)),
    h('a', { class: 'btn primary', href: data.hire.cta_url }, data.hire.cta_label)) : null;

  clear(view);
  view.append(h('div', { class: 'community-hub-page' }, hero, hire, proPitch, tabNav, content));
  draw();
}

// =============================================================================================
// Her side: /community/manage
// =============================================================================================

const FIELDS = {
  dispatch: { body: 'Post', url: 'Link (optional)' },
  kit: { body: 'Description', url: 'Repository link', code: 'Clone command', meta: [['category', 'Category'], ['features', 'Features (one per line)', true]] },
  blueprint: { body: 'Description', meta: [['category', 'Category'], ['components', 'Components (one per line)', true]] },
  perk: { body: 'Details', url: 'Redeem link', code: 'Promo code', meta: [['brand', 'Brand'], ['badge', 'Badge']] },
  showcase: { body: 'Description', url: 'Code link', url2: 'Demo link' },
  question: { answer: 'Your answer' },
};

export async function renderCommunityAdmin(view) {
  clear(view);
  view.append(loading());
  let d;
  try { d = await api('GET', 'community/admin'); } catch (err) { clear(view); view.append(h('div', { class: 'card error' }, err.message)); return; }
  let tab = d.counts.pending || d.counts.unanswered ? 'inbox' : 'content';
  const body = h('div');
  const reload = () => renderCommunityAdmin(view);

  const tabsEl = h('div', { class: 'community-tabs', role: 'tablist' }, [
    ['inbox', `To do (${d.counts.pending + d.counts.unanswered})`],
    ['content', 'Content'],
    ['members', `Members (${d.counts.members})`],
    ['settings', 'Pro tier & "work with me"'],
  ].map(([id, label]) => h('button', {
    type: 'button', role: 'tab', class: `community-tab-btn${tab === id ? ' is-active' : ''}`,
    onclick: (e) => { tab = id; tabsEl.querySelectorAll('button').forEach((b) => b.classList.remove('is-active')); e.currentTarget.classList.add('is-active'); draw(); },
  }, label)));

  function row(item) {
    return h('div', { class: 'card row-between', style: { gap: '12px', alignItems: 'flex-start' } },
      h('div', { style: { minWidth: 0 } },
        h('div', { class: 'row small', style: { gap: '6px', flexWrap: 'wrap' } },
          h('span', { class: 'tag' }, TYPE_LABEL[item.type] || item.type),
          item.tier === 'pro' ? h('span', { class: 'tag' }, icon('lock', 10), ' Pro') : null,
          h('span', { class: `tag ${item.status === 'published' ? 'ok' : item.status === 'pending' ? 'warn' : ''}` }, item.status),
          item.featured ? h('span', { class: 'tag ok' }, 'Featured') : null,
          h('span', { class: 'muted' }, `${item.author || ''}${item.author_email ? ` (${item.author_email})` : ''} · ${timeAgo(item.published_at)} · ▲ ${item.upvotes}`)),
        h('strong', {}, item.title),
        item.answer ? h('p', { class: 'muted small' }, `Answered: ${item.answer.slice(0, 140)}`) : null),
      h('div', { class: 'row', style: { flexShrink: 0 } },
        item.status === 'pending' ? h('button', { type: 'button', class: 'btn primary sm', onclick: () => patch(item, { status: 'published' }) }, 'Approve') : null,
        item.type === 'question' && !item.answer ? h('button', { type: 'button', class: 'btn primary sm', onclick: () => edit(item) }, 'Answer') : null,
        h('button', { type: 'button', class: 'btn ghost sm', onclick: () => edit(item) }, 'Edit'),
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { if (await confirmDialog(`Delete “${item.title}”?`)) { await api('DELETE', `community/admin/items/${item.id}`); reload(); } } }, icon('trash', 13))));
  }

  async function patch(item, changes) {
    try { await api('PATCH', `community/admin/items/${item.id}`, changes); toast('Saved.'); reload(); } catch (err) { toast(err.message, 'err'); }
  }

  async function edit(item = null, type = 'dispatch') {
    const t = item?.type || type;
    const f = FIELDS[t];
    const inputs = {};
    inputs.title = h('input', { class: 'input', value: item?.title || '', maxlength: 200 });
    if (f.body) inputs.body = h('textarea', { class: 'input', rows: 6 }, item?.body || '');
    if (f.answer) inputs.answer = h('textarea', { class: 'input', rows: 6, placeholder: 'They get an email when you save an answer.' }, item?.answer || '');
    if (f.url) inputs.url = h('input', { class: 'input', type: 'url', value: item?.url || '' });
    if (f.url2) inputs.url2 = h('input', { class: 'input', type: 'url', value: item?.url2 || '' });
    if (f.code) inputs.code = h('input', { class: 'input', value: item?.code || '' });
    const meta = {};
    for (const [key, , multi] of f.meta || []) {
      const v = item?.meta?.[key];
      meta[key] = multi ? h('textarea', { class: 'input', rows: 4 }, (v || []).join('\n')) : h('input', { class: 'input', value: v || '' });
    }
    const tags = h('input', { class: 'input', value: (item?.tags || []).join(', ') });
    const tier = h('select', { class: 'input' }, h('option', { value: 'free' }, 'Everyone'), h('option', { value: 'pro', selected: item?.tier === 'pro' }, 'Pro members only'));
    const status = h('select', { class: 'input' }, ['published', 'pending', 'hidden'].map((s) => h('option', { value: s, selected: (item?.status || 'published') === s }, s)));
    const featured = h('input', { type: 'checkbox', checked: !!item?.featured });
    await modal(item ? `Edit ${TYPE_LABEL[t].toLowerCase()}` : `New: ${TYPE_LABEL[t]}`, (close) => h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const payload = { type: t, title: inputs.title.value, tags: tags.value, tier: tier.value, status: status.value, featured: featured.checked };
        for (const k of ['body', 'answer', 'url', 'url2', 'code']) if (inputs[k]) payload[k] = inputs[k].value || null;
        if (f.meta) payload.meta = Object.fromEntries(f.meta.map(([key, , multi]) => [key, multi ? meta[key].value.split('\n').map((s) => s.trim()).filter(Boolean) : meta[key].value]));
        try {
          if (item) await api('PATCH', `community/admin/items/${item.id}`, payload); else await api('POST', 'community/admin/items', payload);
          toast('Saved.');
          close();
          reload();
        } catch (err) { toast(err.message, 'err'); }
      },
    },
    field(t === 'question' ? 'Question' : t === 'perk' ? 'Offer' : 'Title', inputs.title),
    ...['body', 'answer', 'url', 'url2', 'code'].filter((k) => inputs[k]).map((k) => field(f[k], inputs[k])),
    ...(f.meta || []).map(([key, label]) => field(label, meta[key])),
    field('Tags (comma separated)', tags), field('Who sees it', tier), field('Status', status),
    h('label', { class: 'row small', style: { gap: '6px' } }, featured, 'Featured (pinned first)'),
    h('div', { class: 'row-end', style: { marginTop: '12px' } }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))), { wide: true });
  }

  function draw() {
    clear(body);
    if (tab === 'inbox') {
      const todo = d.items.filter((i) => i.status === 'pending' || (i.type === 'question' && i.status === 'published' && !i.answer));
      body.append(todo.length ? h('div', { class: 'stack' }, todo.map(row)) : emptyState(icon('check', 28), 'All caught up', 'No projects to review and no unanswered questions.'));
    } else if (tab === 'content') {
      body.append(
        h('div', { class: 'row', style: { flexWrap: 'wrap', marginBottom: '12px' } }, ['dispatch', 'kit', 'blueprint', 'perk'].map((t) => h('button', { type: 'button', class: 'btn sm', onclick: () => edit(null, t) }, icon('plus', 12), ` ${TYPE_LABEL[t].replace(/s$/, '')}`))),
        d.items.length ? h('div', { class: 'stack' }, d.items.map(row)) : emptyState(icon('doc', 28), 'Nothing published yet', 'Start with a post, a starter kit or a perk.'));
    } else if (tab === 'members') {
      body.append(
        h('div', { class: 'card' },
          h('h2', {}, 'Warm leads'),
          h('p', { class: 'muted small' }, `Members with ${d.hire.min_points}+ points. They see your "work with me" card${d.hire.enabled ? '' : ' (turned off)'}.`),
          d.warm_leads.length ? h('div', { class: 'stack' }, d.warm_leads.map((m) => h('div', { class: 'row-between' },
            h('span', {}, h('strong', {}, m.name), h('span', { class: 'muted small' }, ` · ${m.email} · ${m.points} pts · ${m.level} · ${m.questions} question(s)`)),
            h('div', { class: 'row' },
              m.in_crm ? h('span', { class: 'tag ok' }, 'In CRM') : h('button', { type: 'button', class: 'btn sm', onclick: async () => { await api('POST', `community/admin/members/${m.id}/to-crm`); toast('Added to your CRM.'); reload(); } }, 'Add to CRM'),
              h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('POST', `community/admin/members/${m.id}/comp`, { on: true }); toast('Pro given.'); } }, 'Give Pro'))))) : h('p', { class: 'muted' }, 'No warm leads yet.')),
        h('div', { class: 'card' },
          h('h2', {}, 'Leaderboard (all time)'),
          d.leaderboard.length ? h('ol', {}, d.leaderboard.map((r) => h('li', {}, `${r.name}: ${r.points} pts (${r.level})`))) : h('p', { class: 'muted' }, 'No points yet.')));
    } else if (tab === 'settings') {
      const p = d.pro;
      const hi = d.hire;
      const pi = {
        enabled: h('input', { type: 'checkbox', checked: p.enabled }),
        name: h('input', { class: 'input', value: p.name, maxlength: 40 }),
        amount: h('input', { class: 'input', type: 'number', min: 0, step: '0.01', value: p.amount || '' }),
        currency: h('select', { class: 'input' }, ['usd', 'eur', 'gbp'].map((c) => h('option', { value: c, selected: p.currency === c }, c.toUpperCase()))),
        interval: h('select', { class: 'input' }, h('option', { value: 'month', selected: p.interval === 'month' }, 'Monthly'), h('option', { value: 'year', selected: p.interval === 'year' }, 'Yearly')),
        pitch: h('textarea', { class: 'input', rows: 2, maxlength: 500 }, p.pitch),
        perks: h('textarea', { class: 'input', rows: 4, placeholder: 'One perk per line' }, p.perks.join('\n')),
      };
      const hiI = {
        enabled: h('input', { type: 'checkbox', checked: hi.enabled }),
        min_points: h('input', { class: 'input', type: 'number', min: 0, value: hi.min_points }),
        headline: h('input', { class: 'input', value: hi.headline, maxlength: 120 }),
        body: h('textarea', { class: 'input', rows: 2, maxlength: 400 }, hi.body),
        cta_label: h('input', { class: 'input', value: hi.cta_label, maxlength: 40 }),
        cta_url: h('input', { class: 'input', value: hi.cta_url, placeholder: '/#book or https://…' }),
      };
      const save = async () => {
        try {
          await api('PUT', 'community/admin/settings', {
            pro: { enabled: pi.enabled.checked, name: pi.name.value, amount: Number(pi.amount.value || 0), currency: pi.currency.value, interval: pi.interval.value, pitch: pi.pitch.value, perks: pi.perks.value.split('\n').map((s) => s.trim()).filter(Boolean) },
            hire: { enabled: hiI.enabled.checked, min_points: Number(hiI.min_points.value || 0), headline: hiI.headline.value, body: hiI.body.value, cta_label: hiI.cta_label.value, cta_url: hiI.cta_url.value },
          });
          toast('Saved.');
        } catch (err) { toast(err.message, 'err'); }
      };
      body.append(
        h('div', { class: 'card' },
          h('h2', {}, 'Paid Pro tier'),
          h('p', { class: 'muted small' }, d.stripe ? 'Members pay by card through Stripe (monthly or yearly) and can cancel themselves. Mark any post, kit, blueprint or perk "Pro members only".' : 'Add your Stripe keys in Settings first: without them nobody can join.'),
          h('label', { class: 'row small', style: { gap: '6px' } }, pi.enabled, 'Open the Pro tier'),
          h('div', { class: 'grid grid-3' }, field('Name', pi.name), field('Price', pi.amount), field('Currency', pi.currency)),
          field('Billing', pi.interval), field('Pitch', pi.pitch), field('Perks', pi.perks)),
        h('div', { class: 'card' },
          h('h2', {}, '"Work with me" card'),
          h('p', { class: 'muted small' }, 'Shown to engaged members (not clients) on their hub.'),
          h('label', { class: 'row small', style: { gap: '6px' } }, hiI.enabled, 'Show it'),
          field('Minimum points', hiI.min_points), field('Headline', hiI.headline), field('Text', hiI.body),
          h('div', { class: 'grid grid-2' }, field('Button', hiI.cta_label), field('Button link', hiI.cta_url))),
        h('div', { class: 'row-end' }, h('button', { type: 'button', class: 'btn primary', onclick: save }, 'Save settings')));
    }
  }

  clear(view);
  view.append(pageHead('Community', `${d.counts.members} members · ${d.counts.pro} Pro`, h('button', { type: 'button', class: 'btn ghost', onclick: () => navigate('/community') }, 'View as member')), tabsEl, body);
  draw();
}
