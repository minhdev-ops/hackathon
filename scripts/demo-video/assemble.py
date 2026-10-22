#!/usr/bin/env python3
"""
Ghép video demo cuối cùng từ:
    - work/cards/*.png   (title card, render bằng Playwright)
    - work/video/*.webm  (screen recording từng cảnh)
    - work/audio/*.mp3   (lời dẫn) + *.vtt (phụ đề)

Cơ chế đồng bộ: mỗi cảnh chiếm đúng một "slot" dài = narration + pad.
Card (nếu có) nằm ầu slot, lời dẫn bắt đầu ngay đầu slot nên hình và tiếng
luôn khớp nhau. Tổng thời lượng = tổng slot, tự động nằm trong ngân sách.
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = ROOT / "demo-video" / "work"
VIDEO = WORK / "video"
AUDIO = WORK / "audio"
CARDS = WORK / "cards"
CLIPS = WORK / "clips"
OUT_DIR = ROOT / "demo-video" / "out"

FFMPEG = HERE / "bin" / "ffmpeg"
FONTS_DIR = "/usr/share/fonts"

WIDTH, HEIGHT, FPS = 1920, 1080, 30
DURATION_RE = re.compile(r"Duration: (\d+):(\d+):(\d+\.\d+)")


def run(cmd: list[str]) -> None:
    proc = subprocess.run(cmd, capture_output=True, text=True)
    if proc.returncode != 0:
        print(proc.stderr[-4000:], file=sys.stderr)
        raise RuntimeError(f"ffmpeg thất bại: {' '.join(cmd[:6])} ...")


def probe_duration(path: Path) -> float:
    proc = subprocess.run([str(FFMPEG), "-i", str(path)], capture_output=True, text=True)
    match = DURATION_RE.search(proc.stderr)
    if not match:
        raise RuntimeError(f"Không đọc được duration của {path.name}")
    hours, minutes, seconds = match.groups()
    return int(hours) * 3600 + int(minutes) * 60 + float(seconds)


def ass_time(seconds: float) -> str:
    seconds = max(0.0, seconds)
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    rest = seconds - hours * 3600 - minutes * 60
    return f"{hours}:{minutes:02d}:{rest:05.2f}"


def parse_timestamp(value: str) -> float:
    value = value.strip().replace(",", ".")
    parts = value.split(":")
    if len(parts) == 3:
        hours, minutes, seconds = parts
    elif len(parts) == 2:
        hours, minutes, seconds = "0", parts[0], parts[1]
    else:
        hours, minutes, seconds = "0", "0", parts[0]
    return int(hours) * 3600 + int(minutes) * 60 + float(seconds)


def parse_vtt(path: Path) -> list[tuple[float, float, str]]:
    cues: list[tuple[float, float, str]] = []
    start = end = None
    lines: list[str] = []

    def flush():
        nonlocal start, end, lines
        if start is not None and lines:
            cues.append((start, end if end is not None else start, " ".join(lines)))
        start = end = None
        lines = []

    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line:
            flush()
            continue
        if line.startswith("WEBVTT") or line.startswith("NOTE"):
            continue
        if line.isdigit():
            # Số thứ tự cue của WebVTT.
            continue
        if "-->" in line:
            flush()
            left, right = line.split("-->")
            start = parse_timestamp(left)
            end = parse_timestamp(right.split()[0])
            continue
        lines.append(line)

    flush()
    return cues


def fallback_cues(text: str, duration: float) -> list[tuple[float, float, str]]:
    """Không có VTT: chia câu và chia đều thời lượng theo độ dài câu."""
    sentences = [s.strip() for s in re.split(r"(?<=[.!?])\s+", text) if s.strip()]
    if not sentences:
        return [(0.0, duration, text)]

    weights = [len(s) for s in sentences]
    total = sum(weights)
    cues = []
    cursor = 0.0
    for sentence, weight in zip(sentences, weights):
        span = duration * (weight / total)
        cues.append((cursor, min(duration, cursor + span), sentence))
        cursor += span
    return cues


def build_clips(storyboard, durations) -> list[Path]:
    CLIPS.mkdir(parents=True, exist_ok=True)
    clips: list[Path] = []

    for scene in storyboard["scenes"]:
        scene_id = scene["id"]
        dur = durations["scenes"][scene_id]
        slot = dur["slot"]
        card_dur = dur["card"]
        video_dur = dur["video"]
        out = CLIPS / f"{scene_id}.mp4"

        cmd = [str(FFMPEG), "-y", "-hide_banner", "-loglevel", "error"]
        filters: list[str] = []
        parts: list[str] = []
        index = 0

        if scene_id == "outro":
            # End card làm nền cho cả slot.
            cmd += ["-loop", "1", "-t", f"{slot:.3f}", "-i", str(CARDS / "outro.png")]
            filters.append(
                f"[{index}:v]scale={WIDTH}:{HEIGHT},fps={FPS},format=yuv420p,"
                f"fade=t=in:st=0:d=0.5,fade=t=out:st={max(0.0, slot - 0.5):.3f}:d=0.5,"
                f"setpts=PTS-STARTPTS[v{index}]"
            )
            parts.append(f"v{index}")
            index += 1
        else:
            if card_dur > 0:
                cmd += ["-loop", "1", "-t", f"{card_dur:.3f}", "-i", str(CARDS / f"{scene_id}.png")]
                filters.append(
                    f"[{index}:v]scale={WIDTH}:{HEIGHT},fps={FPS},format=yuv420p,"
                    f"fade=t=in:st=0:d=0.35,fade=t=out:st={max(0.0, card_dur - 0.35):.3f}:d=0.35,"
                    f"setpts=PTS-STARTPTS[c{index}]"
                )
                parts.append(f"c{index}")
                index += 1

            webm = VIDEO / f"{scene_id}.webm"
            if not webm.exists():
                raise RuntimeError(f"Thiếu video cảnh {scene_id}: {webm}")

            cmd += ["-i", str(webm)]
            actual = probe_duration(webm)
            pad = max(0.0, video_dur - actual)
            chain = f"[{index}:v]scale={WIDTH}:{HEIGHT}"
            if pad > 0.05:
                chain += f",tpad=stop_mode=clone:stop_duration={pad:.3f}"
            chain += (
                f",trim=duration={video_dur:.3f},setpts=PTS-STARTPTS,"
                f"fps={FPS},format=yuv420p[v{index}]"
            )
            filters.append(chain)
            parts.append(f"v{index}")
            index += 1

            if len(parts) > 1:
                filters.append(
                    "".join(f"[{p}]" for p in parts) + f"concat=n={len(parts)}:v=1:a=0[out]"
                )
                out_label = "[out]"
            else:
                out_label = parts[0]

        cmd += [
            "-filter_complex", ";".join(filters),
            "-map", out_label,
            "-an",
            "-c:v", "libx264", "-preset", "veryfast", "-crf", "18",
            "-pix_fmt", "yuv420p", "-r", str(FPS),
            str(out),
        ]
        run(cmd)
        clips.append(out)
        print(f"  ✓ clip {scene_id}.mp4  {slot:5.1f}s")

    return clips


def concat_clips(clips: list[Path]) -> Path:
    listing = WORK / "clips.txt"
    listing.write_text(
        "\n".join(f"file '{clip}'" for clip in clips) + "\n", encoding="utf-8"
    )
    target = WORK / "concat.mp4"
    run([
        str(FFMPEG), "-y", "-hide_banner", "-loglevel", "error",
        "-f", "concat", "-safe", "0", "-i", str(listing),
        "-c", "copy", "-movflags", "+faststart", str(target),
    ])
    return target


def build_audio(storyboard, durations) -> Path:
    padded: list[Path] = []
    for scene in storyboard["scenes"]:
        scene_id = scene["id"]
        slot = durations["scenes"][scene_id]["slot"]
        source = AUDIO / f"{scene_id}.mp3"
        target = WORK / "audio_padded" / f"{scene_id}.m4a"
        target.parent.mkdir(parents=True, exist_ok=True)
        run([
            str(FFMPEG), "-y", "-hide_banner", "-loglevel", "error",
            "-i", str(source),
            "-af", f"apad=whole_dur={slot:.3f},atrim=duration={slot:.3f}",
            "-ar", "44100", "-ac", "2", "-c:a", "aac", "-b:a", "192k",
            str(target),
        ])
        padded.append(target)

    listing = WORK / "audio.txt"
    listing.write_text("\n".join(f"file '{item}'" for item in padded) + "\n", encoding="utf-8")
    target = WORK / "audio.m4a"
    run([
        str(FFMPEG), "-y", "-hide_banner", "-loglevel", "error",
        "-f", "concat", "-safe", "0", "-i", str(listing),
        "-c", "copy", str(target),
    ])
    return target


def build_subtitles(storyboard, durations) -> Path:
    header = """[Script Info]
ScriptType: v4.00+
PlayResX: 1920
PlayResY: 1080
WrapStyle: 2
ScaledBorderAndShadow: yes
YCbCr Matrix: TV.709

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Lato,44,&H001B1B18,&H001B1B18,&H00FDFDFC,&H00FFFFFF,0,0,0,0,100,100,0,0,1,2.4,0,2,120,120,46,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""

    events: list[str] = []
    cursor = 0.0

    for scene in storyboard["scenes"]:
        scene_id = scene["id"]
        dur = durations["scenes"][scene_id]
        vtt = AUDIO / f"{scene_id}.vtt"

        if vtt.exists():
            cues = parse_vtt(vtt)
        else:
            cues = fallback_cues(scene["narration"], dur["narration"])

        for start, end, text in cues:
            text = text.replace("\n", " ").strip()
            if not text:
                continue
            events.append(
                f"Dialogue: 0,{ass_time(cursor + start)},{ass_time(cursor + end)},"
                f"Default,,0,0,0,,{text}"
            )

        cursor += dur["slot"]

    target = WORK / "subs.ass"
    target.write_text(header + "\n".join(events) + "\n", encoding="utf-8")
    print(f"  ✓ phụ đề: {len(events)} cue")
    return target


def write_storyboard_md(storyboard, durations, total: float, output: Path) -> None:
    lines = [
        "# TrustGate — Demo video shot list",
        "",
        f"- Thời lượng: **{total:.1f}s** ({int(total // 60)}:{int(total % 60):02d})",
        f"- Độ phân giải: {WIDTH}x{HEIGHT} @ {FPS}fps · H.264 + AAC · phụ đề tiếng Anh burn-in",
        "",
        "| # | Cảnh | Vai trò | Title card | Lời dẫn | Slot |",
        "| --- | --- | --- | --- | --- | --- |",
    ]

    for index, scene in enumerate(storyboard["scenes"], start=1):
        dur = durations["scenes"][scene["id"]]
        card = scene.get("card") or "—"
        role = scene.get("role") or "—"
        lines.append(
            f"| {index} | `{scene['id']}` | {role} | {card} | {scene['narration']} | {dur['slot']:.1f}s |"
        )

    lines += [
        "",
        "## Lời dẫn đầy đủ",
        "",
    ]
    for index, scene in enumerate(storyboard["scenes"], start=1):
        lines += [f"**{index}. {scene.get('card') or scene['id']}**", "", f"> {scene['narration']}", ""]

    output.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"  ✓ shot list: {output}")


def main() -> int:
    storyboard = json.loads((HERE / "storyboard.json").read_text(encoding="utf-8"))
    durations = json.loads((WORK / "durations.json").read_text(encoding="utf-8"))

    if not FFMPEG.exists():
        print("Thiếu ffmpeg — chạy scripts/demo-video/setup.sh trước.", file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    print("==> Ghép clip từng cảnh")
    clips = build_clips(storyboard, durations)

    print("==> Nối các clip")
    video = concat_clips(clips)

    print("==> Dựng track âm thanh")
    audio = build_audio(storyboard, durations)

    print("==> Dựng phụ đề")
    subs = build_subtitles(storyboard, durations)

    print("==> Burn phụ đề + mux âm thanh")
    output = OUT_DIR / "trustgate-demo.mp4"
    run([
        str(FFMPEG), "-y", "-hide_banner", "-loglevel", "error",
        "-i", str(video), "-i", str(audio),
        "-vf", f"ass={subs}:fontsdir={FONTS_DIR}",
        "-map", "0:v:0", "-map", "1:a:0",
        "-c:v", "libx264", "-preset", "slow", "-crf", "20",
        "-pix_fmt", "yuv420p", "-r", str(FPS),
        "-c:a", "aac", "-b:a", "192k",
        "-movflags", "+faststart",
        str(output),
    ])

    total = probe_duration(output)
    size_mb = output.stat().st_size / 1024 / 1024
    limit = float(storyboard.get("max_total_seconds", 180))

    print(f"  ✓ {output}  {total:.1f}s  {size_mb:.1f} MB")

    if total > limit:
        print(f"VƯỢT ngân sách: {total:.1f}s > {limit:.0f}s", file=sys.stderr)
        return 2

    write_storyboard_md(storyboard, durations, total, OUT_DIR / "trustgate-demo.storyboard.md")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
