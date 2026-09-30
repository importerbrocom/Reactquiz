# Mobile (Expo/EAS), Google Play & Apple App Store — Build and Release Guide

Project-specific knowledge for the `mobile/` Expo app and both store release
processes. Captures hard-won fixes so we don't repeat the debugging.

## Identities (do NOT change without verifying against Play + Expo)

- **App name (store + app):** `ERO - Daily Exam Practice`
- **Android package / iOS bundle id:** `com.ero.quiz`
  (Play Console showed temporary name `com.ero.quiz` — this is the real package.)
- **Expo account (owner):** `importerbro`
- **EAS project:** slug `ero-quiz`, id `6d4074a5-90ad-498b-a4d5-1b3c29679fe6`
  - NOTE: there is also a stray `quizpath` project (id `8d7e9e0f-...`) — that is
    the WRONG one. Always use `ero-quiz`.
- Config lives in `mobile/app.config.ts` (`slug`, `owner`, `extra.eas.projectId`,
  `android.package`, `ios.bundleIdentifier`).

## Expo SDK / versions (SDK 52 — do NOT let it drift to 57)

- Expo **SDK 52**: `expo ~52`, `react 18.3.1`, `react-native 0.76.9`,
  `@types/react ~18.3.12`.
- **NEVER run `npx expo install --fix` or `npx expo install`** on a machine that
  has a global `expo@57` — it rewrites `package.json` to SDK 57 and breaks
  everything (ERESOLVE, missing `expo/config-plugins`, `crypto-random-string`,
  etc.). If deps are missing, add the exact SDK-52 version to `package.json`
  manually instead.
- If a machine's `package.json` got bumped to 57, restore with
  `git checkout -- package.json` then `npm install --legacy-peer-deps`.

## Required files/config for the build (all already committed)

- `mobile/babel.config.js` — `babel-preset-expo` + `react-native-reanimated/plugin`
  (reanimated plugin MUST be last). Without babel config the "Bundle JavaScript"
  phase fails.
- `mobile/metro.config.js` — default `expo/metro-config`.
- `mobile/src/App.tsx` — must call `registerRootComponent(App)` because
  `package.json` "main" is `src/App.tsx`.
- Runtime deps the app needs (were missing, now added): `expo-asset`,
  `expo-font`, `expo-splash-screen`, `react-native-gesture-handler`,
  `react-native-reanimated`.
- **`mobile/package-lock.json` MUST be committed.** EAS Build runs
  `npm ci --include=dev`, which fails ("Install dependencies" step) without a
  lockfile. Regenerate with a clean `npm install --legacy-peer-deps` and commit.

## Versioning (Play requires each upload to have a HIGHER versionCode)

- `eas.json` uses `cli.appVersionSource: "local"` (NOT remote) and the
  production profile has **no** `autoIncrement`, so the versionCode is taken
  literally from `app.config.ts` `android.versionCode`.
- Play already used **versionCode 6** (v1.0.5). Current config = **versionCode 7,
  version 1.0.6**. For each new upload, bump `android.versionCode` (+1) and
  usually `version` too.

## iOS / Apple App Store (the side that keeps failing)

The iOS App Store build fails at ~39s while the Android build of the same commit
succeeds. A sub-60s failure is **before the Xcode compile** — it is config or
credentials, never app code. The two causes, in order of likelihood:

1. **Apple signing/credentials.** EAS needs a Distribution Certificate and an App
   Store provisioning profile for `com.ero.quiz`, tied to an **active Apple
   Developer Program membership ($99/yr)**. No membership → no way to produce
   either → the build dies early. Set these up interactively with `eas
   credentials` (choose iOS → production → let EAS generate and store them). This
   is the step a machine without the owner's Apple login CANNOT do for him.
2. **Missing iOS `buildNumber`.** `eas.json` has `appVersionSource: "local"`, so
   EAS reads versions from `app.config.ts`, not the server. Android works because
   `android.versionCode` is set; the `ios` block has **no `buildNumber`**, so
   there is nothing to stamp the build with. Add `ios.buildNumber` (a string) and
   bump it on every App Store upload, exactly as `android.versionCode` is bumped:

   ```ts
   ios: {
     supportsTablet: false,
     bundleIdentifier: 'com.ero.quiz',
     buildNumber: '9',            // string; +1 every App Store build
     infoPlist: { NSAppTransportSecurity: { NSAllowsArbitraryLoads: false } },
   },
   ```

- The `production` profile in `eas.json` configures only `android`; iOS falls back
  to defaults. That is fine for building, but be explicit if a distribution quirk
  appears.
- **Reading the failure is mandatory before guessing.** The dashboard shows *that*
  it failed; the build detail page shows *why*. Open the red phase (Install
  dependencies / Prebuild / Fastlane / credentials) and read the last ~20 lines.
  From a machine with the owner logged in: `eas build:list --platform ios
  --limit 3` then `eas build:view <BUILD_ID>`.
- Build/submit commands (mirror the Android flow):

  ```
  eas build  --platform ios --profile production
  eas submit --platform ios --profile production   # uploads to App Store Connect
  ```

- After `eas submit`, finish in **App Store Connect**: attach the build to an app
  record (bundle id `com.ero.quiz`), fill listing + privacy, submit for App
  Review (1–3 days). TestFlight is the way to test a production build on device.
- The `agent`/sandbox cannot log into Expo or Apple as the owner, so it can fix
  **config** (buildNumber, eas.json) and explain **credentials**, but the owner
  must run `eas credentials`, `eas build`, and the App Store Connect steps himself.

## Build & release procedure (run on a real machine, NOT cPanel)

cPanel has no Node and cannot build or upload Android apps. Build on a PC:

```
cd <repo>/mobile
git pull origin main
# clean install to (re)generate a valid lockfile, then commit it
npm install --legacy-peer-deps
git add package-lock.json package.json && git commit -m "lockfile" && git push
eas build --platform android --profile production
```
- Login: `eas login` (browser or terminal). Owner account = `importerbro`.
- Prompts: "install expo-updates?" → **n**; "generate keystore?" → **y** (EAS
  manages signing; reuses the existing `ero-quiz` keystore on later builds).
- Output: a `.aab` download link on the build page
  (`expo.dev/accounts/importerbro/projects/ero-quiz/builds/...`).

Upload: Play Console → Test and release → Production → Create/Edit release →
upload the `.aab` → Save → Review → Roll out. Then Policy status → the
Misleading Claims issue → Submit for review.

## The Google Play policy issue we are fixing

- Violation: **Misleading Claims → "App store listing mismatch"** — the app's
  installed icon/name differed from the store listing.
- Store listing icon = correct ERO chrome logo. The **"In-app experience"** icon
  was a blue blob baked into the old `.aab`. This is fixable ONLY by building a
  NEW `.aab` (with the ERO icon assets in `mobile/src/assets/`) and uploading it.
  It CANNOT be fixed from Play Console settings or from cPanel.

## Website / PWA (separate from the Android app)

- The live site (`mediprep.nokkoo.in`) is served from cPanel and is behind
  **Cloudflare** (30-day cache — must Purge Everything after changes).
- A **service worker** (Workbox) precaches icons/manifest; bump cache names or
  unregister it to force updates. Icons and manifest were versioned as
  `ero-*-v3.png` to bypass stale caches. The PWA install prompt now shows "ERO".

## Security note

- GitHub PATs and any secrets must NEVER be pasted into chat. If exposed, revoke
  immediately at github.com/settings/tokens and generate a new one.
