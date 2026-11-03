import { h, api, toast, clear } from '../core.js';
import { icon } from '../icons.js';

export async function renderCommunityHub(view) {
  clear(view);

  const container = h('div', { class: 'community-hub-page' });
  container.append(h('div', { class: 'community-loading muted' }, icon('sparkle', 16), ' Loading Community Hub...'));
  view.append(container);

  try {
    const data = await api('GET', 'portal/community/hub');
    clear(container);

    let activeTab = 'kits';

    // 1. Hero Header
    const hero = h('div', { class: 'community-hero' },
      h('div', { class: 'community-hero-content' },
        h('div', { class: 'community-hero-badge' }, icon('sparkle', 13), '519K+ Developer Community Hub'),
        h('h1', { class: 'community-hero-title' }, 'Engineering Resources, Starter Kits & AMA'),
        h('p', { class: 'community-hero-sub muted' },
          `Welcome, ${data.member?.name || 'Engineer'}! Access Delia’s battle-tested production templates, submit questions for upcoming YouTube videos, and redeem exclusive developer perks.`)),
      h('div', { class: 'community-hero-card' },
        h('span', { class: 'community-hero-card-role' }, data.member?.role || 'Community Member'),
        h('strong', {}, data.member?.name || 'Follower'),
        h('span', { class: 'muted small' }, `Member since ${data.member?.joined || 'Recently'}`)));

    // 2. Tab Navigation
    const tabs = [
      { id: 'kits', label: 'Starter Kits', icon: 'code', count: data.kits?.length || 3 },
      { id: 'blueprints', label: 'Architecture Scaffolder', icon: 'sliders', count: data.blueprints?.length || 4 },
      { id: 'showcase', label: 'Project Showcase', icon: 'sparkle', count: data.showcase?.length || 3 },
      { id: 'dispatches', label: 'Engineering Dispatches', icon: 'doc', count: data.dispatches?.length || 3 },
      { id: 'ama', label: 'Ask Delia / AMA', icon: 'chat', count: data.ama?.length || 2 },
      { id: 'perks', label: 'Partner Perks', icon: 'tag', count: data.perks?.length || 3 },
    ];

    const tabContent = h('div', { class: 'community-tab-content' });

    const tabNav = h('div', { class: 'community-tabs', role: 'tablist' },
      tabs.map((t) => h('button', {
        type: 'button',
        role: 'tab',
        class: `community-tab-btn${t.id === activeTab ? ' is-active' : ''}`,
        'aria-selected': String(t.id === activeTab),
        onclick: (e) => {
          activeTab = t.id;
          tabNav.querySelectorAll('.community-tab-btn').forEach((b) => b.classList.remove('is-active'));
          e.currentTarget.classList.add('is-active');
          renderTab();
        },
      },
        icon(t.icon, 14),
        h('span', {}, t.label),
        h('span', { class: 'community-tab-badge' }, String(t.count)))));

    function renderTab() {
      clear(tabContent);

      if (activeTab === 'kits') {
        // Tab 1: Starter Kits
        const grid = h('div', { class: 'community-kits-grid' },
          (data.kits || []).map((kit) => h('div', { class: 'community-kit-card' },
            h('div', { class: 'community-kit-head' },
              h('span', { class: 'community-kit-cat' }, kit.category),
              h('span', { class: 'community-kit-badge' }, kit.badge)),
            h('h3', { class: 'community-kit-title' }, kit.title),
            h('p', { class: 'community-kit-desc muted small' }, kit.desc),
            h('ul', { class: 'community-kit-feats' },
              (kit.features || []).map((f) => h('li', {}, icon('check', 11), h('span', {}, f)))),
            h('div', { class: 'community-kit-foot' },
              h('button', {
                type: 'button',
                class: 'btn sm primary',
                onclick: () => {
                  navigator.clipboard?.writeText(kit.clone).then(() => toast('Git clone command copied to clipboard!')).catch(() => {});
                },
              }, icon('copy', 13), ' Copy Clone URL'),
              h('a', {
                href: kit.github,
                target: '_blank',
                rel: 'noopener',
                class: 'btn sm ghost',
              }, icon('external', 13), ' View Repo')))));

        tabContent.append(
          h('div', { class: 'community-section-intro' },
            h('h2', {}, 'Production-Grade Architecture Templates'),
            h('p', { class: 'muted small' }, 'Free MIT-licensed repositories authored and maintained by Delia Lazarescu. Clone and deploy in seconds.')),
          grid);

      } else if (activeTab === 'blueprints') {
        // Tab 2: Architecture Blueprints & Interactive Scaffolder
        const blueprintsGrid = h('div', { class: 'community-kits-grid' },
          (data.blueprints || []).map((bp) => h('div', { class: 'community-kit-card blueprint-card' },
            h('div', { class: 'community-kit-head' },
              h('span', { class: 'community-kit-cat' }, bp.category),
              h('span', { class: 'community-kit-badge' }, bp.cost_tier)),
            h('h3', { class: 'community-kit-title' }, bp.title),
            h('p', { class: 'community-kit-desc muted small' }, bp.desc),
            h('div', { class: 'blueprint-meta' },
              h('span', {}, icon('clock', 12), ` P95 Latency: ${bp.latency_p95}`),
              h('span', {}, icon('code', 12), ` ${bp.recommended_stack}`)),
            h('ul', { class: 'community-kit-feats' },
              (bp.components || []).map((c) => h('li', {}, icon('check', 11), h('span', {}, c)))),
            h('div', { class: 'community-kit-foot' },
              h('button', {
                type: 'button',
                class: 'btn sm primary block',
                onclick: () => openScaffoldModal(bp),
              }, icon('sliders', 13), ' Configure & Scaffold Blueprint')))));

        tabContent.append(
          h('div', { class: 'community-section-intro' },
            h('h2', {}, 'Interactive Architecture Blueprints & Scaffolding Engine'),
            h('p', { class: 'muted small' }, 'Select an architecture, tailor database and caching parameters, and generate ready-to-run Docker Compose boilerplates.')),
          blueprintsGrid);

      } else if (activeTab === 'showcase') {
        // Tab 3: Member Project Showcase & Upvotes
        const showcaseGrid = h('div', { class: 'community-kits-grid' },
          (data.showcase || []).map((proj) => {
            const upvoteBtn = h('button', {
              type: 'button',
              class: 'btn sm ghost',
              onclick: async (e) => {
                e.currentTarget.disabled = true;
                try {
                  await api('POST', `portal/community/showcase/${proj.id}/upvote`);
                  proj.upvotes = (proj.upvotes || 0) + 1;
                  e.currentTarget.innerHTML = `▲ ${proj.upvotes}`;
                  toast('Project upvoted!');
                } catch {}
              },
            }, `▲ ${proj.upvotes || 1}`);

            return h('div', { class: `community-kit-card ${proj.is_featured ? 'is-featured' : ''}` },
              h('div', { class: 'community-kit-head' },
                h('span', { class: 'community-kit-cat' }, `By ${proj.author}`),
                proj.is_featured ? h('span', { class: 'tag ok' }, icon('sparkle', 10), ' Featured by Tech Unicorn') : null),
              h('h3', { class: 'community-kit-title' }, proj.title),
              h('p', { class: 'community-kit-desc muted small' }, proj.desc),
              h('div', { class: 'community-dispatch-tags', style: { marginBottom: '14px' } },
                (proj.tags || []).map((t) => h('span', { class: 'tag' }, `#${t}`))),
              h('div', { class: 'community-kit-foot' },
                upvoteBtn,
                h('div', { style: { display: 'flex', gap: '6px' } },
                  proj.demo_url ? h('a', { href: proj.demo_url, target: '_blank', rel: 'noopener', class: 'btn sm primary' }, icon('external', 12), ' Demo') : null,
                  proj.repo_url ? h('a', { href: proj.repo_url, target: '_blank', rel: 'noopener', class: 'btn sm ghost' }, icon('code', 12), ' Code') : null)));
          }));

        const submitBanner = h('div', { class: 'card callout community-showcase-banner' },
          h('div', {},
            h('h3', {}, 'Built something with Delia’s kits or blueprints?'),
            h('p', { class: 'muted small' }, 'Share your project with 519K+ developers and get featured on upcoming Tech Unicorn live code reviews.')),
          h('button', {
            type: 'button', class: 'btn primary', onclick: () => openShowcaseModal(),
          }, icon('plus', 14), ' Submit Your Project'));

        tabContent.append(
          h('div', { class: 'community-section-intro' },
            h('h2', {}, 'Community Project Showcase'),
            h('p', { class: 'muted small' }, 'Real-world production tools and experiments built by community engineers.')),
          submitBanner,
          showcaseGrid);

      } else if (activeTab === 'dispatches') {
        // Tab 2: Dispatches
        const list = h('div', { class: 'community-dispatches-list' },
          (data.dispatches || []).map((d) => h('article', { class: 'community-dispatch-card' },
            h('div', { class: 'community-dispatch-head' },
              h('span', { class: 'community-dispatch-date muted small' }, `${d.date} · ${d.read_time}`),
              h('div', { class: 'community-dispatch-tags' },
                (d.tags || []).map((t) => h('span', { class: 'tag' }, `#${t}`)))),
            h('h3', { class: 'community-dispatch-title' }, d.title),
            h('p', { class: 'community-dispatch-summary muted' }, d.summary))));

        tabContent.append(
          h('div', { class: 'community-section-intro' },
            h('h2', {}, 'Behind-The-Scenes Engineering Notes'),
            h('p', { class: 'muted small' }, 'Architecture writeups, performance case studies, and prompt design notes directly from Delia’s workbench.')),
          list);

      } else if (activeTab === 'ama') {
        // Tab 3: AMA Question Board
        const questionInput = h('textarea', {
          class: 'input',
          rows: 3,
          placeholder: 'Ask Delia anything about system architecture, AI tools, career transitions, or building in public...',
        });
        const askBtn = h('button', { type: 'button', class: 'btn primary' }, icon('sparkle', 14), ' Submit Question');

        askBtn.onclick = async () => {
          const qText = questionInput.value.trim();
          if (qText.length < 10) {
            toast('Please enter a question with at least 10 characters.', 'err');
            return;
          }
          askBtn.disabled = true;
          try {
            const res = await api('POST', 'portal/community/ask', { question: qText });
            toast(res.message);
            questionInput.value = '';
            data.ama = [res.item, ...(data.ama || [])];
            renderTab();
          } catch (err) {
            toast(err.message || 'Could not submit question.', 'err');
          } finally {
            askBtn.disabled = false;
          }
        };

        const askBox = h('div', { class: 'community-ask-box' },
          h('h3', {}, 'Have a question for an upcoming YouTube video or livestream?'),
          h('p', { class: 'muted small' }, 'Delia answers the highest-upvoted questions weekly in her technical Q&A videos.'),
          questionInput,
          h('div', { class: 'row-end', style: { marginTop: '10px' } }, askBtn));

        const amaList = h('div', { class: 'community-ama-list' },
          (data.ama || []).map((item) => {
            const upvoteBtn = h('button', {
              type: 'button',
              class: 'community-upvote-btn',
              onclick: async (e) => {
                e.currentTarget.disabled = true;
                try {
                  await api('POST', 'portal/community/upvote', { id: item.id });
                  item.upvotes = (item.upvotes || 0) + 1;
                  upvoteCount.textContent = String(item.upvotes);
                  toast('Upvoted!');
                } catch {
                  // ignore
                }
              },
            }, icon('chevron-up', 14));
            const upvoteCount = h('span', { class: 'community-upvote-count' }, String(item.upvotes || 1));

            return h('div', { class: 'community-ama-card' },
              h('div', { class: 'community-ama-vote' }, upvoteBtn, upvoteCount),
              h('div', { class: 'community-ama-body' },
                h('div', { class: 'community-ama-head' },
                  h('strong', {}, item.author || 'Engineer'),
                  item.answered_at ? h('span', { class: 'tag ok' }, icon('check', 10), ' Answered by Delia') : null),
                h('p', { class: 'community-ama-q' }, item.question),
                item.answer ? h('blockquote', { class: 'community-ama-answer' },
                  h('div', { class: 'community-ama-answer-head' }, icon('sparkle', 12), h('strong', {}, 'Delia Lazarescu')),
                  h('p', {}, item.answer)) : null));
          }));

        tabContent.append(askBox, amaList);

      } else if (activeTab === 'perks') {
        // Tab 4: Perks
        const perksGrid = h('div', { class: 'community-perks-grid' },
          (data.perks || []).map((p) => h('div', { class: 'community-perk-card' },
            h('div', { class: 'community-perk-head' },
              h('strong', {}, p.brand),
              h('span', { class: 'community-perk-badge' }, p.badge)),
            h('h3', { class: 'community-perk-offer' }, p.offer),
            h('div', { class: 'community-perk-code-box' },
              h('code', {}, p.code),
              h('button', {
                type: 'button',
                class: 'btn ghost sm',
                onclick: () => {
                  navigator.clipboard?.writeText(p.code).then(() => toast(`Promo code "${p.code}" copied!`)).catch(() => {});
                },
              }, icon('copy', 12), ' Copy Code')),
            h('a', {
              href: p.url,
              target: '_blank',
              rel: 'noopener',
              class: 'btn primary block sm',
              style: { marginTop: '12px' },
            }, 'Redeem Offer', icon('external', 12)))));

        tabContent.append(
          h('div', { class: 'community-section-intro' },
            h('h2', {}, 'Exclusive Partner Tools & Cloud Credits'),
            h('p', { class: 'muted small' }, 'Hand-picked infrastructure and AI tooling credits for members of the Tech Unicorn community.')),
          perksGrid);
      }
    }

    function openScaffoldModal(bp) {
      const nameInput = h('input', { type: 'text', class: 'input', value: bp.id.replace('bp-', '') + '-app' });
      const dbSelect = h('select', { class: 'input' },
        h('option', { value: 'postgres', selected: true }, 'PostgreSQL + pgvector (Recommended)'),
        h('option', { value: 'sqlite' }, 'SQLite (Zero-config local file)'));
      const cacheSelect = h('select', { class: 'input' },
        h('option', { value: 'redis', selected: true }, 'Redis (Persistent / Pub-Sub)'),
        h('option', { value: 'memory' }, 'In-Memory Cache'));

      const outputArea = h('pre', { class: 'auth-install-output', style: { maxHeight: '220px', marginTop: '14px' }, hidden: true });
      const copyBtn = h('button', { type: 'button', class: 'btn secondary sm', hidden: true }, icon('copy', 12), ' Copy Docker Compose');

      const modal = h('div', { class: 'modal-overlay' },
        h('div', { class: 'modal-card' },
          h('div', { class: 'modal-head' },
            h('h3', {}, `Scaffold: ${bp.title}`),
            h('button', { type: 'button', class: 'btn ghost sm', onclick: () => modal.remove() }, icon('x', 14))),
          h('p', { class: 'muted small' }, bp.desc),
          h('form', {
            onsubmit: async (e) => {
              e.preventDefault();
              try {
                const res = await api('POST', 'portal/community/blueprints/scaffold', {
                  blueprint_id: bp.id,
                  project_name: nameInput.value,
                  database: dbSelect.value,
                  cache: cacheSelect.value,
                });
                outputArea.textContent = res.files['docker-compose.yml'];
                outputArea.hidden = false;
                copyBtn.hidden = false;
                copyBtn.onclick = () => {
                  navigator.clipboard?.writeText(res.files['docker-compose.yml']).then(() => toast('docker-compose.yml copied!')).catch(() => {});
                };
                toast('Scaffolding manifest generated!');
              } catch (err) {
                toast(err.message, 'err');
              }
            },
          },
            h('label', { class: 'field' }, h('span', { class: 'label' }, 'Project Name'), nameInput),
            h('div', { class: 'installer-form-row' },
              h('label', { class: 'field' }, h('span', { class: 'label' }, 'Database Engine'), dbSelect),
              h('label', { class: 'field' }, h('span', { class: 'label' }, 'Cache Layer'), cacheSelect)),
            h('div', { class: 'modal-actions', style: { marginTop: '16px' } },
              copyBtn,
              h('button', { type: 'button', class: 'btn secondary', onclick: () => modal.remove() }, 'Close'),
              h('button', { type: 'submit', class: 'btn primary' }, icon('sparkle', 14), ' Generate Scaffold')),
            outputArea)));

      document.body.append(modal);
    }

    function openShowcaseModal() {
      const titleInput = h('input', { type: 'text', class: 'input', required: true, placeholder: 'e.g. Autonomous Terminal Agent' });
      const descInput = h('textarea', { class: 'input', required: true, rows: 2, placeholder: 'What did you build and how does it work?' });
      const repoInput = h('input', { type: 'url', class: 'input', required: true, placeholder: 'https://github.com/username/project' });
      const demoInput = h('input', { type: 'url', class: 'input', placeholder: 'https://project-demo.com (optional)' });
      const tagsInput = h('input', { type: 'text', class: 'input', placeholder: 'AI, FastApi, Vite' });

      const modal = h('div', { class: 'modal-overlay' },
        h('div', { class: 'modal-card' },
          h('div', { class: 'modal-head' },
            h('h3', {}, 'Submit Your Project to Community Showcase'),
            h('button', { type: 'button', class: 'btn ghost sm', onclick: () => modal.remove() }, icon('x', 14))),
          h('form', {
            onsubmit: async (e) => {
              e.preventDefault();
              try {
                const res = await api('POST', 'portal/community/showcase', {
                  title: titleInput.value,
                  desc: descInput.value,
                  repo_url: repoInput.value,
                  demo_url: demoInput.value || null,
                  tags: tagsInput.value,
                });
                modal.remove();
                toast('Your project has been submitted to the showcase!');
                data.showcase = [res.item, ...(data.showcase || [])];
                renderTab();
              } catch (err) {
                toast(err.message, 'err');
              }
            },
          },
            h('label', { class: 'field' }, h('span', { class: 'label' }, 'Project Title'), titleInput),
            h('label', { class: 'field' }, h('span', { class: 'label' }, 'Description'), descInput),
            h('div', { class: 'installer-form-row' },
              h('label', { class: 'field' }, h('span', { class: 'label' }, 'GitHub Repository URL'), repoInput),
              h('label', { class: 'field' }, h('span', { class: 'label' }, 'Live Demo URL'), demoInput)),
            h('label', { class: 'field' }, h('span', { class: 'label' }, 'Tags (comma separated)'), tagsInput),
            h('div', { class: 'modal-actions', style: { marginTop: '16px' } },
              h('button', { type: 'button', class: 'btn secondary', onclick: () => modal.remove() }, 'Cancel'),
              h('button', { type: 'submit', class: 'btn primary' }, icon('sparkle', 14), ' Submit Project')))));

      document.body.append(modal);
    }

    renderTab();

    container.append(hero, tabNav, tabContent);
  } catch (err) {
    clear(container);
    container.append(h('div', { class: 'card error' },
      h('h2', {}, 'Could not load community hub'),
      h('p', { class: 'muted' }, err.message || 'Please try signing in again.')));
  }
}

