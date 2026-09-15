# ERO Mobile (Expo)

React Native app built with Expo SDK 52, released via EAS Build.

## Version management

`eas.json` sets `cli.appVersionSource: "remote"` and `autoIncrement: true` on
the `preview` and `production` profiles. This means:

- EAS tracks and **auto-increments the Android `versionCode`** (and iOS build
  number) on every build — no manual file edits required.
- Do **not** set `android.versionCode` in `app.config.ts`; it is intentionally
  omitted so the remote source is authoritative.
- The user-facing `version` (`1.0.0`) is still set in `app.config.ts`. Bump it
  there when you start a new release cycle for the stores.

## One-time setup before your first build

1. Install the CLI: `npm i -g eas-cli` (or use `npx eas-cli`).
2. Log in: `eas login`.
3. From `mobile/`, run `eas init` — this creates/links the EAS project and
   writes the real `projectId` into the app config. (You can also export it as
   `EAS_PROJECT_ID`.)

## Build commands

```bash
cd mobile

# Production Android App Bundle (.aab) for Google Play
eas build --platform android --profile production

# Internal APK you can sideload to verify the icon/name before submitting
eas build --platform android --profile preview
```

## Build profiles (eas.json)

| Profile      | Distribution | Android output | Auto-increment |
|--------------|--------------|----------------|----------------|
| development  | internal     | apk (dev client) | no           |
| preview      | internal     | apk            | yes            |
| production   | store        | app-bundle (.aab) | yes         |

## Resubmitting to Google Play (icon/name fix)

The app icon and name now match the Play Store listing:
- name: `ERO - Daily Exam Practice`
- icon: `src/assets/icon.png` + adaptive `src/assets/adaptive-icon.png`
  (real ERO chrome logo)

Steps:
1. `eas build --platform android --profile production` (versionCode auto-bumps).
2. Download the `.aab`, or use `eas submit --platform android` (the `submit`
   config uploads to the `internal` track as a `draft`).
3. In Play Console, confirm the installed icon + name match the listing, then
   promote/submit for review.
