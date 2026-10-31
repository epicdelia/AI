// Carousel slides as images: 1080×1350 (4:5, the size Instagram and LinkedIn show largest),
// drawn in the browser. Download them as PNGs, or as one PDF for a LinkedIn document post.
import { h, clear, slug, toast } from './core.js';
import { icon } from './icons.js';

const W = 1080;
const H = 1350;
const PAD = 96;

/** Dark text on light brand colours, white on dark ones. */
function inkFor(hex) {
  const m = /^#?([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i.exec(hex || '');
  if (!m) return '#ffffff';
  const [r, g, b] = m.slice(1).map((x) => parseInt(x, 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.45 ? '#1d1720' : '#ffffff';
}

function wrap(ctx, text, maxWidth) {
  const lines = [];
  for (const para of String(text).split('\n')) {
    let line = '';
    for (const word of para.split(/\s+/).filter(Boolean)) {
      const next = line ? `${line} ${word}` : word;
      if (ctx.measureText(next).width > maxWidth && line) { lines.push(line); line = word; } else line = next;
    }
    lines.push(line);
  }
  return lines;
}

/** One slide on a canvas. The text shrinks until it fits. */
export function drawSlide(text, i, total, { color = '#d8487e', handle = '' } = {}) {
  const canvas = document.createElement('canvas');
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  const ink = inkFor(color);

  ctx.fillStyle = color;
  ctx.fillRect(0, 0, W, H);
  const glow = ctx.createLinearGradient(0, 0, W, H);
  glow.addColorStop(0, 'rgba(255,255,255,0.10)');
  glow.addColorStop(1, 'rgba(0,0,0,0.12)');
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, W, H);

  const first = i === 0;
  const family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
  let size = first ? 104 : 64;
  let lines;
  for (;;) {
    ctx.font = `${first ? 800 : 650} ${size}px ${family}`;
    lines = wrap(ctx, text.trim(), W - PAD * 2);
    if (lines.length * size * 1.25 <= H - PAD * 3.2 || size <= 30) break;
    size -= 4;
  }
  ctx.fillStyle = ink;
  ctx.textBaseline = 'top';
  const block = lines.length * size * 1.25;
  let y = Math.max(PAD * 1.6, (H - block) / 2 - (first ? 40 : 0));
  for (const line of lines) { ctx.fillText(line, PAD, y); y += size * 1.25; }

  ctx.font = `600 34px ${family}`;
  ctx.globalAlpha = 0.8;
  ctx.fillText(`${i + 1} / ${total}`, W - PAD - ctx.measureText(`${i + 1} / ${total}`).width, PAD * 0.75);
  if (handle) ctx.fillText(`@${handle}`, PAD, H - PAD * 0.75 - 34);
  if (i < total - 1) {
    const arrow = 'Swipe →';
    ctx.fillText(arrow, W - PAD - ctx.measureText(arrow).width, H - PAD * 0.75 - 34);
  }
  ctx.globalAlpha = 1;
  return canvas;
}

function downloadCanvas(canvas, name) {
  return new Promise((resolve) => canvas.toBlob((blob) => {
    const a = h('a', { href: URL.createObjectURL(blob), download: name });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    resolve();
  }, 'image/png'));
}

/** All slides as one PDF via the browser's print dialog ("Save as PDF"), for LinkedIn documents. */
function printPdf(canvases, title) {
  const w = window.open('', '_blank');
  if (!w) { toast('Allow pop-ups to save the PDF.', 'err'); return; }
  const pages = canvases.map((c) => `<img src="${c.toDataURL('image/png')}" alt="">`).join('');
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${title.replace(/[<>&"]/g, '')}</title><style>
    @page { size: 1080px 1350px; margin: 0; }
    html, body { margin: 0; background: #fff; }
    img { display: block; width: 1080px; height: 1350px; page-break-after: always; break-after: page; }
  </style></head><body>${pages}<script>window.onload = () => setTimeout(() => window.print(), 300);<\/script></body></html>`);
  w.document.close();
}

/** The card under a carousel post: thumbnails plus PNG and PDF export. */
export function carouselCard(slides, { name = 'carousel', color, handle = '', pdf = false } = {}) {
  const grid = h('div', { class: 'slide-grid' });
  let canvases = [];
  function render() {
    canvases = slides.filter((s) => s.trim()).map((s, i, all) => drawSlide(s, i, all.length, { color, handle }));
    clear(grid).append(canvases.map((c) => { c.className = 'slide-thumb'; return c; }));
  }
  render();
  const file = slug(name) || 'carousel';
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('h3', {}, 'Slides as images'),
      h('div', { class: 'row' },
        h('button', { class: 'btn ghost sm', title: 'Redraw after editing', onclick: render }, icon('refresh', 15)),
        h('button', {
          class: 'btn sm',
          onclick: async () => {
            render();
            for (const [i, c] of canvases.entries()) await downloadCanvas(c, `${file}-${String(i + 1).padStart(2, '0')}.png`);
            toast(`${canvases.length} slide(s) downloaded.`);
          },
        }, icon('download', 15), 'PNGs'),
        pdf ? h('button', { class: 'btn sm primary', onclick: () => { render(); printPdf(canvases, file); } }, icon('download', 15), 'PDF') : null)),
    h('p', { class: 'muted small' }, `1080 × 1350, your brand colour (Settings → Social).${pdf ? ' LinkedIn carousels are PDF documents: choose “Save as PDF” in the print dialog.' : ''}`),
    grid);
}
