import { createReducer, on } from '@ngrx/store';

import { ProductListItem, ProductQuery } from '../../models/product.model';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export const PRODUCTS_FEATURE_KEY = 'products';

export const DEFAULT_QUERY: ProductQuery = { page: 1, limit: 20, name: null, priceMin: null, priceMax: null };

export interface ProductsState {
  items: ProductListItem[];
  total: number;
  totalPages: number;
  /** Параметры последнего запроса: при возврате из карточки список открывается там же. */
  query: ProductQuery;
  loading: boolean;
  error: string | null;
}

export const initialState: ProductsState = {
  items: [],
  total: 0,
  totalPages: 0,
  query: DEFAULT_QUERY,
  loading: false,
  error: null,
};

export const productsReducer = createReducer(
  initialState,
  on(loadProducts, (state, { query }): ProductsState => ({ ...state, query, loading: true, error: null })),
  on(
    loadProductsSuccess,
    (state, { page }): ProductsState => ({
      ...state,
      items: page.items,
      total: page.total,
      totalPages: page.totalPages,
      loading: false,
    }),
  ),
  on(loadProductsFailure, (state, { error }): ProductsState => ({ ...state, loading: false, error })),
);
