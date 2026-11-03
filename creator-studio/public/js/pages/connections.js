// Connections: sign in to each social network (OAuth) so numbers update themselves, agents can
// publish and comments land in the Inbox. Each network needs its own free developer app; the
// "Set up" dialog walks through it and shows the exact redirect address to paste.
import { h, api, busy, toast, pageHead, clear, field, timeAgo, compact, modal, confirmDialog } from '../core.js';
import { icon } from '../icons.js';
import { PLATFORM_ICONS } from './social.js';

const STATUS = {
  connected: ['ok', 'Connected'],
  expired: ['bad', 'Needs reconnect'],
  error: ['bad', 'Error'],
};

function daysLeft(iso) {
  return iso ? Math.ceil((new Date(iso) - Date.now()) / 86400000) : null;
}

export async function renderConnections(view) {
  let data = await api('GET', 'social/accounts');
  const params = new URLSearchParams(location.search);
  if (params.get('connected')) toast(`${labelOf(params.get('connected'))} is connected. Your first numbers are in.`);
  if (params.get('error')) toast(`${params.get('network') ? `${labelOf(params.get('network'))}: ` : ''}${params.get('error')}`, 'err', 9000);
  if (params.toString()) history.replaceState({}, '', '/connections');

  function labelOf(p) { return data.networks.find((n) => n.platform === p)?.label || p; }

  const grid = h('div', { class: 'cx-grid' });
  const banner = h('div');

  function draw() {
    const connected = data.networks.filter((n) => n.account);
    clear(banner).append(
      data.https ? null : h('div', { class: 'card callout warn cx-https' },
        h('div', { class: 'row-between wrap' },
          h('div', {},
            h('strong', {}, 'Secure address needed for Instagram, Threads and TikTok'),
            h('p', { class: 'muted small' }, `Meta and TikTok only send you back to an https:// address. The studio runs at ${data.app_url}. YouTube, LinkedIn and X work as it is.`)),
          h('button', { class: 'btn sm', onclick: httpsHelp }, icon('key', 14), 'How to set it up'))),
      connected.length ? h('div', { class: 'row cx-summary' },
        h('span', { class: 'muted small' }, `${connected.length} of ${data.networks.length} connected`),
        h('button', {
          class: 'btn ghost sm', onclick: (e) => busy(e.currentTarget, 'Syncing…', async () => {
            const r = await api('POST', 'social/accounts/sync');
            const msgs = Object.values(r.results);
            toast(msgs.length ? msgs.map((m) => m.message).join(' · ') : 'Everything is already up to date (synced in the last hour).', msgs.some((m) => !m.ok) ? 'err' : 'ok', 8000);
            data = await api('GET', 'social/accounts');
            draw();
          }),
        }, icon('refresh', 14), 'Sync all now')) : null);
    clear(grid).append(data.networks.map(card));
  }

  function card(n) {
    const a = n.account;
    const [tone, label] = a ? (STATUS[a.status] || STATUS.connected) : (n.configured ? ['new', 'Ready to connect'] : ['', 'Not set up']);
    const left = a ? daysLeft(a.expires_at) : null;
    const connect = h('button', {
      class: `btn ${a ? 'sm' : 'primary sm'}`, disabled: !n.configured || n.blocked,
      title: n.blocked ? 'Needs the secure https:// address first' : (!n.configured ? 'Add the app keys first (Set up)' : ''),
      onclick: () => busy(connect, 'Opening…', async () => { const r = await api('POST', `social/accounts/${n.platform}/connect`); location.assign(r.url); }),
    }, icon('plug', 14), a ? 'Reconnect' : 'Connect');

    return h('div', { class: `card cx-card pf-${n.platform}${a?.status === 'expired' ? ' cx-expired' : ''}` },
      h('div', { class: 'cx-top' },
        h('span', { class: 'cx-logo' }, icon(PLATFORM_ICONS[n.platform], 22)),
        h('div', { class: 'cx-name' }, h('h2', {}, n.label),
          a ? h('span', { class: 'muted small' }, a.handle ? `@${String(a.handle).replace(/^@/, '')}` : (a.name || 'Connected')) : h('span', { class: 'muted small' }, n.info.limits.split('.')[0] + '.')),
        h('span', { class: `pill ${tone}` }, label)),
      a ? h('div', { class: 'cx-account' },
        a.avatar_url ? h('img', { class: 'cx-avatar', src: a.avatar_url, alt: '', referrerpolicy: 'no-referrer', loading: 'lazy' }) : null,
        h('div', { class: 'cx-stats' },
          a.meta?.followers != null ? h('span', {}, h('strong', {}, compact(a.meta.followers)), ' followers') : null,
          a.meta?.views_28d != null ? h('span', {}, h('strong', {}, compact(a.meta.views_28d)), ' views (28 days)') : null,
          a.meta?.likes != null ? h('span', {}, h('strong', {}, compact(a.meta.likes)), ' likes') : null,
          n.x && n.x.limit ? h('span', {}, h('strong', {}, `${n.x.used}/${n.x.limit}`), ' X requests this month') : null),
        h('p', { class: 'muted small cx-when' },
          a.last_synced_at ? `Synced ${timeAgo(a.last_synced_at)}` : 'Not synced yet',
          left !== null && !a.renews ? ` · sign-in lasts ${left} more day${left === 1 ? '' : 's'}` : '',
          left !== null && left <= 7 && !a.renews ? ' — reconnect soon' : ''),
        a.last_error ? h('p', { class: 'error-text small' }, a.last_error) : null) : null,
      h('ul', { class: 'cx-unlocks' }, n.info.unlocks.map((u) => h('li', {}, icon('check', 13), u))),
      h('div', { class: 'row cx-actions' },
        connect,
        h('button', { class: 'btn ghost sm', onclick: () => setup(n) }, icon('gear', 14), n.configured ? 'App keys' : 'Set up'),
        a ? h('button', {
          class: 'btn ghost sm', onclick: (e) => busy(e.currentTarget, 'Syncing…', async () => {
            try {
              const r = await api('POST', `social/accounts/${n.platform}/sync`);
              toast(r.message);
              data.networks = r.networks;
            } catch (err) {
              toast(err.message, 'err', 9000);
              data = await api('GET', 'social/accounts');
            }
            draw();
          }),
        }, icon('refresh', 14), 'Sync') : null,
        a ? h('button', {
          class: 'btn ghost sm cx-disconnect', onclick: async () => {
            if (!(await confirmDialog(`Disconnect ${n.label}? Your saved posts and numbers stay; syncing${n.platform === 'linkedin' || n.platform === 'threads' || n.platform === 'x' ? ', agent publishing' : ''} and its Inbox stop.`, { ok: 'Disconnect' }))) return;
            const r = await api('DELETE', `social/accounts/${n.platform}`);
            data.networks = r.networks;
            draw();
            toast(`${n.label} disconnected.`);
          },
        }, 'Disconnect') : null));
  }

  async function setup(n) {
    const id = h('input', { type: 'text', value: n.client_id || '', autocomplete: 'off', spellcheck: 'false' });
    const secret = h('input', { type: 'password', autocomplete: 'new-password', placeholder: n.has_secret ? '•••••••• saved (leave empty to keep)' : '' });
    const cap = n.platform === 'x' ? h('input', { type: 'number', min: 0, max: 100000, value: n.x?.limit ?? 0 }) : null;
    const copy = (text) => navigator.clipboard?.writeText(text).then(() => toast('Copied.'), () => toast('Copy it by hand.', 'err'));
    await modal(`${n.label}: set up`, (close) => h('div', { class: 'cx-setup' },
      h('p', { class: 'muted small' }, n.info.limits),
      h('ol', { class: 'cx-steps' }, n.info.steps.map((s) => h('li', {}, s))),
      h('div', { class: 'field' }, h('label', {}, 'Redirect address to paste'),
        h('div', { class: 'cx-copy' }, h('code', {}, n.redirect_uri), h('button', { class: 'btn sm', type: 'button', onclick: () => copy(n.redirect_uri) }, icon('copy', 14), 'Copy')),
        n.blocked ? h('span', { class: 'hint error-text' }, 'This network needs https://. Set up the secure address first (top of the page).') : null),
      h('a', { class: 'btn ghost sm', href: n.info.console, target: '_blank', rel: 'noopener' }, icon('external', 14), `Open the ${n.label} developer console`),
      h('div', { class: 'grid grid-2 tight', style: { marginTop: '14px' } },
        field(n.info.id_label, id), field(n.info.secret_label, secret, 'stored encrypted')),
      cap ? field('X requests the studio may use per month', cap, '0 = off (syncs use 2, each published post 1); X bills per request') : null,
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '18px' } },
        n.configured ? h('button', {
          class: 'btn ghost sm', type: 'button', onclick: async (e) => {
            if (!(await confirmDialog(`Remove the ${n.label} app keys?`, { ok: 'Remove' }))) return;
            const r = await api('PUT', `social/accounts/${n.platform}/app`, { clear: true });
            data.networks = r.networks; draw(); close();
          },
        }, 'Remove keys') : h('span'),
        h('button', {
          class: 'btn primary', type: 'button', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            const body = { client_id: id.value.trim(), client_secret: secret.value.trim() || null };
            if (cap) body.x_monthly_requests = Number(cap.value || 0);
            const r = await api('PUT', `social/accounts/${n.platform}/app`, body);
            data.networks = r.networks;
            draw();
            toast(r.networks.find((x) => x.platform === n.platform)?.configured ? `Saved. Press Connect on ${n.label}.` : 'Saved. Add both the ID and the secret to connect.');
            close();
          }),
        }, 'Save'))), { wide: true });
  }

  function httpsHelp() {
    modal('Secure address (https) for Instagram, Threads and TikTok', () => h('div', { class: 'cx-setup' },
      h('p', {}, 'Meta and TikTok only return you to an https:// address, and Threads refuses “localhost”. The simplest fix on this computer is Caddy, a free one-file web server that adds https in front of the studio:'),
      h('ol', { class: 'cx-steps' },
        h('li', {}, 'Download Caddy for Windows from caddyserver.com/download and put caddy.exe somewhere handy.'),
        h('li', {}, 'Add this line to C:\\Windows\\System32\\drivers\\etc\\hosts (as administrator): ', h('code', {}, '127.0.0.1 studio.test')),
        h('li', {}, 'In a terminal: ', h('code', {}, 'caddy reverse-proxy --from https://studio.test --to localhost:8000'), ' (the first time, allow it to install its local certificate).'),
        h('li', {}, 'In the studio’s .env file set ', h('code', {}, 'APP_URL=https://studio.test'), ', restart the studio, and open it at https://studio.test.'),
        h('li', {}, 'Paste the new redirect addresses (they now start with https://studio.test) into each developer app.')),
      h('p', { class: 'muted small' }, 'Prefer not to? A Cloudflare Tunnel gives a public https address too, but then the studio’s sign-in page is reachable from the internet, so only do that with a strong password.')), { wide: true });
  }

  view.append(
    pageHead('Connections', 'Sign in to your networks once. Numbers update every hour, agents can publish where it’s allowed, and comments and DMs arrive in your Inbox. Sign-in tokens stay encrypted on this computer.'),
    banner, grid);
  draw();
}
