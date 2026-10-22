#!/usr/bin/env bash
# Cài đặt công cụ cho pipeline demo video:
#   - edge-tts        : sinh lời dẫn tiếng Anh (neural voice) + WebVTT word timing
#   - gTTS            : fallback khi edge-tts lỗi mạng
#   - imageio-ffmpeg  : static ffmpeg (libx264 + aac + mp4 + libass), không cài ffmpeg hệ thống
set -euo pipefail

cd "$(dirname "$0")"

VENV=".venv"

if [ ! -d "$VENV" ]; then
    echo "==> Tạo virtualenv $VENV"
    python3 -m venv "$VENV"
fi

echo "==> Cài edge-tts, gTTS, imageio-ffmpeg"
"$VENV/bin/pip" install -q --upgrade pip >/dev/null
"$VENV/bin/pip" install -q edge-tts gTTS imageio-ffmpeg

echo "==> Link ffmpeg tĩnh vào bin/ffmpeg"
mkdir -p bin
FFMPEG_BIN="$("$VENV/bin/python" -c 'import imageio_ffmpeg; print(imageio_ffmpeg.get_ffmpeg_exe())')"
ln -sf "$FFMPEG_BIN" bin/ffmpeg

echo "==> Kiểm tra encoder bắt buộc"
missing=0
# Lưu output vào biến trước: `grep -q` đóng pipe sớm sẽ khiến ffmpeg nhận
# SIGPIPE và `pipefail` báo sai là thiếu encoder.
ENCODERS="$(bin/ffmpeg -hide_banner -encoders 2>&1)"
FILTERS="$(bin/ffmpeg -hide_banner -filters 2>&1)"

for enc in libx264 aac; do
    if ! grep -q "$enc" <<<"$ENCODERS"; then
        echo "    THIẾU encoder: $enc" >&2
        missing=1
    fi
done
for filt in ass overlay; do
    if ! grep -qE "^ *[TSC.]+ +$filt " <<<"$FILTERS"; then
        echo "    THIẾU filter: $filt" >&2
        missing=1
    fi
done
[ "$missing" -eq 0 ] || { echo "ffmpeg thiếu tính năng bắt buộc" >&2; exit 1; }

echo "==> OK: $(bin/ffmpeg -version 2>/dev/null | head -1)"
echo "    python: $("$VENV/bin/python" --version 2>&1)"
