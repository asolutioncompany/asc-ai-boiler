#!/usr/bin/env python3
"""
Build project card stock images: fake website layout with the original photo as the hero.

Backs up existing stock/*.jpg to stock/originals/ before overwriting (for revert).

Usage:
  python3 scripts/generate-project-mockup-images.py
"""

from __future__ import annotations

import shutil
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
STOCK = ROOT / 'content' / 'media' / 'stock'
ORIGINALS = STOCK / 'originals'

WIDTH = 1536
HEIGHT = 1024
CHROME_H = 32
SITE_HEADER_H = 56
HERO_H = 400
CONTENT_PAD = 48

BG = (16, 20, 24)
SURFACE = (26, 32, 40)
MUTED_BG = (46, 54, 72)
CYAN = (103, 216, 239)
FG = (248, 248, 242)

PROJECT_FILES = (
	'project-pool-service.jpg',
	'project-landscaping.jpg',
	'project-dog-groomer.jpg',
	'projects-default.jpg',
)


def cover_crop_resize(source: Image.Image, target_w: int, target_h: int) -> Image.Image:
	"""Resize source to cover target_w x target_h, center crop."""
	src = source.convert('RGB')
	sw, sh = src.size
	scale = max(target_w / sw, target_h / sh)
	nw = int(sw * scale)
	nh = int(sh * scale)
	resized = src.resize((nw, nh), Image.Resampling.LANCZOS)
	left = (nw - target_w) // 2
	top = (nh - target_h) // 2
	return resized.crop((left, top, left + target_w, top + target_h))


def draw_chrome(draw: ImageDraw.ImageDraw, y0: int) -> int:
	draw.rectangle((0, y0, WIDTH, y0 + CHROME_H), fill=SURFACE)
	dot_y = y0 + CHROME_H // 2
	for i, color in enumerate(((248, 95, 114), (254, 193, 68), (166, 226, 44))):
		x = 20 + i * 18
		draw.ellipse((x - 5, dot_y - 5, x + 5, dot_y + 5), fill=color)
	return y0 + CHROME_H


def draw_site_header(draw: ImageDraw.ImageDraw, y0: int) -> int:
	y1 = y0 + SITE_HEADER_H
	draw.rectangle((0, y0, WIDTH, y1), fill=SURFACE)
	draw.rectangle((CONTENT_PAD, y0 + 18, CONTENT_PAD + 120, y0 + 38), fill=MUTED_BG)
	draw.rectangle((WIDTH - CONTENT_PAD - 220, y0 + 20, WIDTH - CONTENT_PAD, y0 + 36), fill=MUTED_BG)
	draw.rectangle((WIDTH - CONTENT_PAD - 300, y0 + 20, WIDTH - CONTENT_PAD - 240, y0 + 36), fill=BG)
	draw.rectangle((WIDTH - CONTENT_PAD - 380, y0 + 20, WIDTH - CONTENT_PAD - 320, y0 + 36), fill=BG)
	return y1


def draw_content_blocks(draw: ImageDraw.ImageDraw, y0: int) -> None:
	draw.rectangle((0, y0, WIDTH, HEIGHT), fill=BG)
	block_w = (WIDTH - CONTENT_PAD * 2 - 24) // 2
	block_h = 120
	y = y0 + 32
	draw.rectangle((CONTENT_PAD, y, CONTENT_PAD + 280, y + 14), fill=FG)
	draw.rectangle((CONTENT_PAD, y + 26, CONTENT_PAD + 420, y + 38), fill=MUTED_BG)
	y2 = y + 56
	draw.rectangle((CONTENT_PAD, y2, CONTENT_PAD + block_w, y2 + block_h), fill=SURFACE)
	x2 = CONTENT_PAD + block_w + 24
	draw.rectangle((x2, y2, x2 + block_w, y2 + block_h), fill=SURFACE)
	draw.rectangle((CONTENT_PAD, y2 + block_h + 32, CONTENT_PAD + 200, y2 + block_h + 46), fill=CYAN)


def build_mockup(hero_source: Image.Image) -> Image.Image:
	out = Image.new('RGB', (WIDTH, HEIGHT), BG)
	draw = ImageDraw.Draw(out)

	y = draw_chrome(draw, 0)
	y = draw_site_header(draw, y)

	hero = cover_crop_resize(hero_source, WIDTH, HERO_H)
	out.paste(hero, (0, y))
	y += HERO_H

	# Thin accent under hero
	draw.rectangle((0, y, WIDTH, y + 4), fill=CYAN)
	y += 4

	draw_content_blocks(draw, y)
	return out


def backup_and_build(filename: str) -> None:
	src_path = STOCK / filename
	if not src_path.is_file():
		raise FileNotFoundError(src_path)

	ORIGINALS.mkdir(parents=True, exist_ok=True)
	backup_path = ORIGINALS / filename
	if not backup_path.is_file():
		shutil.copy2(src_path, backup_path)
		print(f'Backed up {filename} -> originals/')
	else:
		print(f'Keep existing backup for {filename}')

	source = Image.open(backup_path)
	mockup = build_mockup(source)
	mockup.save(src_path, format='JPEG', quality=88, optimize=True)
	print(f'Wrote {src_path} ({mockup.size[0]}x{mockup.size[1]})')


def main() -> int:
	for name in PROJECT_FILES:
		backup_and_build(name)
	return 0


if __name__ == '__main__':
	raise SystemExit(main())
