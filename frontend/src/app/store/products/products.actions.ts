import { createAction, props } from '@ngrx/store';

import { ProductPage, ProductQuery } from '../../models/product.model';

/** Запросить страницу товаров с этими параметрами. */
export const loadProducts = createAction('[Products] Load Products', props<{ query: ProductQuery }>());

/** Сервер вернул страницу. */
export const loadProductsSuccess = createAction('[Products] Load Products Success', props<{ page: ProductPage }>());

/** Запрос не удался. */
export const loadProductsFailure = createAction('[Products] Load Products Failure', props<{ error: string }>());
