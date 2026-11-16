import { HttpErrorResponse, HttpInterceptorFn, HttpStatusCode } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '../services/auth.service';

/** Добавляет к запросам в API заголовок Authorization: Bearer <JWT>. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = inject(AuthService).token;
  if (!token || !req.url.startsWith('/api/') || req.url === '/api/auth/login') {
    return next(req);
  }
  return next(req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }));
};

/**
 * Глобальная обработка ошибок:
 * 401 — токен истёк или неверен: выход и переход на вход с возвратом на текущую страницу;
 * 5xx и недоступный сервер — всплывающее сообщение, страница сама покажет свою ошибку.
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  return next(req).pipe(
    catchError((err: unknown) => {
      if (err instanceof HttpErrorResponse) {
        if (err.status === HttpStatusCode.Unauthorized && req.url !== '/api/auth/login') {
          auth.logout(router.url);
        } else if (err.status >= 500 || err.status === 0) {
          snackBar.open(
            err.status === 0 ? 'Сервер недоступен. Проверьте подключение.' : 'Ошибка сервера. Попробуйте ещё раз.',
            'OK',
            { duration: 5000 },
          );
        }
      }
      return throwError(() => err);
    }),
  );
};
