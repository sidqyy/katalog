import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, FlatList, Image, TouchableOpacity, StyleSheet, ActivityIndicator, Linking, Platform, useWindowDimensions, ScrollView } from 'react-native';
import { fetchProducts, fetchCategories } from '../api/apiService';

// Untuk tampilan web agar tidak terlalu lebar, kita batasi lebarnya
const MAX_WIDTH = 1200;
const isWeb = Platform.OS === 'web';

export default function HomeScreen({ navigation }) {
  const { width } = useWindowDimensions();
  const numCols = width >= 1024 ? 4 : width >= 768 ? 3 : width >= 480 ? 2 : 1;
  const paddingHorizontal = width >= 768 ? 20 : 10;
  
  // Kalkulasi lebar maksimal kartu agar tidak stretch saat jumlah item kurang dari jumlah kolom
  const maxCardWidth = (Math.min(width, MAX_WIDTH) - (paddingHorizontal * 2)) / numCols - 20;
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [search, setSearch] = useState('');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [kategori, setKategori] = useState('Semua');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const [categories, setCategories] = useState(['Semua']);

  useEffect(() => {
    const loadCategories = async () => {
      const fetchedCats = await fetchCategories();
      setCategories(['Semua', ...fetchedCats]);
    };
    loadCategories();
  }, []);

  const fetchApiData = async (currentPage, isRefresh = false) => {
    if (currentPage === 1 && !isRefresh) setLoading(true);
    else if (isRefresh) setRefreshing(true);
    else setLoadingMore(true);

    const queryKategori = kategori === 'Semua' ? '' : kategori;
    const data = await fetchProducts(search, minPrice, maxPrice, queryKategori, currentPage, 10);
    
    if (data.length < 10) setHasMore(false);
    else setHasMore(true);

    if (currentPage === 1) setProducts(data);
    else setProducts(prev => [...prev, ...data]);
    
    setLoading(false);
    setRefreshing(false);
    setLoadingMore(false);
  };

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      setPage(1);
      fetchApiData(1);
    }, 500);
    return () => clearTimeout(timeoutId);
  }, [search, minPrice, maxPrice, kategori]);

  const handleLoadMore = () => {
    if (!loadingMore && !loading && hasMore) {
      const nextPage = page + 1;
      setPage(nextPage);
      fetchApiData(nextPage);
    }
  };

  const onRefresh = () => {
    setPage(1);
    fetchApiData(1, true);
  };

  const renderItem = ({ item }) => (
    <TouchableOpacity 
      style={[styles.card, { maxWidth: maxCardWidth }]} 
      activeOpacity={0.8}
      onPress={() => navigation.navigate('ProductDetail', { product: item })}
    >
      <View style={styles.imageContainer}>
        <Image source={{ uri: item.link_gambar }} style={styles.image} resizeMode="cover" />
        <View style={styles.categoryBadge}>
          <Text style={styles.categoryText}>{item.kategori || 'Produk'}</Text>
        </View>
      </View>
      <View style={styles.cardBody}>
        <Text style={styles.name} numberOfLines={2}>{item.nama}</Text>
        <Text style={styles.price}>Rp {item.harga.toLocaleString('id-ID')}</Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      {/* Background Gradient Effect via Views */}
      <View style={styles.bgBlob1} />
      <View style={styles.bgBlob2} />

      <View style={styles.innerContainer}>
        {/* Header / Hero Section */}
        <View style={styles.header}>
          <Text style={styles.title}>Katalog Premium</Text>
          <Text style={styles.subtitle}>Temukan koleksi produk terbaik kami</Text>
        </View>

        {/* Kategori Filter */}
        <View style={styles.categoryContainer}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.categoryScroll}>
            {categories.map((cat, index) => (
              <TouchableOpacity 
                key={index} 
                style={[styles.categoryChip, kategori === cat && styles.categoryChipActive]}
                onPress={() => setKategori(cat)}
              >
                <Text style={[styles.categoryChipText, kategori === cat && styles.categoryChipTextActive]}>{cat}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>
        </View>

        {/* Filter & Search Bar - Glassmorphism Style */}
        <View style={styles.filterContainer}>
          <View style={styles.searchWrapper}>
            <Text style={styles.searchIcon}>🔍</Text>
            <TextInput
              style={styles.searchInput}
              placeholder="Cari produk impianmu..."
              placeholderTextColor="#94a3b8"
              value={search}
              onChangeText={setSearch}
            />
          </View>
          
          <View style={styles.priceFilter}>
            <View style={styles.priceInputWrapper}>
              <Text style={styles.currencyLabel}>Rp</Text>
              <TextInput
                style={styles.priceInput}
                placeholder="Min Harga"
                placeholderTextColor="#94a3b8"
                keyboardType="numeric"
                value={minPrice}
                onChangeText={setMinPrice}
              />
            </View>
            <Text style={styles.priceDivider}>-</Text>
            <View style={styles.priceInputWrapper}>
              <Text style={styles.currencyLabel}>Rp</Text>
              <TextInput
                style={styles.priceInput}
                placeholder="Max Harga"
                placeholderTextColor="#94a3b8"
                keyboardType="numeric"
                value={maxPrice}
                onChangeText={setMaxPrice}
              />
            </View>
          </View>
        </View>

        {/* Product List */}
        <View style={{flex: 1, width: '100%'}}>
          {loading ? (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color="#6366f1" />
              <Text style={styles.loadingText}>Memuat produk...</Text>
            </View>
          ) : (
            <FlatList
              key={numCols} // Force re-render jika kolom berubah (web)
              data={products}
              keyExtractor={(item) => item.id.toString()}
              numColumns={numCols}
              renderItem={renderItem}
              contentContainerStyle={[styles.listContainer, { paddingHorizontal }]}
              refreshing={refreshing}
              onRefresh={onRefresh}
              onEndReached={handleLoadMore}
              onEndReachedThreshold={0.5}
              ListFooterComponent={loadingMore ? <ActivityIndicator size="small" color="#6366f1" style={{ margin: 20 }} /> : null}
              ListEmptyComponent={
                <View style={styles.emptyContainer}>
                  <Text style={styles.emptyEmoji}>📦</Text>
                  <Text style={styles.emptyText}>Produk tidak ditemukan</Text>
                </View>
              }
            />
          )}
        </View>

        {/* Magic Button -> Navigasi ke Admin */}
        <TouchableOpacity 
          style={styles.magicButton} 
          onPress={() => Linking.openURL('http://localhost/Katalog/admin.php')} 
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { 
    flex: 1, 
    backgroundColor: '#0f172a', // Dark theme background
    alignItems: 'center',
  },
  innerContainer: {
    flex: 1,
    width: '100%',
    maxWidth: MAX_WIDTH,
    position: 'relative'
  },
  bgBlob1: {
    position: 'absolute', top: -100, left: -100, width: 300, height: 300,
    backgroundColor: 'rgba(99, 102, 241, 0.15)', borderRadius: 150,
  },
  bgBlob2: {
    position: 'absolute', top: '30%', right: -150, width: 400, height: 400,
    backgroundColor: 'rgba(16, 185, 129, 0.1)', borderRadius: 200,
  },
  header: {
    paddingTop: 40,
    paddingHorizontal: 20,
    paddingBottom: 20,
  },
  title: {
    fontSize: 32,
    fontWeight: '800',
    color: '#f8fafc',
    marginBottom: 5,
  },
  subtitle: {
    fontSize: 16,
    color: '#94a3b8',
  },
  categoryContainer: {
    marginBottom: 15,
  },
  categoryScroll: {
    paddingHorizontal: 20,
    gap: 10, // Hanya didukung di versi terbaru, fallback dengan marginRight pada chip jika bermasalah, tapi Expo modern support
  },
  categoryChip: {
    backgroundColor: 'rgba(30, 41, 59, 0.7)',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    marginRight: 10, // Fallback untuk gap
  },
  categoryChipActive: {
    backgroundColor: '#6366f1',
    borderColor: '#6366f1',
  },
  categoryChipText: {
    color: '#94a3b8',
    fontWeight: '600',
  },
  categoryChipTextActive: {
    color: '#fff',
  },
  filterContainer: { 
    marginHorizontal: 20,
    marginBottom: 20,
    padding: 20, 
    backgroundColor: 'rgba(30, 41, 59, 0.7)', 
    borderRadius: 24,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    ...Platform.select({
      ios: { shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.3, shadowRadius: 20 },
      android: { elevation: 10 },
      web: { boxShadow: '0 20px 40px -10px rgba(0,0,0,0.5)', backdropFilter: 'blur(12px)' }
    }),
  },
  searchWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    borderRadius: 16,
    paddingHorizontal: 15,
    marginBottom: 15,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.05)',
  },
  searchIcon: {
    fontSize: 18,
    marginRight: 10,
  },
  searchInput: { 
    flex: 1, 
    color: '#f8fafc',
    fontSize: 16,
    paddingVertical: 12,
    outlineStyle: 'none' // For Web
  },
  priceFilter: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    justifyContent: 'space-between' 
  },
  priceInputWrapper: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    borderRadius: 12,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.05)',
  },
  currencyLabel: {
    color: '#64748b',
    fontWeight: 'bold',
    marginRight: 8,
  },
  priceInput: { 
    flex: 1,
    color: '#f8fafc',
    paddingVertical: 10,
    fontSize: 14,
    outlineStyle: 'none'
  },
  priceDivider: {
    color: '#64748b',
    marginHorizontal: 10,
    fontWeight: 'bold',
  },
  listContainer: { 
    padding: 10,
    paddingBottom: 40,
  },
  card: { 
    // Pastikan flex tetap 1, tapi width kita paksa agar di mobile 1 kolom tidak terlalu besar
    flex: 1, 
    margin: 10,
    width: '100%',
    backgroundColor: 'rgba(30, 41, 59, 0.8)', 
    borderRadius: 20, 
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    ...Platform.select({
      ios: { shadowColor: '#000', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.2, shadowRadius: 10 },
      android: { elevation: 5 },
      web: { boxShadow: '0 10px 25px -5px rgba(0,0,0,0.3)', transition: 'transform 0.3s ease' }
    }),
  },
  // Efek hover untuk web (React Native standar tak dukung langsung di StyleSheet, tapi box-shadow/transition bisa)
  imageContainer: {
    position: 'relative',
    width: '100%',
    aspectRatio: 1, // Persegi
    backgroundColor: 'rgba(15, 23, 42, 0.5)',
  },
  image: { 
    width: '100%', 
    height: '100%'
  },
  categoryBadge: {
    position: 'absolute',
    top: 10,
    left: 10,
    backgroundColor: 'rgba(15, 23, 42, 0.7)',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 10,
    backdropFilter: 'blur(5px)',
  },
  categoryText: {
    color: '#38bdf8',
    fontSize: 10,
    fontWeight: 'bold',
    textTransform: 'uppercase',
  },
  cardBody: {
    padding: 15,
  },
  name: { 
    fontSize: 15, 
    fontWeight: '600', 
    color: '#e2e8f0', 
    marginBottom: 8,
    lineHeight: 22,
  },
  price: { 
    fontSize: 18, 
    color: '#34d399', 
    fontWeight: '800' 
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 50,
  },
  loadingText: {
    color: '#94a3b8',
    marginTop: 15,
    fontSize: 16,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 60,
  },
  emptyEmoji: {
    fontSize: 50,
    marginBottom: 10,
  },
  emptyText: {
    color: '#94a3b8',
    fontSize: 18,
  },
  magicButton: { 
    position: 'absolute', 
    bottom: 10, 
    right: 10, 
    width: 30, 
    height: 30, 
    backgroundColor: 'rgba(255,255,255,0.02)', 
    borderRadius: 15, 
    zIndex: 999 
  }
});
