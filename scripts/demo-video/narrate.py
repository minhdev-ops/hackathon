#!/usr/bin/env python3
"""
Sinh lời dẫn tiếng Anh cho từng cảnh của video demo.

- edge-tts (neural voice) -> mp3 + WebVTT (word timing)
- gTTS làm fallback khi edge-tts lỗi mạng (không có VTT -> phụ đề suy từ câu)

Đầu ra:
    demo-video/work/audio/<id>.mp3
    demo-video/work/audio/<id>.vtt
    demo-video/work/durations.json   -> hợp đồng độ dài giữa stage quay và stage ghép
"""
from __future__ import annotations

import json
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = ROOT / "demo-video" / "work"
AUDIO = WORK / "audio"

FFMPEG = HERE / "bin" / "ffmpeg"
VENV_PY = HERE / ".venv" / "bin" / "python"

DURATION_RE = re.compile(r"Duration: (\d+):(\d+):(\d+\.\d+)")


def probe_duration(path: Path) -> float:
    """Đọc duration của file media bằng ffmpeg (không cần ffprobe)."""
    proc = subprocess.run(
        [str(FFMPEG), "-i", str(path)],
        capture_output=True,
        text=True,
    )
    match = DURATION_RE.search(proc.stderr)
    if not match:
        raise RuntimeError(f"Không đọc được duration của {path.name}")

    hours, minutes, seconds = match.groups()
    return int(hours) * 3600 + int(minutes) * 60 + float(seconds)


def synth_edge_tts(text: str, mp3: Path, vtt: Path, voice: str, rate: str) -> bool:
    cmd = [
        str(HERE / ".venv" / "bin" / "edge-tts"),
        "--voice", voice,
        "--rate", rate,
        "--text", text,
        "--write-media", str(mp3),
        "--write-subtitles", str(vtt),
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True)
    return proc.returncode == 0 and mp3.exists() and mp3.stat().st_size > 1000


def synth_gtts(text: str, mp3: Path) -> bool:
    code = (
        "from gtts import gTTS\n"
        f"gTTS({text!r}, lang='en').save({str(mp3)!r})\n"
    )
    proc = subprocess.run([str(VENV_PY), "-c", code], capture_output=True, text=True)
    return proc.returncode == 0 and mp3.exists() and mp3.stat().st_size > 1000


def main() -> int:
    storyboard = json.loads((HERE / "storyboard.json").read_text(encoding="utf-8"))
    voice = storyboard.get("voice", "en-US-AndrewNeural")
    card_seconds = float(storyboard.get("card_seconds", 1.5))
    pad_seconds = float(storyboard.get("pad_seconds", 0.35))

    if not FFMPEG.exists():
        print("Thiếu ffmpeg — chạy scripts/demo-video/setup.sh trước.", file=sys.stderr)
        return 1

    if AUDIO.exists():
        shutil.rmtree(AUDIO)
    AUDIO.mkdir(parents=True, exist_ok=True)

    durations = {"scenes": {}}
    total = 0.0
    failures = []

    for scene in storyboard["scenes"]:
        scene_id = scene["id"]
        text = scene["narration"]
        mp3 = AUDIO / f"{scene_id}.mp3"
        vtt = AUDIO / f"{scene_id}.vtt"

        # Câu càng ngắn thì giọng đọc càng nhanh hơn -> bám sát `target`.
        target = float(scene.get("target", 12))
        rate = "+0%"
        ok = False

        for attempt in range(3):
            if synth_edge_tts(text, mp3, vtt, voice, rate):
                ok = True
                break
            print(f"    edge-tts lỗi (lần {attempt + 1}/3) cho cảnh {scene_id}", file=sys.stderr)
            time.sleep(2 + attempt * 3)

        if not ok:
            print(f"    fallback gTTS cho cảnh {scene_id}", file=sys.stderr)
            ok = synth_gtts(text, mp3)
            if ok and vtt.exists():
                vtt.unlink()

        if not ok:
            failures.append(scene_id)
            continue

        narration = probe_duration(mp3)
        card = card_seconds if scene.get("card") else 0.0

        # Cảnh outro dùng luôn card làm hình nền => không tách card riêng.
        if scene_id == "outro":
            card = 0.0

        slot = round(narration + pad_seconds, 3)
        video = round(slot - card, 3)

        if video < 1.0:
            # Cảnh quá ngắn: bỏ card để chừa chỗ cho hành động.
            card = 0.0
            slot = round(narration + pad_seconds, 3)
            video = slot

        durations["scenes"][scene_id] = {
            "narration": round(narration, 3),
            "card": round(card, 3),
            "slot": slot,
            "video": video,
        }
        total += slot
        print(f"  ✓ {scene_id:<16} lời dẫn {narration:5.1f}s  card {card:3.1f}s  video {video:5.1f}s")

    if failures:
        print(f"Không sinh được lời dẫn cho: {', '.join(failures)}", file=sys.stderr)
        return 1

    durations["total_slot"] = round(total, 3)
    durations["card_seconds"] = card_seconds
    durations["pad_seconds"] = pad_seconds

    WORK.mkdir(parents=True, exist_ok=True)
    (WORK / "durations.json").write_text(
        json.dumps(durations, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    limit = float(storyboard.get("max_total_seconds", 180))
    print(f"  Tổng slot: {total:.1f}s (giới hạn {limit:.0f}s)")
    if total > limit:
        print("VƯỢT ngân sách thời lượng — cần rút lời dẫn.", file=sys.stderr)
        return 2

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
