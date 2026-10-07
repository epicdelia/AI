// Run: node tests/extension/site_style.test.mjs
import { createRequire } from "node:module";
import assert from "node:assert/strict";
const { styleFor } = createRequire(import.meta.url)("../../extension/site-style.js");

const cases = [
  ["https://mail.google.com/mail/u/0/#inbox", "email"],
  ["https://outlook.office.com/mail/", "email"],
  ["https://app.slack.com/client/T1/C1", "message"],
  ["https://discord.com/channels/1/2", "message"],
  ["https://web.whatsapp.com/", "message"],
  ["https://www.linkedin.com/messaging/thread/1/", "message"],
  ["https://www.linkedin.com/feed/", "auto"],
  ["https://www.notion.so/page-123", "notes"],
  ["https://docs.google.com/document/d/1/edit", "notes"],
  ["https://chatgpt.com/", "message"],
  ["not a url", "message"],
  ["https://evil-mail.google.com.attacker.io/", "message"],
];
for (const [url, want] of cases) assert.equal(styleFor("site", url), want, url);
assert.equal(styleFor("email", "https://app.slack.com/"), "email", "explicit choice overrides the site");
console.log(`site_style: ${cases.length + 1} checks passed`);
