// Dashboard "Speaking": her next talk with a countdown, what the landing page's "On Stage" counts,
// speaking requests that came in through the website, and a manager for the talks themselves
// (add, edit, feature, hide, stage photo). Changes show on the landing page straight away.
import { h, api, apiUpload, link, modal, toast, field, timeAgo, confirmDialog } from './core.js';
import { icon } from './icons.js';

const fmt = (d) => new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
const days = (d) => Math.round((new Date(`${d}T00:00:00`) - new Date(new Date().toDateString())) / 86400000);

export function speakingCard(s, onChange) {
  if (!s) return null;
  const next = s.next;
  const n = next ? days(next.date) : null;
  return h('div', { class: 'card speaking-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Speaking'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } },
          `${s.stats.talks} talks on your website · ${s.stats.keynotes} keynotes${s.stats.cities ? ` · ${s.stats.cities} cities` : ''}`)),
      h('div', { class: 'row' },
        link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'),
        h('button', { class: 'btn sm', onclick: () => manageTalks().then((changed) => changed && onChange?.()) }, icon('mic', 14), 'Manage talks'))),
    h('div', { class: 'speaking-grid' },
      next
        ? h('div', { class: 'speaking-next' },
          h('span', { class: 'speaking-count' }, h('b', {}, n <= 0 ? 'Today' : n), n > 0 ? h('span', {}, n === 1 ? 'day' : 'days') : null),
          h('div', {},
            h('p', { class: 'muted small', style: { margin: 0 } }, 'Next on stage'),
            h('strong', {}, next.event),
            h('p', { class: 'muted small', style: { margin: 0 } }, [fmt(next.date), next.time, next.city].filter(Boolean).join(' · '))))
        : h('div', { class: 'speaking-next is-empty' },
          h('span', { class: 'speaking-count' }, icon('mic', 22)),
          h('div', {},
            h('strong', {}, 'No upcoming talks'),
            h('p', { class: 'muted small', style: { margin: 0 } }, 'Add one with its date and it counts down here and on your website.'))),
      h('div', { class: 'speaking-requests' },
        h('p', { class: 'muted small', style: { margin: 0 } }, 'Speaking requests from your website'),
        h('strong', { class: 'speaking-req-num' }, s.requests),
        s.latest_requests?.length
          ? h('ul', {}, s.latest_requests.map((r) => h('li', {},
            link('/deals', {}, r.company || r.name), h('span', { class: 'muted small' }, ` · ${timeAgo(r.created_at)}`))))
          : h('p', { class: 'muted small', style: { margin: 0 } }, 'Organizers use “Book Delia to speak” on your site; requests land in Deals.'))));
}

/** Add, edit, feature, hide and delete talks. Resolves true when something changed. */
export function manageTalks() {
  return modal('Your talks', (close) => {
    let changed = false;
    let kinds = {};
    const list = h('div', { class: 'talks-manage' }, h('p', { class: 'muted' }, 'Loading…'));

    const load = async () => {
      const res = await api('GET', 'talks');
      kinds = res.kinds;
      draw(res.talks);
    };

    function draw(talks) {
      list.replaceChildren(
        talks.length ? h('ul', { class: 'talks-list' }, talks.map(row)) : h('p', { class: 'muted' }, 'No talks yet.'),
        h('button', { class: 'btn', onclick: () => edit(null) }, icon('plus', 14), 'Add a talk'));
    }

    function row(t) {
      return h('li', { class: `talks-row${t.visible ? '' : ' is-hidden'}` },
        t.image_url ? h('img', { src: t.image_url, alt: '' }) : h('span', { class: 'talks-ph' }, icon('mic', 16)),
        h('div', { class: 'talks-main' },
          h('strong', {}, t.event, t.featured ? h('span', { class: 'pill', style: { marginLeft: '6px' } }, 'Featured') : null, t.upcoming ? h('span', { class: 'pill ok', style: { marginLeft: '6px' } }, 'Upcoming') : null),
          h('span', { class: 'muted small' }, [t.kind_label, t.date ? fmt(t.date) : 'No date', t.city].filter(Boolean).join(' · '))),
        h('button', { class: `btn sm ${t.featured ? 'primary' : 'ghost'}`, title: 'Lead the section with this talk', onclick: () => save(t.id, { featured: !t.featured }) }, icon('star', 13)),
        h('button', { class: 'btn sm ghost', title: t.visible ? 'Hide from your website' : 'Show on your website', onclick: () => save(t.id, { visible: !t.visible }) }, icon(t.visible ? 'eye' : 'x', 13)),
        h('button', { class: 'btn sm ghost', onclick: () => edit(t) }, icon('pen', 13), 'Edit'));
    }

    async function save(id, patch) {
      try {
        await api('PATCH', `talks/${id}`, patch);
        changed = true;
        await load();
      } catch (err) { toast(err.message, 'err'); }
    }

    function edit(t) {
      const f = (name, props = {}) => h('input', { class: 'input', name, value: t?.[name] ?? '', ...props });
      const inputs = {
        event: f('event', { required: true, placeholder: 'KM & AI Summit 2025' }),
        title: f('title', { placeholder: 'The talk’s title (optional)' }),
        kind: h('select', { class: 'input' }, Object.entries(kinds).map(([k, label]) => h('option', { value: k, selected: (t?.kind || 'keynote') === k }, label))),
        date: f('date', { type: 'date' }),
        time: f('time', { placeholder: '9:00 to 10:00 a.m.' }),
        venue: f('venue', { placeholder: 'Venue' }),
        city: f('city', { placeholder: 'Scottsdale, AZ' }),
        url: f('url', { type: 'url', placeholder: 'Post, recording or event page' }),
        description: h('textarea', { class: 'input', rows: 3, maxlength: 600 }, t?.description || ''),
      };
      const photo = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp' });
      const box = h('form', {
        class: 'talks-form',
        onsubmit: async (e) => {
          e.preventDefault();
          const body = Object.fromEntries(Object.entries(inputs).map(([k, el]) => [k, el.value.trim() === '' ? null : el.value.trim()]));
          try {
            const res = t ? await api('PATCH', `talks/${t.id}`, body) : await api('POST', 'talks', body);
            if (photo.files[0]) {
              const fd = new FormData();
              fd.append('image', photo.files[0]);
              await apiUpload(`talks/${res.talk.id}/image`, fd);
            }
            changed = true;
            toast(t ? 'Talk updated.' : 'Talk added.');
            await load();
          } catch (err) { toast(err.message, 'err'); }
        },
      },
        h('div', { class: 'grid grid-2' }, field('Event', inputs.event), field('Type', inputs.kind)),
        field('Talk title', inputs.title),
        h('div', { class: 'grid grid-3' }, field('Date', inputs.date), field('Time', inputs.time), field('City', inputs.city)),
        h('div', { class: 'grid grid-2' }, field('Venue', inputs.venue), field('Link', inputs.url)),
        field('Description', inputs.description, 'One or two sentences for your website'),
        field('Stage photo', photo, t?.image_url ? 'Choosing a file replaces the current photo' : 'JPEG, PNG or WebP, up to 8 MB'),
        h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '12px' } },
          t ? h('button', {
            type: 'button', class: 'btn ghost danger',
            onclick: async () => {
              if (!(await confirmDialog(`Delete “${t.event}”?`))) return;
              try { await api('DELETE', `talks/${t.id}`); changed = true; await load(); } catch (err) { toast(err.message, 'err'); }
            },
          }, icon('trash', 13), 'Delete') : h('span'),
          h('div', { class: 'row' },
            h('button', { type: 'button', class: 'btn ghost', onclick: () => load() }, 'Back'),
            h('button', { type: 'submit', class: 'btn primary' }, t ? 'Save' : 'Add talk'))));
      list.replaceChildren(box);
      inputs.event.focus();
    }

    load().catch((err) => list.replaceChildren(h('p', { class: 'error' }, err.message)));
    return h('div', {}, list,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '14px' } },
        h('button', { class: 'btn ghost', onclick: () => close(changed) }, 'Done')));
  }, { wide: true });
}
