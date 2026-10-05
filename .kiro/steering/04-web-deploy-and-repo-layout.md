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

## The WORKING manual deploy (until the Actions pipeline is fixed)

The server (cPanel, user `uddjzwrz`, host `s803`) serves the site from
`~/mediprep.nokkoo.in/`. Confirmed facts about that folder:

- It is BOTH the Laravel API root AND the served web root AND a (broken) git
  clone on an empty `master` with no commits. `git pull` there is not viable.
- `.htaccess` routes `^api/(.*)$` → `public/index.php` (Laravel), and everything
  else → `index.html` (the SPA). So the live frontend is literally `index.html`
  + `assets/` sitting in that folder.
- **No Node/npm on the server** (`node: command not found`). The build CANNOT
  run there — it must be built off-server and the output uploaded.
- The API is confirmed at **`/api/v1`** (Laravel `routes/api.php` has
  `Route::prefix('v1')`). A POST to `/api/v1/auth/login` returns **422**
  (validation) when empty — that 422 is the "API is alive" signal. There is no
  `/health` route, so `/api/v1/health` 404s — that is NOT a failure.
- The web build's `VITE_API_BASE_URL` must therefore be
  `https://mediprep.nokkoo.in/api/v1` (this is already the default in
  `web/src/config/env.ts` and `build-web.yml`).

Proven deploy procedure (used successfully Oct 2026):

1. Ensure the redesign/changes are merged to `main`.
2. Trigger the build artifact (the `build-web.yml` workflow has
   `workflow_dispatch`): `gh api -X POST
   repos/importerbrocom/Reactquiz/actions/workflows/332727965/dispatches
   -f ref=main`. It builds `web/` with the right prod API URL and uploads a
   `web-dist` artifact (30-day retention).
3. Owner downloads the `web-dist` artifact from the run page (browser, logged in
   as importerbro).
4. On the server, BACK UP first:
   `mkdir -p ~/backup-frontend-$(date +%F) && cp -a index.html assets
   manifest.webmanifest service-worker.js sw.js offline.html favicon.svg icons
   ~/backup-frontend-$(date +%F)/`
5. Upload the zip, then: `rm -rf assets` (clear old hashed chunks),
   `unzip -o web-dist.zip -d web-dist-new`, `cp -a web-dist-new/. ./`, clean up.
6. Verify `index.html`'s `assets/index-*.js|css` refs exist in `assets/`.
7. **Bust caches or the change is invisible:** Cloudflare → Purge Everything,
   and the Workbox service worker reactivates on the second load / can be
   unregistered via DevTools → Application → Service Workers.
8. Rollback if needed: `rm -rf assets && cp -a ~/backup-frontend-<date>/. ./`.

The redesigned UI was deployed this way and confirmed live. To make this
automatic, `deploy-web.yml` still needs the `DEPLOY_*` secrets AND a real
publish step (rsync `web/dist` into `~/mediprep.nokkoo.in/`, excluding the
Laravel dirs) — until then, the manual steps above are the path.
