// Website: everything about her public landing page in one place. The funnel (is it working?),
// the editors for each section (hero, marquee, partners, pricing, about, speaking, reach, feed),
// booking hours for real intro-call slots, and tracked links. The dashboard keeps only today.
import { h, api, pageHead, link } from '../core.js';
import { icon } from '../icons.js';
import { websiteCard, reachCard } from '../website-feed.js';
import { speakingCard } from '../speaking.js';
import { aboutCard } from '../about-editor.js';
import { pricingCard } from '../pricing-editor.js';
import { partnersCard } from '../partners-editor.js';
import { marqueeCard } from '../marquee-editor.js';
import { siteHeroCard } from '../hero-editor.js';
import { funnelCard, attributionCard, trackedLinkCard, bookingHoursCard } from '../revenue.js';
import { journeyCard } from './dashboard.js';
import { testimonialsCard, emailListCard } from '../proof-editor.js';
import { faqCard } from '../landing-faq.js';

export async function renderWebsite(view) {
  const d = await api('GET', 'website');
  const redraw = () => { view.replaceChildren(); renderWebsite(view); };
  const section = (title, desc) => h('div', { class: 'section-head' }, h('div', {}, h('h2', { class: 'section-title' }, title), desc ? h('p', { class: 'muted small', style: { margin: 0 } }, desc) : null));

  view.append(
    pageHead('Website', 'Your public landing page: how it converts, what it says, and when people can book you.',
      link('/landing', { class: 'btn' }, icon('eye', 15), 'Preview'),
      h('a', { class: 'btn primary', href: d.site_url, target: '_blank', rel: 'noopener' }, icon('external', 15), 'Open live page')),
    h('div', { class: 'grid grid-2' }, funnelCard(), attributionCard()),
    journeyCard(d.journey),
    h('div', { class: 'grid grid-2', style: { marginTop: '18px' } }, bookingHoursCard(), trackedLinkCard()),
    h('div', { class: 'grid grid-2', style: { marginTop: '18px' } }, testimonialsCard(), emailListCard()),
    h('div', { style: { marginTop: '18px' } }, faqCard()),
    section('Page sections', 'Each card edits one section of the page, top to bottom.'),
    [
      siteHeroCard(d.hero, redraw),
      marqueeCard(d.marquee, redraw),
      partnersCard(d.partners, redraw),
      pricingCard(d.pricing, redraw),
      aboutCard(d.about, redraw),
      speakingCard(d.speaking, redraw),
      reachCard(d.reach, redraw),
      websiteCard(d.website, redraw),
    ].filter(Boolean));
}
