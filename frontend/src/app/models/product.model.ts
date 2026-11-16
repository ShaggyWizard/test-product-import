/** Доп. атрибут товара из столбцов «Доп. поле: …». */
export interface ProductAttribute {
  id: number;
  key: string;
  value: string;
}

/** Картинка товара: исходная ссылка и локальная копия. */
export interface ProductImage {
  id: number;
  url: string;
  /** Путь к скачанному файлу; null — скачать не удалось. */
  path: string | null;
  /** Адрес для <img>: локальная копия или исходная ссылка. */
  src: string;
}

/** Карточка товара. */
export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  price: number;
  /** Скидка, %. */
  discount: number;
  createdAt: string;
  updatedAt: string;
  attributes: ProductAttribute[];
  images: ProductImage[];
}

/** Строка списка товаров. */
export interface ProductListItem {
  id: number;
  externalCode: string;
  name: string;
  price: number;
  discount: number;
  thumbnail: string | null;
}

/** Страница списка с сервера. */
export interface ProductPage {
  items: ProductListItem[];
  total: number;
  page: number;
  limit: number;
  totalPages: number;
}

/** Параметры запроса списка: страница, размер, поиск и диапазон цены. */
export interface ProductQuery {
  page: number;
  limit: number;
  name?: string | null;
  priceMin?: number | null;
  priceMax?: number | null;
}
