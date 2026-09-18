import { ExpoConfig, ConfigContext } from 'expo/config';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'ERO - Daily Exam Practice',
  slug: 'quizpath',
  owner: 'importerbro',
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
    eas: {
      // Real EAS project ID for @importerbro/quizpath.
      projectId: process.env.EAS_PROJECT_ID || '8d7e9e0f-499d-44b0-b21f-22defa4511a9',
    },
  },
});
