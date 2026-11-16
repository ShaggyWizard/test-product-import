import { registerLocaleData } from '@angular/common';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import localeRu from '@angular/common/locales/ru';
import { ApplicationConfig, LOCALE_ID, isDevMode, provideZoneChangeDetection } from '@angular/core';
import { MAT_PAGINATOR_DEFAULT_OPTIONS, MatPaginatorIntl } from '@angular/material/paginator';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideEffects } from '@ngrx/effects';
import { provideStore } from '@ngrx/store';
import { provideStoreDevtools } from '@ngrx/store-devtools';

import { routes } from './app.routes';
import { authInterceptor, errorInterceptor } from './auth/auth.interceptor';
import { RuPaginatorIntl } from './shared/ru-paginator-intl';
import { ProductsEffects } from './store/products/products.effects';
import { PRODUCTS_FEATURE_KEY, productsReducer } from './store/products/products.reducer';

registerLocaleData(localeRu);

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    // withComponentInputBinding: параметр маршрута :id приходит в компонент как input.
    provideRouter(routes, withComponentInputBinding()),
    // Порядок: сначала добавляем токен, затем обрабатываем ошибки ответа (401, 5xx).
    provideHttpClient(withFetch(), withInterceptors([authInterceptor, errorInterceptor])),
    provideAnimationsAsync(),
    provideStore({ [PRODUCTS_FEATURE_KEY]: productsReducer }),
    provideEffects(ProductsEffects),
    provideStoreDevtools({ maxAge: 25, logOnly: !isDevMode() }),
    { provide: LOCALE_ID, useValue: 'ru' },
    { provide: MatPaginatorIntl, useClass: RuPaginatorIntl },
    { provide: MAT_PAGINATOR_DEFAULT_OPTIONS, useValue: { pageSizeOptions: [10, 20, 50, 100], showFirstLastButtons: true } },
  ],
};
