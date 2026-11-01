// Deals: the money loop around each deal. Follow-ups (drafted, never sent), proposals with a
// payment link, a per-brand media kit link that says when it was opened, sponsor compliance
// checks, a wrap report from real numbers, outbound pitches and sponsor prospects from her niche.
import {
  h, api, busy, toast, clear, pill, modal, field, copyText, mdBlock, timeAgo, compact, link,
} from './core.js';
import { icon } from './icons.js';
import { printMarkdown } from './components.js';

const KIND_PILL = { kit_opened: ['ok', 'opened kit'], testimonial: ['picked', 'ask for a review'], checkout: ['bad', 'unpaid checkout'], invoiced: ['bad', null] };
const usd = (n, cur = 'USD') => `${cur === 'USD' ? '$' : `${cur} `}${Math.round(Number(n) || 0).toLocaleString()}`;
const lines = (v) => String(v || '').split('\n').map((x) => x.trim()).filter(Boolean);
const pairs = (v, a, b) => lines(v).map((l) => { const i = l.indexOf(':'); return i > 0 ? { [a]: l.slice(0, i).trim(), [b]: l.slice(i + 1).trim() } : { [a]: l, [b]: '' }; });
const unpairs = (xs, a, b) => (xs || []).map((x) => (x[b] ? `${x[a]}: ${x[b]}` : x[a])).join('\n');

export const SOURCE_ICONS = { youtube: 'brand:youtube', linkedin: 'brand:linkedin', instagram: 'brand:instagram', x: 'brand:x', tiktok: 'brand:tiktok', threads: 'brand:threads' };

/** "from YouTube" on a deal card, with the video or post when a tracked link said so. */
export function sourceChip(deal) {
  if (!deal.source) return null;
  const label = { link_in_bio: 'Link in bio', media_kit: 'Media kit', referral: 'Other sites', direct: 'Direct', search: 'Search', newsletter: 'Newsletter', email: 'Email' }[deal.source]
    || deal.source.charAt(0).toUpperCase() + deal.source.slice(1);
  const item = deal.attribution?.item;
  return h('span', { class: 'pf-chip', title: item ? `Came through a tracked link (${item})` : 'Where this website lead came from' },
    icon(SOURCE_ICONS[deal.source] || 'globe', 12), ` from ${label}${item?.startsWith('yt:') ? ' · a video' : item ? ' · a post' : ''}`);
}

/** A website lead's rule-of-thumb fit score, with the reasons on hover. */
export function leadScoreChip(ls) {
  if (!ls) return null;
  return h('span', { class: `pf-chip lead-score ${ls.score >= 70 ? 'hi' : ls.score >= 45 ? 'mid' : 'lo'}`, title: `${ls.label} (rule of thumb): ${ls.reasons.join(', ') || 'little detail in the form'}` },
    icon('target', 12), ` fit ${ls.score}`);
}

// ---------------------------------------------------------------- follow-ups

/** The "needs a follow-up" list at the top of Deals (and on the dashboard). */
export function followUpsCard(items, { onChange, compactView = false } = {}) {
  if (!items?.length) return null;
  return h('div', { class: 'card follow-ups-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, `Needs a follow-up · ${items.length}`),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'Leads, proposals and invoices that went quiet. The studio drafts it; you send it.')),
      compactView ? link('/deals', { class: 'btn ghost sm' }, 'Open deals', icon('arrow', 14)) : null),
    h('ul', { class: 'item-list' }, items.map((f) => h('li', {},
      h('div', { class: 'item-main' },
        h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
          h('strong', {}, f.brand_name),
          pill((KIND_PILL[f.kind] || ['WARN'])[0], (KIND_PILL[f.kind] || [])[1] || `${f.days_quiet}d quiet`),
          leadScoreChip(f.lead_score)),
        h('div', { class: 'muted small clamp-1' }, `${f.reason}${f.rate ? ` · ${usd(f.rate, f.currency)}` : ''}${f.source ? ` · from ${f.source}` : ''}`)),
      h('div', { class: 'row', style: { gap: '6px' } },
        h('button', { class: 'btn sm primary', onclick: () => followUpDialog({ id: f.deal_id, brand_name: f.brand_name, contact_email: f.contact_email }, onChange) }, icon('mail', 13), 'Draft'),
        compactView ? null : h('button', {
          class: 'btn sm ghost', title: 'Hide it for 3 days',
          onclick: async (e) => busy(e.currentTarget, '…', async () => { await api('POST', `deals/${f.deal_id}/snooze`, { days: 3 }); toast('Snoozed for 3 days.'); onChange?.(); }),
        }, 'Snooze'))))));
}

export function followUpDialog(deal, onDone) {
  return modal(`Follow up: ${deal.brand_name}`, (close) => {
    const angle = h('input', { type: 'text', placeholder: 'Optional angle, e.g. offer a smaller pilot, or share a new case study' });
    const subject = h('input', { type: 'text' });
    const body = h('textarea', { rows: 9 });
    const draftBox = h('div', { hidden: true },
      field('Subject', subject),
      field('Email', body, 'edit freely: nothing is sent from the studio'),
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '12px', flexWrap: 'wrap', gap: '8px' } },
        h('div', { class: 'row' },
          h('button', { class: 'btn', onclick: () => copyText(`Subject: ${subject.value}\n\n${body.value}`, 'Email copied.') }, icon('copy', 14), 'Copy'),
          deal.contact_email ? h('a', {
            class: 'btn', target: '_blank', rel: 'noopener',
            onclick: (e) => { e.currentTarget.href = `mailto:${encodeURIComponent(deal.contact_email)}?subject=${encodeURIComponent(subject.value)}&body=${encodeURIComponent(body.value)}`; },
            href: '#',
          }, icon('external', 14), 'Open in email') : null),
        h('button', {
          class: 'btn primary',
          onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            await api('POST', `deals/${deal.id}/contacted`, { note: `Followed up: ${subject.value}` });
            toast('Marked as followed up. The clock restarts.');
            close(true);
            onDone?.();
          }),
        }, icon('check', 14), 'I sent it')));
    const draftBtn = h('button', {
      class: 'btn primary',
      onclick: (e) => busy(e.currentTarget, 'Drafting…', async () => {
        const r = await api('POST', `deals/${deal.id}/follow-up`, { angle: angle.value.trim() || null });
        subject.value = r.draft.subject;
        body.value = r.draft.body;
        draftBox.hidden = false;
        draftBtn.textContent = 'Draft again';
        if (!r.draft.ai) toast('AI isn’t available, so this is a plain template. Edit it before sending.', 'warn');
      }),
    }, icon('sparkle', 14), 'Draft the email');
    setTimeout(() => draftBtn.click(), 0);
    return h('div', {},
      deal.contact_email ? h('p', { class: 'muted small' }, `To: ${deal.contact_email}`) : h('p', { class: 'muted small' }, 'No email on this deal yet: add it with Edit.'),
      h('div', { class: 'row', style: { gap: '8px', alignItems: 'flex-end' } }, h('div', { style: { flex: 1 } }, field('Angle', angle)), draftBtn),
      draftBox);
  }, { wide: true });
}

// ---------------------------------------------------------------- proposals

export function proposalDialog(deal, onDone) {
  return modal(`Proposal: ${deal.brand_name}`, (close) => {
    const box = h('div', {}, h('p', { class: 'muted' }, 'Loading…'));
    let state = null;

    async function load() {
      state = await api('GET', `deals/${deal.id}/proposal`);
      state.proposal ? drawProposal() : drawStart();
    }

    function drawStart() {
      const planSel = h('select', {}, h('option', { value: '' }, 'Match it from the inquiry'),
        state.plans.map((p) => h('option', { value: p.key, selected: p.key === state.suggested_plan }, `${p.name} · $${p.price.toLocaleString()}`)));
      const notes = h('textarea', { rows: 4, placeholder: 'Anything to include: scope they asked for, a start date, a discount you agreed…' });
      clear(box).append(
        h('p', { class: 'muted small' }, `Scope, timeline, investment and next steps, written from the inquiry and your Packages & pricing. The price is ${Number(deal.rate) > 0 ? `this deal’s fee (${usd(deal.rate, deal.currency)})` : 'the package price'}, never made up.`),
        h('div', { class: 'grid grid-2' }, field('Package', planSel), h('div')),
        field('Your notes', notes),
        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
          h('button', {
            class: 'btn primary',
            onclick: (e) => busy(e.currentTarget, 'Writing the proposal…', async () => {
              const r = await api('POST', `deals/${deal.id}/proposal`, { plan: planSel.value || null, notes: notes.value.trim() || null });
              state = { ...state, proposal: r.proposal, markdown: r.markdown };
              drawProposal();
            }),
          }, icon('sparkle', 14), 'Write proposal')));
    }

    function drawProposal() {
      const p = state.proposal;
      const linkBox = h('div');
      const pctSel = h('select', { 'aria-label': 'How much to charge now' },
        [[100, 'Full amount'], [50, '50% deposit'], [30, '30% deposit'], [25, '25% deposit']].map(([v, l]) => h('option', { value: v }, l)));
      if (p.payment_url) linkBox.append(h('p', { class: 'small' }, icon('lock', 12), ' Payment link: ', h('a', { href: p.payment_url, target: '_blank', rel: 'noopener' }, 'open'), ' ', h('button', { class: 'btn ghost sm', onclick: () => copyText(p.payment_url, 'Payment link copied.') }, icon('copy', 12), 'Copy')));
      clear(box).append(
        h('div', { class: 'row', style: { justifyContent: 'space-between', flexWrap: 'wrap', gap: '8px', marginBottom: '10px' } },
          h('span', { class: 'muted small' }, `Written ${timeAgo(p.generated_at)}${p.ai ? '' : ' (template: AI wasn’t available)'} · valid until ${p.valid_until || '—'}`),
          h('div', { class: 'row' },
            h('button', { class: 'btn sm ghost', onclick: drawEdit }, icon('pen', 13), 'Edit'),
            h('button', { class: 'btn sm ghost', onclick: drawStart }, icon('refresh', 13), 'Rewrite'),
            h('button', { class: 'btn sm ghost', onclick: () => copyText(state.markdown, 'Proposal copied as text.') }, icon('copy', 13), 'Copy'),
            h('button', { class: 'btn sm ghost', onclick: () => printMarkdown(p.title, state.markdown) }, icon('file-text', 13), 'PDF'))),
        h('div', { class: 'card proposal-preview' }, mdBlock(state.markdown)),
        h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '14px', flexWrap: 'wrap', gap: '8px' } },
          h('div', { class: 'row', style: { gap: '6px' } },
            pctSel,
            h('button', {
              class: 'btn', disabled: !state.stripe, title: state.stripe ? 'A Stripe Checkout link for this deal' : 'Add your Stripe key in Settings first',
              onclick: (e) => busy(e.currentTarget, 'Creating…', async () => {
                const r = await api('POST', `deals/${deal.id}/payment-link`, { percent: Number(pctSel.value) });
                state.proposal.payment_url = r.url;
                if (r.markdown) state.markdown = r.markdown;
                await copyText(r.url, `Payment link for ${usd(r.amount, r.currency)} copied.`);
                drawProposal();
              }),
            }, icon('lock', 14), 'Payment link')),
          h('button', {
            class: 'btn primary',
            onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
              await api('POST', `deals/${deal.id}/proposal/sent`);
              toast('Proposal marked as sent. You’ll be reminded to follow up in 5 days.');
              close(true);
              onDone?.();
            }),
          }, icon('check', 14), 'I sent it')),
        linkBox);
    }

    function drawEdit() {
      const p = state.proposal;
      const title = h('input', { type: 'text', value: p.title });
      const summary = h('textarea', { rows: 3 }, p.summary);
      const goals = h('textarea', { rows: 3 }, (p.goals || []).join('\n'));
      const scope = h('textarea', { rows: 5 }, unpairs(p.scope, 'item', 'detail'));
      const timeline = h('textarea', { rows: 4 }, unpairs(p.timeline, 'when', 'milestone'));
      const deliverables = h('textarea', { rows: 3 }, (p.deliverables || []).join('\n'));
      const assumptions = h('textarea', { rows: 3 }, (p.assumptions || []).join('\n'));
      const next = h('textarea', { rows: 3 }, (p.next_steps || []).join('\n'));
      const terms = h('input', { type: 'text', value: p.investment?.terms || '' });
      clear(box).append(
        h('p', { class: 'muted small' }, 'One item per line. Scope and timeline: "Item: detail".'),
        field('Title', title), field('Summary', summary),
        h('div', { class: 'grid grid-2' }, field('Goals', goals), field('Deliverables', deliverables)),
        field('Scope', scope), field('Timeline', timeline),
        h('div', { class: 'grid grid-2' }, field('What you need from them', assumptions), field('Next steps', next)),
        field(`Payment terms (amount: ${usd(p.investment?.amount, p.investment?.currency)}, from the deal’s fee)`, terms),
        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
          h('button', { class: 'btn ghost', onclick: drawProposal }, 'Cancel'),
          h('button', {
            class: 'btn primary',
            onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
              const r = await api('PUT', `deals/${deal.id}/proposal`, { proposal: {
                ...p, title: title.value, summary: summary.value, goals: lines(goals.value), scope: pairs(scope.value, 'item', 'detail'),
                timeline: pairs(timeline.value, 'when', 'milestone'), deliverables: lines(deliverables.value), assumptions: lines(assumptions.value),
                next_steps: lines(next.value), investment: { ...p.investment, terms: terms.value },
              } });
              state = { ...state, proposal: r.proposal, markdown: r.markdown };
              toast('Proposal saved.');
              drawProposal();
            }),
          }, 'Save')));
    }

    load().catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));
    return box;
  }, { wide: true });
}

// ---------------------------------------------------------------- media kit link

export function kitLinkDialog(deal) {
  return modal(`Media kit for ${deal.brand_name}`, () => {
    const box = h('div', {}, h('p', { class: 'muted' }, 'Making the link…'));
    api('POST', `deals/${deal.id}/kit-link`).then((r) => clear(box).append(
      h('p', { class: 'muted small' }, 'A private link to your live media kit, made for this brand. When they open it, it shows up in your notifications and as a follow-up: the best moment to write.'),
      h('div', { class: 'row', style: { gap: '8px' } },
        h('input', { type: 'text', readonly: true, value: r.url, style: { flex: 1 }, onfocus: (e) => e.target.select() }),
        h('button', { class: 'btn primary', onclick: () => copyText(r.url, 'Media kit link copied.') }, icon('copy', 14), 'Copy')),
      h('p', { class: 'small', style: { marginTop: '10px' } }, r.views ? `Opened ${r.views} time${r.views === 1 ? '' : 's'}, last ${timeAgo(r.viewed_at)}.` : 'Not opened yet.'),
    )).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));
    return box;
  });
}

// ---------------------------------------------------------------- sponsor compliance & wrap report

export function complianceDialog(deal, { content = '', title = '' } = {}) {
  return modal(`Sponsor check: ${deal.brand_name}`, () => {
    const text = h('textarea', { rows: 8, placeholder: 'Paste the sponsored script segment, caption or post…' }, content);
    const out = h('div', { style: { marginTop: '14px' } });
    if (!content && deal.script_id) {
      api('GET', `scripts/${deal.script_id}`).then((r) => { if (!text.value) { text.value = r.script.body || ''; title ||= r.script.title; } }).catch(() => {});
    }
    const run = h('button', {
      class: 'btn primary',
      onclick: (e) => busy(e.currentTarget, 'Checking against the deal…', async () => {
        if (!text.value.trim()) throw new Error('Paste the content to check first.');
        const r = (await api('POST', 'agents/studio/audit-deal-fulfillment', { deal_id: deal.id, content: text.value, title: title || undefined })).result;
        const c = r.checks || {};
        const tp = Object.entries(c.talking_points_checklist || {});
        clear(out).append(h('div', { class: 'card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } },
            h('strong', {}, `Compliance ${r.compliance_score}/100`),
            pill(r.status === 'approved' ? 'ok' : 'WARN', r.status)),
          h('ul', { class: 'checklist', style: { marginTop: '10px' } },
            h('li', { class: c.ftc_disclosure?.compliant ? 'done' : '' }, h('span', { class: 'check' }, c.ftc_disclosure?.compliant ? icon('check', 14) : '!'), `Ad disclosure${c.ftc_disclosure?.detected_phrase ? `: “${c.ftc_disclosure.detected_phrase}”` : ''}${c.ftc_disclosure?.notes ? ` · ${c.ftc_disclosure.notes}` : ''}`),
            h('li', { class: c.tracking_and_promo?.compliant ? 'done' : '' }, h('span', { class: 'check' }, c.tracking_and_promo?.compliant ? icon('check', 14) : '!'), `Code ${c.tracking_and_promo?.promo_detected ? 'found' : 'missing'} · link ${c.tracking_and_promo?.link_detected ? 'found' : 'missing'}`),
            h('li', { class: c.competitor_conflicts?.has_conflict ? '' : 'done' }, h('span', { class: 'check' }, c.competitor_conflicts?.has_conflict ? '!' : icon('check', 14)), c.competitor_conflicts?.has_conflict ? `Competitor mentioned: ${(c.competitor_conflicts.conflicting_brands || []).join(', ')}` : 'No competitor conflicts'),
            ...tp.map(([point, ok]) => h('li', { class: ok ? 'done' : '' }, h('span', { class: 'check' }, ok ? icon('check', 14) : '!'), point))),
          r.missing_mandatories?.length ? h('p', { class: 'small' }, h('strong', {}, 'Missing: '), r.missing_mandatories.join('; ')) : null,
          r.suggested_remedies?.length ? h('ul', { class: 'mini-list' }, r.suggested_remedies.map((x) => h('li', {}, x))) : null,
          r.armored_disclosure_snippet ? h('div', { class: 'row', style: { marginTop: '8px', gap: '8px' } },
            h('code', { style: { flex: 1, whiteSpace: 'pre-wrap' } }, r.armored_disclosure_snippet),
            h('button', { class: 'btn sm ghost', onclick: () => copyText(r.armored_disclosure_snippet, 'Disclosure copied.') }, icon('copy', 12), 'Copy')) : null,
          h('p', { class: 'muted small', style: { marginTop: '8px' } }, 'An AI check against this deal’s talking points, do-nots, code and link. Read it before you publish; it’s not legal advice.')));
      }),
    }, icon('check', 14), 'Check it');
    return h('div', {},
      h('p', { class: 'muted small' }, `Talking points: ${deal.key_talking_points || 'none set'} · Do-nots: ${deal.do_nots || 'none set'}${deal.coupon_code ? ` · Code: ${deal.coupon_code}` : ''}`),
      field('Content', text),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '10px' } }, run),
      out);
  }, { wide: true });
}

export function wrapReportDialog(deal) {
  return modal(`Wrap report: ${deal.brand_name}`, () => {
    const impressions = h('input', { type: 'number', min: 0, placeholder: 'From the platform’s analytics' });
    const clicks = h('input', { type: 'number', min: 0, placeholder: 'From your tracking link' });
    const url = h('input', { type: 'url', placeholder: 'https://youtu.be/…' });
    const comments = h('textarea', { rows: 3, placeholder: 'Paste 1-3 real viewer comments, one per line (optional)' });
    const out = h('div', { style: { marginTop: '14px' } });
    const run = h('button', {
      class: 'btn primary',
      onclick: (e) => busy(e.currentTarget, 'Writing the report…', async () => {
        const r = (await api('POST', 'agents/studio/generate-deal-wrap-report', {
          deal_id: deal.id,
          impressions: impressions.value ? Number(impressions.value) : undefined,
          clicks: clicks.value ? Number(clicks.value) : undefined,
          live_url: url.value.trim() || undefined,
          positive_comments: lines(comments.value),
        })).result;
        const k = r.key_metrics || {};
        const text = `${r.brand_name} campaign wrap-up\n\n${r.executive_summary}\n\nImpressions: ${k.impressions ? Number(k.impressions).toLocaleString() : 'not measured'}\nCTR: ${k.ctr}\nCPM: ${k.estimated_cpm ? `$${k.estimated_cpm}` : 'n/a'}\n\n${r.contractual_fulfillment_statement}`;
        clear(out).append(h('div', { class: 'card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } }, h('strong', {}, r.campaign_status), pill(r.ready_for_invoicing ? 'ok' : 'WARN', r.ready_for_invoicing ? 'ready to invoice' : 'not ready')),
          h('p', {}, r.executive_summary),
          (r.audience_sentiment?.highlighted_quotes || []).length ? h('ul', { class: 'mini-list' }, r.audience_sentiment.highlighted_quotes.map((q) => h('li', {}, `“${q}”`))) : null,
          h('div', { class: 'row', style: { justifyContent: 'flex-end' } }, h('button', { class: 'btn sm', onclick: () => copyText(text, 'Report copied for email.') }, icon('copy', 13), 'Copy for email'))));
      }),
    }, icon('sparkle', 14), 'Write report');
    return h('div', {},
      h('p', { class: 'muted small' }, 'Only the numbers you enter (or the linked post’s logged numbers) are used. Nothing is estimated or invented.'),
      h('div', { class: 'grid grid-2' }, field('Impressions / views', impressions), field('Clicks', clicks)),
      field('Live link', url), field('Viewer comments', comments),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '10px' } }, run),
      out);
  }, { wide: true });
}

// ---------------------------------------------------------------- outbound pitch & prospects

export function outboundPitchDialog({ brand = '', domain = '', onSaved } = {}) {
  return modal('Outbound sponsor pitch', (close) => {
    const brandIn = h('input', { type: 'text', value: brand, placeholder: 'e.g. Supabase', required: true });
    const catIn = h('input', { type: 'text', placeholder: 'e.g. Postgres database / developer tool' });
    const angleIn = h('input', { type: 'text', placeholder: 'Optional: an angle, e.g. a benchmark video' });
    const out = h('div', { style: { marginTop: '14px' } });
    const run = h('button', {
      class: 'btn primary',
      onclick: (e) => busy(e.currentTarget, 'Writing the pitch…', async () => {
        if (!brandIn.value.trim()) throw new Error('Add the brand first.');
        const r = (await api('POST', 'agents/studio/synthesize-brand-pitch', { brand_name: brandIn.value.trim(), product_category: catIn.value.trim() || undefined, pitch_angle: angleIn.value.trim() || undefined })).result;
        const mail = r.outbound_email || {};
        const pitchText = `Subject: ${mail.subject}\n\n${mail.body}`;
        clear(out).append(
          h('div', { class: 'grid grid-3' }, Object.values(r.tiered_packages || {}).map((t) => h('div', { class: 'card' },
            h('strong', {}, t.name), h('div', { class: 'deal-rate' }, usd(t.rate)), h('p', { class: 'muted small' }, t.deliverables)))),
          (r.integration_concepts || []).length ? h('div', { class: 'card', style: { marginTop: '10px' } }, h('h3', {}, 'Video ideas for them'),
            h('ul', { class: 'mini-list' }, r.integration_concepts.map((c) => h('li', {}, h('strong', {}, c.title), c.hook ? ` · ${c.hook}` : '')))) : null,
          h('div', { class: 'card', style: { marginTop: '10px' } },
            h('div', { class: 'row', style: { justifyContent: 'space-between' } }, h('strong', {}, `Subject: ${mail.subject || ''}`),
              h('button', { class: 'btn sm ghost', onclick: () => copyText(pitchText, 'Pitch copied.') }, icon('copy', 13), 'Copy')),
            h('pre', { style: { whiteSpace: 'pre-wrap', fontFamily: 'inherit' } }, mail.body || '')),
          h('p', { class: 'muted small' }, 'Package rates come from your rate card; reach numbers in the email are AI estimates: check them against your media kit before sending.'),
          h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
            h('button', {
              class: 'btn primary',
              onclick: (ev) => busy(ev.currentTarget, 'Saving…', async () => {
                const s = await api('POST', 'deals/prospects', { brand: brandIn.value.trim(), domain: domain || null, pitch: pitchText });
                toast(s.message);
                close(true);
                onSaved?.(s.deal);
              }),
            }, icon('plus', 14), 'Save as prospect')));
      }),
    }, icon('sparkle', 14), 'Write pitch');
    return h('div', {},
      h('div', { class: 'grid grid-3' }, field('Brand', brandIn), field('Category', catIn), field('Angle', angleIn)),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '10px' } }, run),
      out);
  }, { wide: true });
}

export function prospectsDialog(onSaved) {
  return modal('Sponsor prospects in your niche', () => {
    const box = h('div', {}, h('p', { class: 'muted' }, 'Looking through your breakout scans…'));
    api('GET', 'deals/prospects').then((r) => {
      if (!r.prospects.length) {
        clear(box).append(h('p', { class: 'muted' }, 'No sponsors spotted yet. Every breakout scan reads the descriptions of your competitors’ recent videos for “sponsored by…” lines and sponsor links; run a scan on Breakouts and check back.'),
          link('/breakouts', { class: 'btn' }, icon('trend', 14), 'Open Breakouts'));
        return;
      }
      clear(box).append(
        h('p', { class: 'muted small' }, 'Brands paying for videos in your niche right now, from the descriptions of your competitors’ recent uploads. More channels means a bigger budget for your audience.'),
        h('ul', { class: 'item-list' }, r.prospects.map((p) => h('li', {},
          h('div', { class: 'item-main' },
            h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
              h('strong', {}, p.brand), p.domain ? h('span', { class: 'muted small' }, p.domain) : null,
              p.deal ? pill('ok', `in deals · ${p.deal.stage}`) : null),
            h('div', { class: 'muted small clamp-1' }, `${p.channels.length} channel${p.channels.length === 1 ? '' : 's'} (${p.channels.slice(0, 3).join(', ')}) · ${p.mentions} video${p.mentions === 1 ? '' : 's'} · ${compact(p.views)} views`),
            h('a', { class: 'muted small clamp-1', href: p.example.url, target: '_blank', rel: 'noopener', title: p.example.snippet }, `e.g. “${p.example.title}”`)),
          p.deal ? link('/deals', { class: 'btn sm ghost' }, 'Open') : h('button', { class: 'btn sm primary', onclick: () => outboundPitchDialog({ brand: p.brand, domain: p.domain, onSaved }) }, icon('sparkle', 13), 'Pitch')))));
    }).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));
    return box;
  }, { wide: true });
}

/** A booked intro call on a deal card: when, and a calendar file. */
export function meetingLine(deal) {
  if (!deal.meeting_at) return null;
  const at = new Date(deal.meeting_at);
  return h('div', { class: 'row small', style: { gap: '8px', margin: '6px 0' } },
    icon('calendar', 13),
    h('span', {}, `Intro call ${at.toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}`),
    at > new Date() ? h('a', { href: `/api/deals/${deal.id}/meeting.ics`, class: 'btn ghost sm' }, 'Add to calendar') : pill('', 'past'));
}
