import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';

import { errorMessage } from '../../models/api-error.model';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  /**
   * switchMap: если пользователь быстро листает или меняет фильтр,
   * старый запрос отменяется и на экран не попадёт устаревший ответ.
   */
  readonly loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      switchMap(({ query }) =>
        this.productService.getProducts(query).pipe(
          map((page) => loadProductsSuccess({ page })),
          catchError((err: unknown) => of(loadProductsFailure({ error: errorMessage(err) }))),
        ),
      ),
    ),
  );
}
