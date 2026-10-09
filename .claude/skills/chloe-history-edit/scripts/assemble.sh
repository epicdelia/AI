#!/usr/bin/env bash
# Hard-cut generated scenes into one 1080x1920 reel, mix an ambient bed under the
# dialogue (-12 dB), and normalise to -14 LUFS for Instagram.
# Usage: assemble.sh <out.mp4> <ambient audio | -> <scene1> [scene2 ...]
set -euo pipefail

if [[ $# -lt 3 ]]; then
  echo "usage: $0 <out.mp4> <ambient audio | -> <scene1> [scene2 ...]" >&2
  exit 2
fi
out="$1"; amb="$2"; shift 2
command -v ffmpeg >/dev/null || { echo "ffmpeg not found" >&2; exit 1; }
[[ "$amb" == "-" || -f "$amb" ]] || { echo "no such file: $amb" >&2; exit 1; }
for f in "$@"; do [[ -f "$f" ]] || { echo "no such file: $f" >&2; exit 1; }; done

inputs=(); fc=""; cat=""; n=$#; i=0
for f in "$@"; do
  inputs+=(-i "$f")
  # Scenes without an audio track get silence so concat stays aligned.
  if ffprobe -v error -select_streams a -show_entries stream=index -of csv=p=0 "$f" | grep -q .; then
    a="[$i:a]aresample=48000,aformat=channel_layouts=stereo[a$i];"
  else
    d=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$f")
    a="anullsrc=r=48000:cl=stereo,atrim=0:$d[a$i];"
  fi
  fc+="[$i:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,setsar=1,fps=30,format=yuv420p[v$i];$a"
  cat+="[v$i][a$i]"; i=$((i+1))
done
fc+="${cat}concat=n=$n:v=1:a=1[v][dlg];"

if [[ "$amb" == "-" ]]; then
  fc+="[dlg]loudnorm=I=-14:TP=-1.5:LRA=11[aout]"
else
  inputs+=(-stream_loop -1 -i "$amb")
  fc+="[$n:a]aresample=48000,aformat=channel_layouts=stereo,volume=-12dB[amb];"
  fc+="[dlg][amb]amix=inputs=2:duration=first:normalize=0,loudnorm=I=-14:TP=-1.5:LRA=11[aout]"
fi

ffmpeg -hide_banner -loglevel error -y "${inputs[@]}" -filter_complex "$fc" \
  -map "[v]" -map "[aout]" -c:v libx264 -preset medium -crf 18 -c:a aac -b:a 192k \
  -movflags +faststart "$out"
echo "assembled $n scene(s) -> $out"
