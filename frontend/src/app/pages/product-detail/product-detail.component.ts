import { AsyncPipe, CurrencyPipe, DatePipe, DecimalPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';

import { errorMessage } from '../../models/api-error.model';
import { Product, ProductImage } from '../../models/product.model';
import { ImportService } from '../../services/import.service';
import { ProductService } from '../../services/product.service';
import { ImportStatusComponent } from '../../shared/import-status/import-status.component';

interface DetailState {
  loading: boolean;
  product: Product | null;
  error: string | null;
}

@Component({
  selector: 'app-product-detail',
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DatePipe,
    DecimalPipe,
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatTableModule,
    MatProgressBarModule,
    ImportStatusComponent,
  ],
  templateUrl: './product-detail.component.html',
  styleUrl: './product-detail.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductDetailComponent {
  private readonly productService = inject(ProductService);
  private readonly importService = inject(ImportService);

  /** :id из маршрута (withComponentInputBinding). */
  readonly id = input.required<string>();

  /** Выбранная картинка в галерее. */
  readonly selectedImage = signal<ProductImage | null>(null);

  readonly attributeColumns = ['key', 'value'];

  /** Последний импорт: прогресс, если он ещё идёт, и отчёт об ошибках после. */
  readonly lastImport$ = this.importService.watchLatest().pipe(catchError(() => of(null)));

  readonly state$: Observable<DetailState> = toObservable(this.id).pipe(
    switchMap((id) => {
      this.selectedImage.set(null);
      const numericId = Number(id);
      if (!Number.isInteger(numericId) || numericId < 1) {
        return of<DetailState>({ loading: false, product: null, error: 'Товар не найден.' });
      }
      return this.productService.getProduct(numericId).pipe(
        map((product): DetailState => ({ loading: false, product, error: null })),
        catchError((err: unknown) => of<DetailState>({ loading: false, product: null, error: errorMessage(err) })),
        startWith<DetailState>({ loading: true, product: null, error: null }),
      );
    }),
  );

  mainImage(product: Product): ProductImage | null {
    return this.selectedImage() ?? product.images[0] ?? null;
  }

  hasDiscount(product: Product): boolean {
    return product.discount > 0;
  }
}
