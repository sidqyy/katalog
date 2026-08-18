import React from 'react';
import { View } from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import HomeScreen from './src/screens/HomeScreen';
import ProductDetailScreen from './src/screens/ProductDetailScreen';

const Stack = createNativeStackNavigator();

const DarkThemeConfig = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: '#0f172a',
    card: '#0f172a',
    text: '#f8fafc',
    border: '#1e293b',
  },
};

const linking = {
  prefixes: ['https://katalog.jsflorist.com', 'http://localhost', 'katalog://'],
  config: {
    screens: {
      Home: '',
      ProductDetail: 'product/:id',
    },
  },
};

export default function App() {
  return (
    <View style={{ flex: 1, backgroundColor: '#0f172a' }}>
      <NavigationContainer theme={DarkThemeConfig} linking={linking}>
        <Stack.Navigator 
          initialRouteName="Home"
          screenOptions={{
            headerStyle: { backgroundColor: '#0f172a' },
            headerTintColor: '#f8fafc',
            headerTitleStyle: { fontWeight: 'bold' },
            contentStyle: { backgroundColor: '#0f172a' }
          }}
        >
          <Stack.Screen 
            name="Home" 
            component={HomeScreen} 
            options={{ title: 'Katalog Produk', headerShown: false }} 
          />
          <Stack.Screen 
            name="ProductDetail" 
            component={ProductDetailScreen} 
            options={{ title: '', headerTransparent: true, headerTintColor: '#fff' }} 
          />
        </Stack.Navigator>
      </NavigationContainer>
    </View>
  );
}
