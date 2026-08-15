// === KONFIGURASI URL API ===
// Gunakan ini untuk SERVER PRODUCTION (cPanel Rumahweb)
const BASE_URL = 'https://katalog.jsflorist.com/api/api_produk.php'; 

// Gunakan ini JIKA INGIN TESTING LOKAL (Hapus tanda // di bawah, dan beri // pada link production di atas)
// const BASE_URL = 'http://192.168.100.194/Katalog/api/api_produk.php'; 

export const fetchProducts = async (search = '', minPrice = '', maxPrice = '', kategori = '', page = 1, limit = 10) => {
  try {
    let url = `${BASE_URL}?page=${page}&limit=${limit}&`;
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (kategori) url += `kategori=${encodeURIComponent(kategori)}&`;
    if (minPrice) url += `min_price=${minPrice}&`;
    if (maxPrice) url += `max_price=${maxPrice}`;

    const response = await fetch(url);
    const json = await response.json();
    
    if (json.status === 'success') {
      const IP = '192.168.100.194';
      return json.data.map(item => ({
        ...item,
        link_gambar: item.link_gambar ? item.link_gambar.replace('localhost', IP).replace('127.0.0.1', IP) : item.link_gambar
      }));
    } else {
      throw new Error("Gagal mengambil data");
    }
  } catch (error) {
    console.error("Fetch API Error: ", error);
    return [];
  }
};

export const fetchCategories = async () => {
  try {
    const url = `${BASE_URL.replace('api_produk.php', 'api_kategori.php')}`;
    const response = await fetch(url);
    const json = await response.json();
    
    if (json.status === 'success') {
      return json.data;
    } else {
      throw new Error("Gagal mengambil kategori");
    }
  } catch (error) {
    console.error("Fetch Kategori Error: ", error);
    return [];
  }
};

export const fetchSettings = async () => {
  try {
    const url = `${BASE_URL.replace('api_produk.php', 'api_settings.php')}`;
    const response = await fetch(url);
    const json = await response.json();
    
    if (json.status === 'success') {
      return json.data;
    } else {
      throw new Error("Gagal mengambil settings");
    }
  } catch (error) {
    console.error("Fetch Settings Error: ", error);
    return null;
  }
};
