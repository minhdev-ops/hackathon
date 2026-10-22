#!/usr/bin/env bash
# Chạy toàn bộ pipeline demo video.
#
#   bash scripts/demo-video/build.sh              # setup + lời dẫn + quay + ghép
#   bash scripts/demo-video/build.sh --narrate    # chỉ sinh lời dẫn
#   bash scripts/demo-video/build.sh --record     # chỉ quay lại (giữ lời dẫn cũ)
#   bash scripts/demo-video/build.sh --assemble   # chỉ ghép lại từ video + audio có sẵn
set -euo pipefail

cd "$(dirname "$0")"
ROOT="$(cd ../.. && pwd)"

MODE="all"
case "${1:-}" in
    --narrate)  MODE="narrate" ;;
    --record)   MODE="record" ;;
    --assemble) MODE="assemble" ;;
    "")         MODE="all" ;;
    *) echo "Tham số không hợp lệ: $1" >&2; exit 1 ;;
esac

if [ ! -x ".venv/bin/python" ] || [ ! -e "bin/ffmpeg" ]; then
    echo "==> Cài đặt công cụ"
    bash setup.sh
fi

if [ "$MODE" = "all" ] || [ "$MODE" = "narrate" ]; then
    echo "==> Sinh lời dẫn (edge-tts)"
    .venv/bin/python narrate.py
fi

if [ "$MODE" = "all" ] || [ "$MODE" = "record" ]; then
    echo "==> Render title card"
    node titles.mjs

    echo "==> Quay các cảnh (Playwright)"
    rm -rf "$ROOT/demo-video/work/video"
    mkdir -p "$ROOT/demo-video/work/video"
    (cd "$ROOT" && npx playwright test --config=scripts/demo-video/playwright.config.js)
fi

if [ "$MODE" = "all" ] || [ "$MODE" = "assemble" ]; then
    echo "==> Ghép video cuối"
    .venv/bin/python assemble.py
fi

echo "==> Xong: demo-video/out/trustgate-demo.mp4"
