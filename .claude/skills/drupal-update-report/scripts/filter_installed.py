#!/usr/bin/env python3
"""Drop outdated packages whose modules/themes are not enabled on the site.

Usage: filter_installed.py <outdated.json> [--drush] [--include-disabled]
Writes the filtered JSON (same shape as `composer outdated --format=json`, plus
a "skipped" list of {name, version, latest, reason}) to stdout and a short
summary to stderr. Feed the stdout file to upgrade_paths.py and release_cache.py.

A package is kept when:
  - it is Drupal core (drupal/core*);
  - it ships at least one enabled module/theme/profile; or
  - it ships no module at all (tooling such as drupal/coder, libraries, recipes)
    and is not a metapackage — it can still break the build, so it stays.
It is skipped ("disabled") when it ships modules/themes and none is enabled, or
when it is a metapackage whose own module name is not enabled. Skipped packages
are still installed in vendor/ (usually as a dependency of something else) and
can still constrain Composer — list them under "Not covered" in the report.

Enabled list: config/sync/core.extension.yml by default (what deploys); --drush
uses `lando drush config:get core.extension` (the local database) instead.
--include-disabled keeps everything (records the reason but skips nothing).
"""
import json
import sys

from dur_common import Packages, enabled_from_config, enabled_from_drush


def classify(pkgs, enabled, name):
    """-> (keep: bool, reason: str)"""
    if name.startswith("drupal/core"):
        return True, "core"
    p = pkgs.by_name.get(name)
    if p is None:
        return True, "not in installed.json (kept)"
    bare = name.split("/", 1)[-1]
    mods = pkgs.module_names(name)
    if p.get("type") == "metapackage" and not p.get("install-path"):
        return (bare in enabled), (f"metapackage; module {bare} enabled" if bare in enabled
                                   else f"metapackage; module {bare} not enabled")
    if mods:
        on = sorted(mods & enabled)
        if on:
            return True, "enabled: " + ", ".join(on[:5]) + (" …" if len(on) > 5 else "")
        return False, "disabled: ships " + ", ".join(sorted(mods)[:5]) + (" …" if len(mods) > 5 else "") + ", none enabled"
    return True, "no module (tooling/library/recipe)"


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    include_disabled = "--include-disabled" in sys.argv
    data = json.load(open(args[0]))
    pkgs = Packages()
    enabled = enabled_from_drush() if "--drush" in sys.argv else enabled_from_config()

    kept, skipped = [], []
    for e in data["installed"]:
        keep, reason = classify(pkgs, enabled, e["name"])
        e = dict(e, enabled_reason=reason)
        if keep or include_disabled:
            kept.append(e)
        else:
            skipped.append({k: e.get(k) for k in ("name", "version", "latest")} | {"reason": reason})

    json.dump(dict(data, installed=kept, skipped=skipped), sys.stdout, indent=1)
    print(f"kept {len(kept)}, skipped {len(skipped)} disabled", file=sys.stderr)
    for s in skipped:
        print(f"  skipped {s['name']} {s['version']} -> {s['latest']}: {s['reason']}", file=sys.stderr)


if __name__ == "__main__":
    main()
