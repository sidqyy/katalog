import React, { useState, useEffect } from 'react';
import { View, Text, Image, StyleSheet, ScrollView, TouchableOpacity, Linking, Platform } from 'react-native';
import { fetchSettings } from '../api/apiService';

export default function ProductDetailScreen({ route, navigation }) {
  const { product } = route.params;

  const [waSettings, setWaSettings] = useState({
    wa1_name: 'Poppy Florist',
    wa1_number: '6281234567890',
    wa2_name: 'JSFlorist',
    wa2_number: '6289876543210'
  });

  useEffect(() => {
    const loadSettings = async () => {
      const settings = await fetchSettings();
      if (settings) {
        setWaSettings(settings);
      }
    };
    loadSettings();
  }, []);

  const handleOrderWhatsApp = (phoneNumber) => {
    const message = `Halo Admin, saya ingin memesan produk berikut:\n\nNama Produk: ${product.nama}\nHarga: Rp ${product.harga.toLocaleString('id-ID')}\nGambar: ${product.link_gambar}\n\nMohon info untuk proses selanjutnya. Terima kasih.`;
    const url = `whatsapp://send?phone=${phoneNumber}&text=${encodeURIComponent(message)}`;
    
    Linking.canOpenURL(url)
      .then((supported) => {
        if (supported) {
          return Linking.openURL(url);
        } else {
          alert('Aplikasi WhatsApp tidak ditemukan di perangkat ini.');
        }
      })
      .catch((err) => console.error('An error occurred', err));
  };

  return (
    <View style={styles.mainContainer}>
      <ScrollView contentContainerStyle={{flexGrow: 1}} bounces={false}>
        {/* Full Header Image */}
        <View style={styles.imageHeader}>
          <Image source={{ uri: product.link_gambar }} style={styles.image} resizeMode="cover" />
          <View style={styles.imageOverlay} />
        </View>
        
        {/* Overlapping Content Card */}
        <View style={styles.contentCard}>
          <View style={styles.categoryBadge}>
            <Text style={styles.categoryText}>{product.kategori || 'Koleksi Premium'}</Text>
          </View>
          
          <Text style={styles.name}>{product.nama}</Text>
          <Text style={styles.price}>Rp {product.harga.toLocaleString('id-ID')}</Text>
          
          <View style={styles.divider} />
          
          <Text style={styles.sectionTitle}>Deskripsi Produk</Text>
          <Text style={styles.description}>{product.deskripsi}</Text>
          
          <View style={{height: 40}} />
          
          {/* Action Buttons for WhatsApp */}
          <View style={styles.buttonRow}>
            <TouchableOpacity style={[styles.waButton, { flex: 1 }]} activeOpacity={0.9} onPress={() => handleOrderWhatsApp(waSettings.wa1_number)}>
              <Text style={styles.waIcon}>💬</Text>
              <Text style={styles.waButtonText}>Pesan via {waSettings.wa1_name}</Text>
            </TouchableOpacity>
            <View style={{ width: 10 }} />
            <TouchableOpacity style={[styles.waButton, { flex: 1, backgroundColor: '#0ea5e9', shadowColor: '#0ea5e9' }]} activeOpacity={0.9} onPress={() => handleOrderWhatsApp(waSettings.wa2_number)}>
              <Text style={styles.waIcon}>💬</Text>
              <Text style={styles.waButtonText}>Pesan via {waSettings.wa2_name}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  mainContainer: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  imageHeader: {
    width: '100%',
    maxWidth: 1000,
    alignSelf: 'center',
    height: Platform.OS === 'web' ? 500 : 400,
    position: 'relative',
  },
  image: { 
    width: '100%', 
    height: '100%'
  },
  imageOverlay: {
    position: 'absolute',
    bottom: 0, left: 0, right: 0, height: 150,
    backgroundColor: 'transparent',
    backgroundImage: Platform.OS === 'web' ? 'linear-gradient(to bottom, rgba(15,23,42,0) 0%, rgba(15,23,42,1) 100%)' : undefined,
  },
  contentCard: { 
    flex: 1,
    width: '100%',
    maxWidth: 900,
    alignSelf: 'center',
    marginTop: -50,
    backgroundColor: 'rgba(30, 41, 59, 0.95)',
    borderTopLeftRadius: 35,
    borderTopRightRadius: 35,
    padding: 25,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.05)',
    ...Platform.select({
      web: { backdropFilter: 'blur(20px)' }
    })
  },
  categoryBadge: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(56, 189, 248, 0.15)',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    marginBottom: 15,
  },
  categoryText: {
    color: '#38bdf8',
    fontSize: 12,
    fontWeight: 'bold',
    textTransform: 'uppercase',
    letterSpacing: 1,
  },
  name: { 
    fontSize: 28, 
    fontWeight: '800', 
    color: '#f8fafc', 
    marginBottom: 10,
    lineHeight: 34,
  },
  price: { 
    fontSize: 26, 
    color: '#34d399', 
    fontWeight: '900', 
    marginBottom: 20 
  },
  divider: {
    height: 1,
    backgroundColor: 'rgba(255,255,255,0.1)',
    marginVertical: 20,
  },
  sectionTitle: { 
    fontSize: 18, 
    fontWeight: '700', 
    color: '#e2e8f0',
    marginBottom: 15,
    letterSpacing: 0.5,
  },
  description: { 
    fontSize: 16, 
    color: '#94a3b8', 
    lineHeight: 26, 
  },
  buttonRow: {
    flexDirection: 'row',
    width: '100%',
    justifyContent: 'space-between',
    marginTop: 'auto',
  },
  waButton: { 
    flexDirection: 'row',
    backgroundColor: '#10b981', 
    paddingVertical: 14, 
    paddingHorizontal: 10,
    borderRadius: 100, 
    alignItems: 'center',
    justifyContent: 'center',
    ...Platform.select({
      ios: { shadowColor: '#10b981', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 15 },
      android: { elevation: 8 },
      web: { boxShadow: '0 10px 25px -5px rgba(16, 185, 129, 0.5)' }
    }),
  },
  waIcon: {
    fontSize: 18,
    marginRight: 6,
  },
  waButtonText: { 
    color: '#fff', 
    fontSize: 13, 
    fontWeight: 'bold',
    letterSpacing: 0.5,
  }
});
