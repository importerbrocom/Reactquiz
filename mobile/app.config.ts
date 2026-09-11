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
  },
  plugins: ['expo-secure-store', 'expo-notifications'],
  extra: {
    apiBaseUrl: process.env.API_BASE_URL || 'https://api.quizpath.com/api/v1',
    eas: {
      projectId: 'your-eas-project-id',
    },
  },
});
