import { HttpErrorResponse } from '@angular/common/http';

/** Ошибка API: {"error": "...", "details": {"поле": "что не так"}}. */
export interface ApiError {
  error: string;
  details?: Record<string, string>;
}

/** Понятный текст ошибки для показа пользователю. */
export function errorMessage(err: unknown): string {
  if (err instanceof HttpErrorResponse) {
    if (err.status === 0) {
      return 'Сервер недоступен. Проверьте, что запущен docker compose.';
    }
    const body = err.error as Partial<ApiError> | null;
    if (body?.details) {
      return `${body.error ?? 'Ошибка'} ${Object.values(body.details).join(' ')}`;
    }
    return body?.error ?? `Ошибка сервера (${err.status}).`;
  }
  return err instanceof Error ? err.message : 'Неизвестная ошибка.';
}
