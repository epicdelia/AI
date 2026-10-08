#!/usr/bin/env bash
# Finishing grade approximating covacut's look: warm high-contrast sepia-leaning
# highlights, slight chromatic aberration, film grain, soft vignette.
# Usage: cova_grade.sh <in> <out> [strength 0..1, default 1]
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "usage: $0 <in> <out> [strength 0..1]" >&2
  exit 2
fi
in="$1"; out="$2"; s="${3:-1}"
command -v ffmpeg >/dev/null || { echo "ffmpeg not found" >&2; exit 1; }
[[ -f "$in" ]] || { echo "no such file: $in" >&2; exit 1; }
if ! awk -v s="$s" 'BEGIN{exit !(s ~ /^[0-9]*\.?[0-9]+$/ && s>=0 && s<=1)}'; then
  echo "strength must be a number between 0 and 1" >&2
  exit 2
fi

p() { awk -v s="$s" -v a="$1" 'BEGIN{printf "%.3f", a*s}'; }
shift_px=$(awk -v s="$s" 'BEGIN{printf "%d", (s*3)+0.5}')
grain=$(awk -v s="$s" 'BEGIN{printf "%d", s*10}')

vf="scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,setsar=1"
vf+=",eq=contrast=$(awk -v s="$s" 'BEGIN{printf "%.3f", 1+0.15*s}'):saturation=$(awk -v s="$s" 'BEGIN{printf "%.3f", 1-0.25*s}')"
vf+=",colorbalance=rs=$(p 0.06):bs=$(p -0.06):rm=$(p 0.08):gm=$(p 0.02):bm=$(p -0.08):rh=$(p 0.10):gh=$(p 0.05):bh=$(p -0.08)"
[[ "$shift_px" -gt 0 ]] && vf+=",rgbashift=rh=-${shift_px}:bh=${shift_px}"
[[ "$grain" -gt 0 ]] && vf+=",noise=alls=${grain}:allf=t"
vf+=",vignette=angle=$(awk -v s="$s" 'BEGIN{printf "%.3f", 0.15+0.35*s}'),fps=30,format=yuv420p"

ffmpeg -hide_banner -loglevel error -y -i "$in" -vf "$vf" \
  -c:v libx264 -preset medium -crf 18 -c:a copy -movflags +faststart "$out"
echo "graded -> $out (strength $s)"
