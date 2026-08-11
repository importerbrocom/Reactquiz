import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAuthStore } from '../store/auth.store';

// Screens
import { LoginScreen } from '../screens/LoginScreen';
import { RegisterScreen } from '../screens/RegisterScreen';
import { DashboardScreen } from '../screens/DashboardScreen';
import { QuizScreen } from '../screens/QuizScreen';
import { DaysScreen } from '../screens/DaysScreen';
import { ProgressScreen } from '../screens/ProgressScreen';
import { ProfileScreen } from '../screens/ProfileScreen';

export type RootStackParamList = {
  Auth: undefined;
  Main: undefined;
  Quiz: { courseId: string; day: number };
};

export type AuthStackParamList = {
  Login: undefined;
  Register: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Days: undefined;
  Progress: undefined;
  Profile: undefined;
};

const RootStack = createNativeStackNavigator<RootStackParamList>();
const AuthStack = createNativeStackNavigator<AuthStackParamList>();
const MainTab = createBottomTabNavigator<MainTabParamList>();

function AuthNavigator() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
      <AuthStack.Screen name="Register" component={RegisterScreen} />
    </AuthStack.Navigator>
  );
}

function MainNavigator() {
  return (
    <MainTab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarStyle: { backgroundColor: '#0f172a', borderTopColor: '#1e293b' },
        tabBarActiveTintColor: '#818cf8',
        tabBarInactiveTintColor: '#64748b',
      }}
    >
      <MainTab.Screen name="Home" component={DashboardScreen} options={{ tabBarLabel: 'Home' }} />
      <MainTab.Screen name="Days" component={DaysScreen} options={{ tabBarLabel: 'Days' }} />
      <MainTab.Screen name="Progress" component={ProgressScreen} options={{ tabBarLabel: 'Progress' }} />
      <MainTab.Screen name="Profile" component={ProfileScreen} options={{ tabBarLabel: 'Profile' }} />
    </MainTab.Navigator>
  );
}

export function AppNavigator() {
  const { isAuthenticated, isLoading } = useAuthStore();

  if (isLoading) return null;

  return (
    <NavigationContainer>
      <RootStack.Navigator screenOptions={{ headerShown: false }}>
        {isAuthenticated ? (
          <>
            <RootStack.Screen name="Main" component={MainNavigator} />
            <RootStack.Screen name="Quiz" component={QuizScreen} options={{ gestureEnabled: false }} />
          </>
        ) : (
          <RootStack.Screen name="Auth" component={AuthNavigator} />
        )}
      </RootStack.Navigator>
    </NavigationContainer>
  );
}
