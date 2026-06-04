#!/usr/bin/env python3
"""
Export service icons from generated PNGs (solid #000000 background).

Modes:
  --from-regen       Flood-fill background + snap to cyan/white (sharp, can look pixelated).
  --flood-only       Flood-fill background only; keeps generator anti-aliasing.
  --copy-source      Copy regen PNGs to content/media/icons-source/ (no processing).

For best edges, edit icons-source/*-1024-source.png in GIMP (Color to Alpha on black),
then save over content/media/icons/*.png and *-list.png (384×256), deploy + restore.

Usage:
  python3 scripts/normalize-service-icons.py --copy-source
  python3 scripts/normalize-service-icons.py --from-regen
  python3 scripts/normalize-service-icons.py --flood-only --from-regen
  python3 scripts/normalize-service-icons.py path/to/source.png
"""

from __future__ import annotations

import argparse
import sys
from collections import deque
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
ICONS_DIR = ROOT / 'content' / 'media' / 'icons'
SOURCE_DIR = ROOT / 'content' / 'media' / 'icons-source'
REGEN_DIR = Path.home() / '.cursor/projects/media-keith-Data-work-asc-ai-boiler/assets'

FULL_SIZE = 1024
LIST_WIDTH = 384
LIST_HEIGHT = 256
BACKGROUND_MAX_CHANNEL = 48
WHITE_LUM = 230
WHITE_CHROMA = 50

CYAN = (103, 216, 239, 255)
WHITE = (248, 248, 242, 255)
TRANSPARENT = (0, 0, 0, 0)

REGEN_MAP = {
	'service-design.png': 'service-design-regen.png',
	'service-hosting.png': 'service-hosting-regen.png',
	'service-maintenance.png': 'service-maintenance-regen.png',
}


def lum(r: int, g: int, b: int) -> float:
	return 0.299 * r + 0.587 * g + 0.114 * b


def is_background_pixel(r: int, g: int, b: int, a: int) -> bool:
	if a < 12:
		return True
	return max(r, g, b) <= BACKGROUND_MAX_CHANNEL


def is_white_pixel(r: int, g: int, b: int) -> bool:
	max_c = max(r, g, b)
	min_c = min(r, g, b)
	return lum(r, g, b) >= WHITE_LUM and (max_c - min_c) <= WHITE_CHROMA


def flood_remove_background(im: Image.Image) -> Image.Image:
	"""Remove background connected to the image border (uniform transparency)."""
	im = im.convert('RGBA')
	width, height = im.size
	pixels = im.load()
	seen = set()
	queue: deque[tuple[int, int]] = deque()

	for x in range(width):
		queue.append((x, 0))
		queue.append((x, height - 1))
	for y in range(height):
		queue.append((0, y))
		queue.append((width - 1, y))

	while queue:
		x, y = queue.popleft()
		if x < 0 or y < 0 or x >= width or y >= height:
			continue
		if (x, y) in seen:
			continue
		seen.add((x, y))
		r, g, b, a = pixels[x, y]
		if not is_background_pixel(r, g, b, a):
			continue
		pixels[x, y] = TRANSPARENT
		queue.append((x + 1, y))
		queue.append((x - 1, y))
		queue.append((x, y + 1))
		queue.append((x, y - 1))

	return im


def snap_to_palette(im: Image.Image) -> Image.Image:
	"""Art pixels only: solid opaque cyan or white."""
	im = im.convert('RGBA')
	out = Image.new('RGBA', im.size, TRANSPARENT)
	src = im.load()
	dst = out.load()

	for y in range(im.height):
		for x in range(im.width):
			r, g, b, a = src[x, y]
			if a < 12:
				continue
			if is_background_pixel(r, g, b, a):
				continue
			if is_white_pixel(r, g, b):
				dst[x, y] = WHITE
				continue
			dst[x, y] = CYAN

	return out


def export_palette_icon(im: Image.Image, flood_only: bool = False) -> Image.Image:
	im = flood_remove_background(im)
	if flood_only:
		return im
	return snap_to_palette(im)


def square_crop_pad(im: Image.Image) -> Image.Image:
	bbox = im.getbbox()
	if not bbox:
		raise RuntimeError('No visible artwork in icon')
	cropped = im.crop(bbox)
	cw, ch = cropped.size
	side = max(cw, ch)
	padded = Image.new('RGBA', (side, side), TRANSPARENT)
	padded.paste(cropped, ((side - cw) // 2, (side - ch) // 2))
	return padded


def copy_sources_from_regen() -> None:
	SOURCE_DIR.mkdir(parents=True, exist_ok=True)
	for full_name, regen_name in REGEN_MAP.items():
		src = REGEN_DIR / regen_name
		if not src.is_file():
			raise FileNotFoundError(f'Missing regen file: {src}')
		stem = full_name.replace('.png', '')
		dest = SOURCE_DIR / f'{stem}-1024-source.png'
		dest.write_bytes(src.read_bytes())
		print(f'Wrote {dest}')


def save_pair(src: Path, full_name: str, flood_only: bool = False) -> None:
	processed = export_palette_icon(Image.open(src), flood_only=flood_only)
	padded = square_crop_pad(processed)
	resample = Image.Resampling.LANCZOS if flood_only else Image.Resampling.NEAREST
	full = padded.resize((FULL_SIZE, FULL_SIZE), resample)
	small = full.resize((LIST_WIDTH, LIST_HEIGHT), resample)

	full_path = ICONS_DIR / full_name
	list_path = ICONS_DIR / full_name.replace('.png', '-list.png')
	full.save(full_path, format='PNG', optimize=True)
	small.save(list_path, format='PNG', optimize=True)

	partial = sum(
		1
		for y in range(full.height)
		for x in range(full.width)
		if 0 < full.getpixel((x, y))[3] < 255
	)
	colors = {full.getpixel((x, y)) for y in range(0, full.height, 50) for x in range(0, full.width, 50) if full.getpixel((x, y))[3]}
	print(f'{full_name}: partial_alpha={partial} palette={colors} bytes={full_path.stat().st_size}')


def main() -> int:
	parser = argparse.ArgumentParser()
	parser.add_argument('sources', nargs='*', type=Path)
	parser.add_argument('--from-regen', action='store_true')
	parser.add_argument(
		'--flood-only',
		action='store_true',
		help='Remove background only; do not snap to solid cyan/white',
	)
	parser.add_argument(
		'--copy-source',
		action='store_true',
		help='Write unprocessed regen files to content/media/icons-source/',
	)
	args = parser.parse_args()

	if args.copy_source:
		copy_sources_from_regen()
		return 0

	if args.from_regen:
		for full_name, regen_name in REGEN_MAP.items():
			src = REGEN_DIR / regen_name
			if not src.is_file():
				print(f'Missing: {src}', file=sys.stderr)
				return 1
			save_pair(src, full_name, flood_only=args.flood_only)
		return 0

	if not args.sources:
		parser.print_help()
		return 1

	for src in args.sources:
		save_pair(src, src.name, flood_only=args.flood_only)

	return 0


if __name__ == '__main__':
	sys.exit(main())
