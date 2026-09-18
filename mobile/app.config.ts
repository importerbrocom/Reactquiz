import { ExpoConfig, ConfigContext } from 'expo/config';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'ERO - Daily Exam Practice',
  slug: 'quizpath',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './src/assets/icon.png',
  userInterfaceStyle: 'dark',
  splash: {
    image: './src/assets/splash.png',
    resizeMode: 'contain',
    backgroundColor: '#0B1120',
  },
  assetBundlePatterns: ['**/*'],
  ios: {
    supportsTablet: false,
    bundleIdentifier: 'com.quizpath.app',
    infoPlist: {
      NSAppTransportSecurity: {
        NSAllowsArbitraryLoads: false,
      },
    },
  },
  android: {
    adaptiveIcon: {
      foregroundImage: './src/assets/adaptive-icon.png',
      backgroundColor: '#0B1120',
    },
    package: 'com.quizpath.app',
    // versionCode is intentionally omitted: eas.json uses
    // cli.appVersionSource="remote" with autoIncrement, so EAS manages and
    // auto-increments the Android versionCode on every production build.
  },
  plugins: ['expo-secure-store', 'expo-notifications'],
  extra: {
    apiBaseUrl: process.env.API_BASE_URL || 'https://mediprep.nokkoo.in/api/v1',
    // NOTE: eas.projectId is intentionally omitted. Run `eas init` in the
    // mobile/ directory once; it writes the real projectId here automatically.
    // A placeholder value here causes "Invalid UUID appId" build errors.
    ...(process.env.EAS_PROJECT_ID
      ? { eas: { projectId: process.env.EAS_PROJECT_ID } }
      : {}),
  },
});
