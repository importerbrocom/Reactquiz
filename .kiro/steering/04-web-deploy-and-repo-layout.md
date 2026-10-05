# Web app, deployment & repo layout — what actually ships, and why it doesn't

Project-specific knowledge for the `web/` React app and how (and whether) it
reaches the live site. Captures hard-won facts so we don't re-debug them.

## What is actually live at mediprep.nokkoo.in

- The live site is the **`web/` React 19 PWA** (Vite build). Its browser tab
  title is **"ERO - Elior Research Orbit"**.
- It is a **PWA with a Workbox service worker** that aggressively precaches
  assets. After any deploy, a stale service worker can keep serving old files
  in a browser — bump cache names / unregister, and Cloudflare must be purged
  (30-day cache). This is why "I changed it but nothing changed" is usually a
  cache, not a code, problem.

## The deploy pipeline is BROKEN — nothing reaches the site

This is the single most important fact: **editing `web/` and merging to `main`
does NOT update the live site today.** Confirmed repeatedly (failures back to
Aug 2026, still failing Oct 2026).

- `.github/workflows/deploy-web.yml` has two defects:
  1. **Trigger is `paths: ['web/**']` only.** Any change OUTSIDE `web/` (e.g. a
     root-level file) never triggers a deploy at all.
  2. **The "Deploy to server" step fails every run** with
     `error: missing server host`. The `appleboy/ssh-action` inputs
     (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`) are **empty GitHub repo
     secrets**. These are not in the repo and the agent cannot set them — the
     owner must add them under Settings → Secrets and variables → Actions.
  3. Even if the SSH connected, the script only does `git pull origin main` and
     has a `# Copy built assets` comment but **never copies `web/dist`** to the
     web root. So it would still not publish the Vite build.
- `Build Web` (`build-web.yml`) SUCCEEDS — the app compiles fine. Only the
  deploy step is broken. So a green "build" tells you nothing about whether the
  site updated.
- To actually ship, the workflow needs real secrets AND a real publish step
  (rsync `web/dist` to the server web root). Fixing the workflow is safe for the
  agent; adding the secrets and confirming the server path is the owner's part.

## Repo layout — source vs. stale build artifacts

The repo root historically mixed real source with committed build output. After
the cleanup (PR #34) the root should contain only sources + repo essentials:

- **Real projects:** `web/` (React source — the live site), `api/` (Laravel),
  `mobile/` (Expo).
- **Essentials:** `.github/`, `.kiro/`, `deploy/`, `docs/`, `load-tests/`,
  `tools/`, `Makefile`, `README.md`, `.gitignore`.
- **Removed as dead/duplicate** (do not reintroduce): `admin-dashboard.html`
  (an old standalone dashboard that was NOT served), a stale committed Vite
  build at the root (`index.html`, `assets/`, `service-worker.js`,
  `offline.html`, `manifest.webmanifest`, `favicon.svg`, `icons.svg`, root
  `icons/`), and `web-*.tar.gz` build archives. Icons already live in
  `web/public/icons/`; `admin-dashboard.html` edits had zero effect on the live
  site because the server serves the `web/` SPA for all routes.

## web/ theming — the design system

- Theme tokens live in **`web/src/styles/index.css`** under `@theme` (Tailwind
  v4 CSS-first config), NOT a `tailwind.config.js`.
- The ERO redesign shifted the **primary palette from indigo (#6366f1) to blue
  (#2563eb)**, deep-navy surfaces (`#0a0e1a` base), and added accent hues
  (teal/purple/amber/pink/green/cyan) for subject tiles and stat cards.
- Mobile mirrors this in **`mobile/src/theme.ts`** (`colors/spacing/radius/
  typography`). Keep the two in sync when either changes.
- A shared **`Logo`** component is the single brand mark: `web/src/components/
  ui/Logo.tsx` and `mobile/src/components/Logo.tsx`. Both currently point at the
  app icon as a PLACEHOLDER — swap `LOGO_SRC` / `LOGO_SOURCE` to the real
  `ero-logo` asset (one line) once it exists in the repo.

## Verifying web changes without a browser

The agent can't open the app, so verify with the toolchain (run from `web/`,
Node 22 via nvm):

```
npx tsc -b          # type-check
npx vite build      # production build; also surfaces most runtime-shape errors
```

A clean `tsc -b` + `vite build` is the bar before opening a web PR. (Mobile:
`cd mobile && npx tsc --noEmit`; there is no committed lockfile, so deps install
with `npm install --legacy-peer-deps`.)
