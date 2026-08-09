# Kiro steering for this repository

`steering/` holds the project context Kiro should load in **every** session: what the product is,
which architectural decisions are settled and why, and the conventions and environment traps.

These files are committed on purpose — the repository is the only thing that travels between
machines and accounts, so this is how a fresh session picks up without re-deriving months of
decisions.

## Starting a session on a new machine or account

Kiro loads steering from the workspace configuration directory, which is **not** this folder. After
cloning, install these files into the load path once:

```bash
mkdir -p /projects/.kiro/steering
cp .kiro/steering/*.md /projects/.kiro/steering/
```

Then confirm Kiro has them by asking it to summarise the locked decisions. If it cannot name
ADR 001–003, the steering did not load.

Alternatively, just tell Kiro: *"read `.kiro/steering/*.md` in this repo and follow them"*.

## Keeping them true

Steering that has drifted out of date is worse than none, because it will be trusted. When a
decision changes, update `01-locked-decisions.md` in the same commit as the code, and record the
alternative that was rejected — the rejected option is usually the more useful half.
