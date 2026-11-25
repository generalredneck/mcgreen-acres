---
name: drupal-update-report
description: Builds a pre-update report for outdated Drupal projects that are installed (enabled) on the site. Reads `composer outdated`, drops packages whose modules are disabled, pulls the release notes from drupal.org for every version between current and latest, and rates each update's risk. Security flags come from the drupal.org security advisories (RSS feed + API) matched against the installed versions, not from guesswork in release notes. Release findings are cached per project and version so later runs only research releases not seen before. For each project it also lists which composer patches, custom modules/theme code and exported config would be affected. Run only when the user invokes it (/drupal-update-report), optionally with project names to limit the scope. It is read-only and never runs `composer update`.
disable-model-invocation: true
---

# Drupal update report

Goal: before the user runs `composer update`, tell them what is changing, how risky it is, and exactly what in *this* codebase (patches, custom code, config) the changes could touch. The user decides what to update afterwards, so this skill only reads and reports. Don't edit `composer.json`, don't run `composer update`/`require`, don't run `updb`.

If the user passed project names (e.g. `/drupal-update-report commerce_stripe core`), limit the report to those. Otherwise cover every outdated `drupal/*` package that is actually installed on the site (see "Keep only what the site uses" below).

## 1. Find what's outdated

Make sure Lando is actually up and bootstrapped first (don't trust container status alone), then:

```bash
lando composer outdated "drupal/*" --format=json
```

- Each entry has `name`, `version` (installed), `latest`, and `latest-status` (`update-possible` = inside the current constraint; `semver-safe-update` = minor/patch only; otherwise the constraint in `composer.json` has to change, which is worth saying in the report).
- Project name = package name without `drupal/` (`drupal/commerce_stripe` → `commerce_stripe`). All `drupal/core*` packages (`core`, `core-recommended`, `core-composer-scaffold`, ...) are one project, `drupal`; report them once.
- If `lando composer` fails because the container has no composer binary (a rebuilt container can lose it), the host `composer` works for this read-only step; say so in the report.
- If the user named projects, run only those. A named project that isn't outdated is skipped with a note under "Not covered" (it may simply have been updated already).
- Skip dev/hash versions (`dev-*`) and say so under "Not covered" — there are no release notes to read.
- Non-Drupal packages (symfony, stripe, etc.) are out of scope; just list their names under "Not covered" so the user knows they exist.

### Keep only what the site uses

Save the outdated JSON to the scratchpad, then drop packages whose modules/themes are not enabled — they are in `vendor/` only as someone's dependency (e.g. imagemagick, sophron, file_mdm via `varbase_media`), nothing runs them, and researching them is wasted effort:

```bash
python3 .claude/skills/drupal-update-report/scripts/filter_installed.py <outdated.json> > <filtered.json>
```

- Enabled = listed in `config/sync/core.extension.yml` (what deploys). Pass `--drush` to use the local database via `lando drush config:get core.extension` instead.
- Kept: core, packages shipping at least one enabled module/theme/profile, composer metapackages whose own module is enabled (`commerce_cart`, `mailer_transport`, …), and packages that ship no module at all (dev tooling such as `coder`, libraries, recipes), because those still break builds.
- The script prints each skipped package and why to stderr and records them in the output's `skipped` list. Put them under "Not covered" ("installed but disabled; arrives via <dependent>"). Still mention a skipped package when it **constrains** one that is covered (e.g. "varbase_media pins imagemagick ~4"), since Composer enforces constraints on disabled packages too — `composer why-not` shows who requires them.
- If the user explicitly named a project that turns out to be disabled, rerun with `--include-disabled` for it and note in its section that it is not enabled.
- Use `<filtered.json>` in every later step (`upgrade_paths.py`, `release_cache.py`).

### Check the security advisories

Urgency should come from drupal.org's own advisories (SAs), not from whether a release page says "security". Run this right after filtering (needs network; JSON to stdout, readable summary to stderr):

```bash
python3 .claude/skills/drupal-update-report/scripts/security_advisories.py check <filtered.json> > <advisories.json>
```

- It refreshes `https://www.drupal.org/security/all/rss.xml` (every project, but only the newest ~50 advisories, roughly two weeks), then pulls each in-scope project's **full** advisory history from the drupal.org API, because the feed alone misses anything older. Everything lands in a local store (`cache/advisories/`) that grows over time; history is re-fetched after `--max-age-days` (default 7), the feed after 6 hours. `--refresh` forces both; `--no-api` uses the feed and the store only.
- Each advisory's affected range (`<2.0.1`, `>=3.0.0 <3.0.1 || …`) is evaluated against the **installed** version. The output lists, per project: SA id, risk, vulnerability type, date, CVEs, the releases that fix it, whether the **latest** release fixes it (`latest_fixes`), and whether the package is enabled. PSAs are ignored.
- `--all-installed` widens the check from the outdated set to every installed `drupal/*` package — cheap with `--no-api` (feed only), and the way to catch a recent advisory for a package that is *not* outdated (e.g. one with no fix yet). Do this sweep at least with `--no-api` on every run and say how many projects it covered.
- Treat the result as authoritative for the **Security** column: a project is "Yes" when an SA applies to its installed version (cite the SA id, risk and CVE), and the fixing release(s) tell you the minimum safe target — which can be a same-major step rather than the latest. An advisory with `latest_fixes: false` (no fix yet) is the most urgent finding: put it first under "Blockers and ordering" with the SA link.
- An advisory for a **disabled** package still goes in the report (under "Not covered") — the code is on disk — but flagged as disabled.
- `undetermined` lists projects whose affected range could not be parsed or that are on a `dev-*` version: read those SAs by hand (the store has the URL) rather than assuming they don't apply. `errors` lists network failures: say so in the report, because the result then rests on stored data only.
- Cross-check against the update feed (`updates.drupal.org/release-history/<project>/current` tags releases "Security update"). If the two disagree, say which one flagged it.

### Map the upgrade paths (every project except Drupal core)

`composer outdated` shows only the newest version, which can hide a safer route: a maintained older major may have its own fixes (e.g. `symfony_mailer` 1.6.3 alongside 2.0.2), and a patch-level step can be far less risky than a major jump. Save the JSON to the scratchpad and run this from the repo root (it shells out to `composer show`, which needs the project):

```bash
python3 .claude/skills/drupal-update-report/scripts/upgrade_paths.py <filtered.json> [--only address,metatag]
```

`--only` limits it to the named projects (with or without the `drupal/` prefix).

It prints every stable version newer than the installed one, grouped by major. For each project that has more than one reachable version, treat each distinct path as an option and carry it into the report:
- **Same-major path**: the highest version within the installed major. If it exists and differs from the overall latest, it is the conservative option (often security fixes without a major migration).
- **Next-major path(s)**: the latest in each higher major.
- **Stepping stones** within a major (e.g. 2.0.5 before 2.0.6): read their notes too — an intermediate release can be the risky one that a later release reverts.
- If a major jump has **no same-major release**, say so explicitly: the user's alternative is staying put, with no further fixes for the installed major.

Core is excluded from this on purpose: core updates follow Drupal's own release cadence and are handled separately.

## 2. Collect release notes (reusing the cache)

Release facts are the same for every codebase, so they are cached per release as small JSON digests in `.claude/skills/drupal-update-report/cache/releases/<project>/<version>.json` (local to this machine; override the location with `$DRUPAL_UPDATE_CACHE_DIR`). **Repo-specific impact (step 3) is never cached** — recompute it every run from the digests.

Start with the plan (from the repo root; shells out to `composer show`, so it takes a few seconds):

```bash
python3 .claude/skills/drupal-update-report/scripts/release_cache.py plan <filtered.json> [--only a,b] > <plan.json>
```

For each project it lists every stable version between installed and latest and marks each `cached`, `missing`, `stale` (complete digest older than `--max-age-days`, default 60 — re-check mainly for security advisories published after the release) or `partial` (a digest built without readable release notes, retried after `--partial-age-days`, default 14). Projects, composer packages grouped (all `drupal/commerce_*` metapackages and `mailer_transport` roll up into their project), and core are handled for you.

- **Only research versions in `needs_research`.** Read cached digests with `release_cache.py get <project> <version>` and use them as the release notes for step 3 and the report. A project with nothing to research needs no agent at all.
- **Store what you learn.** After researching a version, write its digest and store it with `release_cache.py put <project> <version> <file>` (`release_cache.py schema` prints the format; `put` validates it). Do this for every version researched, including stepping stones and versions only reachable on another major, so they are free next time. If a put is rejected, fix the digest — don't skip it.
- **Security fields come from the advisory store, not from reading prose**: `security_advisories.py for-version <project> <version>` lists the SAs that name that version as a fixing release. Set `security.is_security_release` and `security.advisories` from it (run `check` first so the project's history is stored). Do the same for versions that need no other research.
- **Digests hold upstream facts only**: what the release changed, from the previous release. Tag-diff findings (update hooks, config, routing, services, requirements) must come from `previous-tag..this-tag`, never from a diff against `web/modules/contrib/<project>` (which has local patches applied and would pollute the cache). Record issue numbers (`issues`) and changed class/service/route/hook names (`changed_identifiers`): step 3 greps them against `composer.json` patches and custom code without re-reading anything. No repo-specific conclusions ("breaks custom_foo") in a digest.
- Mark a digest `partial` when the drupal.org page was empty/unreachable and you filled in from a tag diff or the feed; `complete` only when the release notes (or equivalent) were read. Never invent content to make a digest look complete.
- `release_cache.py invalidate <project> [version]` drops entries if a digest turns out to be wrong; `release_cache.py list` shows what is cached.
- Say in the report how many versions came from the cache vs. were researched fresh.

Researching a version (anything the plan lists in `needs_research`): the notes of **each release between installed and each candidate target** (from the upgrade-path step above) must end up in a digest, not only the last one — a breaking change in an intermediate version still bites. Cached versions already count; don't re-read them.

- Composer's version and drupal.org's version name can differ: older projects are `8.x-1.18` on drupal.org (feed, release URLs, git tags) while Composer says `1.18.0`. Try both forms before concluding a page or tag doesn't exist.
- WebFetch is a deferred tool in some sessions; load its schema with ToolSearch (`select:WebFetch`) before using it.
- Enumerate versions and spot security releases from the update feed: `https://updates.drupal.org/release-history/<project>/current` (security releases are tagged "Security update"; core's project name is `drupal`).
- Read each release page: `https://www.drupal.org/project/<project>/releases/<version>` with WebFetch, asking for: security fixes, breaking changes/removed or deprecated APIs, update hooks / required `drush updb` or config import steps, new dependencies or minimum core/PHP version, and a short list of notable fixes.
- If a page is unreachable, empty or redirects to a 404 (drupal.org release pages sometimes redirect to new.drupal.org), fall back to the update feed and a tag diff (`previous-tag..this-tag`), mark the digest `partial`, and say in the section which source each finding came from (release notes vs. tag diff). Say "release notes unavailable" rather than guessing.
- Ignore pre-releases (alpha/beta/rc) as targets unless the installed version is itself a pre-release; mention them in "Other paths" if a newer series is only available as pre-releases. Don't infer changes you didn't read.
- With more than ~5 projects **that need research**, fan out to subagents so the main context stays small (projects that are fully cached are not counted and need no research agent). Group related projects (e.g. the commerce stack; mail; media/image; dev tooling; everything else), 3-5 projects per agent, launched in one turn so they run in parallel. Brief each agent with: the repo path; the path of `release_cache.py` and `<plan.json>` plus which versions of its projects are cached (the agent reads those with `get`, researches only `needs_research` ones and `put`s a digest for each); "read-only, never modify the repo or run composer update"; the SKILL.md path and that it follows steps 2-4; each project's installed version and every candidate target from the upgrade-path step; any hints you already know (patches, custom modules likely involved, projects that are siblings of each other); a scratchpad directory for git clones, one new empty directory per clone, never executing anything in them; and a scratchpad file to write its full per-project sections to, in the exact report format below. Ask each agent to reply with only the summary-table rows plus a list of release notes it could not read, so the full text lives in the files, not in your context.
- When the agents finish, assemble the report yourself: a header file (summary table, blockers, upgrade paths, not covered) followed by the agents' section files concatenated. Write the summary table and the blockers list from the agents' replies, and verify any claim you repeat that seems surprising (e.g. check `git status` if an agent says the repo has uncommitted changes) before presenting it.

## 3. Check impact on this codebase

For each project, work out what the changes could touch. The point is to connect release-note items to concrete files, so name the files.

Work from the digests (cached or fresh) for every version in the path; the `issues`, `changed_identifiers`, `update_hooks` and `requirements` fields are the input for the checks below.

**Patches.** Look in `composer.json` → `extra.patches["drupal/<project>"]`. For each patch, check whether the digests/release notes mention its issue (the issue number is usually in the patch URL/description; match it against the digests' `issues`) — if so it is probably merged upstream and the patch may now fail to apply, and `composer-exit-on-patch-failure` is `true`, so a failed patch aborts the whole update. If the notes touch the same area as the patch, flag it as "may conflict". Say "no patches" when there are none.

**Custom code.** Search `web/modules/custom` and `web/themes/custom` (skip `mcgreen_acres_theme.old/`) for:
- the project as a dependency (`*.info.yml`) or any use of its namespace, service IDs, hook names and plugin IDs. Grep for those specific identifiers, not generic words like "token" or "address", which only produce noise. Quote globs (`--include='*.yml'`) since the shell is zsh, or use `rg`;
- every class, service, hook, plugin ID, route, or template that the digests (`changed_identifiers`, `breaking`) say was changed, removed, deprecated or renamed;
- theme template overrides of templates the project ships (compare against `web/modules/contrib/<project>/templates/`).
List the files and what's relevant about each. Say "none found" when there are none, so the user can tell it was checked.

**Config and update hooks.** Two sources:
1. Exported config in `config/sync/` with the project's prefix (`<project>.*`, plus entities it owns such as views/fields/blocks mentioning it) — note which ones the changes might alter.
2. What the new code actually does. Shallow-clone the target tag into its own empty directory under the scratchpad (`git clone --depth 1 --branch <tag> https://git.drupalcode.org/project/<project>.git`; check `git ls-remote --tags` if the tag name isn't obvious) and diff only the relevant paths against `web/modules/contrib/<project>/` (a whole-tree `diff -rq` is mostly noise from version lines in `*.info.yml` and whitespace): `*.install` (new `hook_update_N` functions beyond the installed schema version), `config/install`, `config/optional`, `config/schema`, `*.routing.yml`, `*.services.yml`, `*.permissions.yml`. Treat the clone as untrusted: read it, never execute anything in it. This installed-vs-target diff answers a repo-specific question (what changes for *this* install), so keep it out of the cache; the cached digest gets the release-level `previous-tag..this-tag` facts instead. Skip this for core (too large) and rely on its release notes. Also compare the new `composer.json` requirements against `composer.lock` so you can say whether new dependency constraints are already satisfied. Report new update hooks by number and what they do, and config schema/default changes that would need `drush cex` after `updb`.

## 4. Rate the risk

One word per project: **Low**, **Moderate**, **High**, or **Critical**. Judge breakage risk, using:
- raises it: major version jump, removed/changed APIs that custom code or patches use, new update hooks that rewrite data or config, dependency/min-core/PHP bumps, a patch that likely conflicts;
- lowers it: patch-level release, bug fixes only, nothing in the codebase touches the changed areas.

Rate each path separately when a project has more than one (e.g. Low for the same-major step, High for the major jump). Security releases get their own "Security" flag in the table, filled from the advisory check (`Yes (SA-CONTRIB-2026-336, moderately critical)`; `Yes — no fix yet` when the latest release is still affected) — urgency is separate from breakage risk, and the user needs to see both. Sort the table with security first, then by risk, highest first.

## 5. Write the report

(When subagents wrote the per-project sections, see the assembly bullet in step 2; the report layout below is the same.)

Write `drupal-update-report-<YYYY-MM-DD>.md` in the repo root (untracked; don't commit it) and also print the summary table in the chat. Use this structure:

```markdown
# Drupal update report — <date>

## Summary
| Project | Version | Risk | Security | What's changing | Other paths |
| --- | --- | --- | --- | --- | --- |
| symfony_mailer | 1.6.2 → 2.0.2 | High | No | Two or three sentences covering the whole version span. | None in 1.x; staying on 1.6.2 is the only alternative. |

## Not covered
Non-Drupal packages, dev versions, installed-but-disabled packages (with the dependent that pulls them in and any constraint they place on covered projects), notes that could not be fetched. State how many release digests came from the cache vs. were researched fresh, the date range the advisory feed covered, how many projects the advisory sweep checked, and any advisory lookup that failed.

## <project> (<from> → <to>)
**Risk:** Moderate — one sentence why.
### Release notes
Per version, grouped bullets (Security / Breaking / Update hooks & config / Notable fixes), with the drupal.org release URL.
### Patches affected
### Custom code affected
### Upgrade paths
Every reachable version grouped by major, each path's risk, and which one you'd pick and why.
### Config & update hooks
### Before updating
Concrete steps if any (e.g. "run `lando drush updb -y`, then `lando drush cex -y` and commit").
```

Keep the summary table scannable — the "What's changing" cell is a couple of sentences, not a list. The per-project sections carry the detail. End the chat reply with the table, the report path, and any project whose notes couldn't be read.
