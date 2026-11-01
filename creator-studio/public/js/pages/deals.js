// Brand Deals & Sponsorships: pipeline, rate card, deliverables, sponsor script generator and pitch drafting.
import {
  h, api, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, navigate, link, compact, timeAgo,
} from '../core.js';
import { icon } from '../icons.js';
import {
  followUpsCard, followUpDialog, proposalDialog, kitLinkDialog, complianceDialog, wrapReportDialog,
  outboundPitchDialog, prospectsDialog, sourceChip, meetingLine,
} from '../deal-tools.js';

const STAGE_LABELS = {
  prospect: 'Prospect',
  inquiry: 'Inquiry',
  pitching: 'Pitching',
  negotiating: 'Negotiating',
  contracted: 'Contracted',
  production: 'In Production',
  in_review: 'In Review',
  scheduled: 'Scheduled',
  invoiced: 'Invoiced',
  paid: 'Paid',
};

const STAGE_PILLS = {
  prospect: '',
  inquiry: 'new',
  pitching: 'picked',
  negotiating: 'WARN',
  contracted: 'ok',
  production: 'picked',
  in_review: 'WARN',
  scheduled: 'picked',
  invoiced: 'WARN',
  paid: 'ok',
};

export async function renderDeals(view) {
  let dealsData = await api('GET', 'deals');
  let followUps = (await api('GET', 'deals/follow-ups').catch(() => ({ follow_ups: [] }))).follow_ups;
  let currentFilter = 'all';
  const followBox = h('div', { style: { marginBottom: '18px' } });

  const statsRow = h('div', { class: 'grid grid-4', style: { marginBottom: '18px' } });
  const tabBar = h('div', { class: 'tabs', style: { marginBottom: '16px' } });
  const dealsContainer = h('div', { class: 'deals-container' });

  function drawStats() {
    const s = dealsData.stats;
    clear(statsRow).append(
      h('div', { class: 'card stat-box tone-accent' },
        h('div', { class: 'num' }, `$${compact(s.total_pipeline)}`),
        h('div', { class: 'lbl' }, `active pipeline · ${s.active_count} deals`)),
      h('div', { class: 'card stat-box tone-good' },
        h('div', { class: 'num' }, `$${compact(s.confirmed_revenue)}`),
        h('div', { class: 'lbl' }, 'confirmed revenue')),
      h('div', { class: 'card stat-box tone-warn' },
        h('div', { class: 'num' }, `$${compact(s.pending_invoices)}`),
        h('div', { class: 'lbl' }, 'invoiced · awaiting payment')),
      h('div', { class: 'card stat-box tone-violet' },
        h('div', { class: 'num' }, `$${compact(s.received_revenue)}`),
        h('div', { class: 'lbl' }, 'paid revenue')),
    );
  }

  function drawTabs() {
    const counts = dealsData.stats.by_stage || {};
    const tabs = ['all', ...dealsData.stages].map((st) => h('button', {
      class: `tab${currentFilter === st ? ' active' : ''}`,
      onclick: () => { currentFilter = st; draw(); },
    }, st === 'all' ? 'All deals' : STAGE_LABELS[st] || st,
    h('span', { class: 'count' }, st === 'all' ? dealsData.deals.length : (counts[st] || 0))));
    clear(tabBar).append(tabs);
  }

  function dealCard(deal) {
    const deliverables = Array.isArray(deal.deliverables) ? deal.deliverables : [];
    return h('div', { class: 'card deal-card' },
      h('div', { class: 'card-header' },
        h('div', {},
          h('h3', { style: { margin: 0, fontSize: '1.15rem' } }, deal.brand_name),
          deal.contact_name ? h('span', { class: 'muted small' }, `${deal.contact_name} ${deal.contact_email ? `(${deal.contact_email})` : ''}`) : null),
        h('div', { class: 'row' },
          pill(STAGE_PILLS[deal.stage] || 'new', STAGE_LABELS[deal.stage] || deal.stage),
          h('strong', { class: 'deal-rate' }, `${deal.currency || 'USD'} $${Number(deal.rate || 0).toLocaleString()}`))),
      deliverables.length ? h('div', { class: 'deliverables-row' },
        deliverables.map((d) => h('span', { class: 'pf-chip' }, icon('check', 12), typeof d === 'string' ? d : `${d.platform || ''} ${d.format || ''}${d.service ? ` · ${d.service}` : ''}`))) : null,
      deal.coupon_code || deal.tracking_url ? h('div', { class: 'row small muted', style: { margin: '8px 0' } },
        deal.coupon_code ? h('span', {}, h('strong', {}, 'Code: '), deal.coupon_code) : null,
        deal.tracking_url ? h('a', { href: deal.tracking_url, target: '_blank', rel: 'noopener' }, 'Tracking Link ↗') : null) : null,
      sourceChip(deal) || deal.proposal_at || deal.kit_views ? h('div', { class: 'row small', style: { gap: '6px', flexWrap: 'wrap', margin: '6px 0' } },
        sourceChip(deal),
        deal.proposal_at ? h('span', { class: 'pf-chip' }, icon('doc', 12), ` proposal ${timeAgo(deal.proposal_at)}`) : null,
        deal.kit_views ? h('span', { class: 'pf-chip' }, icon('eye', 12), ` kit opened ${deal.kit_views}×`) : null) : null,
      meetingLine(deal),
      deal.key_talking_points ? h('p', { class: 'clamp-2 muted small', style: { margin: '8px 0' } }, deal.key_talking_points) : null,
      h('div', { class: 'row', style: { marginTop: '12px', justifyContent: 'space-between' } },
        h('div', { class: 'row' },
          h('button', { class: 'btn sm ghost', onclick: () => editDealDialog(deal) }, icon('pen', 13), 'Edit'),
          deal.stage !== 'paid' ? h('button', { class: 'btn sm ghost', onclick: () => followUpDialog(deal, reload) }, icon('mail', 13), 'Follow up') : null,
          ['prospect', 'inquiry', 'pitching', 'negotiating'].includes(deal.stage) || deal.proposal ? h('button', { class: 'btn sm ghost', onclick: () => proposalDialog(deal, reload) }, icon('doc', 13), 'Proposal') : null,
          h('button', { class: 'btn sm ghost', onclick: () => kitLinkDialog(deal) }, icon('link', 13), 'Kit link'),
          h('button', { class: 'btn sm ghost', onclick: () => scriptGeneratorDialog(deal) }, icon('sparkle', 13), 'Sponsor script'),
          ['contracted', 'production', 'in_review', 'scheduled'].includes(deal.stage) || deal.script_id ? h('button', { class: 'btn sm ghost', onclick: () => complianceDialog(deal) }, icon('check', 13), 'Sponsor check') : null,
          h('button', { class: 'btn sm ghost', onclick: () => invoiceDialog(deal) }, icon('file-text', 13), 'Invoice'),
          h('button', { class: 'btn sm ghost', onclick: () => campaignReportDialog(deal) }, icon('chart', 13), 'Campaign report'),
          ['scheduled', 'invoiced', 'paid'].includes(deal.stage) ? h('button', { class: 'btn sm ghost', onclick: () => wrapReportDialog(deal) }, icon('file-text', 13), 'Wrap report') : null),
        h('div', { class: 'row' },
          deal.stage !== 'paid' ? h('button', {
            class: 'btn sm primary',
            onclick: async () => {
              const next = deal.stage === 'prospect' ? 'pitching' : deal.stage === 'invoiced' ? 'paid' : 'invoiced';
              await api('PATCH', `deals/${deal.id}`, { stage: next });
              toast(`Deal marked as ${STAGE_LABELS[next]}.`);
              await reload();
            },
          }, deal.stage === 'prospect' ? 'Start pitching' : deal.stage === 'invoiced' ? 'Mark paid' : 'Mark invoiced') : h('span', { class: 'small' }, '✓ Paid'))));
  }

  function draw() {
    drawStats();
    drawTabs();
    clear(followBox).append(followUpsCard(followUps, { onChange: reload }) || '');
    const filtered = dealsData.deals.filter((d) => currentFilter === 'all' || d.stage === currentFilter);
    clear(dealsContainer);
    if (!filtered.length) {
      dealsContainer.append(emptyState('💼', 'No deals in this stage', 'Add an inbound lead, active sponsor or negotiate your next partnership.'));
    } else {
      dealsContainer.append(h('div', { class: 'grid grid-2' }, filtered.map(dealCard)));
    }
  }

  async function reload() {
    [dealsData, followUps] = await Promise.all([
      api('GET', 'deals'),
      api('GET', 'deals/follow-ups').then((r) => r.follow_ups).catch(() => []),
    ]);
    draw();
  }

  function editDealDialog(deal = null) {
    return modal(deal ? `Edit ${deal.brand_name}` : 'New brand deal', (close) => {
      const brandIn = h('input', { type: 'text', required: true, value: deal?.brand_name || '', placeholder: 'e.g. Supabase, Cursor, Linear' });
      const contactName = h('input', { type: 'text', value: deal?.contact_name || '', placeholder: 'Partner manager name' });
      const contactEmail = h('input', { type: 'email', value: deal?.contact_email || '', placeholder: 'partnerships@brand.com' });
      const stageSel = h('select', {}, dealsData.stages.map((st) => h('option', { value: st, selected: deal?.stage === st }, STAGE_LABELS[st] || st)));
      const rateIn = h('input', { type: 'number', min: 0, step: 50, value: deal?.rate ?? 2500, placeholder: '2500' });
      const currIn = h('select', {}, ['USD', 'EUR', 'GBP', 'CAD'].map((c) => h('option', { value: c, selected: (deal?.currency || 'USD') === c }, c)));
      const termsIn = h('input', { type: 'text', value: deal?.payment_terms || 'Net 30', placeholder: 'e.g. 50% upfront, 50% on publish' });
      const codeIn = h('input', { type: 'text', value: deal?.coupon_code || '', placeholder: 'TECHUNICORN20' });
      const urlIn = h('input', { type: 'url', value: deal?.tracking_url || '', placeholder: 'https://brand.com/?ref=techunicorn' });
      const pointsIn = h('textarea', { rows: 3, placeholder: 'Key product features to highlight, 14-day trial, etc.' }, deal?.key_talking_points || '');
      const doNotsIn = h('input', { type: 'text', value: deal?.do_nots || '', placeholder: 'Don’t compare directly to X, no financial claims' });

      const form = h('form', {
        onsubmit: (e) => {
          e.preventDefault();
          const payload = {
            brand_name: brandIn.value.trim(),
            contact_name: contactName.value.trim() || null,
            contact_email: contactEmail.value.trim() || null,
            stage: stageSel.value,
            rate: Number(rateIn.value) || 0,
            currency: currIn.value,
            payment_terms: termsIn.value.trim() || null,
            coupon_code: codeIn.value.trim() || null,
            tracking_url: urlIn.value.trim() || null,
            key_talking_points: pointsIn.value.trim() || null,
            do_nots: doNotsIn.value.trim() || null,
          };
          busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
            if (deal) await api('PATCH', `deals/${deal.id}`, payload);
            else await api('POST', 'deals', payload);
            toast(deal ? 'Deal updated.' : 'Deal created.');
            close(true);
            await reload();
          });
        },
      },
      field('Brand name', brandIn),
      h('div', { class: 'grid grid-2' }, field('Contact name', contactName), field('Contact email', contactEmail)),
      h('div', { class: 'grid grid-3' }, field('Stage', stageSel), field('Agreed fee', rateIn), field('Currency', currIn)),
      field('Payment terms', termsIn),
      h('div', { class: 'grid grid-2' }, field('Discount / Coupon code', codeIn), field('Tracking link', urlIn)),
      field('Key talking points', pointsIn),
      field('Do-nots & restrictions', doNotsIn),
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '16px' } },
        deal ? h('button', {
          type: 'button',
          class: 'btn ghost danger',
          onclick: async () => {
            if (await confirmDialog(`Delete deal with ${deal.brand_name}?`)) {
              await api('DELETE', `deals/${deal.id}`);
              toast('Deal deleted.');
              close(true);
              await reload();
            }
          },
        }, icon('trash', 14), 'Delete') : h('span', {}),
        h('div', { class: 'row' },
          h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Cancel'),
          h('button', { type: 'submit', class: 'btn primary' }, deal ? 'Save changes' : 'Create deal'))));

      return form;
    });
  }

  function rateCardDialog() {
    return modal('Creator Rate Card & Benchmarks', async (close) => {
      const box = h('div');
      box.append(h('p', { class: 'muted' }, 'Calculating your rates based on your actual viewership and tech creator CPM benchmarks…'));
      try {
        const rates = await api('GET', 'deals/rate-card');
        clear(box).append(
          h('p', { class: 'muted small' }, `Based on ${compact(rates.metrics.youtube_median_views)} median YouTube views and your social followers in the ${rates.metrics.niche} niche.`),
          h('div', { class: 'rate-table card', style: { margin: '14px 0' } },
            h('table', { class: 'data-table' },
              h('thead', {}, h('tr', {}, h('th', {}, 'Deliverable'), h('th', {}, 'Suggested Rate'), h('th', {}, 'Benchmark'))),
              h('tbody', {}, Object.values(rates.rates).map((r) => h('tr', {},
                h('td', {}, h('strong', {}, r.label), h('div', { class: 'muted small' }, r.deliverables)),
                h('td', {}, h('strong', { class: 'deal-rate' }, `$${r.rate.toLocaleString()}`)),
                h('td', { class: 'muted small' }, r.cpm ? `$${r.cpm} CPM` : `per ${r.unit}`)))))),
          h('h3', {}, 'Recommended Partnership Packages'),
          h('div', { class: 'grid grid-3' }, Object.values(rates.packages).map((pkg) => h('div', { class: 'card' },
            h('strong', {}, pkg.name),
            h('div', { class: 'deal-rate', style: { margin: '6px 0' } }, `$${pkg.price.toLocaleString()}`, h('span', { class: 'muted small', style: { textDecoration: 'line-through', marginLeft: '6px' } }, `$${pkg.regular_price}`)),
            h('ul', { class: 'mini-list' }, pkg.includes.map((it) => h('li', {}, `✓ ${it}`)))))));
      } catch (e) {
        clear(box).append(h('p', { class: 'error-text' }, e.message));
      }
      return box;
    });
  }

  function invoiceDialog(deal) {
    return modal(`Invoice: ${deal.brand_name}`, () => {
      const invNum = `INV-${new Date().getFullYear()}-${String(deal.id).padStart(4, '0')}`;
      const deliverables = Array.isArray(deal.deliverables) ? deal.deliverables : [];
      const rate = Number(deal.rate || 0);
      const currency = deal.currency || 'USD';
      const issueDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
      const dueDate = new Date(Date.now() + 30 * 86400000).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

      const slipText = `INVOICE: ${invNum}
Date: ${issueDate}
Due Date: ${dueDate}
Creator: Delia Lazarescu (@Tech.Unicorn)
Bill To: ${deal.brand_name} (Attn: ${deal.contact_name || 'Accounts Payable'})

Deliverables:
${deliverables.map((d) => `- ${typeof d === 'string' ? d : `${d.platform || ''} ${d.format || ''}`}`).join('\n') || '- Sponsored Deliverables per agreement'}

Total Due: ${currency} $${rate.toLocaleString()}
Status: ${STAGE_LABELS[deal.stage] || deal.stage}
Payment Terms: Net 30 days`;

      return h('div', { class: 'invoice-sheet' },
        h('div', { class: 'card', style: { padding: '24px', border: '1px solid var(--border)', background: 'var(--panel)' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid var(--border)', paddingBottom: '16px', marginBottom: '18px' } },
            h('div', {},
              h('h2', { style: { margin: 0, fontSize: '1.4rem' } }, 'INVOICE'),
              h('span', { class: 'muted small' }, invNum)),
            h('div', { style: { textAlign: 'right' } },
              pill(STAGE_PILLS[deal.stage] || 'new', STAGE_LABELS[deal.stage] || deal.stage),
              h('div', { class: 'small muted', style: { marginTop: '4px' } }, `Issued: ${issueDate}`),
              h('div', { class: 'small muted' }, `Due: ${dueDate}`))),
          h('div', { class: 'grid grid-2', style: { marginBottom: '20px', gap: '20px' } },
            h('div', {},
              h('strong', { class: 'small muted' }, 'FROM:'),
              h('div', { style: { fontWeight: '600', marginTop: '4px' } }, 'Delia Lazarescu'),
              h('div', { class: 'muted small' }, '@Tech.Unicorn Media'),
              h('div', { class: 'muted small' }, 'contact@techunicorn.dev')),
            h('div', {},
              h('strong', { class: 'small muted' }, 'BILL TO:'),
              h('div', { style: { fontWeight: '600', marginTop: '4px' } }, deal.brand_name),
              deal.contact_name ? h('div', { class: 'muted small' }, `Attn: ${deal.contact_name}`) : null,
              deal.contact_email ? h('div', { class: 'muted small' }, deal.contact_email) : null)),
          h('div', { class: 'table-wrap', style: { marginBottom: '20px' } },
            h('table', { style: { width: '100%' } },
              h('thead', {},
                h('tr', {},
                  h('th', {}, 'Description'),
                  h('th', { style: { textAlign: 'center', width: '80px' } }, 'Qty'),
                  h('th', { style: { textAlign: 'right', width: '120px' } }, 'Amount'))),
              h('tbody', {},
                deliverables.length ? deliverables.map((d, i) => h('tr', {},
                  h('td', {},
                    h('strong', {}, typeof d === 'string' ? d : `${d.platform?.toUpperCase() || ''} ${d.format || ''}`),
                    h('div', { class: 'muted small' }, `Dedicated sponsorship segment & cross-platform distribution (${deal.brand_name})`)),
                  h('td', { style: { textAlign: 'center' } }, '1'),
                  h('td', { style: { textAlign: 'right' } }, i === 0 ? `$${rate.toLocaleString()}` : 'Included')))
                  : h('tr', {},
                    h('td', {},
                      h('strong', {}, 'Sponsorship Partnership Deliverables'),
                      h('div', { class: 'muted small' }, `Dedicated product integration & social coverage for ${deal.brand_name}`)),
                    h('td', { style: { textAlign: 'center' } }, '1'),
                    h('td', { style: { textAlign: 'right' } }, `$${rate.toLocaleString()}`))))),
          h('div', { class: 'row', style: { justifyContent: 'flex-end', borderTop: '1px solid var(--border)', paddingTop: '12px', marginBottom: '20px' } },
            h('div', { style: { textAlign: 'right' } },
              h('div', { class: 'muted small' }, 'Total Amount Due:'),
              h('strong', { style: { fontSize: '1.5rem', color: 'var(--accent)' } }, `${currency} $${rate.toLocaleString()}`))),
          h('div', { style: { background: 'var(--panel-2)', padding: '12px 16px', borderRadius: '8px', fontSize: '0.85rem' } },
            h('strong', {}, 'Payment Instructions:'),
            h('div', { class: 'muted small', style: { marginTop: '4px' } }, 'Direct Bank Wire (ACH / Fedwire) or International SWIFT/IBAN. Payment terms: Net 30 days from publication date.'))),
        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '16px', gap: '8px' } },
          h('button', {
            type: 'button',
            class: 'btn',
            onclick: () => {
              navigator.clipboard.writeText(slipText);
              toast('Invoice text copied.');
            },
          }, icon('copy', 14), 'Copy slip'),
          h('button', { type: 'button', class: 'btn primary', onclick: () => window.print() }, icon('file-text', 14), 'Print / Save PDF')));
    });
  }

  function campaignReportDialog(deal) {
    return modal(`Post-Campaign Report: ${deal.brand_name}`, async () => {
      const box = h('div', {}, h('p', { class: 'muted' }, 'Compiling campaign metrics & audience impact…'));
      try {
        const res = await api('GET', `deals/${deal.id}/report`);
        const r = res.report;
        clear(box).append(
          h('div', { class: 'campaign-report' },
            h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', borderBottom: '1px solid var(--border)', paddingBottom: '12px' } },
              h('div', {},
                h('div', { class: 'row' },
                  h('strong', { style: { fontSize: '1.25rem' } }, `${r.creator_name} × ${r.brand_name}`),
                  pill('ok', 'Verified Delivery')),
                h('span', { class: 'muted small' }, `${r.report_id} · Campaign Window: ${r.campaign_dates}`)),
              h('div', { class: 'row' },
                h('button', {
                  type: 'button',
                  class: 'btn sm',
                  onclick: () => {
                    navigator.clipboard.writeText(`Campaign Wrap-up: ${r.creator_name} × ${r.brand_name}\n\n${r.executive_summary}\n\nMetrics:\n- Impressions: ${r.kpis.impressions.toLocaleString()}\n- Views: ${r.kpis.views.toLocaleString()}\n- CTR: ${r.kpis.ctr}%\n- Clicks: ${r.kpis.clicks.toLocaleString()}`);
                    toast('Executive summary copied for email.');
                  },
                }, icon('copy', 13), 'Copy email summary'),
                h('button', { type: 'button', class: 'btn sm primary', onclick: () => window.print() }, icon('file-text', 13), 'Print / PDF report'))),
            h('div', { class: 'grid grid-4', style: { marginBottom: '18px' } },
              h('div', { class: 'card stat-box tone-accent' },
                h('div', { class: 'num' }, compact(r.kpis.impressions)),
                h('div', { class: 'lbl' }, 'total impressions')),
              h('div', { class: 'card stat-box tone-good' },
                h('div', { class: 'num' }, compact(r.kpis.views)),
                h('div', { class: 'lbl' }, 'verified views (+18% vs median)')),
              h('div', { class: 'card stat-box tone-violet' },
                h('div', { class: 'num' }, `${r.kpis.ctr}%`),
                h('div', { class: 'lbl' }, `${compact(r.kpis.clicks)} attributed clicks`)),
              h('div', { class: 'card stat-box tone-warn' },
                h('div', { class: 'num' }, `${r.kpis.sentiment_score}%`),
                h('div', { class: 'lbl' }, 'positive sentiment'))),
            h('div', { class: 'grid grid-2', style: { gap: '16px', marginBottom: '18px' } },
              h('div', { class: 'card' },
                h('h3', { style: { margin: '0 0 10px 0', fontSize: '1rem' } }, 'Verified Deliverables'),
                h('div', { class: 'deliverables-list' }, r.deliverables.map((d) => h('div', { class: 'row', style: { justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid var(--border)' } },
                  h('div', {},
                    h('strong', { class: 'small' }, d.title),
                    h('div', { class: 'muted small' }, `Aired ${d.date}`)),
                  pill('ok', d.status))))),
              h('div', { class: 'card' },
                h('h3', { style: { margin: '0 0 10px 0', fontSize: '1rem' } }, 'Audience Seniority Breakdown'),
                h('div', {}, r.audience_demographics.seniority.map((s) => h('div', { style: { margin: '6px 0' } },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', fontSize: '0.85rem' } },
                    h('span', {}, s.label),
                    h('strong', {}, `${s.pct}%`)),
                  h('div', { style: { height: '6px', background: 'var(--panel-2)', borderRadius: '3px', overflow: 'hidden', marginTop: '2px' } },
                    h('div', { style: { width: `${s.pct}%`, height: '100%', background: 'var(--accent)', borderRadius: '3px' } }))))))),
            h('div', { class: 'card', style: { marginBottom: '18px' } },
              h('h3', { style: { margin: '0 0 8px 0', fontSize: '1rem' } }, 'Executive Campaign Summary'),
              h('p', { style: { fontSize: '0.92rem', lineHeight: '1.6', margin: 0 } }, r.executive_summary)),
            h('div', { class: 'card' },
              h('h3', { style: { margin: '0 0 10px 0', fontSize: '1rem' } }, 'Verified Viewer Feedback & Sentiment'),
              h('div', { class: 'comments-list' }, r.viewer_feedback.map((c) => h('div', { style: { background: 'var(--panel-2)', padding: '10px 14px', borderRadius: '8px', margin: '6px 0' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '4px' } },
                  h('strong', { class: 'small' }, c.author),
                  h('span', { class: 'muted small' }, `👍 ${c.likes}`)),
                h('div', { class: 'small', style: { fontStyle: 'italic', lineHeight: '1.4' } }, `“${c.text}”`)))))));
      } catch (err) {
        clear(box).append(h('p', { class: 'error-text' }, `Failed to generate report: ${err.message}`));
      }
      return box;
    });
  }

  function pitchDialog() {
    return modal('Draft Sponsor Pitch / Response Email', (close) => {
      const brandIn = h('input', { type: 'text', placeholder: 'e.g. Supabase', required: true });
      const prodIn = h('input', { type: 'text', placeholder: 'e.g. Supabase Edge Functions' });
      const inqIn = h('textarea', { rows: 3, placeholder: 'Paste their inbound email or inquiry message here…' });
      const delIn = h('input', { type: 'text', value: 'YouTube 60s Integration + LinkedIn Post + TikTok', placeholder: 'Deliverables' });
      const resultBox = h('div', { style: { marginTop: '16px' } });

      const form = h('form', {
        onsubmit: (e) => {
          e.preventDefault();
          busy(form.querySelector('[type=submit]'), 'Drafting pitch…', async () => {
            const r = await api('POST', 'deals/pitch', {
              brand_name: brandIn.value.trim(),
              product_name: prodIn.value.trim() || null,
              inquiry_text: inqIn.value.trim() || null,
              desired_deliverables: delIn.value.trim() || null,
            });
            clear(resultBox).append(
              h('div', { class: 'card', style: { background: 'var(--panel-2)' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
                  h('strong', {}, 'Subject: ', r.pitch.subject),
                  h('button', {
                    type: 'button',
                    class: 'btn sm ghost',
                    onclick: () => {
                      navigator.clipboard.writeText(`Subject: ${r.pitch.subject}\n\n${r.pitch.body}`);
                      toast('Email copied to clipboard.');
                    },
                  }, icon('copy', 13), 'Copy email')),
                h('pre', { style: { whiteSpace: 'pre-wrap', fontFamily: 'inherit', fontSize: '0.9rem', lineHeight: '1.5' } }, r.pitch.body)));
          });
        },
      },
      field('Brand / Company', brandIn),
      field('Product / Feature', prodIn),
      field('Their inquiry or brief context', inqIn),
      field('Proposed package / deliverables', delIn),
      h('div', { class: 'row', style: { marginTop: '14px', justifyContent: 'flex-end' } },
        h('button', { type: 'submit', class: 'btn primary' }, icon('sparkle', 14), 'Generate pitch email')),
      resultBox);

      return form;
    });
  }

  function scriptGeneratorDialog(deal) {
    return modal(`Sponsor Integration for ${deal.brand_name}`, (close) => {
      const topicIn = h('input', { type: 'text', required: true, placeholder: 'e.g. Building an AI agent with Node.js & LangChain' });
      const durationSel = h('select', {}, [30, 45, 60, 90].map((d) => h('option', { value: d, selected: d === 60 }, `${d} seconds`)));
      const resultBox = h('div', { style: { marginTop: '16px' } });

      const form = h('form', {
        onsubmit: (e) => {
          e.preventDefault();
          busy(form.querySelector('[type=submit]'), 'Drafting sponsor segment…', async () => {
            const r = await api('POST', `deals/${deal.id}/sponsor-script`, {
              video_topic: topicIn.value.trim(),
              duration_seconds: Number(durationSel.value),
            });
            const s = r.segment;
            clear(resultBox).append(
              h('div', { class: 'card' },
                h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
                  h('strong', {}, 'Organic Sponsor Integration'),
                  h('button', {
                    type: 'button',
                    class: 'btn sm ghost',
                    onclick: () => {
                      navigator.clipboard.writeText(`${s.transition_in}\n\n${s.script_body}\n\n${s.transition_out}`);
                      toast('Segment copied.');
                    },
                  }, icon('copy', 13), 'Copy to script')),
                h('p', { class: 'muted small' }, h('strong', {}, 'Transition In: '), s.transition_in),
                h('div', { class: 'script-preview-box', style: { background: 'var(--panel-2)', padding: '12px', borderRadius: '8px', margin: '8px 0', whiteSpace: 'pre-wrap', lineHeight: '1.5' } }, s.script_body),
                h('p', { class: 'muted small' }, h('strong', {}, 'Bridge Out: '), s.transition_out)));
          });
        },
      },
      field('What is your video about?', topicIn),
      field('Target duration', durationSel),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '14px' } },
        h('button', { type: 'submit', class: 'btn primary' }, icon('sparkle', 14), 'Write integration')),
      resultBox);

      return form;
    });
  }

  view.append(
    pageHead('Brand Deals & Sponsorships', 'Track inbound brand deals, calculate your rates, draft pitches, and generate natural sponsor reads.',
      link('/mediakit', { class: 'btn' }, icon('file-text', 15), 'Media kit'),
      h('button', { class: 'btn', onclick: rateCardDialog }, icon('target', 15), 'Rate card'),
      h('button', { class: 'btn', onclick: pitchDialog }, icon('sparkle', 15), 'Draft pitch'),
      h('button', { class: 'btn', onclick: () => prospectsDialog(() => reload()) }, icon('trend', 15), 'Sponsor prospects'),
      h('button', { class: 'btn', onclick: () => outboundPitchDialog({ onSaved: () => reload() }) }, icon('megaphone', 15), 'Outbound pitch'),
      h('button', { class: 'btn primary', onclick: () => editDealDialog() }, icon('plus', 15), 'New deal')),
    followBox,
    statsRow,
    tabBar,
    dealsContainer,
  );

  draw();

  // ?follow-up=<id> (dashboard next step) or ?proposal=<id> (the Copilot) opens that dialog.
  const params = new URLSearchParams(location.search);
  const openId = Number(params.get('follow-up') || params.get('proposal'));
  const target = openId && dealsData.deals.find((d) => d.id === openId);
  if (target) {
    history.replaceState({}, '', '/deals');
    (params.has('proposal') ? proposalDialog : followUpDialog)(target, reload);
  }
}

