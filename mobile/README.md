# ERO Mobile App (React Native / Expo)

## Setup

1. Install Node.js 18+ and npm
2. Install Expo CLI: `npm install -g expo-cli eas-cli`
3. Create free Expo account: https://expo.dev
4. Login: `eas login`

## Install dependencies

```bash
cd mobile
npm install
```

## Run locally (development)

```bash
npx expo start
```
Scan the QR code with Expo Go app on your phone.

## Build Android APK

```bash
# First time: link to Expo project
eas init

# Build APK for testing
eas build --platform android --profile preview

# Build AAB for Play Store
eas build --platform android --profile production
```

## Build iOS (requires Apple Developer account)

```bash
eas build --platform ios --profile production
```

## App Features

- Login / Register
- Daily Quiz (10 questions from API)
- Answer submission with instant feedback
- Progress tracking
- Profile management
- Dark theme matching ERO branding
- Connects to: https://mediprep.nokkoo.in/api/v1

## API Configuration

Edit `app.json` → `expo.extra.apiBaseUrl` to change the API URL.
Or edit `src/api/client.ts` directly.
