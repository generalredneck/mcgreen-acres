#!/usr/bin/env python3
"""Per-release digest cache for drupal-update-report.

Release facts (what a release changed) do not depend on this codebase, so they
are researched once and reused by later runs. Repo-specific impact (patches,
custom code, config) is NOT cached — recompute it each run from the digests.

Cache layout: <cache>/releases/<project>/<version>.json
<cache> defaults to <skill dir>/cache, override with $DRUPAL_UPDATE_CACHE_DIR.

Commands
  plan <outdated.json> [--only a,b] [--max-age-days N] [--partial-age-days N]
      For every project (composer packages grouped by drupal.org project; core
      is included, with versions taken from the outdated entry), list each stable
      version between installed and latest and say whether its digest is
      cached / stale / partial / missing. Prints JSON to stdout, a summary to
      stderr. Only "missing", "stale" and "partial" versions need research.
  get <project> <version>
      Print the digest (exit 1 if absent).
  put <project> <version> [file|-]
      Validate and store a digest (JSON from file or stdin).
  list [project]
      One line per cached digest.
  invalidate <project> [version]
      Delete one digest or all of a project's.
  schema
      Print an example digest.

Digest rules (the point is that they are valid for ANY codebase):
  - facts are about the release itself, i.e. the change from the PREVIOUS
    release; for tag-diff findings diff previous-tag..this-tag, never
    against web/modules/contrib (that tree has local patches applied);
  - status "complete" = the drupal.org release notes (or equivalent) were read;
    "partial" = notes missing/thin and details inferred from a tag diff or
    feed only — partial digests are retried after --partial-age-days;
  - never put repo-specific conclusions in a digest ("affects
    custom_foo", "patch X conflicts"); only upstream facts.
"""
import datetime as dt
import json
import os
import sys
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from dur_common import Packages, cache_base, stable_versions_newer, vkey

LIST_FIELDS = ("breaking", "update_hooks", "config_changes", "requirements",
               "notable_fixes", "issues", "changed_identifiers", "sources")
REQUIRED = ("project", "version", "status", "summary", "security") + LIST_FIELDS
EXAMPLE = {
    "project": "metatag",
    "version": "2.2.4",
    "status": "complete",
    "fetched": "2026-11-12",
    "release_url": "https://www.drupal.org/project/metatag/releases/2.2.4",
    "summary": "One or two sentences about the release.",
    "security": {"is_security_release": False, "advisories": []},
    "breaking": ["Removed/changed/deprecated APIs, renamed services, dropped config"],
    "update_hooks": ["metatag_update_8205: what it does (hook_update_N / post_update, by name)"],
    "config_changes": ["config schema/default changes that need `drush cex` after updb"],
    "requirements": ["min core 10.3", "new dependency foo/bar ^2"],
    "notable_fixes": ["short bullets"],
    "issues": ["3054585", "3187215"],
    "changed_identifiers": ["Drupal\\\\metatag\\\\MetatagManager::foo", "route metatag.settings", "service metatag.manager"],
    "sources": ["release-notes", "tag-diff", "update-feed", "change-record", "security-advisory"],
}


def cache_dir():
    return cache_base() / "releases"


def digest_path(project, version):
    safe = lambda s: s.replace("/", "_")
    return cache_dir() / safe(project) / f"{safe(version)}.json"


def age_days(digest):
    try:
        return (dt.date.today() - dt.date.fromisoformat(digest["fetched"])).days
    except (KeyError, ValueError):
        return 10**6


def entry_state(project, version, max_age, partial_age):
    path = digest_path(project, version)
    if not path.exists():
        return "missing"
    try:
        d = json.load(open(path))
    except ValueError:
        return "missing"
    if d.get("status") == "partial":
        return "partial" if age_days(d) >= partial_age else "cached"
    return "stale" if age_days(d) >= max_age else "cached"


def validate(project, version, d):
    errors = [f"missing key: {k}" for k in REQUIRED if k not in d]
    if d.get("project") != project:
        errors.append(f"project is {d.get('project')!r}, expected {project!r}")
    if d.get("version") != version:
        errors.append(f"version is {d.get('version')!r}, expected {version!r}")
    if d.get("status") not in ("complete", "partial"):
        errors.append("status must be 'complete' or 'partial'")
    for k in LIST_FIELDS:
        if k in d and not isinstance(d[k], list):
            errors.append(f"{k} must be a list")
    sec = d.get("security")
    if not (isinstance(sec, dict) and "is_security_release" in sec and isinstance(sec.get("advisories", []), list)):
        errors.append("security must be {is_security_release: bool, advisories: [..]}")
    if not str(d.get("summary", "")).strip():
        errors.append("summary is empty")
    return errors


def cmd_plan(argv):
    args = [a for a in argv if not a.startswith("--")]
    opt = lambda name, default: next((argv[i + 1] for i, a in enumerate(argv) if a == name), default)
    max_age, partial_age = int(opt("--max-age-days", 60)), int(opt("--partial-age-days", 14))
    only = set(opt("--only", "").replace("drupal/", "").split(",")) - {""}
    entries = json.load(open(args[0]))["installed"]
    pkgs = Packages()

    # group composer packages by drupal.org project; core packages share versions
    groups = {}
    for e in entries:
        if e["version"].startswith("dev-"):
            continue
        project = pkgs.project_of(e["name"])
        if only and project not in only and e["name"].replace("drupal/", "") not in only:
            continue
        g = groups.setdefault(project, {"installed": e["version"], "packages": [], "probe": e})
        g["packages"].append(e["name"])
        if e["name"] in (f"drupal/{project}", "drupal/core"):
            g["probe"], g["installed"] = e, e["version"]

    def versions(item):
        project, g = item
        e = g["probe"]
        if project == "drupal":  # core: just installed -> latest, minor/patch steps unknown to composer here
            return project, [v for v in stable_versions_newer("drupal/core", g["installed"])]
        try:
            return project, stable_versions_newer(e["name"], e["version"])
        except Exception as exc:  # noqa: BLE001
            return project, f"ERROR {exc}"

    out, counts = {}, {"cached": 0, "stale": 0, "partial": 0, "missing": 0}
    with ThreadPoolExecutor(max_workers=6) as pool:
        for project, vs in pool.map(versions, sorted(groups.items())):
            g = groups[project]
            if isinstance(vs, str):
                out[project] = {"installed": g["installed"], "packages": g["packages"], "error": vs}
                continue
            states = {v: entry_state(project, v, max_age, partial_age) for v in vs}
            for s in states.values():
                counts[s] += 1
            out[project] = {
                "installed": g["installed"],
                "packages": sorted(g["packages"]),
                "versions": states,
                "needs_research": [v for v, s in states.items() if s != "cached"],
            }
    json.dump({"cache_dir": str(cache_dir()), "projects": out, "counts": counts}, sys.stdout, indent=1)
    print(f"\nversions: {counts}", file=sys.stderr)
    todo = [p for p, v in out.items() if v.get("needs_research")]
    print(f"projects needing research: {len(todo)}/{len(out)}", file=sys.stderr)


def cmd_get(argv):
    path = digest_path(*argv[:2])
    if not path.exists():
        sys.exit(1)
    sys.stdout.write(path.read_text())


def cmd_put(argv):
    project, version = argv[:2]
    src = argv[2] if len(argv) > 2 else "-"
    d = json.load(sys.stdin if src == "-" else open(src))
    d.setdefault("fetched", dt.date.today().isoformat())
    errors = validate(project, version, d)
    if errors:
        print("not stored:\n  " + "\n  ".join(errors), file=sys.stderr)
        sys.exit(2)
    path = digest_path(project, version)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(d, indent=1) + "\n")
    print(f"stored {path}")


def cmd_list(argv):
    root = cache_dir()
    for p in sorted(root.glob(f"{argv[0] if argv else '*'}/*.json"), key=lambda p: (p.parent.name, vkey(p.stem))):
        d = json.load(open(p))
        sec = " SECURITY" if d.get("security", {}).get("is_security_release") else ""
        print(f"{p.parent.name} {p.stem} {d.get('status')} fetched {d.get('fetched')}{sec}")


def cmd_invalidate(argv):
    project = argv[0]
    targets = [digest_path(project, argv[1])] if len(argv) > 1 else list((cache_dir() / project).glob("*.json"))
    for t in targets:
        if t.exists():
            t.unlink()
            print(f"removed {t}")


def main():
    cmds = {"plan": cmd_plan, "get": cmd_get, "put": cmd_put, "list": cmd_list,
            "invalidate": cmd_invalidate, "schema": lambda _: print(json.dumps(EXAMPLE, indent=1))}
    if len(sys.argv) < 2 or sys.argv[1] not in cmds:
        sys.exit(__doc__)
    cmds[sys.argv[1]](sys.argv[2:])


if __name__ == "__main__":
    main()
