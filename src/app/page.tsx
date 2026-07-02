import Link from "next/link";
import { PLANS } from "@/lib/plans";

const FEATURES = [
  {
    title: "Team chat with GPT models",
    body: "A fast, streaming chat interface your whole company can use — conversations stay private to each teammate inside your workspace.",
  },
  {
    title: "Usage metering & quotas",
    body: "Every token is metered per workspace. Monthly quotas by plan keep spend predictable, with a real-time usage dashboard for admins.",
  },
  {
    title: "API access for your product",
    body: "Ship AI features in your own app with workspace API keys and an OpenAI-compatible completions endpoint.",
  },
  {
    title: "Admin & team controls",
    body: "Invite teammates with role-based access, manage seats, rotate API keys, and switch plans — all self-serve.",
  },
];

export default function LandingPage() {
  return (
    <main className="min-h-screen bg-zinc-950 text-zinc-100">
      <header className="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
        <span className="text-lg font-semibold text-white">
          Fluent<span className="text-indigo-400">AI</span>
        </span>
        <nav className="flex items-center gap-4 text-sm">
          <a href="#pricing" className="text-zinc-400 hover:text-white">
            Pricing
          </a>
          <Link href="/login" className="text-zinc-400 hover:text-white">
            Sign in
          </Link>
          <Link
            href="/signup"
            className="rounded-lg bg-indigo-500 px-3 py-1.5 font-medium text-white hover:bg-indigo-400"
          >
            Get started
          </Link>
        </nav>
      </header>

      <section className="mx-auto max-w-3xl px-6 pb-20 pt-24 text-center">
        <h1 className="text-4xl font-semibold leading-tight text-white sm:text-5xl">
          The AI assistant built for <span className="text-indigo-400">your whole team</span>
        </h1>
        <p className="mx-auto mt-5 max-w-xl text-lg text-zinc-400">
          FluentAI gives your company a secure, metered workspace on top of GPT models — chat
          for every teammate, an API for your product, and controls for your admins.
        </p>
        <div className="mt-8 flex justify-center gap-3">
          <Link
            href="/signup"
            className="rounded-xl bg-indigo-500 px-6 py-3 font-medium text-white hover:bg-indigo-400"
          >
            Create your workspace — free
          </Link>
          <a
            href="#pricing"
            className="rounded-xl border border-zinc-700 px-6 py-3 font-medium text-zinc-300 hover:bg-zinc-900"
          >
            View pricing
          </a>
        </div>
        <p className="mt-4 text-xs text-zinc-600">
          No credit card required · 100k free tokens every month
        </p>
      </section>

      <section className="mx-auto grid max-w-5xl gap-4 px-6 pb-20 sm:grid-cols-2">
        {FEATURES.map((f) => (
          <div key={f.title} className="rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6">
            <h3 className="font-semibold text-white">{f.title}</h3>
            <p className="mt-2 text-sm leading-relaxed text-zinc-400">{f.body}</p>
          </div>
        ))}
      </section>

      <section id="pricing" className="mx-auto max-w-5xl px-6 pb-24">
        <h2 className="text-center text-2xl font-semibold text-white">
          Simple per-seat pricing
        </h2>
        <div className="mt-8 grid gap-4 sm:grid-cols-3">
          {Object.values(PLANS).map((p) => (
            <div
              key={p.id}
              className={`rounded-2xl border p-6 ${
                p.id === "PRO"
                  ? "border-indigo-500 bg-indigo-500/5"
                  : "border-zinc-800 bg-zinc-900/50"
              }`}
            >
              {p.id === "PRO" && (
                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-indigo-400">
                  Most popular
                </p>
              )}
              <h3 className="text-lg font-semibold text-white">{p.name}</h3>
              <p className="mt-2 text-3xl font-semibold text-white">
                ${p.priceMonthly}
                <span className="text-sm font-normal text-zinc-500">/seat/mo</span>
              </p>
              <p className="mt-2 text-sm text-zinc-400">{p.description}</p>
              <ul className="mt-4 space-y-2 text-sm text-zinc-300">
                <li>✓ {p.monthlyTokenLimit.toLocaleString()} tokens / month</li>
                <li>✓ Up to {p.seatLimit} seats</li>
                <li>✓ Models: {p.models.join(", ")}</li>
                <li>{p.apiAccess ? "✓ API access" : "— No API access"}</li>
              </ul>
              <Link
                href="/signup"
                className={`mt-6 block rounded-lg px-4 py-2 text-center text-sm font-medium ${
                  p.id === "PRO"
                    ? "bg-indigo-500 text-white hover:bg-indigo-400"
                    : "border border-zinc-700 text-zinc-300 hover:bg-zinc-900"
                }`}
              >
                Start with {p.name}
              </Link>
            </div>
          ))}
        </div>
      </section>

      <footer className="border-t border-zinc-900 py-8 text-center text-xs text-zinc-600">
        © {new Date().getFullYear()} FluentAI · A demo B2B AI workspace
      </footer>
    </main>
  );
}
