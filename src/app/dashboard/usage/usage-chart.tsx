"use client";

import { useState } from "react";

interface Day {
  date: string;
  label: string;
  tokens: number;
}

const W = 800;
const H = 220;
const PAD = { top: 16, right: 8, bottom: 24, left: 48 };
const SERIES = "#6366f1"; // indigo-500 — validated vs #18181b surface (L 0.51, 4.1:1)

function niceMax(n: number) {
  if (n <= 0) return 10;
  const pow = 10 ** Math.floor(Math.log10(n));
  const scaled = n / pow;
  const step = scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10;
  return step * pow;
}

function fmt(n: number) {
  if (n >= 1_000_000) return `${(n / 1_000_000).toLocaleString(undefined, { maximumFractionDigits: 1 })}M`;
  if (n >= 1_000) return `${(n / 1_000).toLocaleString(undefined, { maximumFractionDigits: 1 })}k`;
  return n.toLocaleString();
}

export default function UsageChart({ days }: { days: Day[] }) {
  const [hover, setHover] = useState<number | null>(null);

  const max = niceMax(Math.max(...days.map((d) => d.tokens)));
  const plotW = W - PAD.left - PAD.right;
  const plotH = H - PAD.top - PAD.bottom;
  const slot = plotW / days.length;
  const barW = Math.max(4, slot - 2); // 2px surface gap between bars
  const baselineY = PAD.top + plotH;
  const maxIdx = days.reduce((best, d, i) => (d.tokens > days[best].tokens ? i : best), 0);
  const hasData = days.some((d) => d.tokens > 0);

  const y = (v: number) => PAD.top + plotH * (1 - v / max);
  const gridVals = [0.25, 0.5, 0.75, 1].map((f) => f * max);

  return (
    <section className="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
      <h2 className="text-sm font-semibold text-white">Daily tokens — last 30 days</h2>
      <div className="relative mt-3">
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label="Bar chart of daily token usage for the last 30 days">
          {/* gridlines (recessive) */}
          {gridVals.map((v) => (
            <g key={v}>
              <line x1={PAD.left} x2={W - PAD.right} y1={y(v)} y2={y(v)} stroke="#27272a" strokeWidth="1" />
              <text x={PAD.left - 8} y={y(v) + 3} textAnchor="end" fontSize="10" fill="#71717a" style={{ fontVariantNumeric: "tabular-nums" }}>
                {fmt(v)}
              </text>
            </g>
          ))}
          {/* baseline */}
          <line x1={PAD.left} x2={W - PAD.right} y1={baselineY} y2={baselineY} stroke="#3f3f46" strokeWidth="1" />

          {/* bars: rounded data-end at top, square at baseline */}
          {days.map((d, i) => {
            const x = PAD.left + i * slot + (slot - barW) / 2;
            const h = (d.tokens / max) * plotH;
            const r = Math.min(4, barW / 2, h);
            const top = baselineY - h;
            return (
              <g key={d.date}>
                {d.tokens > 0 && (
                  <path
                    d={`M ${x} ${baselineY} V ${top + r} Q ${x} ${top} ${x + r} ${top} H ${x + barW - r} Q ${x + barW} ${top} ${x + barW} ${top + r} V ${baselineY} Z`}
                    fill={SERIES}
                    opacity={hover === null || hover === i ? 1 : 0.45}
                  />
                )}
                {/* selective direct label: peak day only */}
                {i === maxIdx && d.tokens > 0 && (
                  <text x={x + barW / 2} y={top - 6} textAnchor="middle" fontSize="10" fill="#e4e4e7" style={{ fontVariantNumeric: "tabular-nums" }}>
                    {fmt(d.tokens)}
                  </text>
                )}
                {/* hit target: full column, full height */}
                <rect
                  x={PAD.left + i * slot}
                  y={PAD.top}
                  width={slot}
                  height={plotH}
                  fill="transparent"
                  onMouseEnter={() => setHover(i)}
                  onMouseLeave={() => setHover(null)}
                />
              </g>
            );
          })}

          {/* x labels: first, middle, last */}
          {[0, Math.floor(days.length / 2), days.length - 1].map((i) => (
            <text key={i} x={PAD.left + i * slot + slot / 2} y={H - 6} textAnchor="middle" fontSize="10" fill="#71717a">
              {days[i].label}
            </text>
          ))}
        </svg>

        {hover !== null && (
          <div
            className="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 rounded-lg border border-zinc-700 bg-zinc-950 px-2.5 py-1.5 text-xs shadow-lg"
            style={{ left: `${((PAD.left + hover * slot + slot / 2) / W) * 100}%` }}
          >
            <p className="whitespace-nowrap text-zinc-400">{days[hover].label}</p>
            <p className="whitespace-nowrap font-medium text-zinc-100 tabular-nums">
              {days[hover].tokens.toLocaleString()} tokens
            </p>
          </div>
        )}

        {!hasData && (
          <p className="absolute inset-0 flex items-center justify-center text-sm text-zinc-600">
            No usage in the last 30 days yet.
          </p>
        )}
      </div>
    </section>
  );
}
