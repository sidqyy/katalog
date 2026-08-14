const BASE_URL = 'http://127.0.0.1/Katalog/api/api_produk.php'; // Sesuaikan IP jika dijalankan di HP fisik

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
      return json.data;
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
