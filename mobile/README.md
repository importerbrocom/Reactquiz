# ERO Mobile (Expo)

React Native app built with Expo SDK 52, released via EAS Build.

## Version management

`eas.json` sets `cli.appVersionSource: "local"`. This means versioning is
**authoritative in `app.config.ts`** and you bump it manually per release:

- Bump `android.versionCode` (currently `8`) by hand for **every** upload to
  Google Play. Play rejects any bundle whose `versionCode` is not strictly
  higher than the previously uploaded one.
- Bump the user-facing `version` (currently `1.0.7`) when you start a new
  release cycle for the stores.
- EAS does **not** auto-increment with `appVersionSource: "local"`, so the
  values committed here are exactly what ends up in the build.

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
| preview      | internal     | apk            | no             |
| production   | store        | app-bundle (.aab) | no (manual) |

## Resubmitting to Google Play (icon/name fix)

The app icon and name now match the Play Store listing:
- name: `ERO - Daily Exam Practice`
- icon: `src/assets/icon.png` + adaptive `src/assets/adaptive-icon.png`
  (real ERO chrome logo)

Steps:
1. Bump `android.versionCode` in `app.config.ts`, then run
   `eas build --platform android --profile production`.
2. Download the `.aab`, or use `eas submit --platform android` (the `submit`
   config uploads to the `internal` track as a `draft`).
3. In Play Console, confirm the installed icon + name match the listing, then
   promote/submit for review.
