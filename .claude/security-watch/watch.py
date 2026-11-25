#!/usr/bin/env python3
"""Security-advisory watcher (REPORT-ONLY).

Meant to be run from cron every ~20 minutes. It is cheap and does not use
Claude unless something new needs looking at:

  1. Only acts on Wednesdays 11:00-16:09 America/Chicago (override: --force).
  2. Gate (no LLM, no Lando): drupal.org advisory feed matched against the
     installed versions (drupal-update-report's security_advisories.py) plus
     `composer audit --no-dev` for everything else.
  3. If an advisory that applies here has not been handled before, start ONE
     read-only `claude -p` run that follows the drupal-update-report skill and
     writes a report + verdict.json into runs/<timestamp>/.
  4. Evaluate the verdict against the (not yet active) auto-apply rule and
     record "would auto-apply: yes/no" so we can see how it rates advisories.
     Nothing is ever changed: no composer update, no git, no drush, no prod.

Files (all under .claude/security-watch/, git-ignored with the rest of .claude):
  state.json   advisory keys already handled (delete to re-run everything)
  watch.log    one line per poll
  latest.md    summary of the most recent run that did something
  ~/.local/state/mcgreen-security-watch/runs/<ts>/   findings.json, prompt.md, settings.json, claude.json, report.md, verdict.json

Usage: watch.py [--force] [--dry-run] [--no-notify]
  --force     ignore the Wednesday window
  --dry-run   run the gate and print what would happen; no Claude, no state
"""
import argparse
import datetime as dt
import fcntl
import fnmatch
import json
import os
import subprocess
import sys
from pathlib import Path
from zoneinfo import ZoneInfo

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
SKILL = REPO / ".claude/skills/drupal-update-report"
TZ = ZoneInfo("America/Chicago")
# Claude Code refuses to let an unattended session write under .claude/ even with an allow rule,
# so the per-run folders (where Claude writes its report) live outside the repo.
RUNS = Path.home() / ".local/state/mcgreen-security-watch/runs"
MAX_ATTEMPTS = 2           # Claude runs per advisory before giving up and telling a human
BUDGET_USD = "6"
TIMEOUT_S = 45 * 60

# Projects a future auto-apply mode must never touch without a human.
HUMAN_ONLY = ["drupal", "commerce", "commerce_*", "stripe*", "mcgreen_*"]


def log(msg):
    line = f"{dt.datetime.now(TZ):%Y-%m-%d %H:%M:%S} {msg}"
    print(line)
    with open(HERE / "watch.log", "a") as f:
        f.write(line + "\n")


def in_window(now):
    return now.weekday() == 2 and (11 <= now.hour < 16 or (now.hour == 16 and now.minute < 10))


def run(cmd, **kw):
    return subprocess.run(cmd, capture_output=True, text=True, cwd=REPO, **kw)


# ------------------------------------------------------------------ gate
def gate():
    """-> (findings {key: dict}, errors [str])"""
    findings, errors = {}, []

    r = run(["python3", str(SKILL / "scripts/security_advisories.py"), "check", "--all-installed", "--no-api"])
    try:
        sa = json.loads(r.stdout)
    except ValueError:
        sa = None
        errors.append(f"security_advisories.py failed: {r.stderr.strip()[-300:]}")
    if sa:
        errors += sa.get("errors", [])
        for project, p in sa["projects"].items():
            for a in p["advisories"]:
                findings[a["id"]] = {
                    "key": a["id"], "source": "drupal.org", "project": project, "package": p["package"],
                    "installed": p["installed"], "enabled": p["enabled"], "title": a["title"],
                    "url": a["url"], "risk": a["risk"], "cves": a.get("cves", []),
                    "fixed_in": a.get("fixed_in", []), "latest_fixes": a.get("latest_fixes"),
                }
        for project, u in sa["undetermined"].items():
            for a in u["advisories"]:
                aid = a["id"] if isinstance(a, dict) else a
                findings[f"undetermined:{aid}"] = {
                    "key": f"undetermined:{aid}", "source": "drupal.org", "project": project,
                    "package": f"drupal/{project}", "installed": u["installed"], "enabled": True,
                    "title": f"{aid} (cannot tell if it applies: {u['reason']})", "url": "",
                    "risk": "unknown", "cves": [], "fixed_in": [], "latest_fixes": None,
                }

    r = run(["composer", "audit", "--no-dev", "--format=json", "--no-interaction"])
    try:
        audit = json.loads(r.stdout)
    except ValueError:
        audit = None
        errors.append(f"composer audit failed: {(r.stderr or r.stdout).strip()[-300:]}")
    if audit:
        for pkg, advs in audit.get("advisories", {}).items():
            for a in advs:
                key = a.get("advisoryId") or a.get("cve") or a.get("title")
                if key in findings:  # same SA already found via drupal.org
                    continue
                findings[key] = {
                    "key": key, "source": "composer audit", "project": pkg.split("/")[-1], "package": pkg,
                    "installed": "see composer.lock", "enabled": True, "title": a.get("title", key),
                    "url": a.get("link", ""), "risk": a.get("severity") or "unknown",
                    "cves": [a["cve"]] if a.get("cve") else [], "fixed_in": [],
                    "latest_fixes": None, "affected": a.get("affectedVersions", ""),
                }
    return findings, errors


# ----------------------------------------------------------- claude run
SETTINGS = {
    "allow": [
        "Read", "Glob", "Grep", "Agent",
        "WebFetch(domain:www.drupal.org)", "WebFetch(domain:updates.drupal.org)",
        "WebFetch(domain:git.drupalcode.org)",
        "Bash(python3 .claude/skills/drupal-update-report/scripts/*)",
        "Bash(lando composer outdated *)", "Bash(lando composer show *)", "Bash(lando composer why *)",
        "Bash(lando composer why-not *)", "Bash(lando composer audit *)", "Bash(lando info *)",
        "Bash(composer outdated *)", "Bash(composer show *)", "Bash(composer why *)",
        "Bash(composer why-not *)", "Bash(composer audit *)",
        "Bash(git clone --depth 1 *)", "Bash(git ls-remote *)", "Bash(git log *)", "Bash(git diff *)",
        "Bash(git show *)", "Bash(git status *)", "Bash(diff *)",
        "Bash(ls *)", "Bash(cat *)", "Bash(head *)", "Bash(tail *)", "Bash(grep *)", "Bash(cd *)", "Bash(wc *)", "Bash(sort *)", "Bash(mkdir -p *)",
        "Bash(date *)", "Bash(curl -s https://updates.drupal.org/*)", "Bash(curl -sL https://www.drupal.org/*)",
    ],
    "deny": [
        "Bash(ssh *)", "Bash(scp *)", "Bash(rsync *)", "Bash(*@prod*)", "Bash(*lando drush*)",
        "Bash(lando composer update*)", "Bash(lando composer require*)", "Bash(lando composer remove*)",
        "Bash(composer update*)", "Bash(composer require*)", "Bash(composer remove*)",
        "Bash(git push*)", "Bash(git commit*)", "Bash(git checkout*)", "Bash(git reset*)",
        "Edit(//{repo}/composer.*)", "Read(//{repo}/drush/sites/**)", "Read(//{repo}/.env)",
        "Read(//{repo}/web/sites/*/settings.local.php)", "Read(//{repo}/.lando.local.yml)",
    ],
}

PROMPT = """\
You are running unattended from a cron job on {date} (America/Chicago), in REPORT-ONLY mode.
Never modify the repository, never run composer update/require, updb, git commit/push. You can only write
inside the run directory below.

Read `.claude/skills/drupal-update-report/SKILL.md` and follow it, with these overrides:

- Run directory (scratchpad, and the ONLY place you may write): `{run}`
  - Write the full report to `{run}/report.md` instead of the repo root.
  - Use `{run}/work/` for git clones and temp files (one new empty dir per clone).
- Scope: only these projects (as if the user had passed them by name): {projects}
  They were selected because these advisories apply to the installed versions:
{findings}
  Treat the advisories above as the Security column's source of truth and put them first under
  "Blockers and ordering". Pick the MINIMUM safe target (often a same-major step) and rate it, rather
  than defaulting to the latest release. Rate the risk of that minimum path, not only the latest.
- Non-Drupal findings (source "composer audit", package not under drupal/): add a short section to the
  report with package, installed version, advisory, fixed version, which `composer.json` requirement
  pulls it in (`composer why`) and whether the fix fits the current constraint.
- composer.lock on origin/master {stale}
- Subagents are allowed for the research step, per the skill. Everything stays read-only.
- Only the skill's own scripts, composer/git read commands, grep and ls are allowed in Bash; there is no ad-hoc
  python or heredoc. Use the Read and Grep tools for composer.json, composer.lock and JSON files.

Also write `{run}/verdict.json` (valid JSON, no comments) so a script can read your ratings:

{{"findings": [{{
  "advisory_ids": ["SA-..."], "project": "name", "package": "drupal/name",
  "installed": "x.y.z", "target": "minimum safe version you recommend",
  "same_major": true, "risk": "Low|Moderate|High|Critical",
  "patches_affected": ["composer.json patch descriptions, or empty"],
  "custom_code_affected": ["paths, or empty"],
  "update_hooks": ["hook_update_N: what it does, or empty"],
  "config_changes": false, "needs_constraint_change": false,
  "post_update_steps": ["e.g. drush updb, drush cex"], "notes": "one or two sentences"
}}]}}

One entry per project. Be honest in `risk`: this verdict will be compared with what a human decides.

Finish with a reply of at most 15 lines: the summary table and the report path. Nothing else.
"""


def build_prompt(run_dir, new, stale):
    lines = "\n".join(
        f"  - {f['key']} ({f['source']}): {f['package']} {f['installed']}, {f['risk']}; fixed in "
        f"{', '.join(f['fixed_in']) or 'unknown'}; {f['url']}" for f in new)
    projects = ", ".join(sorted({f["project"] for f in new if f["package"].startswith("drupal/")})) or "(none; non-Drupal only)"
    return PROMPT.format(date=dt.datetime.now(TZ).strftime("%Y-%m-%d %H:%M"), run=run_dir, projects=projects,
                         findings=lines, stale=stale)


def stale_note():
    """Is this checkout's composer.lock the same as origin/master's?"""
    run(["git", "fetch", "--quiet", "origin", "master"], timeout=60)
    r = run(["git", "diff", "--quiet", "HEAD", "origin/master", "--", "composer.lock"])
    if r.returncode == 0:
        return "matches this checkout."
    return ("DIFFERS from this checkout, so installed versions here may be out of date; if the advisory is "
            "already fixed on origin/master, say so prominently.")


def run_claude(run_dir, new, stale):
    run_dir.mkdir(parents=True, exist_ok=True)
    (run_dir / "work").mkdir(exist_ok=True)
    (run_dir / "findings.json").write_text(json.dumps(new, indent=1))
    settings = {"permissions": {
        # Edit rules cover the Write tool too; Write(path) rules do not match.
        "allow": SETTINGS["allow"] + [f"Edit(/{run_dir}/**)"],
        "deny": [d.replace("{repo}", str(REPO).lstrip("/")) for d in SETTINGS["deny"]],
    }}
    (run_dir / "settings.json").write_text(json.dumps(settings, indent=1))
    prompt = build_prompt(run_dir, new, stale)
    (run_dir / "prompt.md").write_text(prompt)

    env = dict(os.environ)
    env["PATH"] = ":".join([str(Path.home() / ".local/bin"), str(Path.home() / ".lando/bin"),
                            "/usr/local/bin", "/usr/bin", "/bin", env.get("PATH", "")])
    cmd = ["claude", "-p", prompt, "--output-format", "json", "--permission-mode", "dontAsk",
           "--setting-sources", "project", "--settings", str(run_dir / "settings.json"),
           "--max-budget-usd", BUDGET_USD, "--add-dir", str(run_dir)]
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, cwd=REPO, env=env, timeout=TIMEOUT_S)
    except subprocess.TimeoutExpired:
        (run_dir / "claude.json").write_text(json.dumps({"is_error": True, "result": "timed out"}))
        return {"is_error": True, "result": f"timed out after {TIMEOUT_S // 60} minutes"}
    (run_dir / "claude.json").write_text(r.stdout or json.dumps({"stderr": r.stderr}))
    try:
        out = json.loads(r.stdout)
    except ValueError:
        out = {"is_error": True, "result": (r.stderr or r.stdout)[-500:]}
    if r.returncode != 0:
        out["is_error"] = True
    return out


# --------------------------------------------------------------- verdict
def evaluate(verdict):
    """The auto-apply rule we are considering. Returns [(project, ok, why)]. Informational only."""
    rows = []
    for f in verdict.get("findings", []):
        why = []
        if any(fnmatch.fnmatch(f["project"], p) for p in HUMAN_ONLY):
            why.append("human-only project (core / commerce / payments / custom)")
        if f.get("risk") != "Low":
            why.append(f"risk is {f.get('risk')}")
        if not f.get("same_major", False):
            why.append("not a same-major step")
        for key, label in [("patches_affected", "patches affected"), ("custom_code_affected", "custom code affected"),
                           ("update_hooks", "update hooks")]:
            if f.get(key):
                why.append(label)
        if f.get("config_changes"):
            why.append("config changes")
        if f.get("needs_constraint_change"):
            why.append("composer constraint change needed")
        rows.append((f["project"], not why, "; ".join(why)))
    return rows


def write_latest(new, out, run_dir, rows, stale):
    lines = [f"# Security watch: {dt.datetime.now(TZ):%Y-%m-%d %H:%M}", "",
             "Mode: REPORT-ONLY (nothing was changed).", "", "## New advisories"]
    for f in new:
        lines.append(f"- **{f['key']}** {f['package']} {f['installed']} ({f['risk']}); fixed in "
                     f"{', '.join(f['fixed_in']) or 'unknown'}. {f['url']}")
    lines += ["", f"Claude run: {'FAILED: ' + str(out.get('result'))[:300] if out.get('is_error') else 'ok'}; "
              f"cost ${out.get('total_cost_usd', '?')}; composer.lock on origin/master {stale}",
              f"Report: {run_dir}/report.md", "", "## Would auto-apply under the proposed rule?"]
    lines += [f"- {p}: {'YES' if ok else 'no'}{'' if ok else ' (' + why + ')'}" for p, ok, why in rows] or ["- no verdict.json produced"]
    (HERE / "latest.md").write_text("\n".join(lines) + "\n")


def notify(title, body):
    env = dict(os.environ, DBUS_SESSION_BUS_ADDRESS=f"unix:path=/run/user/{os.getuid()}/bus",
               DISPLAY=os.environ.get("DISPLAY", ":0"))
    try:
        subprocess.run(["notify-send", "-u", "critical", title, body], env=env, timeout=10)
    except Exception as e:  # best effort
        log(f"notify failed: {e}")


# ------------------------------------------------------------------ main
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--no-notify", action="store_true")
    args = ap.parse_args()

    now = dt.datetime.now(TZ)
    if not (args.force or args.dry_run) and not in_window(now):
        return 0  # silent: cron fires all week, the window is enforced here

    lock = open(HERE / ".lock", "w")
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        log("another run is in progress; skipping")
        return 0

    state_path = HERE / "state.json"
    state = json.loads(state_path.read_text()) if state_path.exists() else {"handled": {}, "attempts": {}}

    findings, errors = gate()
    for e in errors:
        log(f"gate error: {e}")
    todo = [f for k, f in sorted(findings.items())
            if k not in state["handled"] and state["attempts"].get(k, 0) < MAX_ATTEMPTS and f["enabled"]]
    disabled = [k for k, f in findings.items() if not f["enabled"] and k not in state["handled"]]
    log(f"gate: {len(findings)} applicable advisories, {len(todo)} new"
        f"{', disabled-only: ' + ', '.join(disabled) if disabled else ''}")

    exhausted = [k for k in findings if state["attempts"].get(k, 0) >= MAX_ATTEMPTS and k not in state["handled"]]
    if exhausted and not args.dry_run:
        log(f"giving up automatically on {exhausted} after {MAX_ATTEMPTS} failed runs")

    if not todo:
        return 0
    if args.dry_run:
        print(json.dumps(todo, indent=1))
        return 0

    stale = stale_note()
    run_dir = RUNS / f"{dt.datetime.now(TZ):%Y%m%d-%H%M}"
    log(f"starting claude for {[f['key'] for f in todo]} in {run_dir}")
    out = run_claude(run_dir, todo, stale)

    verdict = {}
    try:
        verdict = json.loads((run_dir / "verdict.json").read_text())
    except (OSError, ValueError):
        pass
    ok = not out.get("is_error") and bool(verdict) and (run_dir / "report.md").exists()
    for f in todo:
        if ok:
            state["handled"][f["key"]] = {"run": str(run_dir), "at": now.isoformat()}
        else:
            state["attempts"][f["key"]] = state["attempts"].get(f["key"], 0) + 1
    state_path.write_text(json.dumps(state, indent=1))

    rows = evaluate(verdict)
    write_latest(todo, out, run_dir, rows, stale)
    log(f"claude finished ok={ok} cost=${out.get('total_cost_usd', '?')} verdict="
        f"{[(p, 'auto' if a else 'human') for p, a, _ in rows]}")

    if not args.no_notify:
        ids = ", ".join(f["key"] for f in todo)
        if ok:
            notify(f"Security advisory: {ids}", f"Report ready (report-only). See {HERE / 'latest.md'}")
        else:
            notify(f"Security advisory NOT analysed: {ids}",
                   f"Claude run failed ({str(out.get('result'))[:120]}). Needs a human. {run_dir}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
