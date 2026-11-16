import { Routes } from '@angular/router';

import { authGuard, guestGuard } from './auth/auth.guard';

/**
 * Страницы грузятся лениво (loadComponent): код страницы не скачивается, пока её не открыли.
 * Всё, кроме входа, закрыто authGuard (CanActivate).
 */
export const routes: Routes = [
  {
    path: 'login',
    title: 'Вход',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'products' },
      {
        path: 'products',
        title: 'Товары',
        loadComponent: () => import('./pages/product-list/product-list.component').then((m) => m.ProductListComponent),
      },
      {
        path: 'products/:id',
        title: 'Карточка товара',
        loadComponent: () =>
          import('./pages/product-detail/product-detail.component').then((m) => m.ProductDetailComponent),
      },
      {
        path: 'import',
        title: 'Импорт товаров',
        loadComponent: () => import('./pages/import/import.component').then((m) => m.ImportComponent),
      },
    ],
  },
  { path: '**', redirectTo: 'products' },
];
