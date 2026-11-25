---
type: llm
focus: trace
weight: 1
---

The result matches this description: Packages that are in vendor/ but whose modules are not enabled (according to config/sync/core.extension.yml) are filtered out with filter_installed.py before any release notes are fetched; they are listed under 'Not covered' with the dependent that pulls them in, and any constraint they place on covered projects is mentioned. Packages with no module (e.g. drupal/coder) and composer metapackages of enabled modules are kept.
