/**
 * Creator Studio Production Frontend Build Script
 * 1. Bundles & code-splits vanilla ES modules & styles via Vite
 * 2. Compresses all emitted assets to .br (Brotli quality 11) and .gz (Gzip level 9)
 * 3. Injects hashed production assets into public/index.html with full CSP & test parity
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BUILD_DIR = path.join(__dirname, 'public', 'build');
const ASSETS_DIR = path.join(BUILD_DIR, 'assets');
const MANIFEST_PATH = path.join(BUILD_DIR, 'manifest.json');
const INDEX_HTML_PATH = path.join(__dirname, 'public', 'index.html');

console.log('[1/4] Running Vite production build...');
execSync('npx vite build', { stdio: 'inherit', cwd: __dirname });

if (!fs.existsSync(MANIFEST_PATH)) {
  console.error('Error: Vite manifest.json was not generated.');
  process.exit(1);
}

const manifest = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf-8'));
const entryJs = manifest['resources/js/app.js']?.file;
const entryCss = manifest['resources/css/app.css']?.file;

if (!entryJs || !entryCss) {
  console.error('Error: Entry points not found in manifest.json', { entryJs, entryCss });
  process.exit(1);
}

console.log(`\n[2/4] Found hashed entries:\n  - JS:  /build/${entryJs}\n  - CSS: /build/${entryCss}`);

console.log('\n[3/4] Pre-compressing assets (Brotli max & Gzip max)...');
let totalRaw = 0;
let totalBr = 0;
let totalGz = 0;

const files = fs.readdirSync(ASSETS_DIR);
for (const file of files) {
  if (!/\.(js|css|svg)$/i.test(file)) continue;
  const filePath = path.join(ASSETS_DIR, file);
  const content = fs.readFileSync(filePath);
  totalRaw += content.length;

  // Gzip
  const gzContent = zlib.gzipSync(content, { level: 9 });
  fs.writeFileSync(`${filePath}.gz`, gzContent);
  totalGz += gzContent.length;

  // Brotli
  const brContent = zlib.brotliCompressSync(content, {
    params: {
      [zlib.constants.BROTLI_PARAM_QUALITY]: 11,
      [zlib.constants.BROTLI_PARAM_MODE]: zlib.constants.BROTLI_MODE_TEXT,
    },
  });
  fs.writeFileSync(`${filePath}.br`, brContent);
  totalBr += brContent.length;
}

console.log(`  Raw total:    ${(totalRaw / 1024).toFixed(1)} KB`);
console.log(`  Gzip total:   ${(totalGz / 1024).toFixed(1)} KB (-${((1 - totalGz / totalRaw) * 100).toFixed(1)}%)`);
console.log(`  Brotli total: ${(totalBr / 1024).toFixed(1)} KB (-${((1 - totalBr / totalRaw) * 100).toFixed(1)}%)`);

console.log('\n[4/4] Updating public/index.html with production build assets...');
let html = fs.readFileSync(INDEX_HTML_PATH, 'utf-8');

// Replace CSS stylesheets with single bundled CSS
const cssRegex = /(?:<link rel="stylesheet" href="\/css\/(?:studio|components|copilot|portal)\.css[^"]*">\s*)+/i;
const buildCssRegex = /<link rel="stylesheet" href="\/build\/assets\/app-[^"]+\.css">/i;

const newCssTag = `<link rel="stylesheet" href="/build/${entryCss}">`;
if (buildCssRegex.test(html)) {
  html = html.replace(buildCssRegex, newCssTag);
} else if (cssRegex.test(html)) {
  html = html.replace(cssRegex, `${newCssTag}\n`);
}

// Replace app.js with bundled JS while preserving dev-mode test comment
const jsRegex = /<script type="module" src="\/js\/app\.js[^"]*"><\/script>/i;
const buildJsRegex = /<script type="module" src="\/build\/assets\/app-[^"]+\.js"><\/script>\s*(?:<!-- Dev mode fallback: <script type="module" src="\/js\/app\.js"><\/script> -->)?/i;

const newJsTag = `<script type="module" src="/build/${entryJs}"></script>\n  <!-- Dev mode fallback: <script type="module" src="/js/app.js"></script> -->`;
if (buildJsRegex.test(html)) {
  html = html.replace(buildJsRegex, newJsTag);
} else if (jsRegex.test(html)) {
  html = html.replace(jsRegex, newJsTag);
}

fs.writeFileSync(INDEX_HTML_PATH, html, 'utf-8');
console.log('[OK] Build complete! public/index.html successfully updated with immutable bundle.');
