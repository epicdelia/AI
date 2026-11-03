// User-linked API Keys Vault & Credit System modal
import { h, api, toast, modal, pill, clear } from './core.js';
import { icon } from './icons.js';

export async function openUserKeysModal(onSaved) {
  return modal('My AI Keys & Multi-Model Engine Vault 🔑', (close) => {
    const container = h('div', { class: 'user-keys-modal' });

    async function load() {
      clear(container);
      container.append(h('p', { class: 'muted small' }, 'Loading your AI key vault...'));

      try {
        const data = await api('GET', 'user/keys');
        render(data);
      } catch (err) {
        clear(container);
        container.append(h('p', { class: 'bad' }, `Failed to load keys: ${err.message}`));
      }
    }

    function render(data) {
      clear(container);

      const headerBox = h('div', {
        class: 'row',
        style: {
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '12px 16px',
          background: 'var(--bg-subtle, rgba(255,255,255,0.05))',
          borderRadius: '10px',
          marginBottom: '16px',
          flexWrap: 'wrap',
          gap: '10px'
        }
      },
        h('div', {},
          h('strong', {}, 'Studio AI Engine: '),
          h('span', { class: 'pill tone-accent small' }, 'Google Gemini 4 Argon (Default)'),
          h('div', { class: 'muted small', style: { marginTop: '4px' } },
            'Keys are stored encrypted in the database linked directly to your user account (BYOK).')),
        h('div', { class: 'row', style: { alignItems: 'center', gap: '8px' } },
          h('span', { class: 'muted small' }, 'Credit Balance:'),
          h('span', { class: 'pill tone-good' }, `${Number(data.credit_balance || 0).toFixed(2)} Credits`))
      );

      const list = h('div', { class: 'user-keys-grid', style: { display: 'flex', flexDirection: 'column', gap: '14px' } });

      data.providers.forEach((p) => {
        const inputKey = h('input', {
          type: 'password',
          placeholder: p.configured ? p.masked_key : `Paste ${p.label} key (${p.placeholder})`,
          style: { flex: '1', minWidth: '180px' },
          autocomplete: 'off'
        });

        const statusPill = p.configured
          ? h('span', { class: 'pill tone-good small' }, icon('check', 12), ' BYOK Active')
          : h('span', { class: 'pill small' }, 'Using Studio Credits');

        const testBtn = h('button', {
          class: 'btn sm ghost',
          onclick: async () => {
            testBtn.disabled = true;
            try {
              const res = await api('POST', 'user/keys/test', {
                provider: p.provider,
                api_key: inputKey.value.trim() || undefined
              });
              toast(res.message || 'Key verified successfully!', 'ok');
            } catch (e) {
              toast(e.message || 'Verification failed', 'err');
            } finally {
              testBtn.disabled = false;
            }
          }
        }, icon('bolt', 13), ' Test');

        const saveBtn = h('button', {
          class: 'btn sm primary',
          onclick: async () => {
            const val = inputKey.value.trim();
            if (!val) {
              toast('Please enter a key first.', 'warn');
              return;
            }
            saveBtn.disabled = true;
            try {
              await api('POST', 'user/keys', {
                provider: p.provider,
                api_key: val
              });
              toast(`Saved key for ${p.label}.`, 'ok');
              await load();
              if (onSaved) onSaved();
            } catch (e) {
              toast(e.message || 'Failed to save key', 'err');
            } finally {
              saveBtn.disabled = false;
            }
          }
        }, icon('check', 13), ' Save');

        const clearBtn = p.configured ? h('button', {
          class: 'btn sm ghost bad',
          title: 'Remove this key',
          onclick: async () => {
            if (!confirm(`Remove your saved ${p.label} key?`)) return;
            try {
              await api('DELETE', `user/keys/${p.provider}`);
              toast(`Removed key for ${p.label}.`);
              await load();
              if (onSaved) onSaved();
            } catch (e) {
              toast(e.message || 'Failed to remove', 'err');
            }
          }
        }, icon('trash', 13)) : null;

        const card = h('div', {
          class: 'card',
          style: {
            padding: '12px 14px',
            background: 'var(--card-bg, #1a1622)',
            border: p.configured ? '1px solid var(--border-accent, #9333ea55)' : '1px solid var(--border-subtle, rgba(255,255,255,0.08))'
          }
        },
          h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
            h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
              h('strong', {}, p.label),
              statusPill),
            h('a', { href: p.doc_url, target: '_blank', rel: 'noopener', class: 'muted small hint' }, 'Get API Key ↗')),
          h('p', { class: 'muted small', style: { margin: '0 0 10px' } }, `Powers: ${p.models}`),
          h('div', { class: 'row', style: { gap: '8px', alignItems: 'center', flexWrap: 'wrap' } },
            inputKey,
            saveBtn,
            p.configured ? testBtn : null,
            clearBtn));

        list.append(card);
      });

      container.append(
        headerBox,
        list,
        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '16px' } },
          h('button', { class: 'btn', onclick: () => close() }, 'Done'))
      );
    }

    load();
    return container;
  }, { wide: true });
}

