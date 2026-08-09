# Running this project in Autonomous mode

Autonomous mode allows **one task per session**. You cannot start a second task in the same
session — steering it via chat only adjusts the current task's scope. So the unit of work is
**one session per phase**, not one session for the rest of the project.

## Pre-flight (do this once)

1. **Merge PR #2, then PR #3.** Autonomous mode clones the repository's default branch. Everything
   built so far — the whole quiz engine *and* the steering files — currently lives on
   `phase-3-quiz-engine`. If `main` is cloned as it stands today, the agent gets Phases 1–2 only
   and will start rebuilding work that already exists.
2. Confirm `.kiro/steering/*.md` is present on `main` after merging. Those files are picked up
   automatically and are what stop the agent re-deriving or contradicting settled decisions.

## Starting the session

At app.kiro.dev → **New session** → **Select repo** `importerbrocom/Reactquiz` → toggle
**Autonomous ON in the chat input bar before submitting** (it cannot be switched afterwards) →
paste the prompt.

## Answer the three open questions inside the prompt

Autonomous mode front-loads its clarifying questions, and if it needs an answer mid-run the task
parks in **Needs attention** and waits. Deciding these in the prompt avoids that:

1. **Explanations** — 247 of 305 questions have none. Ship without them, or hold those back?
2. **Option-letter references** — ~50 explanations say "option B is correct", which per-exposure
   shuffling makes wrong. Rewrite them, or set `shuffle_options = false` on those questions?
3. **`require_test_pass_to_advance`** — currently false, so a student advances having *sat* the
   month-end test, pass or fail. Keep, or require a pass?

## Prompt template — Phase 4 (React 19 PWA)

Replace the bracketed parts.

> Build Phase 4 of QuizPath: the React 19 student PWA in `web/`, against the existing Laravel API.
>
> Read `.kiro/steering/*.md` first — the product model, the locked decisions and the conventions are
> all there. `docs/phase-3/student-api.md` is the API contract. `docs/phase-1/06-pwa-and-offline.md`
> is the offline design. `docs/phase-1/02-folder-structures.md` has the intended layout.
>
> Scope:
> - Token auth against the existing Sanctum endpoints, with refresh handling.
> - Onboarding (exam → programme), dashboard driven by `next_action`, day timeline, the quiz player,
>   the month-end test with windowed fetching, progress and mistakes screens.
> - Offline support: cache the day payload, queue answers in an outbox, drain on reconnect. The API
>   is already idempotent for this — send `client_answer_uuid` per answer and `client_batch_uuid`
>   per test batch, and drop a queued answer when the response comes back `replayed: true`.
> - Submit the option `key`, never the display position. Option order comes from the server and must
>   not be re-sorted client-side.
> - Never cache answer verdicts or test results — the API marks them `no-store`.
>
> Decisions: [answer the three questions above]
>
> Acceptance criteria:
> - `npm run build` and `npm run test` pass; typecheck and lint clean.
> - Every screen listed above is reachable and works against a seeded API.
> - Airplane-mode test: finish a day offline, reconnect, and the answers arrive exactly once with no
>   duplicate submissions and no double-counted progress.
> - A locked day renders the server's `unlock_hint` rather than a generic error, and a 423 is not
>   treated as a failure.
> - No correct answers or explanations appear in any cached payload or client bundle.
>
> Out of scope: admin panel, imports, notifications, deployment.

## Later phases

Same shape, one session each: Phase 5 admin panel, Phase 6 XLSX/CSV import endpoint (format is
already designed in `docs/import-format.md` with templates in `docs/templates/`), then
notifications, reporting, hardening, deployment.

## When Autonomous mode is the wrong choice

It suits well-defined work with clear acceptance criteria. For deciding *what* to build, weighing
trade-offs, or anything you want to iterate on closely, use default (collaborative) mode. The three
open questions above are a good example — they are product decisions, not implementation tasks.
