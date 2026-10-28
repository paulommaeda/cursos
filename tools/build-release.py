#!/usr/bin/env python3
"""Build the installable WordPress asset using only production files."""
from __future__ import annotations

import argparse
from pathlib import Path
import re
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

VERSION_PATTERN = r"(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)"
TOP_LEVEL = ("nexo-cursos.php", "uninstall.php", "readme.txt", "LICENSE.txt", "GUIA.md")
ASSET_SUFFIXES = {".css", ".js", ".svg", ".png", ".jpg", ".jpeg", ".webp", ".woff", ".woff2"}


def one_match(pattern: str, text: str, label: str) -> str:
    matches = re.findall(pattern, text, re.MULTILINE)
    if len(matches) != 1:
        raise ValueError(f"Expected exactly one {label}.")
    return matches[0].strip()


def build(root: Path, output: Path, tag: str | None) -> tuple[str, int]:
    root = root.resolve(strict=True)
    main = (root / "nexo-cursos.php").read_text(encoding="utf-8")
    readme = (root / "readme.txt").read_text(encoding="utf-8")
    version = one_match(r"^[ \t]*\*[ \t]*Version:[ \t]*([^\r\n]+)$", main, "plugin Version header")
    constant = one_match(r"define\(\s*['\"]NEXO_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", main, "NEXO_VERSION constant")
    stable = one_match(r"^Stable tag:[ \t]*([^\r\n]+)$", readme, "Stable tag")
    if not re.fullmatch(VERSION_PATTERN, version):
        raise ValueError("Plugin version must be stable X.Y.Z.")
    if constant != version or stable != version:
        raise ValueError("Plugin Version, NEXO_VERSION and Stable tag must match.")
    if tag is not None and (not re.fullmatch("v" + VERSION_PATTERN, tag) or tag != "v" + version):
        raise ValueError("Release tag must be vX.Y.Z and match the plugin version.")
    update_uri = one_match(r"^[ \t]*\*[ \t]*Update URI:[ \t]*([^\r\n]+)$", main, "Update URI")
    if update_uri != "https://github.com/paulommaeda/cursos":
        raise ValueError("Unexpected update source.")

    paths = [root / name for name in TOP_LEVEL]
    for folder, suffixes in (("includes", {".php"}), ("assets", ASSET_SUFFIXES)):
        directory = root / folder
        if not directory.is_dir() or directory.is_symlink():
            raise ValueError(f"Required production directory missing or linked: {folder}")
        paths.extend(p for p in directory.rglob("*") if p.is_file() and p.suffix.lower() in suffixes)
    for path in paths:
        if not path.is_file() or path.is_symlink() or not path.resolve().is_relative_to(root):
            raise ValueError(f"Production file missing, linked or outside root: {path.name}")
        if path.suffix == ".php" and not path.read_bytes().startswith(b"<?php"):
            raise ValueError(f"PHP must begin with <?php and have no BOM: {path.name}")
    if root / "includes" / "class-updater.php" not in paths:
        raise ValueError("GitHub updater module missing.")

    if not (root / "includes" / "class-commerce.php").is_file():
        raise ValueError("Course commerce module missing")
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, "w", ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(paths):
            name = "nexo-cursos/" + path.relative_to(root).as_posix()
            entry = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = ZIP_DEFLATED
            archive.writestr(entry, path.read_bytes(), compresslevel=9)
    with ZipFile(output) as archive:
        if archive.testzip() is not None:
            raise ValueError("ZIP integrity validation failed.")
        names = archive.namelist()
        if len(names) != len(set(names)) or "nexo-cursos/nexo-cursos.php" not in names:
            raise ValueError("ZIP plugin layout is invalid.")
        if any(not name.startswith("nexo-cursos/") or ".." in Path(name).parts for name in names):
            raise ValueError("ZIP has an invalid path.")
    return version, len(paths)


def build_theme(root: Path, output: Path, tag: str | None) -> tuple[str, int]:
    root = root.resolve()
    theme = root / "themes" / "shalom-formacao"
    if not theme.exists():
        theme = root.parent / "shalom-formacao"
    theme = theme.resolve(strict=True)
    style = (theme / "style.css").read_text(encoding="utf-8")
    version = one_match(r"^Version:[ \t]*([^\r\n]+)$", style, "theme Version header")
    if not re.fullmatch(VERSION_PATTERN, version):
        raise ValueError("Invalid theme version")
    if tag and tag != "v" + version:
        raise ValueError("Theme version must match release tag")
    required = ["style.css", "functions.php", "index.php", "header.php", "footer.php", "front-page.php", "page.php", "single.php", "404.php", "LICENSE.txt", "readme.txt"]
    paths = [theme / name for name in required]
    if (theme / "screenshot.png").is_file():
        paths.append(theme / "screenshot.png")
    for path in paths:
        if not path.is_file() or path.is_symlink() or not path.resolve().is_relative_to(theme):
            raise ValueError("Invalid theme file: " + path.name)
        if path.suffix == ".php" and not path.read_bytes().startswith(b"<?php"):
            raise ValueError("Theme PHP contains leading output or BOM")
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, "w", ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(paths):
            entry = ZipInfo("shalom-formacao/" + path.name, date_time=(1980,1,1,0,0,0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = ZIP_DEFLATED
            archive.writestr(entry, path.read_bytes(), compresslevel=9)
    with ZipFile(output) as archive:
        if archive.testzip() is not None or "shalom-formacao/style.css" not in archive.namelist():
            raise ValueError("Invalid installable theme ZIP")
    return version, len(paths)


def main_cli() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--output", type=Path, default=Path("dist/nexo-cursos.zip"))
    parser.add_argument("--tag", help="Require a matching stable GitHub tag, such as v1.1.0.")
    args = parser.parse_args()
    try:
        version, count = build(args.root, args.output, args.tag)
        theme_version, theme_count = build_theme(args.root, args.output.with_name("shalom-formacao.zip"), args.tag)
        if theme_version != version:
            raise ValueError("Plugin and theme must share the release version")
    except (OSError, ValueError) as error:
        parser.exit(1, f"Package validation failed: {error}\n")
    print(f"Shalom Formação {theme_version}: {theme_count} files")
    print(f"Nexo Cursos {version}: {count} files; {args.output.stat().st_size} bytes; {args.output}")


if __name__ == "__main__":
    main_cli()
