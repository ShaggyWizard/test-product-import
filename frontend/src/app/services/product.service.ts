import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { Product, ProductPage, ProductQuery } from '../models/product.model';

/** Запросы к API товаров. Компоненты не ходят в HttpClient напрямую. */
@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = '/api/products';

  /** Страница списка: пагинация и фильтры считаются на сервере. */
  getProducts(query: ProductQuery): Observable<ProductPage> {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);

    const name = query.name?.trim();
    if (name) {
      params = params.set('name', name);
    }
    if (query.priceMin != null) {
      params = params.set('priceMin', query.priceMin);
    }
    if (query.priceMax != null) {
      params = params.set('priceMax', query.priceMax);
    }

    return this.http.get<ProductPage>(this.baseUrl, { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.baseUrl}/${id}`);
  }
}
