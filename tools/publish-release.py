#!/usr/bin/env python3
"""Publish the already validated ZIP without overwriting an existing release or tag."""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import re
import subprocess

STABLE_TAG = r"v(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)"


def run(*args: str, optional_404: bool = False) -> str | None:
    process = subprocess.run(args, capture_output=True, text=True, check=False)
    if process.returncode:
        if optional_404 and re.search(r"\bHTTP 404\b", process.stderr):
            return None
        raise RuntimeError(process.stderr.strip() or f"Command failed: {args[0]}")
    return process.stdout.strip()


def github_json(endpoint: str, optional_404: bool = False) -> dict | None:
    body = run("gh", "api", endpoint, optional_404=optional_404)
    if body is None:
        return None
    result = json.loads(body)
    if not isinstance(result, dict):
        raise RuntimeError("GitHub returned an unexpected response.")
    return result


def main_cli() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--tag", required=True)
    parser.add_argument("--allow-create-tag", action="store_true", help="Allow a manual dispatch to create a missing tag at HEAD.")
    parser.add_argument("--skip-existing-release", action="store_true", help="Preserve an existing release during a repeated main-branch publication.")
    args = parser.parse_args()
    repo = os.environ.get("GH_REPO", "")
    if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repo):
        parser.exit(1, "GH_REPO must identify the current GitHub repository.\n")
    if not re.fullmatch(STABLE_TAG, args.tag):
        parser.exit(1, "A stable tag in vX.Y.Z format is required.\n")
    asset = Path("dist/nexo-cursos.zip")
    if not asset.is_file():
        parser.exit(1, "The validated dist/nexo-cursos.zip asset is missing.\n")
    theme_asset = Path("dist/shalom-formacao.zip")
    if not theme_asset.is_file():
        parser.exit(1, "The validated companion theme asset is missing.\n")
    try:
        target = run("git", "rev-parse", "HEAD")
        if not target or not re.fullmatch(r"[0-9a-f]{40}", target):
            raise RuntimeError("Could not identify the selected source commit.")
        if args.skip_existing_release:
            existing = github_json(f"repos/{repo}/releases/tags/{args.tag}", optional_404=True)
            if existing is not None:
                if existing.get("tag_name") != args.tag:
                    raise RuntimeError("GitHub returned a release for an unexpected tag.")
                print(f"Release {args.tag} already exists and was preserved. No tag or asset was changed.")
                return
        ref = github_json(f"repos/{repo}/git/ref/tags/{args.tag}", optional_404=True)
        if ref is None and not args.allow_create_tag:
            raise RuntimeError("The pushed release tag is missing on GitHub.")
        if ref is not None:
            reference = ref.get("object", {})
            for _ in range(5):
                if not isinstance(reference, dict):
                    raise RuntimeError("GitHub returned an invalid tag object.")
                object_type = reference.get("type")
                sha = reference.get("sha", "")
                if not isinstance(sha, str) or not re.fullmatch(r"[0-9a-f]{40}", sha):
                    raise RuntimeError("GitHub returned an invalid tag SHA.")
                if object_type == "commit":
                    break
                if object_type != "tag":
                    raise RuntimeError("The release tag does not reference a commit.")
                tag_object = github_json(f"repos/{repo}/git/tags/{sha}")
                reference = tag_object.get("object", {})
            else:
                raise RuntimeError("Tag object nesting exceeds the supported limit.")
            if sha != target:
                raise RuntimeError("The existing tag points to a different commit. No tag or release was changed.")
        command = ["gh", "release", "create", args.tag, str(asset), str(theme_asset), "--generate-notes", "--title", f"Nexo Cursos {args.tag}"]
        command.extend(["--target", target] if ref is None else ["--verify-tag"])
        result = run(*command)
        print(result)
    except (RuntimeError, OSError, ValueError) as error:
        parser.exit(1, f"Release not published: {error}\n")


if __name__ == "__main__":
    main_cli()
