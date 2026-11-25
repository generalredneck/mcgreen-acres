#!/usr/bin/env python3
"""Drupal security advisories (SAs) matched against what is installed here.

Two sources, merged into a local store at <cache>/advisories/<project>.json:
  - RSS  https://www.drupal.org/security/all/rss.xml  — one request, every
    project, but only the newest ~50 advisories (about two weeks);
  - API  https://www.drupal.org/api-d7/node.json?type=sa&field_project=<nid>
    — one project's full advisory history (2 requests; project nid lookup is
    stored). Fetched only for projects in scope, re-fetched after --max-age-days.
The store grows over time, so later runs need fewer requests.

Commands
  check <filtered.json> [--all-installed] [--no-api] [--max-age-days N] [--refresh]
      Main entry. Refreshes the RSS feed, fetches API history for the outdated
      (and skipped/disabled) projects — or every installed drupal/* project with
      --all-installed — then reports, per project, every advisory whose affected
      range includes the INSTALLED version: id, risk, type, date, the releases
      that fix it, whether the latest release fixes it, and whether the
      package is enabled. JSON on stdout, readable summary on stderr.
      Projects with an unparseable range or a dev-* version are listed
      under "undetermined" for manual review, never silently dropped.
  for-version <project> <version>
      Advisories that name <version> as a fixing release (use this to fill a
      release digest's security.advisories and is_security_release).
  list [project]
      Stored advisories, one line each.
  refresh-feed [--max-age-hours N]
      Just the RSS step.

Network failures never abort a run: the script falls back to the store and
lists what could not be fetched under "errors". PSAs (public service
announcements) are ignored; they are not vulnerabilities.
"""
import datetime as dt
import html
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from concurrent.futures import ThreadPoolExecutor

from dur_common import Packages, cache_base

FEED_URL = "https://www.drupal.org/security/all/rss.xml"
API = "https://www.drupal.org/api-d7/node.json"
UA = "drupal-update-report"  # generic on purpose: no site or personal details
STAGE = {"dev": 0, "alpha": 1, "beta": 2, "rc": 3}


def store_dir():
    return cache_base() / "advisories"


def http_get(url, timeout=30):
    last = None
    for attempt in range(2):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": UA})
            with urllib.request.urlopen(req, timeout=timeout) as r:
                return r.read().decode("utf-8", "replace")
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            last = exc
            time.sleep(1 + attempt)
    raise RuntimeError(f"{url}: {last}")


# ------------------------------------------------------------- versions
def parse_version(v):
    """'8.x-1.18' / '2.0.0-beta2' / 'v1.2.3' -> comparable tuple, or None."""
    v = re.sub(r"^v", "", v.strip())
    v = re.sub(r"^\d+\.x-", "", v)
    m = re.fullmatch(r"(\d+)(?:\.(\d+|x|\*))?(?:\.(\d+|x|\*))?(?:-(alpha|beta|rc|dev)\.?(\d*))?", v)
    if not m:
        return None
    nums = [int(x) if x and x.isdigit() else 0 for x in m.groups()[:3]]
    stage = STAGE.get(m.group(4), 4)
    return (*nums, stage, int(m.group(5) or 0))


def satisfies(version, constraint):
    """True/False, or None when the constraint cannot be parsed."""
    pv = parse_version(version)
    if pv is None or not constraint or not constraint.strip():
        return None
    result = False
    for group in re.split(r"\s*\|\|?\s*", constraint.strip()):
        tokens = re.findall(r"(<=|>=|!=|==|<|>|=)?\s*([0-9][\w.\-*]*)", group)
        if not tokens or re.sub(r"(<=|>=|!=|==|<|>|=)?\s*[0-9][\w.\-*]*", "", group).strip(" ,"):
            return None
        ok = True
        for op, ver in tokens:
            bound = parse_version(ver)
            if bound is None:
                return None
            if re.search(r"[x*]$", ver) and not op:  # 1.x / 1.2.* wildcard
                depth = ver.count(".")
                ok &= pv[: max(depth, 1)] == bound[: max(depth, 1)]
                continue
            ok &= {"<": pv < bound, "<=": pv <= bound, ">": pv > bound, ">=": pv >= bound,
                   "!=": pv != bound, "": pv == bound, "=": pv == bound, "==": pv == bound}[op]
        result |= ok
    return result


# ------------------------------------------------------------ SA records
def clean(html_text):
    return html.unescape(re.sub(r"<[^>]+>", "", html_text or "")).strip()


def fixed_versions(solution_html, project):
    seen = []
    for m in re.finditer(r"/project/([\w-]+)/releases/([\w.\-]+)", html.unescape(solution_html or "")):
        if m.group(1) == project and m.group(2) not in seen:
            seen.append(re.sub(r"^\d+\.x-", "", m.group(2)))
    return seen


def risk_parts(text):
    m = re.match(r"^\s*(.*?)\s+(\d+)\s*[∕/]\s*(\d+)", text.replace(" ", " "))
    return (m.group(1), f"{m.group(2)}/{m.group(3)}") if m else (text.strip(), "")


def from_rss_item(item):
    title = item.findtext("title") or ""
    sa = re.search(r"\bSA-(CORE|CONTRIB)-\d{4}-\d+", title)
    if not sa:
        return None
    raw = item.findtext("description") or ""
    f = {m.group(1): m.group(2) for m in re.finditer(
        r'field-name-([\w-]+) .*?<div class="field-items">(.*?)</div></div>', raw, re.S)}
    project = clean(f.get("project-machine-name", ""))
    try:
        date = dt.datetime.strptime(clean(f.get("drupalorg-sa-date", "")), "%Y-%B-%d").date().isoformat()
    except ValueError:
        date = ""
    risk, score = risk_parts(clean(f.get("field-sa-criticality", "")))
    return project, {
        "id": sa.group(0), "title": title, "url": item.findtext("link"), "date": date,
        "risk": risk, "score": score, "type": clean(f.get("field-sa-type", "")),
        "affected": clean(f.get("field-affected-versions", "")),
        "cves": re.findall(r"CVE-\d+-\d+", clean(f.get("field-sa-cve", ""))),
        "fixed_in": fixed_versions(f.get("field-sa-solution", ""), project),
        "solution": clean(f.get("field-sa-solution", ""))[:300], "source": "rss",
    }


def from_api_node(node, project):
    title = node.get("title") or ""
    sa = re.search(r"\bSA-(CORE|CONTRIB)-\d{4}-\d+", title)
    if not sa or str(node.get("field_is_psa")) == "1" or str(node.get("status")) == "0":
        return None
    val = lambda k: (node.get(k) or {}).get("value", "") if isinstance(node.get(k), dict) else (node.get(k) or "")
    risk = re.match(r".*? - (.*?) - ", title)
    cves = node.get("field_sa_cve") or []
    return {
        "id": sa.group(0), "title": title, "url": node.get("url"),
        "date": dt.datetime.fromtimestamp(int(node.get("created") or 0), dt.timezone.utc).date().isoformat(),
        "risk": risk.group(1) if risk else "", "score": "", "type": node.get("field_sa_type") or "",
        "affected": (node.get("field_affected_versions") or "").strip(),
        "cves": cves if isinstance(cves, list) else [cves],
        "fixed_in": fixed_versions(val("field_sa_solution"), project),
        "solution": clean(val("field_sa_solution"))[:300], "source": "api",
    }


# ------------------------------------------------------------------ store
def load(project):
    p = store_dir() / f"{project}.json"
    if p.exists():
        try:
            return json.load(open(p))
        except ValueError:
            pass
    return {"project": project, "advisories": {}}


def save(rec):
    store_dir().mkdir(parents=True, exist_ok=True)
    (store_dir() / f"{rec['project']}.json").write_text(json.dumps(rec, indent=1) + "\n")


def merge(rec, adv):
    old = rec["advisories"].get(adv["id"])
    if old and old.get("source") == "api" and adv["source"] == "rss":
        return  # API record is the fuller one
    rec["advisories"][adv["id"]] = adv


def age(ts, unit):
    try:
        return (dt.datetime.now(dt.timezone.utc) - dt.datetime.fromisoformat(ts)).total_seconds() / unit
    except (TypeError, ValueError):
        return 10**9


NOW = lambda: dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")


def refresh_feed(max_age_hours=6, force=False):
    meta_p = store_dir() / "_feed.json"
    meta = json.load(open(meta_p)) if meta_p.exists() else {}
    if not force and age(meta.get("fetched"), 3600) < max_age_hours:
        return meta, None
    try:
        root = ET.fromstring(http_get(FEED_URL))
    except (RuntimeError, ET.ParseError) as exc:
        return meta, str(exc)
    n, newest, oldest = 0, "", "9999"
    for item in root.findall("./channel/item"):
        parsed = from_rss_item(item)
        if not parsed or not parsed[0]:
            continue
        project, adv = parsed
        rec = load(project)
        merge(rec, adv)
        save(rec)
        n += 1
        newest, oldest = max(newest, adv["date"]), min(oldest, adv["date"])
    meta = {"fetched": NOW(), "items": n, "newest": newest, "oldest": oldest}
    store_dir().mkdir(parents=True, exist_ok=True)
    meta_p.write_text(json.dumps(meta, indent=1) + "\n")
    return meta, None


def fetch_history(project, max_age_days=7, force=False):
    """Full SA history for one project from the API. Returns error string or None."""
    rec = load(project)
    if not force and age(rec.get("history_fetched"), 86400) < max_age_days:
        return None
    try:
        if not rec.get("nid"):
            q = urllib.parse.urlencode({"field_project_machine_name": project, "limit": 5})
            nodes = json.loads(http_get(f"{API}?{q}")).get("list", [])
            nodes = [n for n in nodes if str(n.get("type", "")).startswith("project_")]
            if not nodes:
                rec["history_fetched"] = NOW()
                rec["nid"] = None
                save(rec)
                return None  # not a drupal.org project (e.g. a library): nothing to find
            rec["nid"] = nodes[0]["nid"]
        url = f"{API}?" + urllib.parse.urlencode({"type": "sa", "field_project": rec["nid"], "limit": 50,
                                                  "sort": "created", "direction": "DESC"})
        pages = 0
        while url and pages < 6:  # 300 advisories is plenty (core has ~150)
            data = json.loads(http_get(url))
            for node in data.get("list", []):
                adv = from_api_node(node, project)
                if adv:
                    rec["advisories"][adv["id"]] = adv
            url, pages = data.get("next"), pages + 1
            if url:  # the API's own "next" links omit the .json suffix and 404 without it
                url = url.replace("/api-d7/node?", "/api-d7/node.json?")
        rec["history_fetched"] = NOW()
        save(rec)
        return None
    except (RuntimeError, ValueError, KeyError) as exc:
        return f"{project}: {exc}"


# ------------------------------------------------------------------ check
def scope_entries(args, pkgs, filtered, all_installed):
    """-> {project: {package, installed, latest, enabled, reason}}"""
    out = {}

    def add(name, version, latest=None, enabled=True, reason=""):
        project = pkgs.project_of(name)
        cur = out.get(project)
        main = name in (f"drupal/{project}", "drupal/core")
        if cur is None or (main and not cur["main"]):
            out[project] = {"package": name, "installed": version, "latest": latest,
                            "enabled": enabled, "reason": reason, "main": main}

    if filtered:
        for e in filtered.get("installed", []):
            add(e["name"], e["version"], e.get("latest"), True, e.get("enabled_reason", ""))
        for e in filtered.get("skipped", []):
            add(e["name"], e["version"], e.get("latest"), False, e.get("reason", ""))
    if all_installed or not filtered:
        for name, p in pkgs.by_name.items():
            if name.startswith("drupal/") and p.get("type") != "metapackage" and name not in {
                    v["package"] for v in out.values()}:
                add(name, str(p.get("version", "")), None, True, "installed")
    return out


def evaluate(entry, rec):
    hits, undetermined = [], []
    for adv in sorted(rec["advisories"].values(), key=lambda a: a["date"], reverse=True):
        hit = satisfies(entry["installed"], adv["affected"])
        if hit is None:
            undetermined.append(adv)
        elif hit:
            latest = entry.get("latest")
            hits.append(dict(adv, latest_fixes=(None if not latest else satisfies(latest, adv["affected"]) is False)))
    return hits, undetermined


def cmd_check(argv):
    pos = [a for a in argv if not a.startswith("--")]
    opt = lambda n, d: next((argv[i + 1] for i, a in enumerate(argv) if a == n), d)
    max_age = float(opt("--max-age-days", 7))
    filtered = json.load(open(pos[0])) if pos else None
    pkgs = Packages()
    scope = scope_entries(argv, pkgs, filtered, "--all-installed" in argv)
    errors = []

    meta, err = refresh_feed(force="--refresh" in argv)
    if err:
        errors.append(f"RSS feed: {err}")
    if "--no-api" not in argv:
        wanted = [p for p, e in scope.items() if "--all-installed" in argv or e["latest"] or not e["enabled"]]
        with ThreadPoolExecutor(max_workers=4) as pool:
            errors += [e for e in pool.map(
                lambda p: fetch_history(p, max_age, "--refresh" in argv), wanted) if e]

    report = {"feed": meta, "projects": {}, "undetermined": {}, "errors": errors}
    for project, entry in sorted(scope.items()):
        rec = load(project)
        if str(entry["installed"]).startswith("dev-"):
            if rec["advisories"]:
                report["undetermined"][project] = {"installed": entry["installed"],
                                                   "reason": "dev version: cannot match ranges",
                                                   "advisories": sorted(rec["advisories"])}
            continue
        hits, unk = evaluate(entry, rec)
        if hits:
            report["projects"][project] = {"package": entry["package"], "installed": entry["installed"],
                                           "latest": entry["latest"], "enabled": entry["enabled"],
                                           "advisories": hits}
        if unk:
            report["undetermined"][project] = {"installed": entry["installed"],
                                               "reason": "affected range could not be parsed",
                                               "advisories": [{"id": a["id"], "affected": a["affected"]} for a in unk]}
    json.dump(report, sys.stdout, indent=1)

    w = sys.stderr.write
    if meta:
        w(f"feed: {meta.get('items')} advisories, {meta.get('oldest')} .. {meta.get('newest')} (fetched {meta.get('fetched')})\n")
    w(f"checked {len(scope)} projects: {len(report['projects'])} with advisories affecting the installed version\n")
    for project, r in report["projects"].items():
        for a in r["advisories"]:
            fix = ", ".join(a["fixed_in"]) or "none listed"
            lf = {True: "latest fixes it", False: "LATEST STILL AFFECTED", None: "latest unknown"}[a["latest_fixes"]]
            w(f"  {a['id']} {project} {r['installed']}: {a['risk']} / {a['type']} ({a['date']}); fixed in {fix}; {lf}"
              f"{'' if r['enabled'] else '; package DISABLED'}\n")
    for project, r in report["undetermined"].items():
        w(f"  UNDETERMINED {project} {r['installed']}: {r['reason']} {r['advisories']}\n")
    for e in errors:
        w(f"  ERROR {e}\n")


def cmd_for_version(argv):
    project, version = argv[:2]
    want = parse_version(version)
    found = [a for a in load(project)["advisories"].values()
             if any(parse_version(v) == want for v in a["fixed_in"])]
    json.dump(sorted(found, key=lambda a: a["date"]), sys.stdout, indent=1)
    print()
    if not load(project).get("history_fetched"):
        print(f"note: no API history stored for {project}; older advisories may be missing "
              f"(run check, or fetch via the API)", file=sys.stderr)


def cmd_list(argv):
    d = store_dir()
    for p in sorted(d.glob(f"{argv[0] if argv else '*'}.json")):
        if p.name == "_feed.json":
            continue
        rec = json.load(open(p))
        for a in sorted(rec["advisories"].values(), key=lambda a: a["date"], reverse=True):
            print(f"{rec['project']} {a['id']} {a['date']} {a['risk']} / {a['type']} affected {a['affected']} fixed {a['fixed_in']} [{a['source']}]")


def cmd_refresh_feed(argv):
    opt = lambda n, d: next((argv[i + 1] for i, a in enumerate(argv) if a == n), d)
    meta, err = refresh_feed(float(opt("--max-age-hours", 0)))
    print(json.dumps(meta), file=sys.stdout)
    if err:
        sys.exit(f"error: {err}")


def main():
    cmds = {"check": cmd_check, "for-version": cmd_for_version, "list": cmd_list, "refresh-feed": cmd_refresh_feed}
    if len(sys.argv) < 2 or sys.argv[1] not in cmds:
        sys.exit(__doc__)
    cmds[sys.argv[1]](sys.argv[2:])


if __name__ == "__main__":
    main()
