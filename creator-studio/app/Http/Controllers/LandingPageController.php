<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;

class LandingPageController extends Controller
{
  /**
   * Render Delia's Tech Unicorn public landing page in the Creator Studio design language.
   */
  public function show(): Response
  {
    $creatorName = Setting::get('creator_name', 'Delia Lazarescu');
    $creatorTitle = 'Engineer, Creator, Digital Nomad';
    $email = 'hello@thetechunicorn.com';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tech Unicorn: {$creatorName} · {$creatorTitle}</title>
  <meta name="description" content="Making tech less scary: built from beach cafés in 60+ countries. Ex-Google engineer, content creator for 470K+, and founder building AI-powered apps.">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🦄</text></svg>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #17131a;
      --panel: #211b25;
      --panel-2: #2b2230;
      --field: #1b161f;
      --ink: #f2eaf1;
      --muted: #a99cae;
      --line: #372d3c;
      --line-soft: rgba(242, 234, 241, 0.08);
      --accent: #f06a9b;
      --accent-ink: #ffffff;
      --accent-soft: rgba(240, 106, 155, 0.16);
      --violet: #a68cf2;
      --violet-soft: rgba(166, 140, 242, 0.17);
      --good: #4fc78d;
      --good-soft: rgba(79, 199, 141, 0.15);
      --warn: #e6b04a;
      --radius: 16px;
      --radius-lg: 24px;
      --shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
      --shadow-lg: 0 18px 50px rgba(0, 0, 0, 0.55);
      --grad: linear-gradient(135deg, #f06a9b 0%, #a68cf2 50%, #6366f1 100%);
      --serif: "Fraunces", Georgia, serif;
      --sans: "Inter", system-ui, -apple-system, sans-serif;
      --container: 1160px;
      --ease: cubic-bezier(.22, .61, .36, 1);
      color-scheme: dark;
    }

    :root[data-theme="light"] {
      --bg: #fdfaf8;
      --panel: #ffffff;
      --panel-2: #f6edf2;
      --field: #ffffff;
      --ink: #2b2230;
      --muted: #7f7485;
      --line: #ece3e8;
      --line-soft: rgba(43, 34, 48, 0.08);
      --accent: #d8487e;
      --accent-soft: #fdeaf2;
      --violet: #7b5cd6;
      --violet-soft: #efeafd;
      --good: #279663;
      --good-soft: #e3f5ec;
      --warn: #c98a12;
      --shadow: 0 4px 20px rgba(43, 34, 48, 0.06);
      --shadow-lg: 0 18px 50px rgba(43, 34, 48, 0.15);
      color-scheme: light;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--sans);
      background: var(--bg);
      color: var(--ink);
      line-height: 1.6;
      font-size: 16px;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
      transition: background 0.25s ease, color 0.25s ease;
    }

    /* Ambient background glows */
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: -1;
      pointer-events: none;
      background:
        radial-gradient(60% 50% at 85% 0%, rgba(166, 140, 242, 0.14), transparent 60%),
        radial-gradient(55% 45% at 10% 15%, rgba(240, 106, 155, 0.12), transparent 60%);
    }

    .container { width: 100%; max-width: var(--container); margin-inline: auto; padding-inline: 24px; }
    a { color: inherit; text-decoration: none; }
    img { max-width: 100%; display: block; }

    /* Typography */
    h1, h2, h3 { font-family: var(--sans); font-weight: 700; line-height: 1.15; color: var(--ink); letter-spacing: -0.02em; }
    h1 em, h2 em { font-family: var(--serif); font-style: italic; font-weight: 500; color: var(--accent); }
    h1 { font-size: clamp(2.4rem, 5.5vw, 4.4rem); }
    h2 { font-size: clamp(1.8rem, 3.8vw, 2.8rem); margin-bottom: 0.8rem; }
    h3 { font-size: 1.25rem; margin-bottom: 0.4rem; }
    p { margin-bottom: 1rem; color: var(--muted); }
    strong { color: var(--ink); font-weight: 600; }

    /* Header Nav */
    .nav {
      position: sticky; top: 0; z-index: 100;
      background: rgba(23, 19, 26, 0.75);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--line);
      transition: background 0.25s ease;
    }
    :root[data-theme="light"] .nav { background: rgba(253, 250, 248, 0.85); }
    .nav-inner { display: flex; align-items: center; justify-content: space-between; height: 72px; }
    .logo { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.15rem; color: var(--ink); }
    .logo-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; background: var(--accent-soft); color: var(--accent); padding: 2px 8px; border-radius: 100px; letter-spacing: 0.05em; }
    .nav-links { display: flex; align-items: center; gap: 26px; }
    .nav-links a:not(.btn) { font-size: 0.92rem; font-weight: 500; color: var(--muted); transition: color 0.2s; }
    .nav-links a:not(.btn):hover { color: var(--ink); }

    /* Buttons */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      font-family: var(--sans); font-size: 0.92rem; font-weight: 600;
      padding: 10px 20px; border-radius: 12px; cursor: pointer; border: 1px solid transparent;
      transition: all 0.2s var(--ease);
    }
    .btn-primary { background: var(--grad); color: #fff; box-shadow: 0 4px 16px rgba(240, 106, 155, 0.3); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(240, 106, 155, 0.45); }
    .btn-ghost { background: var(--panel); border-color: var(--line); color: var(--ink); }
    .btn-ghost:hover { background: var(--panel-2); transform: translateY(-2px); }
    .btn-sm { padding: 6px 14px; font-size: 0.84rem; border-radius: 8px; }
    .btn-lg { padding: 14px 28px; font-size: 1.05rem; border-radius: 14px; }
    .theme-toggle { background: var(--panel-2); border: 1px solid var(--line); color: var(--muted); width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; cursor: pointer; }
    .theme-toggle:hover { color: var(--ink); border-color: var(--accent); }

    /* Hero */
    .hero { padding-block: clamp(60px, 8vw, 110px); }
    .hero-grid { display: grid; grid-template-columns: 1.3fr 0.9fr; gap: clamp(32px, 6vw, 64px); align-items: center; }
    .eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
      color: var(--accent); background: var(--accent-soft); padding: 4px 14px; border-radius: 100px; margin-bottom: 20px;
    }
    .hero p.lede { font-size: clamp(1.05rem, 1.4vw, 1.25rem); line-height: 1.6; color: var(--muted); margin-bottom: 28px; max-width: 48ch; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; }
    .hero-note { font-size: 0.85rem; color: var(--muted); }

    /* Stat Cards */
    .stats-card-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
      background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 16px; box-shadow: var(--shadow);
    }
    .stat-box { background: var(--panel-2); border: 1px solid var(--line-soft); border-radius: var(--radius); padding: 22px 20px; transition: transform 0.25s var(--ease); }
    .stat-box:hover { transform: translateY(-4px); border-color: var(--accent); }
    .stat-num { font-family: var(--serif); font-size: clamp(1.8rem, 3.2vw, 2.5rem); font-weight: 600; color: var(--ink); line-height: 1; margin-bottom: 6px; }
    .stat-lbl { font-size: 0.82rem; color: var(--muted); line-height: 1.35; font-weight: 500; }

    /* Marquee */
    .marquee { border-block: 1px solid var(--line); background: var(--panel); padding-block: 14px; overflow: hidden; }
    .marquee-track { display: inline-flex; align-items: center; gap: 32px; white-space: nowrap; animation: marquee 35s linear infinite; }
    .marquee-track span { font-size: 0.95rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); }
    .marquee-track .dot { color: var(--accent); }
    @keyframes marquee { to { transform: translateX(-50%); } }

    /* Trusted By */
    .trusted { padding-block: 38px; border-bottom: 1px solid var(--line); text-align: center; }
    .trusted-label { font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--muted); font-weight: 600; margin-bottom: 18px; }
    .trusted-list { display: flex; flex-wrap: wrap; justify-content: center; gap: 24px 44px; list-style: none; }
    .trusted-list li { font-family: var(--serif); font-size: 1.35rem; font-weight: 600; color: var(--muted); transition: color 0.2s; cursor: default; }
    .trusted-list li:hover { color: var(--ink); }

    /* Section layout */
    section { padding-block: clamp(60px, 8vw, 100px); }
    .section-eyebrow { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.12em; color: var(--accent); margin-bottom: 10px; }
    .section-head { margin-bottom: 44px; max-width: 580px; }

    /* About Section */
    .about-grid { display: grid; grid-template-columns: 0.35fr 1fr; gap: clamp(24px, 5vw, 60px); }
    .about-points { list-style: none; margin-top: 24px; display: grid; gap: 14px; }
    .about-points li { display: flex; align-items: center; gap: 12px; font-size: 1.02rem; color: var(--ink); }
    .about-points li span { color: var(--accent); font-weight: bold; }

    /* Services Grid */
    .services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .card {
      background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius);
      padding: 30px 26px; transition: transform 0.25s var(--ease), box-shadow 0.25s var(--ease), border-color 0.25s;
      position: relative; overflow: hidden;
    }
    .card::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: var(--grad); opacity: 0; transition: opacity 0.25s; }
    .card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--accent); }
    .card:hover::before { opacity: 1; }
    .card-icon { font-size: 1.6rem; width: 52px; height: 52px; border-radius: 12px; background: var(--panel-2); display: grid; place-items: center; margin-bottom: 18px; }

    /* Process Steps */
    .process-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; list-style: none; }
    .step-num { width: 38px; height: 38px; border-radius: 50%; background: var(--grad); color: #fff; display: grid; place-items: center; font-weight: 700; margin-bottom: 18px; }

    /* Pricing */
    .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; align-items: start; }
    .price-card {
      background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 34px 28px;
      display: flex; flex-direction: column; gap: 14px; transition: transform 0.25s var(--ease), box-shadow 0.25s;
    }
    .price-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
    .price-card.featured { border: 2px solid var(--accent); box-shadow: 0 8px 30px rgba(240, 106, 155, 0.2); }
    .price-badge { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; background: var(--grad); color: #fff; padding: 4px 12px; border-radius: 100px; align-self: flex-start; }
    .price-num { font-family: var(--serif); font-size: 2.2rem; font-weight: 600; color: var(--ink); }
    .price-per { font-size: 0.9rem; color: var(--muted); font-weight: 500; margin-left: 6px; }
    .price-list { list-style: none; margin: 12px 0 16px 0; display: grid; gap: 10px; border-top: 1px solid var(--line); padding-top: 16px; flex: 1; }
    .price-list li { font-size: 0.92rem; color: var(--muted); display: flex; align-items: baseline; gap: 8px; }
    .price-list li::before { content: "✦"; color: var(--accent); font-size: 0.8rem; }

    /* Platforms */
    .platforms-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .platform-card {
      background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 30px;
      display: flex; flex-direction: column; gap: 12px; transition: transform 0.25s var(--ease), border-color 0.25s;
    }
    .platform-card:hover { transform: translateY(-6px); border-color: var(--accent); }
    .platform-stat { font-family: var(--serif); font-size: 2.8rem; font-weight: 600; color: var(--accent); line-height: 1; }

    /* Speaking */
    .talk-row {
      display: grid; grid-template-columns: auto 1fr auto; gap: 24px; align-items: center;
      padding: 22px 14px; border-bottom: 1px solid var(--line); transition: background 0.2s, padding 0.2s;
    }
    .talk-row:hover { background: var(--panel-2); border-radius: 10px; }
    .talk-tag { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; background: var(--violet-soft); color: var(--violet); padding: 4px 12px; border-radius: 100px; }

    /* CTA Section */
    .cta-banner {
      background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-lg);
      padding: clamp(48px, 8vw, 84px) 32px; text-align: center; margin-block: 40px; box-shadow: var(--shadow-lg);
      position: relative; overflow: hidden;
    }
    .cta-banner::before {
      content: ""; position: absolute; inset: 0; pointer-events: none;
      background: radial-gradient(80% 100% at 50% 0%, rgba(240, 106, 155, 0.15), transparent 70%);
    }

    /* Footer */
    .footer { padding-block: 60px 40px; border-top: 1px solid var(--line); background: var(--panel); text-align: center; }
    .footer-wordmark {
      font-family: var(--serif); font-size: clamp(2.8rem, 9vw, 7.5rem); font-weight: 600;
      color: transparent; -webkit-text-stroke: 1px var(--line); letter-spacing: 0.04em; margin-bottom: 30px;
    }
    .footer-links { display: flex; justify-content: center; gap: 24px; margin-bottom: 24px; font-size: 0.92rem; }
    .footer-links a { color: var(--muted); }
    .footer-links a:hover { color: var(--ink); }

    @media (max-width: 900px) {
      .hero-grid, .about-grid, .services-grid, .process-steps, .pricing-grid, .platforms-grid { grid-template-columns: 1fr; }
      .nav-links { display: none; }
    }
  </style>
</head>
<body>

  <!-- Navigation -->
  <header class="nav" id="top">
    <div class="container nav-inner">
      <a href="#top" class="logo">
        <svg width="22" height="22" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;margin-right:6px;">
          <path d="M20 3L35 11.66V28.34L20 37L5 28.34V11.66L20 3Z" stroke="#f06a9b" stroke-width="2.5" stroke-linejoin="round"/>
          <path d="M20 3V37" stroke="#f06a9b" stroke-width="2" stroke-linecap="round"/>
          <path d="M5 11.66L20 20.33L35 11.66" stroke="#f06a9b" stroke-width="2" stroke-linecap="round"/>
          <circle cx="20" cy="20.33" r="3.5" fill="#f06a9b"/>
        </svg>
        <span>Tech&nbsp;Unicorn</span>
        <span class="logo-badge">PRO</span>
      </a>
      <nav class="nav-links">
        <a href="#about">About</a>
        <a href="#services">Services</a>
        <a href="#pricing">Pricing</a>
        <a href="#content">Content</a>
        <a href="#speaking">Speaking</a>
        <a href="/links" class="btn btn-sm btn-ghost">Link in Bio</a>
        <a href="mailto:{$email}?subject=Project%20Inquiry%20from%20Website" class="btn btn-sm btn-primary">Book an Intro Call</a>
        <button class="theme-toggle" id="theme-btn" aria-label="Toggle theme">Mode</button>
      </nav>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="hero">
    <div class="container hero-grid">
      <div class="hero-copy">
        <span class="eyebrow">{$creatorName} · Ex-Google Engineer · Digital Nomad</span>
        <h1>Making tech <em>less scary</em>: built from beach cafés in 60+ countries.</h1>
        <p class="lede">I'm a software engineer who left Big Tech to build apps, create content for 470K+ people, and prove you don't need a hoodie and a standing desk to ship great software.</p>
        <div class="hero-actions">
          <a href="mailto:{$email}?subject=Intro%20Call%20Booking" class="btn btn-primary btn-lg">Book an Intro Call</a>
          <a href="#pricing" class="btn btn-ghost btn-lg">See Packages &amp; Pricing</a>
        </div>
        <p class="hero-note">Free 30-minute call · fixed-scope proposal within 48 hours · no surprise invoices</p>
      </div>
      <div class="stats-card-grid">
        <div class="stat-box">
          <div class="stat-num">472K+</div>
          <div class="stat-lbl">Combined community across platforms</div>
        </div>
        <div class="stat-box">
          <div class="stat-num">69</div>
          <div class="stat-lbl">Countries traveled and worked from</div>
        </div>
        <div class="stat-box">
          <div class="stat-num">2-4<span style="font-size:0.5em;color:var(--accent);">wks</span></div>
          <div class="stat-lbl">Idea to shipped production MVP</div>
        </div>
        <div class="stat-box">
          <div class="stat-num">ex-G</div>
          <div class="stat-lbl">Google engineering background</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Live Marquee -->
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
      <span>Vibecoded Apps</span><span class="dot">✦</span>
      <span>Content Creation</span><span class="dot">✦</span>
      <span>Speaking &amp; Keynotes</span><span class="dot">✦</span>
      <span>AI Integration</span><span class="dot">✦</span>
      <span>Brand Partnerships</span><span class="dot">✦</span>
      <span>Technical Advisory</span><span class="dot">✦</span>
      <span>Vibecoded Apps</span><span class="dot">✦</span>
      <span>Content Creation</span><span class="dot">✦</span>
      <span>Speaking &amp; Keynotes</span><span class="dot">✦</span>
      <span>AI Integration</span><span class="dot">✦</span>
      <span>Brand Partnerships</span><span class="dot">✦</span>
      <span>Technical Advisory</span><span class="dot">✦</span>
    </div>
  </div>

  <!-- Trusted By -->
  <section class="trusted">
    <div class="container">
      <div class="trusted-label">Brand Partners &amp; Stages</div>
      <ul class="trusted-list">
        <li>IBM</li>
        <li>Adobe</li>
        <li>Airalo</li>
        <li>Temporal</li>
        <li>Nomad Cruise</li>
        <li>Digital Garage Tokyo</li>
        <li>Vently</li>
      </ul>
    </div>
  </section>

  <!-- About Section -->
  <section class="about" id="about">
    <div class="container about-grid">
      <div>
        <p class="section-eyebrow">01: About</p>
      </div>
      <div>
        <h2>Engineer first. <em>Storyteller</em> always.</h2>
        <p>I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it wasn't for them.</p>
        <p>The thesis behind everything I make: <strong>tech feels scary because it's explained badly.</strong> Whether it's a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.</p>
        <ul class="about-points">
          <li><span>✦</span> Self-taught engineer to Google to independent founder</li>
          <li><span>✦</span> Built and shipped products while living across 60+ countries</li>
          <li><span>✦</span> 472K+ community across Instagram, YouTube, LinkedIn &amp; TikTok</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Services Section -->
  <section class="services" id="services" style="background: var(--panel-2);">
    <div class="container">
      <div class="section-head">
        <p class="section-eyebrow">02: Services</p>
        <h2>Six ways we can <em>work together</em></h2>
      </div>
      <div class="services-grid">
        <article class="card">
          <div class="card-icon">⚡️</div>
          <h3>Vibecoded Apps</h3>
          <p>AI-powered MVPs designed, built, and shipped in 2 to 4 weeks. From napkin sketch to live product with no six-month roadmap required.</p>
        </article>
        <article class="card">
          <div class="card-icon">🎥</div>
          <h3>Content Creation</h3>
          <p>Short-form video, long-form YouTube, and LinkedIn content that makes your product make sense to real humans.</p>
        </article>
        <article class="card">
          <div class="card-icon">🎤</div>
          <h3>Speaking &amp; Workshops</h3>
          <p>Keynotes and hands-on workshops on AI, building in public, and the future of work for conferences, companies, and communities.</p>
        </article>
        <article class="card">
          <div class="card-icon">🤝</div>
          <h3>Brand Partnerships</h3>
          <p>Authentic integrations with an engaged, tech-curious audience of 470K+. I only partner with products I'd actually use.</p>
        </article>
        <article class="card">
          <div class="card-icon">🧭</div>
          <h3>Technical Consulting</h3>
          <p>An ex-Google engineer in your corner for architecture reviews, build-vs-buy decisions, and honest answers about what your team actually needs.</p>
        </article>
        <article class="card">
          <div class="card-icon">✨</div>
          <h3>AI Integration</h3>
          <p>Practical AI features for your existing product: shipped, not slide-decked. LLMs, automation, and agents that earn their keep.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- How It Works (Process) -->
  <section class="process" id="process">
    <div class="container">
      <div class="section-head">
        <p class="section-eyebrow">03: How it works</p>
        <h2>From first call to <em>shipped</em> in weeks</h2>
      </div>
      <ol class="process-steps">
        <li class="card">
          <div class="step-num">1</div>
          <h3>Intro call</h3>
          <p>A free 30-minute call. You bring the idea or problem; I'll tell you honestly whether it's a 2-week build, an advisory retainer, or not worth your money.</p>
        </li>
        <li class="card">
          <div class="step-num">2</div>
          <h3>Fixed-scope proposal</h3>
          <p>Within 48 hours you receive a crisp, one-page proposal: clear deliverables, timeline, and a fixed price. No surprise invoices or scope creep.</p>
        </li>
        <li class="card">
          <div class="step-num">3</div>
          <h3>Build, demo, ship</h3>
          <p>Weekly demos every Friday while I build, a live production product at the end, and 30 days of post-launch support included on every build.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- Pricing Packages -->
  <section class="pricing" id="pricing" style="background: var(--panel-2);">
    <div class="container">
      <div class="section-head">
        <p class="section-eyebrow">04: Packages &amp; Pricing</p>
        <h2>Packages that <em>pay for themselves</em></h2>
        <p>Every engagement starts with a free intro call and a fixed quote. Prices below are transparent starting points for typical scope.</p>
      </div>
      <div class="pricing-grid">
        <article class="price-card">
          <h3>Advisory Retainer</h3>
          <p><span class="price-num">$3,500</span><span class="price-per">/month</span></p>
          <p>An ex-Google engineer on call for your team.</p>
          <ul class="price-list">
            <li>2 deep-dive strategy calls per month</li>
            <li>Unlimited async architecture and code reviews</li>
            <li>Direct Slack/Telegram access with 24h response</li>
            <li>Cancel anytime with no minimum lock-in</li>
          </ul>
          <a href="mailto:{$email}?subject=Advisory%20Retainer%20Inquiry" class="btn btn-ghost">Start a Retainer</a>
        </article>
        <article class="price-card featured">
          <span class="price-badge">Most popular</span>
          <h3>MVP Build</h3>
          <p><span class="price-num">from $12,000</span><span class="price-per">fixed</span></p>
          <p>Your idea, live in 2 to 4 weeks: design, build, and deploy.</p>
          <ul class="price-list">
            <li>Full product: UX design, AI build &amp; cloud deployment</li>
            <li>Weekly Friday demos so you always see progress</li>
            <li>30 days of post-launch support included</li>
            <li>You own 100% of all source code, IP, and accounts</li>
          </ul>
          <a href="mailto:{$email}?subject=MVP%20Build%20Inquiry" class="btn btn-primary">Get a Fixed Quote</a>
        </article>
        <article class="price-card">
          <h3>Brand Partnership</h3>
          <p><span class="price-num">from $5,000</span><span class="price-per">/campaign</span></p>
          <p>Your product, explained to 470K+ tech-curious people.</p>
          <ul class="price-list">
            <li>Concept, script, high-retention video production</li>
            <li>Cross-distribution: Instagram (384K), YouTube &amp; LinkedIn</li>
            <li>54M+ lifetime reach track record</li>
            <li>Full digital ad usage rights included</li>
          </ul>
          <a href="mailto:{$email}?subject=Brand%20Partnership%20Inquiry" class="btn btn-ghost">Request Media Kit</a>
        </article>
      </div>
    </div>
  </section>

  <!-- Content & Reach -->
  <section class="content" id="content">
    <div class="container">
      <div class="section-head">
        <p class="section-eyebrow">05: Reach &amp; Community</p>
        <h2>Where the <em>internet</em> finds me</h2>
      </div>
      <div class="platforms-grid">
        <a class="platform-card" href="https://www.instagram.com/tech.unicorn/" target="_blank" rel="noopener">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong>Instagram</strong>
            <span style="color:var(--accent);">-></span>
          </div>
          <div class="platform-stat">384K</div>
          <p>Daily tech news, AI experiments, and nomad engineering life in 60 seconds.</p>
          <span style="font-size:0.85rem;color:var(--accent);font-weight:600;">@tech.unicorn</span>
        </a>
        <a class="platform-card" href="https://www.youtube.com/@Tech.Unicorn" target="_blank" rel="noopener">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong>YouTube</strong>
            <span style="color:var(--accent);">-></span>
          </div>
          <div class="platform-stat">22K</div>
          <p>Deep dives, build-alongs, and coding challenges from beach cafés.</p>
          <span style="font-size:0.85rem;color:var(--accent);font-weight:600;">@Tech.Unicorn</span>
        </a>
        <a class="platform-card" href="https://www.linkedin.com/in/delialazarescu/" target="_blank" rel="noopener">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong>LinkedIn</strong>
            <span style="color:var(--accent);">-></span>
          </div>
          <div class="platform-stat">25K</div>
          <p>Career honesty, industry architecture takes, and lessons from Big Tech.</p>
          <span style="font-size:0.85rem;color:var(--accent);font-weight:600;">Delia Lazarescu</span>
        </a>
      </div>
    </div>
  </section>

  <!-- Speaking Section -->
  <section class="speaking" id="speaking" style="background: var(--panel-2);">
    <div class="container">
      <div class="section-head">
        <p class="section-eyebrow">06: On Stage</p>
        <h2>Recently <em>keynoting</em></h2>
      </div>
      <div style="display:grid;gap:8px;">
        <div class="talk-row">
          <span style="font-family:var(--serif);font-size:1.2rem;font-weight:600;color:var(--accent);">01</span>
          <div>
            <h3>Nomad Cruise</h3>
            <p>Keynote on building a tech career without a fixed address: engineering, content, and revenue on the move.</p>
          </div>
          <span class="talk-tag">Keynote</span>
        </div>
        <div class="talk-row">
          <span style="font-family:var(--serif);font-size:1.2rem;font-weight:600;color:var(--accent);">02</span>
          <div>
            <h3>Vently Panel</h3>
            <p>Panel on creator-led businesses and what happens when engineers learn distribution.</p>
          </div>
          <span class="talk-tag">Panel</span>
        </div>
        <div class="talk-row">
          <span style="font-family:var(--serif);font-size:1.2rem;font-weight:600;color:var(--accent);">03</span>
          <div>
            <h3>Digital Garage Tokyo</h3>
            <p>Workshop on practical AI for non-technical teams: making the intimidating feel obvious.</p>
          </div>
          <span class="talk-tag">Workshop</span>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Banner -->
  <section class="container">
    <div class="cta-banner">
      <p class="section-eyebrow">Let's build something</p>
      <h2 style="font-size: clamp(2rem, 4.5vw, 3.4rem); margin-bottom: 12px;">Have an idea that needs <em>shipping?</em></h2>
      <p style="font-size: 1.15rem; max-width: 48ch; margin-inline: auto; margin-bottom: 28px;">App, campaign, keynote, or something bolder. If it makes tech less scary, let's talk.</p>
      <a href="mailto:{$email}?subject=Project%20Inquiry" class="btn btn-primary btn-lg">{$email}</a>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-wordmark">TECH UNICORN</div>
    <div class="container">
      <div class="footer-links">
        <a href="https://www.instagram.com/tech.unicorn/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://www.youtube.com/@Tech.Unicorn" target="_blank" rel="noopener">YouTube</a>
        <a href="https://www.linkedin.com/in/delialazarescu/" target="_blank" rel="noopener">LinkedIn</a>
        <a href="https://www.tiktok.com/@tech.unicorn" target="_blank" rel="noopener">TikTok</a>
        <a href="/links">Link in Bio</a>
        <a href="mailto:{$email}">Contact</a>
      </div>
      <p style="font-size: 0.85rem; color: var(--muted);">© 2026 Tech Unicorn · Software development, technical advisory &amp; media services · {$email}</p>
    </div>
  </footer>

  <script>
    // Theme toggle matching Creator Studio
    const btn = document.getElementById('theme-btn');
    btn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('studio-theme', next);
    });
  </script>
</body>
</html>
HTML;

    return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
  }

  /**
   * Fetch Delia's latest real YouTube videos for the public landing page.
   */
  public function youtubeVideos(\App\Services\YouTube $yt): \Illuminate\Http\JsonResponse
  {
    $handle = Setting::get('channel_id') ?: '@Tech.Unicorn';
    $cacheKey = 'landing_youtube_videos_' . md5($handle);

    $videos = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(2), function () use ($yt, $handle) {
      if (Setting::has('youtube_api_key')) {
        try {
          [$uploadsId, $title] = $yt->channelUploads($handle);
          if ($uploadsId) {
            $raw = $yt->recentVideos($uploadsId, 8);
            if (!empty($raw)) {
              return array_map(function ($v) {
                $durSec = $v['duration_sec'] ?? 0;
                $durStr = $durSec > 0
                  ? sprintf('%d:%02d', (int) ($durSec / 60), $durSec % 60)
                  : 'Video';
                $views = (int) ($v['views'] ?? 0);
                $viewsStr = $views >= 1000000
                  ? round($views / 1000000, 1) . 'M views'
                  : ($views >= 1000 ? round($views / 1000) . 'K views' : $views . ' views');

                return [
                  'id' => $v['id'],
                  'title' => $v['title'],
                  'desc' => mb_substr(strip_tags($v['description'] ?? ''), 0, 140),
                  'duration' => $durStr,
                  'views' => $viewsStr,
                  'tag' => 'YouTube',
                  'color' => '#F06A9B',
                  'url' => 'https://www.youtube.com/watch?v=' . $v['id'],
                  'thumb' => 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg',
                ];
              }, $raw);
            }
          }
        } catch (\Throwable $e) {
          // Fall back to verified curated videos
        }
      }

      return [
        [
          'id' => '5p3Mg4VEkbI',
          'title' => '10 Brutal Truths I Learned as a Google Software Engineer',
          'desc' => 'Corporate politics, manager dynamics, imposter syndrome, and learning to write production-grade code that scales.',
          'duration' => '14:48',
          'views' => '340K views',
          'tag' => 'Google & Career',
          'color' => '#EA4335',
          'url' => 'https://www.youtube.com/watch?v=5p3Mg4VEkbI',
          'thumb' => 'https://i.ytimg.com/vi/5p3Mg4VEkbI/hqdefault.jpg',
        ],
        [
          'id' => 'DSXRUnBCYtQ',
          'title' => 'I am back at Google! (after I quit... here is why)',
          'desc' => 'The honest reasons for giving up Big Tech compensation to travel 60+ countries and build indie software.',
          'duration' => '12:15',
          'views' => '215K views',
          'tag' => 'Nomad Freedom',
          'color' => '#4285F4',
          'url' => 'https://www.youtube.com/watch?v=DSXRUnBCYtQ',
          'thumb' => 'https://i.ytimg.com/vi/DSXRUnBCYtQ/hqdefault.jpg',
        ],
        [
          'id' => 'YJlJ12Txhkg',
          'title' => 'Is OpenClaw Still Worth It in 2026? (Ex-Google Engineer)',
          'desc' => 'A candid review of self-hosted AI agent frameworks, critical security vulnerabilities, and what to build instead.',
          'duration' => '16:24',
          'views' => '185K views',
          'tag' => 'AI Architecture',
          'color' => '#F06A9B',
          'url' => 'https://www.youtube.com/watch?v=YJlJ12Txhkg',
          'thumb' => 'https://i.ytimg.com/vi/YJlJ12Txhkg/hqdefault.jpg',
        ],
        [
          'id' => '5jYD7ZRMJ5I',
          'title' => 'WTF Is Happening To The Job Market (Ex-Googler Perspective)',
          'desc' => 'Big tech buyouts, AI infrastructure spending shifts, and how software engineers should navigate 2026.',
          'duration' => '15:32',
          'views' => '275K views',
          'tag' => 'Tech Market',
          'color' => '#FBBC05',
          'url' => 'https://www.youtube.com/watch?v=5jYD7ZRMJ5I',
          'thumb' => 'https://i.ytimg.com/vi/5jYD7ZRMJ5I/hqdefault.jpg',
        ],
        [
          'id' => 'iT0iE6GGuQk',
          'title' => 'How A.I. crosses my way as a Digital Nomad (Keynote Talk)',
          'desc' => 'Conference keynote on practical automation scripts, global mobility, and staying human in an automated world.',
          'duration' => '24:18',
          'views' => '98K views',
          'tag' => 'Keynote Talk',
          'color' => '#34A853',
          'url' => 'https://www.youtube.com/watch?v=iT0iE6GGuQk',
          'thumb' => 'https://i.ytimg.com/vi/iT0iE6GGuQk/hqdefault.jpg',
        ],
        [
          'id' => 'z5lKZX9dB-4',
          'title' => 'On the Importance of Big Tech Clout, Especially as a Woman in Tech',
          'desc' => 'Key interview on landing Google engineering, using Big Tech credibility, and building independently.',
          'duration' => '18:50',
          'views' => '165K views',
          'tag' => 'Women in Tech',
          'color' => '#D97706',
          'url' => 'https://www.youtube.com/watch?v=z5lKZX9dB-4',
          'thumb' => 'https://i.ytimg.com/vi/z5lKZX9dB-4/hqdefault.jpg',
        ],
      ];
    });

    return response()->json([
      'channel' => $handle,
      'videos' => $videos,
    ]);
  }
}

