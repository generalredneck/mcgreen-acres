"""Shared helpers for the drupal-update-report scripts (stdlib only).

- version helpers (stable versions newer than installed)
- composer metadata: which package lives where, which drupal.org *project* a
  package belongs to, which module/theme machine names a package ships
- which modules/themes are enabled (config/sync/core.extension.yml or drush)
"""
import json
import os
import re
import subprocess
from collections import defaultdict
from pathlib import Path

SKILL_DIR = Path(__file__).resolve().parent.parent


def cache_base():
    """Root of all skill caches (release digests, advisories)."""
    return Path(os.environ.get("DRUPAL_UPDATE_CACHE_DIR") or SKILL_DIR / "cache")


def repo_root():
    """Repo root = nearest ancestor of the skill dir that has composer.json."""
    for p in [SKILL_DIR, *SKILL_DIR.parents]:
        if (p / "composer.json").exists():
            return p
    return Path.cwd()


# ---------------------------------------------------------------- versions
def vkey(version):
    return [int(x) for x in re.findall(r"\d+", version)][:3]


def stable_versions_newer(name, installed):
    """Every stable x.y.z version of `name` newer than `installed` (sorted)."""
    result = subprocess.run(
        ["composer", "show", "-a", name, "--format=json", "--no-interaction"],
        capture_output=True, text=True, cwd=repo_root(),
    )
    try:
        versions = json.loads(result.stdout)["versions"]
    except (ValueError, KeyError):
        raise RuntimeError(f"composer show {name} failed: {result.stderr.strip()[:200]}")
    newer = [v for v in versions if re.fullmatch(r"v?\d+\.\d+\.\d+", v) and vkey(v) > vkey(installed)]
    return sorted(newer, key=vkey)


def group_by_major(versions):
    out = defaultdict(list)
    for v in versions:
        out[vkey(v)[0]].append(v)
    return dict(sorted(out.items()))


# ------------------------------------------------------- composer metadata
class Packages:
    """Reads vendor/composer/installed.json and indexes info.yml files."""

    def __init__(self, root=None):
        self.root = Path(root) if root else repo_root()
        data = json.load(open(self.root / "vendor/composer/installed.json"))
        self.by_name = {p["name"]: p for p in data["packages"]}
        self._infos = None  # package name -> set of module/theme machine names
        self._owner = None  # machine name -> package name

    def path(self, name):
        p = self.by_name.get(name)
        if not p or not p.get("install-path"):
            return None
        return (self.root / "vendor/composer" / p["install-path"]).resolve()

    def _scan(self):
        self._infos, self._owner = {}, {}
        for name, p in self.by_name.items():
            if not name.startswith("drupal/") or not p.get("install-path"):
                continue
            base = self.path(name)
            found = set()
            if base and base.is_dir():
                for dirpath, dirnames, filenames in os.walk(base):
                    dirnames[:] = [d for d in dirnames if d not in ("node_modules", "tests", "vendor", ".git")]
                    for f in filenames:
                        if f.endswith(".info.yml"):
                            found.add(f[: -len(".info.yml")])
            self._infos[name] = found
            for m in found:
                self._owner.setdefault(m, name)

    def module_names(self, name):
        """Machine names of modules/themes/profiles shipped by a package."""
        if self._infos is None:
            self._scan()
        return self._infos.get(name, set())

    def owner_of_module(self, machine_name):
        if self._owner is None:
            self._scan()
        return self._owner.get(machine_name)

    def project_of(self, name):
        """drupal.org project name for a composer package.

        core packages -> 'drupal'; otherwise the project in the source URL
        (git.drupalcode.org/project/<project>.git); metapackages such as
        drupal/commerce_cart or drupal/mailer_transport (no source, no path)
        -> the package that ships a module of that name; else the bare name.
        """
        if name.startswith("drupal/core"):
            return "drupal"
        p = self.by_name.get(name, {})
        m = re.search(r"/project/([^/.]+)(?:\.git)?$", (p.get("source") or {}).get("url", ""))
        if m:
            return m.group(1)
        bare = name.split("/", 1)[-1]
        owner = self.owner_of_module(bare)
        if owner and owner != name:
            return self.project_of(owner)
        return bare


# --------------------------------------------------------- enabled modules
def enabled_from_config(root=None):
    """Enabled module/theme/profile names from config/sync/core.extension.yml."""
    root = Path(root) if root else repo_root()
    names, section = set(), None
    for line in open(root / "config/sync/core.extension.yml"):
        if m := re.match(r"^profile:\s*(\S+)", line):
            names.add(m.group(1))
        elif re.match(r"^\w+:\s*$", line):
            section = line.split(":")[0]
        elif section in ("module", "theme") and (m := re.match(r"^  (\S+):\s*-?\d+\s*$", line)):
            names.add(m.group(1))
    return names


def enabled_from_drush():
    """Enabled names according to the live (Lando) database."""
    out = subprocess.run(
        ["lando", "drush", "config:get", "core.extension", "--format=json"],
        capture_output=True, text=True, check=True,
    ).stdout
    data = json.loads(out)
    return set(data.get("module", {})) | set(data.get("theme", {}))
