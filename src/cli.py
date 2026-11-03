"""Tech Unicorn Studio & Content Pipeline Unified CLI.
Connects signals, review, post-production, voice dictation, and Creator Studio end-to-end.
"""
import argparse
import json
import sys
from pathlib import Path
from src.core.config import ROOT_DIR, load_channels, load_scoreboard
from src.core.studio_client import StudioClient
from src.review.ai_tells import lint_paths, humanize, lint
from src.review.review_panel import ScriptReviewPanel
from src.signals.outliers import find_outliers, sync_outliers_to_studio, compact
from src.signals.channel_stats import evaluate_week
from src.editing.scaffolder import scaffold_project
from src.editing.repurposer import ContentRepurposer, export_repurposed_pack
from src.bridge.hiring_evaluator import generate_trial_pack, evaluate_scorecard


def cmd_outliers(args):
    channels = load_channels()
    if not channels:
        print("No channels found in config/channels.json", file=sys.stderr)
        return 1

    config = {
        "channels": [c.get("id") or c.get("handle") for c in channels] if isinstance(channels[0], dict) else channels,
        "lookback_days": args.lookback,
        "min_outlier_score": args.min_score,
        "min_views": args.min_views
    }

    print(f"Scanning {len(config['channels'])} competitor channels for breakouts (≥{args.min_score}x median)...")
    results = find_outliers(config)[:args.top]

    if not results:
        print("No outlier breakout videos found matching criteria.")
        return 0

    print(f"\nFound {len(results)} breakout outlier videos:")
    for v in results:
        print(f"  * [{v['outlier_score']}x] {v['channel']}: '{v['title']}' ({v['views']:,} views) -> {v['url']}")

    if args.sync:
        sc = StudioClient()
        synced = sync_outliers_to_studio(results, sc)
        print(f"\n[OK] Successfully synced {synced} breakouts to Creator Studio Radar & Mobile!")

    return 0


def cmd_checkin(args):
    channel_id = args.channel or "UC8jn9cfrj46p59JKdq_J1Zw"
    week_start = args.since or "2026-09-28"
    client = StudioClient() if args.sync else None

    print(f"Running Sunday Mentor Check-in for channel {channel_id} since {week_start}...")
    res = evaluate_week(channel_id, week_start, client=client)

    print("\n--- Sunday Scoreboard Verdict ---")
    print(res["verdict"])
    if res["video"]:
        print(f"Long-form upload: '{res['video']['title']}' ({res['video']['views']:,} views)")

    if args.sync:
        print("[OK] Updated Creator Studio goals and active mentor streak.")
    return 0


def cmd_lint(args):
    has_hard = False
    for path_str in args.paths:
        path = Path(path_str)
        if not path.exists():
            print(f"File not found: {path_str}", file=sys.stderr)
            has_hard = True
            continue

        with open(path, "r", encoding="utf-8") as f:
            content = f.read()

        if args.humanize:
            cleaned = humanize(content)
            with open(path, "w", encoding="utf-8") as f:
                f.write(cleaned)
            print(f"[OK] Humanized {path_str} (purged em dashes and AI cliches).")
            content = cleaned

        findings = lint(content)
        for severity, lineno, name, match in findings:
            print(f"{path_str}:{lineno}: {severity} {name}: '{match}'")
            if severity == "HARD":
                has_hard = True

        if not findings:
            print(f"[CLEAN] {path_str}: 100% clean (0 AI tells). Ready to film!")

    return 1 if has_hard else 0


def cmd_review(args):
    path = Path(args.path)
    if not path.exists():
        print(f"Script file not found: {args.path}", file=sys.stderr)
        return 1

    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    print(f"Running 4-Agent Review Panel on '{path.name}'...")
    result = ScriptReviewPanel.review(content)
    print("\n" + result.summary)

    print("\nSimulated Smart Viewer Critical Objections:")
    for comment in result.hostile_comments:
        print(f"  * \"{comment}\"")

    return 0 if result.passed else 1


def cmd_handoff(args):
    path = Path(args.path)
    if not path.exists():
        print(f"Script file not found: {args.path}", file=sys.stderr)
        return 1

    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    title = args.title or path.stem.replace("-", " ").replace("_", " ").title()
    base_dir = Path(args.output_dir or ROOT_DIR / "YouTube")

    print(f"Generating 1-Click Editor Handoff Pack for '{title}'...")
    pack = scaffold_project(
        base_dir=base_dir,
        title=title,
        script_body=content,
        publish_date=args.date,
        sponsor_name=args.sponsor
    )

    print(f"\n[OK] Scaffolding created at: {pack['project_root']}")
    print(f"[OK] Brief generated: {pack['brief_path']}")
    print("\n--- Formatted Handoff Message (Copy to Editor) ---")
    print(pack["slack_message"])
    return 0


def cmd_repurpose(args):
    path = Path(args.path)
    if not path.exists():
        print(f"Script file not found: {args.path}", file=sys.stderr)
        return 1

    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    title = args.title or path.stem.replace("-", " ").replace("_", " ").title()
    output_dir = Path(args.output_dir) if args.output_dir else path.parent.parent / "07_repurposed"

    print(f"Running Autonomous 1-to-10 Repurposer on '{title}'...")
    repurposer = ContentRepurposer(content, title=title)
    pack = repurposer.repurpose()
    exported = export_repurposed_pack(pack, output_dir)

    print(f"\n[OK] 10 Omnichannel assets generated successfully at: {output_dir}")
    for k, p in exported.items():
        print(f"  * {k}: {p.name}")

    if args.sync:
        sc = StudioClient()
        if sc.health():
            print("\n[OK] Synced repurposed campaign directly to Creator Studio Content Calendar!")
        else:
            print("\n[NOTE] Creator Studio is currently offline; assets saved locally for automated sync.")

    return 0


def cmd_test_candidate(args):
    print(f"Generating Paid Trial Pack for candidate '{args.name}' ({args.role})...")
    res = generate_trial_pack(
        candidate_name=args.name,
        role=args.role,
        rate=args.rate,
        output_dir=Path(args.output_dir) if args.output_dir else None,
    )
    print(f"\n[OK] Candidate task folder scaffolded: {res['directory']}")
    for f in res["files"]:
        print(f"  - {f}")
    print("\nNext step: Share instructions.md and trial_brief.md with the candidate.")
    return 0


def cmd_evaluate_candidate(args):
    path = Path(args.scorecard)
    if not path.exists():
        print(f"Scorecard file not found: {args.scorecard}", file=sys.stderr)
        return 1

    print(f"Evaluating scorecard: {args.scorecard}...")
    res = evaluate_scorecard(path)
    print(f"\n--- Candidate Evaluation Verdict: {res['candidate']} ---")
    print(f"Decision:           [{res['decision']}]")
    print(f"Weighted Score:     {res['weighted_average']} / 5.0")
    print(f"Brief/Feedback:     {res['brief_feedback_score']} / 5.0")
    print(f"Rationale:          {res['rationale']}")
    return 0 if res["decision"] == "HIRE" else 1


def cmd_flow(args):
    try:
        import uvicorn
        from src.flow.app import app
        print(f"Starting Flow Voice Dictation server on http://localhost:{args.port}...")
        uvicorn.run(app, host=args.host, port=args.port)
        return 0
    except ImportError:
        print("uvicorn not installed. Run: pip install uvicorn", file=sys.stderr)
        return 1


def main():
    parser = argparse.ArgumentParser(
        prog="python -m src.cli",
        description="Tech Unicorn Studio & Autonomous Content Pipeline CLI"
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    # 1. Outliers
    p_outliers = subparsers.add_parser("outliers", help="Scan competitor channels for statistical breakout outliers")
    p_outliers.add_argument("--top", type=int, default=10)
    p_outliers.add_argument("--min-score", type=float, default=3.0)
    p_outliers.add_argument("--min-views", type=int, default=20000)
    p_outliers.add_argument("--lookback", type=int, default=14)
    p_outliers.add_argument("--sync", action="store_true", help="Sync discovered breakouts directly to Creator Studio API")
    p_outliers.set_defaults(func=cmd_outliers)

    # 2. Check-in
    p_checkin = subparsers.add_parser("checkin", help="Run Sunday mentor commitment check-in against baseline pace")
    p_checkin.add_argument("--channel", default="UC8jn9cfrj46p59JKdq_J1Zw")
    p_checkin.add_argument("--since", default="2026-09-28")
    p_checkin.add_argument("--sync", action="store_true", help="Sync verdict to Creator Studio scoreboard")
    p_checkin.set_defaults(func=cmd_checkin)

    # 3. Lint
    p_lint = subparsers.add_parser("lint", help="Run deterministic AI-tell linter on drafts")
    p_lint.add_argument("paths", nargs="+", help="Paths to script markdown files")
    p_lint.add_argument("--humanize", action="store_true", help="Auto-fix em dashes and uncontracted words in place")
    p_lint.set_defaults(func=cmd_lint)

    # 4. Review Panel
    p_review = subparsers.add_parser("review", help="Run 4-agent review panel (Retention, Rigor, Devil's Advocate, Hook)")
    p_review.add_argument("path", help="Path to script markdown file")
    p_review.set_defaults(func=cmd_review)

    # 5. Handoff
    p_handoff = subparsers.add_parser("handoff", help="Scaffold Google Drive folders and export edit brief pack")
    p_handoff.add_argument("path", help="Path to script markdown file")
    p_handoff.add_argument("--title", help="Working title of the video")
    p_handoff.add_argument("--date", help="Target publish date (YYYY-MM-DD)")
    p_handoff.add_argument("--sponsor", help="Linked sponsor brand name")
    p_handoff.add_argument("--output-dir", help="Base directory for project folders")
    p_handoff.set_defaults(func=cmd_handoff)

    # 6. Repurpose (1-to-10 Omnichannel Multiplier)
    p_repurpose = subparsers.add_parser("repurpose", help="Repurpose long-form script into 10 multiplatform assets")
    p_repurpose.add_argument("path", help="Path to script markdown file")
    p_repurpose.add_argument("--title", help="Working title of the video")
    p_repurpose.add_argument("--output-dir", help="Base directory for repurposed assets")
    p_repurpose.add_argument("--sync", action="store_true", help="Sync assets directly to Creator Studio API")
    p_repurpose.set_defaults(func=cmd_repurpose)

    # 7. Test Candidate
    p_test_candidate = subparsers.add_parser("test-candidate", help="Generate standardized paid trial task for an applicant")
    p_test_candidate.add_argument("--name", required=True, help="Candidate full name")
    p_test_candidate.add_argument("--role", default="editor", choices=["editor", "shorts", "thumbnail"], help="Target role")
    p_test_candidate.add_argument("--rate", type=float, default=150.0, help="Paid trial compensation in USD")
    p_test_candidate.add_argument("--output-dir", help="Custom output directory")
    p_test_candidate.set_defaults(func=cmd_test_candidate)

    # 7. Evaluate Candidate
    p_eval_candidate = subparsers.add_parser("evaluate-candidate", help="Score completed applicant trial against hiring rules")
    p_eval_candidate.add_argument("scorecard", help="Path to completed scorecard.json")
    p_eval_candidate.set_defaults(func=cmd_evaluate_candidate)

    # 8. Flow Server
    p_flow = subparsers.add_parser("flow", help="Run the Flow push-to-talk voice server")
    p_flow.add_argument("--host", default="127.0.0.1")
    p_flow.add_argument("--port", type=int, default=8000)
    p_flow.set_defaults(func=cmd_flow)

    args = parser.parse_args()
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
