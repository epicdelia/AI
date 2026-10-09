#!/usr/bin/env python3
"""Measure a reel's edit without any API: cuts, shot lengths, pauses, loudness,
a frame grid and (if pocketsphinx is installed) a word-timed transcript.

Usage: python3 -I analyze_reel.py <video> <out_dir>
Writes <out_dir>/<name>.md and <out_dir>/<name>_grid.png.
"""
import itertools
import json
import re
import shutil
import statistics
import subprocess
import sys
from pathlib import Path


def run(args):
    return subprocess.run(args, capture_output=True, text=True, check=False)


def probe(path):
    out = run(["ffprobe", "-v", "error", "-show_entries",
               "format=duration:stream=codec_type,width,height,r_frame_rate",
               "-of", "json", str(path)]).stdout
    data = json.loads(out)
    video = next(s for s in data["streams"] if s["codec_type"] == "video")
    has_audio = any(s["codec_type"] == "audio" for s in data["streams"])
    return float(data["format"]["duration"]), video, has_audio


def cuts(path, threshold):
    err = run(["ffmpeg", "-hide_banner", "-i", str(path), "-an", "-vf",
               f"select='gt(scene,{threshold})',showinfo", "-f", "null", "-"]).stderr
    return [float(t) for t in re.findall(r"pts_time:([0-9.]+)", err)]


def pauses(path):
    err = run(["ffmpeg", "-hide_banner", "-i", str(path), "-vn", "-af",
               "silencedetect=n=-35dB:d=0.35,ebur128", "-f", "null", "-"]).stderr
    starts = [float(x) for x in re.findall(r"silence_start: ([0-9.]+)", err)]
    ends = [float(x) for x in re.findall(r"silence_end: ([0-9.]+)", err)]
    lufs = re.findall(r"I:\s+(-?[0-9.]+) LUFS", err)
    return list(zip(starts, ends)), (float(lufs[-1]) if lufs else None)


def transcript(path, tmp):
    try:
        from pocketsphinx import Decoder
    except ImportError:
        return None
    raw = tmp / "audio.raw"
    run(["ffmpeg", "-hide_banner", "-loglevel", "error", "-y", "-i", str(path),
         "-ac", "1", "-ar", "16000", "-f", "s16le", str(raw)])
    d = Decoder(samprate=16000)
    d.start_utt()
    d.process_raw(raw.read_bytes(), full_utt=True)
    d.end_utt()
    words = []
    for s in d.seg():
        w = re.sub(r"\(\d+\)$", "", s.word)
        if w.startswith(("<", "[")):
            continue
        words.append((s.start_frame / 100, w))
    return words


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: analyze_reel.py <video> <out_dir>")
    src, out = Path(sys.argv[1]), Path(sys.argv[2])
    if not src.is_file():
        sys.exit(f"no such file: {src}")
    out.mkdir(parents=True, exist_ok=True)
    tmp = out / f".tmp_{src.stem}"
    tmp.mkdir(exist_ok=True)

    duration, video, has_audio = probe(src)
    cut_times = cuts(src, 0.25)
    bounds = [0.0] + cut_times + [duration]
    shots = [round(b - a, 2) for a, b in itertools.pairwise(bounds) if b - a > 0.05]

    grid = out / f"{src.stem}_grid.png"
    run(["ffmpeg", "-hide_banner", "-loglevel", "error", "-y", "-i", str(src), "-vf",
         (f"fps={min(2, 28 / max(duration, 1)):.3f},scale=216:-1,"
          "drawtext=text='%{pts\\:hms}':x=4:y=4:fontsize=18:fontcolor=white:box=1:boxcolor=black@0.6,"
          "tile=7x4"), "-frames:v", "1", str(grid)])

    lines = [f"# {src.name}", "",
             f"- Duration: {duration:.2f}s, {video['width']}x{video['height']}, {video['r_frame_rate']} fps",
             (f"- Shots: {len(shots)}; median {statistics.median(shots):.2f}s, "
              f"min {min(shots):.2f}s, max {max(shots):.2f}s"),
             f"- Cuts per 10s: {10 * len(cut_times) / duration:.1f}",
             f"- Cut times: {', '.join(f'{t:.2f}' for t in cut_times) or 'none'}",
             f"- Frame grid: {grid.name}"]
    if has_audio:
        gaps, lufs = pauses(src)
        lines.append(f"- Loudness: {lufs} LUFS" if lufs is not None else "- Loudness: n/a")
        lines.append(f"- Pauses >0.35s: {len(gaps)} "
                     f"({', '.join(f'{a:.1f}-{b:.1f}' for a, b in gaps) or 'none'})")
        words = transcript(src, tmp)
        if words is None:
            lines.append("- Transcript: pocketsphinx not installed (pip install pocketsphinx)")
        else:
            first = [w for t, w in words if t < 3]
            lines += [f"- First 3s spoken: {' '.join(first) or '(none)'}",
                      f"- Words per minute: {60 * len(words) / duration:.0f}",
                      "", "## Rough transcript (machine, verify before quoting)", "",
                      " ".join(f"[{t:.1f}] {w}" if i % 8 == 0 else w
                               for i, (t, w) in enumerate(words))]
    else:
        lines.append("- Audio: none")

    report = out / f"{src.stem}.md"
    report.write_text("\n".join(lines) + "\n")
    shutil.rmtree(tmp, ignore_errors=True)
    print(report)


if __name__ == "__main__":
    main()
