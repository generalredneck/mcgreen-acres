#!/usr/bin/env python3
"""List every stable version newer than the installed one, grouped by major.

composer outdated only reports the single newest version, which hides a safer
same-major release (e.g. symfony_mailer 1.6.3 next to 2.0.2). This lists them all.

Usage: upgrade_paths.py <outdated.json> [--include-core] [--only name1,name2]
  outdated.json is the output of `composer outdated "drupal/*" --format=json`.
Prints one line per project: "<package> <installed>: <major>.x: v1, v2 | <major>.x: ..."
Drupal core packages and dev-* versions are skipped (core is excluded on purpose;
dev versions have no releases).
"""
import json
import re
import subprocess
import sys
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor


def key(version):
    return [int(x) for x in re.findall(r"\d+", version)][:3]


def paths(entry):
    name, installed = entry["name"], entry["version"]
    result = subprocess.run(
        ["composer", "show", "-a", name, "--format=json", "--no-interaction"],
        capture_output=True, text=True,
    )
    try:
        versions = json.loads(result.stdout)["versions"]
    except (ValueError, KeyError):
        return f"{name} {installed}: ERROR reading versions"
    newer = [v for v in versions if re.fullmatch(r"v?\d+\.\d+\.\d+", v) and key(v) > key(installed)]
    by_major = defaultdict(list)
    for v in sorted(newer, key=key):
        by_major[key(v)[0]].append(v)
    body = " | ".join(f"{m}.x: {', '.join(vs)}" for m, vs in sorted(by_major.items()))
    return f"{name} {installed}: {body or 'none'}"


def main():
    include_core = "--include-core" in sys.argv
    only = None
    if "--only" in sys.argv:
        names = sys.argv[sys.argv.index("--only") + 1].split(",")
        only = {n if n.startswith("drupal/") else "drupal/" + n for n in names}
    entries = json.load(open(sys.argv[1]))["installed"]
    entries = [
        e for e in entries
        if not e["version"].startswith("dev-")
        and (include_core or not e["name"].startswith("drupal/core"))
        and (only is None or e["name"] in only)
    ]
    with ThreadPoolExecutor(max_workers=6) as pool:
        for line in pool.map(paths, entries):
            print(line)


if __name__ == "__main__":
    main()
