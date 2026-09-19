import { ExpoConfig, ConfigContext } from 'expo/config';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'ERO - Daily Exam Practice',
  slug: 'ero-quiz',
  owner: 'importerbro',
  version: '1.0.7',
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
    bundleIdentifier: 'com.ero.quiz',
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
    package: 'com.ero.quiz',
    // versionCode 9: Play already has versionCode 8 (rejected: targeted API 34).
    // Each upload must be higher. Using local versioning (appVersionSource="local").
    versionCode: 9,
  },
  plugins: [
    'expo-secure-store',
    'expo-notifications',
    [
      'expo-build-properties',
      {
        android: {
          // Google Play now requires new uploads to target API 36.
          compileSdkVersion: 36,
          targetSdkVersion: 36,
          buildToolsVersion: '36.0.0',
        },
      },
    ],
  ],
  extra: {
    apiBaseUrl: process.env.API_BASE_URL || 'https://mediprep.nokkoo.in/api/v1',
    eas: {
      // Real EAS project ID for @importerbro/quizpath.
      projectId: process.env.EAS_PROJECT_ID || '6d4074a5-90ad-498b-a4d5-1b3c29679fe6',
    },
  },
});
