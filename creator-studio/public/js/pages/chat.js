// Producer chat: brainstorm with an assistant that already knows her channel, ideas and week.
import { h, api, apiStream, liveMarkdown, toast, clear, mdBlock, copyText, confirmDialog, navigate, timeAgo, modal } from '../core.js';
import { icon, brandMark } from '../icons.js';
import { outputPanel, exportMenu } from '../components.js';

const STARTERS = [
  'Give me 5 title ideas for the video I picked this week.',
  'My last video underperformed. Help me figure out why.',
  'Pressure-test this idea: ',
  'Turn my top breakout into three angles only I could make.',
  'I have zero energy this week. What’s the smallest video I can still ship?',
];

export async function renderChat(view, id) {
  const list = (await api('GET', 'chats')).conversations;
  let current = null;
  let messages = [];
  let sending = false;

  const sidebar = h('aside', { class: 'chat-list' });
  const thread = h('div', { class: 'chat-thread', 'aria-live': 'polite' });
  const input = h('textarea', { rows: 2, placeholder: 'Ask your producer… (Enter to send, Shift+Enter for a new line)' });
  const sendBtn = h('button', { class: 'btn primary', onclick: () => send() }, icon('arrow', 16), 'Send');
  const webToggle = h('input', { type: 'checkbox' });
  const webLabel = h('label', { class: 'inline-check web-toggle', title: 'Search the web for this message (needs a Serper or Perplexity key in Settings)' }, webToggle, icon('search', 14), 'Web');

  function drawSidebar() {
    clear(sidebar).append(
      h('button', { class: 'btn primary block', onclick: () => navigate('/chat') }, icon('plus', 16), 'New chat'),
      h('ul', {}, list.map((c) => h('li', { class: current?.id === c.id ? 'active' : '' },
        h('button', { class: 'chat-link', onclick: () => navigate(`/chat/${c.id}`) },
          c.pinned ? icon('pin', 12) : '', h('span', { class: 'clamp-1' }, c.title), h('span', { class: 'muted small' }, timeAgo(c.updated_at)))))));
  }

  function bubble(m) {
    const isUser = m.role === 'user';
    return h('div', { class: `msg ${isUser ? 'user' : 'assistant'}` },
      h('div', { class: 'msg-avatar' }, isUser ? icon('user', 16) : brandMark(16)),
      h('div', { class: 'msg-body' },
        isUser ? h('div', { class: 'pre' }, m.content) : mdBlock(m.content),
        !isUser && m.id ? h('div', { class: 'msg-actions' },
          h('button', { class: 'btn ghost sm', onclick: () => copyText(m.content) }, icon('copy', 14), 'Copy'),
          h('button', {
            class: 'btn ghost sm',
            onclick: () => modal('Save to Library', () => outputPanel(m.content, { title: current?.title, tool: 'chat' }), { wide: true }),
          }, icon('heart', 14), 'Save')) : null));
  }

  function drawThread() {
    clear(thread);
    if (!messages.length) {
      thread.append(h('div', { class: 'chat-empty' },
        h('div', { class: 'empty-icon' }, icon('chat', 30)),
        h('h2', {}, 'Your producer is here'),
        h('p', { class: 'muted' }, 'It knows your niche, voice, this week’s goal, your picked ideas and top breakouts.'),
        h('div', { class: 'starters' }, STARTERS.map((s) => h('button', {
          class: 'starter', onclick: () => { input.value = s; input.focus(); if (!s.endsWith(': ')) send(); },
        }, s)))));
      return;
    }
    thread.append(messages.map(bubble));
    if (sending) thread.append(h('div', { class: 'msg assistant' }, h('div', { class: 'msg-avatar' }, brandMark(16)), h('div', { class: 'msg-body' }, h('span', { class: 'typing' }, h('i'), h('i'), h('i')))));
    thread.scrollTop = thread.scrollHeight;
  }

  async function send() {
    const content = input.value.trim();
    if (!content || sending) return;
    sending = true;
    sendBtn.disabled = true;
    input.value = '';
    try {
      if (!current) {
        current = (await api('POST', 'chats')).conversation;
        list.unshift(current);
        history.replaceState({}, '', `/chat/${current.id}`);
      }
      messages.push({ role: 'user', content });
      drawThread();
      const live = liveMarkdown('Thinking…');
      const liveBubble = h('div', { class: 'msg assistant' }, h('div', { class: 'msg-avatar' }, brandMark(16)), h('div', { class: 'msg-body' }, live.el));
      thread.querySelector('.msg.assistant:last-child .typing')?.closest('.msg')?.remove();
      thread.append(liveBubble);
      thread.scrollTop = thread.scrollHeight;
      const r = await apiStream(`chats/${current.id}/messages`, { content, web: webToggle.checked }, {
        onStatus: (st) => { if (st.step === 'searching') live.status('Searching the web…'); },
        onDelta: (t) => {
          const atBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
          live.append(t);
          if (atBottom) requestAnimationFrame(() => { thread.scrollTop = thread.scrollHeight; });
        },
      });
      messages[messages.length - 1] = r.user_message;
      messages.push(r.message);
      Object.assign(current, r.conversation);
      const idx = list.findIndex((c) => c.id === current.id);
      if (idx > 0) { list.splice(idx, 1); list.unshift(current); }
    } catch (err) {
      toast(err.message, 'err');
      if (err.data?.conversation) Object.assign(current, err.data.conversation);
      // A mid-stream failure means the question was saved; only un-send it if it never reached the server.
      if (!err.data?.user_message && !err.data?.streamed) { messages.pop(); input.value = content; }
    } finally {
      sending = false;
      sendBtn.disabled = false;
      drawThread();
      drawSidebar();
      drawHeader();
      input.focus();
    }
  }

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
  });

  const header = h('div', { class: 'chat-header' });
  function drawHeader() {
    clear(header);
    header.append(h('h1', {}, current ? current.title : 'Producer chat'));
    if (!current) return;
    header.append(h('div', { class: 'row' },
      exportMenu(`chats/${current.id}`, {
        getPrint: () => ({ title: current.title, markdown: messages.map((m) => `**${m.role === 'user' ? 'You' : 'Producer'}:**\n\n${m.content}`).join('\n\n---\n\n') }),
      }),
      h('button', {
        class: 'btn ghost sm', title: current.pinned ? 'Unpin' : 'Pin',
        onclick: async () => {
          try { Object.assign(current, (await api('PATCH', `chats/${current.id}`, { pinned: !current.pinned })).conversation); drawSidebar(); drawHeader(); } catch (e) { toast(e.message, 'err'); }
        },
      }, current.pinned ? 'Unpin' : [icon('pin', 14), 'Pin']),
      h('button', {
        class: 'btn ghost sm',
        onclick: async () => {
          const t = window.prompt('Rename chat', current.title);
          if (!t || !t.trim()) return;
          try { Object.assign(current, (await api('PATCH', `chats/${current.id}`, { title: t.trim() })).conversation); drawSidebar(); drawHeader(); } catch (e) { toast(e.message, 'err'); }
        },
      }, icon('pen', 14), 'Rename'),
      h('button', {
        class: 'btn ghost sm danger',
        onclick: async () => {
          if (!(await confirmDialog('Delete this chat?'))) return;
          try { await api('DELETE', `chats/${current.id}`); navigate('/chat'); } catch (e) { toast(e.message, 'err'); }
        },
      }, icon('trash', 14))));
  }

  if (id) {
    try {
      const r = await api('GET', `chats/${id}`);
      current = list.find((c) => c.id === r.conversation.id) || r.conversation;
      messages = r.messages;
    } catch {
      navigate('/chat', { replace: true });
      return;
    }
  }

  view.append(h('div', { class: 'chat-layout' },
    sidebar,
    h('section', { class: 'chat-main card' },
      header,
      thread,
      h('div', { class: 'chat-compose' }, input, h('div', { class: 'compose-actions' }, webLabel, sendBtn)))));
  drawSidebar();
  drawHeader();
  drawThread();
  setTimeout(() => input.focus(), 0);
}
