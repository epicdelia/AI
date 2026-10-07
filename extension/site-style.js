// "Match the site" style: tone follows the app you're writing in, like Wispr's context awareness.
const SITE_STYLES = [
  [/(^|\.)(mail\.google\.com|outlook\.(live|office|office365)\.com|mail\.yahoo\.com|app\.fastmail\.com|mail\.proton\.me)$/, "email"],
  [/(^|\.)(slack\.com|discord\.com|web\.whatsapp\.com|teams\.microsoft\.com|teams\.live\.com|messenger\.com|web\.telegram\.org|x\.com|twitter\.com)$/, "message"],
  [/(^|\.)(notion\.so|notion\.site|docs\.google\.com|atlassian\.net|coda\.io|obsidian\.md)$/, "notes"],
];
function styleFor(setting, url) {
  if (setting !== "site") return setting;
  let host = "";
  try { host = new URL(url).hostname; } catch {}
  if (/(^|\.)linkedin\.com$/.test(host)) return /\/messaging/.test(url) ? "message" : "auto";
  for (const [re, style] of SITE_STYLES) if (re.test(host)) return style;
  return "message";
}

if (typeof module !== "undefined") module.exports = { styleFor };  // for tests (Node)
