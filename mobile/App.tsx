import React, { useEffect, useState } from 'react';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { StatusBar } from 'expo-status-bar';
import { Ionicons } from '@expo/vector-icons';
import * as SecureStore from 'expo-secure-store';

import LoginScreen from './src/screens/LoginScreen';
import RegisterScreen from './src/screens/RegisterScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import QuizScreen from './src/screens/QuizScreen';
import ProfileScreen from './src/screens/ProfileScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

const DarkTheme = {
  ...DefaultTheme,
  colors: { ...DefaultTheme.colors, background: '#0B1120', card: '#1e293b', text: '#f8fafc', border: '#334155', primary: '#3b82f6' },
};

function MainTabs() {
  return (
    <Tab.Navigator screenOptions={({ route }) => ({
      headerShown: false,
      tabBarStyle: { backgroundColor: '#1e293b', borderTopColor: '#334155', paddingBottom: 8, paddingTop: 8, height: 65 },
      tabBarActiveTintColor: '#3b82f6',
      tabBarInactiveTintColor: '#64748b',
      tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
      tabBarIcon: ({ color, size }) => {
        let iconName: any = 'home';
        if (route.name === 'Dashboard') iconName = 'home';
        else if (route.name === 'Quiz') iconName = 'document-text';
        else if (route.name === 'Profile') iconName = 'person';
        return <Ionicons name={iconName} size={size} color={color} />;
      },
    })}>
      <Tab.Screen name="Dashboard" component={DashboardScreen} />
      <Tab.Screen name="Quiz" component={QuizScreen} options={{ tabBarLabel: "Today's Quiz" }} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
}

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggedIn, setIsLoggedIn] = useState(false);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    const token = await SecureStore.getItemAsync('access_token');
    setIsLoggedIn(!!token);
    setIsLoading(false);
  };

  if (isLoading) return null;

  return (
    <NavigationContainer theme={DarkTheme}>
      <StatusBar style="light" />
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!isLoggedIn ? (
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="Register" component={RegisterScreen} />
          </>
        ) : null}
        <Stack.Screen name="Main" component={MainTabs} />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
