import { AsyncPipe, CurrencyPipe, DecimalPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnInit, inject } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { Router, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';

import { ProductListItem, ProductQuery } from '../../models/product.model';
import { loadProducts } from '../../store/products/products.actions';
import { DEFAULT_QUERY } from '../../store/products/products.reducer';
import {
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsQuery,
  selectProductsTotal,
  selectTotalPages,
} from '../../store/products/products.selectors';

/** Минимальная цена не больше максимальной. */
function priceRangeValidator(group: AbstractControl): ValidationErrors | null {
  const min = group.get('priceMin')?.value as number | null;
  const max = group.get('priceMax')?.value as number | null;
  return min != null && max != null && min > max ? { priceRange: true } : null;
}

@Component({
  selector: 'app-product-list',
  imports: [
    AsyncPipe,
    CurrencyPipe,
    DecimalPipe,
    ReactiveFormsModule,
    RouterLink,
    MatTableModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
  ],
  templateUrl: './product-list.component.html',
  styleUrl: './product-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductListComponent implements OnInit {
  private readonly store = inject(Store);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);

  // Всё, что показывает шаблон, — из стора через async pipe, без ручных .subscribe().
  readonly products$ = this.store.select(selectAllProducts);
  readonly loading$ = this.store.select(selectProductsLoading);
  readonly totalPages$ = this.store.select(selectTotalPages);
  readonly total$ = this.store.select(selectProductsTotal);
  readonly query$ = this.store.select(selectProductsQuery);
  readonly error$ = this.store.select(selectProductsError);

  /** Текущие параметры без подписки — для запросов при смене страницы и фильтра. */
  private readonly query = this.store.selectSignal(selectProductsQuery);

  readonly columns = ['thumbnail', 'name', 'externalCode', 'price', 'discount'];

  readonly filters = this.fb.group(
    {
      name: this.fb.control<string | null>(this.query().name ?? null, Validators.maxLength(255)),
      priceMin: this.fb.control<number | null>(this.query().priceMin ?? null, Validators.min(0)),
      priceMax: this.fb.control<number | null>(this.query().priceMax ?? null, Validators.min(0)),
    },
    { validators: priceRangeValidator },
  );

  ngOnInit(): void {
    // При возврате из карточки список открывается на той же странице и с теми же фильтрами.
    this.load(this.query());
  }

  applyFilters(): void {
    if (this.filters.invalid) {
      this.filters.markAllAsTouched();
      return;
    }
    const { name, priceMin, priceMax } = this.filters.getRawValue();
    this.load({ ...this.query(), page: 1, name: name?.trim() || null, priceMin, priceMax });
  }

  resetFilters(): void {
    this.filters.reset({ name: null, priceMin: null, priceMax: null });
    this.load({ ...DEFAULT_QUERY, limit: this.query().limit });
  }

  onPage(event: PageEvent): void {
    // Сменили размер страницы — начинаем с первой, иначе можно оказаться за концом списка.
    const page = event.pageSize === this.query().limit ? event.pageIndex + 1 : 1;
    this.load({ ...this.query(), page, limit: event.pageSize });
  }

  open(product: ProductListItem): void {
    void this.router.navigate(['/products', product.id]);
  }

  trackById(_: number, product: ProductListItem): number {
    return product.id;
  }

  private load(query: ProductQuery): void {
    this.store.dispatch(loadProducts({ query }));
  }
}
