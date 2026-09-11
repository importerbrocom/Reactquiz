# App icon assets — REQUIRED for Google Play

Google Play rejected the app under the **Misleading Claims policy** because the
**installed app icon and name did not match the Play Store listing**
("App store listing mismatch").

The store listing uses the branded **ERO** chrome ring logo and the name
**"ERO - Daily Exam Practice"**. The build must use the SAME icon and name.

## Drop the following files into this folder

All must be the **official ERO chrome logo** (the same artwork used on the Play
Store listing icon), exported as PNG:

| File                 | Size (px) | Purpose                                                        |
|----------------------|-----------|----------------------------------------------------------------|
| `icon.png`           | 1024×1024 | Main app icon (iOS + fallback). Square, no transparency.       |
| `adaptive-icon.png`  | 1024×1024 | Android adaptive icon foreground. Logo centered, transparent bg, keep artwork inside the safe zone (~66% center) so the circular mask doesn't crop it. |
| `splash.png`         | 1284×2778 (or 2048×2048) | Splash screen logo on `#0B1120` background. |

## Important
- Use the EXACT artwork from the Play Store listing icon so the two match.
- Do NOT reuse the old blue-blob placeholder — that is what caused the rejection.
- After adding these files, rebuild (`eas build --platform android`), install
  the build, and visually confirm the launcher icon + app name match the store
  listing before resubmitting.
