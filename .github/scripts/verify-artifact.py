#!/usr/bin/env python3
"""Verify a downloaded Acme Coming Soon release artifact and its ZIP contents."""

from __future__ import annotations

import hashlib
import re
import stat
import sys
import zipfile
from pathlib import Path, PurePosixPath


PLUGIN_SLUG = "acme-coming-soon"
VERSION = re.compile(r"[0-9]+\.[0-9]+(?:\.[0-9]+)*")
SHA256 = re.compile(r"[0-9a-f]{64}")


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    raise SystemExit(1)


def main() -> None:
    if len(sys.argv) != 3:
        fail("Usage: verify-artifact.py VERSION ARTIFACT_ROOT")

    version = sys.argv[1]
    root = Path(sys.argv[2]).resolve()
    runtime = root / PLUGIN_SLUG
    archive = root / f"{PLUGIN_SLUG}-{version}.zip"
    checksum = root / f"{PLUGIN_SLUG}-{version}.zip.sha256"

    if not VERSION.fullmatch(version):
        fail(f"Invalid version: {version}")
    if not runtime.is_dir():
        fail(f"Missing runtime directory: {runtime}")
    if not archive.is_file():
        fail(f"Missing ZIP: {archive}")
    if not checksum.is_file():
        fail(f"Missing checksum file: {checksum}")

    checksum_lines = checksum.read_text(encoding="utf-8").strip().splitlines()
    if len(checksum_lines) != 1:
        fail("Checksum file must contain exactly one line")

    parts = checksum_lines[0].split(maxsplit=1)
    if len(parts) != 2:
        fail("Malformed checksum file")
    expected_hash = parts[0].lower()
    checksum_name = Path(parts[1].lstrip("*")).name
    if not SHA256.fullmatch(expected_hash):
        fail("Checksum is not a SHA-256 digest")
    if checksum_name != archive.name:
        fail(f"Checksum file names {checksum_name}, expected {archive.name}")

    actual_hash = hashlib.sha256(archive.read_bytes()).hexdigest()
    if actual_hash != expected_hash:
        fail("ZIP SHA-256 checksum does not match")

    runtime_files: dict[str, Path] = {}
    for path in runtime.rglob("*"):
        if path.is_symlink():
            fail(f"Symlink in runtime directory: {path.relative_to(runtime)}")
        if path.is_file():
            runtime_files[path.relative_to(runtime).as_posix()] = path
        elif not path.is_dir():
            fail(f"Unsupported file type in runtime directory: {path.relative_to(runtime)}")

    if f"{PLUGIN_SLUG}.php" not in runtime_files:
        fail(f"Runtime directory is missing {PLUGIN_SLUG}.php")

    try:
        with zipfile.ZipFile(archive) as package:
            bad_member = package.testzip()
            if bad_member is not None:
                fail(f"ZIP CRC check failed for {bad_member}")

            zip_files: dict[str, bytes] = {}
            seen_names: set[str] = set()
            for info in package.infolist():
                name = info.filename
                if not name or "\\" in name or name.startswith("/") or "\x00" in name:
                    fail(f"Unsafe ZIP path: {name!r}")
                path = PurePosixPath(name)
                raw_parts = name[:-1].split("/") if info.is_dir() else name.split("/")
                if any(part in ("", ".", "..") for part in raw_parts):
                    fail(f"Unsafe ZIP path: {name}")
                if any(part in ("", ".", "..") for part in path.parts):
                    fail(f"Unsafe ZIP path: {name}")
                if not name.startswith(f"{PLUGIN_SLUG}/"):
                    fail(f"ZIP entry is outside {PLUGIN_SLUG}/: {name}")
                if name in seen_names:
                    fail(f"Duplicate ZIP entry: {name}")
                seen_names.add(name)
                if info.is_dir():
                    continue

                mode = info.external_attr >> 16
                if stat.S_ISLNK(mode):
                    fail(f"Symlink in ZIP: {name}")
                if info.flag_bits & 0x1:
                    fail(f"Encrypted ZIP entry: {name}")

                zip_files[name.removeprefix(f"{PLUGIN_SLUG}/")] = package.read(info)
    except zipfile.BadZipFile as exc:
        fail(f"Invalid ZIP: {exc}")

    runtime_names = set(runtime_files)
    zip_names = set(zip_files)
    if runtime_names != zip_names:
        missing = sorted(runtime_names - zip_names)
        extra = sorted(zip_names - runtime_names)
        fail(f"ZIP/runtime file mismatch; missing={missing}, extra={extra}")

    for relative, path in runtime_files.items():
        runtime_hash = hashlib.sha256(path.read_bytes()).hexdigest()
        zip_hash = hashlib.sha256(zip_files[relative]).hexdigest()
        if runtime_hash != zip_hash:
            fail(f"ZIP content differs from runtime directory: {relative}")

    print(
        f"Artifact verification passed for Acme Coming Soon {version}: "
        f"{len(runtime_files)} files, SHA-256 {actual_hash}."
    )


if __name__ == "__main__":
    main()
