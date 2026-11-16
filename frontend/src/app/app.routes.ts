import { Routes } from '@angular/router';

/** Страницы грузятся лениво (loadComponent): код карточки не скачивается, пока её не открыли. */
export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'products' },
  {
    path: 'products',
    title: 'Товары',
    loadComponent: () => import('./pages/product-list/product-list.component').then((m) => m.ProductListComponent),
  },
  {
    path: 'products/:id',
    title: 'Карточка товара',
    loadComponent: () => import('./pages/product-detail/product-detail.component').then((m) => m.ProductDetailComponent),
  },
  { path: '**', redirectTo: 'products' },
];
