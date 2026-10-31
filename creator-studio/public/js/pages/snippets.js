// Code & Tech Snippet Card Studio: generate Ray.so / Carbon style visual code cards,
// terminal graphics, and "vs" comparisons for Instagram, LinkedIn and X.
import {
  h, api, toast, pageHead, clear, navigate, link,
} from '../core.js';
import { icon } from '../icons.js';

const THEMES = {
  unicorn: { name: 'Unicorn Neon', bg: 'linear-gradient(135deg, #f43f5e 0%, #a855f7 50%, #6366f1 100%)', window: '#1e1e2e', text: '#cdd6f4' },
  cyberpunk: { name: 'Cyberpunk Dark', bg: 'linear-gradient(135deg, #09090b 0%, #1e1b4b 50%, #4c1d95 100%)', window: '#0f172a', text: '#38bdf8' },
  sunset: { name: 'Warm Sunset', bg: 'linear-gradient(135deg, #ea580c 0%, #db2777 50%, #7c3aed 100%)', window: '#18181b', text: '#fafafa' },
  slate: { name: 'Deep Slate', bg: 'linear-gradient(135deg, #334155 0%, #1e293b 100%)', window: '#0f172a', text: '#e2e8f0' },
  emerald: { name: 'Matrix Emerald', bg: 'linear-gradient(135deg, #064e3b 0%, #022c22 100%)', window: '#022c22', text: '#34d399' },
};

const SAMPLE_CODE = `// Build an autonomous AI agent in 10 lines
import { Agent, Tool } from '@unicorn/ai';

const agent = new Agent({
  model: 'claude-3-7-sonnet',
  tools: [Tool.webSearch(), Tool.codeSandbox()],
  voice: 'Delia (@Tech.Unicorn)',
});

const result = await agent.run('Audit this codebase for security holes');
console.log(result.verdict);`;

export async function renderSnippets(view) {
  let activeTheme = 'unicorn';
  let title = 'agent.ts';
  let showLineNumbers = true;
  let watermark = '@Tech.Unicorn';

  const previewCanvas = h('canvas', { id: 'snippet-canvas', style: { maxWidth: '100%', height: 'auto', borderRadius: '12px', boxShadow: 'var(--shadow-lg)' } });
  const codeArea = h('textarea', { class: 'sp-body', rows: 12, style: { fontFamily: 'monospace', fontSize: '0.95rem' } }, SAMPLE_CODE);
  const titleInput = h('input', { type: 'text', value: title, placeholder: 'Window title (e.g. index.ts)' });
  const watermarkInput = h('input', { type: 'text', value: watermark, placeholder: '@Tech.Unicorn' });

  function drawCard() {
    const canvas = previewCanvas;
    const ctx = canvas.getContext('2d');
    const width = 1200;
    const lines = codeArea.value.split('\n');
    const lineHeight = 32;
    const codeHeight = lines.length * lineHeight;
    const padding = 60;
    const windowPadding = 30;
    const headerHeight = 44;
    const totalHeight = Math.max(675, codeHeight + headerHeight + (padding * 2) + (windowPadding * 2) + 30);

    canvas.width = width;
    canvas.height = totalHeight;

    const theme = THEMES[activeTheme];

    // 1. Draw outer gradient background
    const bgGrad = ctx.createLinearGradient(0, 0, width, totalHeight);
    if (activeTheme === 'unicorn') {
      bgGrad.addColorStop(0, '#f43f5e');
      bgGrad.addColorStop(0.5, '#a855f7');
      bgGrad.addColorStop(1, '#6366f1');
    } else if (activeTheme === 'cyberpunk') {
      bgGrad.addColorStop(0, '#09090b');
      bgGrad.addColorStop(0.5, '#1e1b4b');
      bgGrad.addColorStop(1, '#4c1d95');
    } else if (activeTheme === 'sunset') {
      bgGrad.addColorStop(0, '#ea580c');
      bgGrad.addColorStop(0.5, '#db2777');
      bgGrad.addColorStop(1, '#7c3aed');
    } else {
      bgGrad.addColorStop(0, '#1e293b');
      bgGrad.addColorStop(1, '#0f172a');
    }
    ctx.fillStyle = bgGrad;
    ctx.fillRect(0, 0, width, totalHeight);

    // 2. Draw window box with subtle drop shadow
    const winX = padding;
    const winY = padding;
    const winW = width - (padding * 2);
    const winH = totalHeight - (padding * 2);
    const radius = 16;

    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 20;
    ctx.fillStyle = theme.window;
    ctx.beginPath();
    ctx.roundRect(winX, winY, winW, winH, radius);
    ctx.fill();
    ctx.restore();

    // 3. Traffic lights (macOS dots)
    const dotY = winY + 24;
    const dots = ['#ff5f56', '#ffbd2e', '#27c93f'];
    dots.forEach((color, i) => {
      ctx.beginPath();
      ctx.arc(winX + 24 + (i * 20), dotY, 6, 0, Math.PI * 2);
      ctx.fillStyle = color;
      ctx.fill();
    });

    // 4. Window title
    ctx.font = '14px Inter, system-ui, sans-serif';
    ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
    ctx.textAlign = 'center';
    ctx.fillText(titleInput.value || 'snippet', winX + (winW / 2), dotY + 5);

    // 5. Code text with syntax highlight simulation
    const codeStartX = winX + windowPadding + (showLineNumbers ? 40 : 0);
    const codeStartY = winY + headerHeight + windowPadding;
    ctx.font = '18px "Fira Code", monospace';
    ctx.textAlign = 'left';

    lines.forEach((line, i) => {
      const y = codeStartY + (i * lineHeight);

      // Line number
      if (showLineNumbers) {
        ctx.fillStyle = 'rgba(255, 255, 255, 0.25)';
        ctx.fillText(String(i + 1).padStart(2, ' '), winX + windowPadding, y);
      }

      // Simple keyword syntax tinting
      if (line.trim().startsWith('//') || line.trim().startsWith('#')) {
        ctx.fillStyle = '#6b7280'; // comments gray
      } else if (line.includes('import') || line.includes('const') || line.includes('function') || line.includes('return') || line.includes('await')) {
        ctx.fillStyle = '#f43f5e'; // keywords rose/pink
      } else if (line.includes('class') || line.includes('new')) {
        ctx.fillStyle = '#38bdf8'; // classes sky blue
      } else if (line.includes("'") || line.includes('"')) {
        ctx.fillStyle = '#34d399'; // strings emerald
      } else {
        ctx.fillStyle = theme.text;
      }
      ctx.fillText(line, codeStartX, y);
    });

    // 6. Watermark at bottom right of outer canvas
    if (watermarkInput.value) {
      ctx.font = '600 14px Inter, system-ui, sans-serif';
      ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
      ctx.textAlign = 'right';
      ctx.fillText(watermarkInput.value, width - padding - 10, totalHeight - 20);
    }
  }

  codeArea.addEventListener('input', drawCard);
  titleInput.addEventListener('input', drawCard);
  watermarkInput.addEventListener('input', drawCard);

  function downloadImage() {
    drawCard();
    const linkEl = document.createElement('a');
    linkEl.download = `${titleInput.value || 'code-snippet'}.png`;
    linkEl.href = previewCanvas.toDataURL('image/png');
    linkEl.click();
    toast('Code card PNG downloaded.');
  }

  async function copyImageToClipboard() {
    drawCard();
    previewCanvas.toBlob(async (blob) => {
      try {
        await navigator.clipboard.write([
          new ClipboardItem({ 'image/png': blob }),
        ]);
        toast('Code card copied to clipboard as PNG.');
      } catch (err) {
        toast('Clipboard copy not supported; use Download.', 'err');
      }
    });
  }

  async function createPostFromSnippet() {
    drawCard();
    // Create draft social post with text
    const r = await api('POST', 'social/posts', {
      platform: 'linkedin',
      format: 'post',
      title: `Code snippet: ${titleInput.value}`,
      body: `Here is a quick breakdown of ${titleInput.value}:\n\n${codeArea.value.slice(0, 300)}...\n\nWhat do you think of this approach? Let me know in the comments below! 👇\n\n#SoftwareEngineering #TechUnicorn #Coding`,
    });
    toast('Draft post created.');
    navigate(`/posts/${r.post.id}`);
  }

  async function createCarouselFromSnippet() {
    drawCard();
    const r = await api('POST', 'social/posts', {
      platform: 'linkedin',
      format: 'carousel',
      title: `Carousel: ${titleInput.value || 'Code Guide'}`,
      body: `Deep dive into ${titleInput.value || 'this implementation'}.\n\nSwipe through the slides for the step-by-step breakdown! 👉\n\n#SoftwareEngineering #Coding #TechUnicorn`,
      carousel: {
        slides: [
          { title: titleInput.value || 'Code Guide', body: `Step-by-step implementation guide: ${titleInput.value}` },
          { title: 'The Code', body: codeArea.value.slice(0, 400) },
          { title: 'Core Advantages', body: '1. Clean modular structure\n2. Type-safe and resilient\n3. Production ready' },
          { title: 'Save & Share', body: 'Found this helpful? Repost and follow @Tech.Unicorn for more daily developer insights! 🦄' },
        ],
      },
    });
    toast('Carousel draft created with 4 slides.');
    navigate(`/posts/${r.post.id}`);
  }

  const themeSelectors = h('div', { class: 'choice-row', style: { margin: '12px 0' } },
    Object.entries(THEMES).map(([k, t]) => h('button', {
      class: `btn sm${activeTheme === k ? ' primary' : ''}`,
      onclick: () => { activeTheme = k; drawCard(); },
    }, t.name)));

  const toggleLineNumbers = h('button', {
    class: 'btn sm ghost',
    onclick: () => { showLineNumbers = !showLineNumbers; drawCard(); },
  }, icon('list', 14), 'Toggle line numbers');

  const controlsCard = h('div', { class: 'card' },
    h('h3', {}, 'Snippet Options'),
    h('div', { class: 'grid grid-2' },
      h('div', {}, 'Window filename', titleInput),
      h('div', {}, 'Watermark handle', watermarkInput)),
    h('div', {}, 'Theme Gradient', themeSelectors),
    h('div', { class: 'row', style: { marginTop: '10px' } }, toggleLineNumbers),
    h('div', { style: { marginTop: '14px' } },
      h('strong', {}, 'Paste code / terminal text:'),
      codeArea));

  const previewCard = h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('h3', {}, 'Live Graphic Preview'),
      h('div', { class: 'row' },
        h('button', { class: 'btn sm', onclick: copyImageToClipboard }, icon('copy', 14), 'Copy PNG'),
        h('button', { class: 'btn sm primary', onclick: downloadImage }, icon('image', 14), 'Download PNG'),
        h('button', { class: 'btn sm', onclick: createPostFromSnippet }, icon('arrow', 14), 'Create post'),
        h('button', { class: 'btn sm', onclick: createCarouselFromSnippet }, icon('repeat', 14), 'Create carousel'))),
    previewCanvas);

  view.append(
    pageHead('Code & Tech Snippet Studio', 'Turn code snippets, terminal commands, and tech tips into beautiful visual cards for LinkedIn, Instagram and X.',
      h('button', { class: 'btn', onclick: copyImageToClipboard }, icon('copy', 14), 'Copy image'),
      h('button', { class: 'btn primary', onclick: downloadImage }, icon('image', 15), 'Download PNG'),
      h('button', { class: 'btn', onclick: createCarouselFromSnippet }, icon('repeat', 15), 'Send to carousel')),
    h('div', { class: 'grid grid-2' }, controlsCard, previewCard),
  );

  setTimeout(drawCard, 50);
}

