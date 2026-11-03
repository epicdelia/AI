// Line-based three-way merge, the same algorithm as app/Domains/Scripts/Merge3.php.
// Used by the script editor when edits were typed while a save (which the server merged)
// was in flight. Where both sides changed the same lines differently, both are kept.

function hunks(a, b) {
  const n = a.length;
  const m = b.length;
  let pre = 0;
  while (pre < n && pre < m && a[pre] === b[pre]) pre++;
  let suf = 0;
  while (suf < n - pre && suf < m - pre && a[n - 1 - suf] === b[m - 1 - suf]) suf++;
  const a2 = a.slice(pre, n - suf);
  const b2 = b.slice(pre, m - suf);
  const x = a2.length;
  const y = b2.length;
  if (!x && !y) return [];
  if (x * y > 4000000) return [[pre, pre + x, b2]];
  const L = Array.from({ length: x + 1 }, () => new Int32Array(y + 1));
  for (let i = x - 1; i >= 0; i--) {
    for (let j = y - 1; j >= 0; j--) {
      L[i][j] = a2[i] === b2[j] ? L[i + 1][j + 1] + 1 : Math.max(L[i + 1][j], L[i][j + 1]);
    }
  }
  const out = [];
  let i = 0;
  let j = 0;
  let hs = null;
  let repl = [];
  while (i < x || j < y) {
    if (i < x && j < y && a2[i] === b2[j]) {
      if (hs !== null) { out.push([pre + hs, pre + i, repl]); hs = null; repl = []; }
      i++; j++;
    } else if (j < y && (i >= x || L[i][j + 1] >= L[i + 1][j])) {
      if (hs === null) hs = i;
      repl.push(b2[j]); j++;
    } else {
      if (hs === null) hs = i;
      i++;
    }
  }
  if (hs !== null) out.push([pre + hs, pre + i, repl]);
  return out;
}

function apply(b, start, end, list) {
  const out = [];
  let pos = start;
  for (const [s, e, lines] of list) { out.push(...b.slice(pos, s), ...lines); pos = e; }
  out.push(...b.slice(pos, end));
  return out;
}

const same = (x, y) => x.length === y.length && x.every((v, i) => v === y[i]);

/** @returns {{ text: string, conflicts: number }} */
export function merge3(base, mine, theirs) {
  if (mine === theirs || theirs === base) return { text: mine, conflicts: 0 };
  if (mine === base) return { text: theirs, conflicts: 0 };
  const b = base.split('\n');
  const all = [
    ...hunks(b, mine.split('\n')).map((hk) => [...hk, 'm']),
    ...hunks(b, theirs.split('\n')).map((hk) => [...hk, 't']),
  ].sort((p, q) => p[0] - q[0] || (p[3] === 'm' ? 0 : 1) - (q[3] === 'm' ? 0 : 1));
  const out = [];
  let conflicts = 0;
  let pos = 0;
  let k = 0;
  while (k < all.length) {
    const start = all[k][0];
    let end = all[k][1];
    const region = [all[k++]];
    while (k < all.length && (all[k][0] < end || all[k][0] === start)) { region.push(all[k]); end = Math.max(end, all[k][1]); k++; }
    out.push(...b.slice(pos, start));
    const sides = new Set(region.map((r) => r[3]));
    const mineSide = apply(b, start, end, region.filter((r) => r[3] === 'm'));
    const theirSide = apply(b, start, end, region.filter((r) => r[3] === 't'));
    if (sides.size === 1) out.push(...(sides.has('m') ? mineSide : theirSide));
    else if (same(mineSide, theirSide)) out.push(...mineSide);
    else if (mineSide.length === end - start && theirSide.length === end - start) {
      for (let x = 0; x < end - start; x++) {
        const [bl, ml, tl] = [b[start + x], mineSide[x], theirSide[x]];
        if (ml === tl || tl === bl) out.push(ml);
        else if (ml === bl) out.push(tl);
        else { out.push(ml, tl); conflicts++; }
      }
    } else { out.push(...mineSide, ...theirSide); conflicts++; }
    pos = end;
  }
  out.push(...b.slice(pos));
  return { text: out.join('\n'), conflicts };
}
