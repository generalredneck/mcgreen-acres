---
type: llm
focus: trace
weight: 1
---

The result matches this description: release_cache.py plan is run before any release notes are fetched; versions already cached are read with 'get' and not re-researched, every newly researched version is stored with 'put' as a valid digest, partial digests are marked partial, and the report says how many digests came from the cache vs. fresh research. Repo-specific impact (patches, custom code, config) is still recomputed rather than read from the cache.
