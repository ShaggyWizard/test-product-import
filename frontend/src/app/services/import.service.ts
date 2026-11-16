import { HttpClient, HttpEventType, HttpStatusCode } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, filter, map, of, switchMap, takeWhile, timer } from 'rxjs';

import { ImportJob, isFinished } from '../models/import-job.model';

/** Ход загрузки файла на сервер: проценты, затем принятая задача. */
export type UploadEvent = { type: 'progress'; percent: number } | { type: 'accepted'; job: ImportJob };

/** Запросы к API импорта. Компоненты не ходят в HttpClient напрямую. */
@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = '/api/import';

  /** Как часто спрашивать статус задачи, мс. */
  static readonly POLL_INTERVAL = 1000;

  /** Отправить .xlsx. Сервер отвечает сразу (202), сам импорт идёт в очереди. */
  upload(file: File): Observable<UploadEvent> {
    const body = new FormData();
    body.append('file', file, file.name);

    return this.http.post<ImportJob>(this.baseUrl, body, { reportProgress: true, observe: 'events' }).pipe(
      map((event): UploadEvent | null => {
        if (event.type === HttpEventType.UploadProgress) {
          return { type: 'progress', percent: event.total ? Math.round((event.loaded * 100) / event.total) : 0 };
        }
        if (event.type === HttpEventType.Response && event.body) {
          return { type: 'accepted', job: event.body };
        }
        return null;
      }),
      filter((event): event is UploadEvent => event !== null),
    );
  }

  getJob(id: string): Observable<ImportJob> {
    return this.http.get<ImportJob>(`${this.baseUrl}/${id}`);
  }

  /** Последний импорт или null, если импортов ещё не было. */
  getLatest(): Observable<ImportJob | null> {
    return this.http.get<ImportJob>(`${this.baseUrl}/latest`).pipe(
      catchError((err: { status?: number }) => {
        if (err.status === HttpStatusCode.NotFound) {
          return of(null);
        }
        throw err;
      }),
    );
  }

  /**
   * Polling: спрашивает статус раз в секунду, пока задача не завершится.
   * Последнее значение (done/failed) тоже выдаётся — takeWhile с inclusive.
   * switchMap отменяет зависший запрос, если следующий тик уже наступил.
   */
  watch(id: string): Observable<ImportJob> {
    return timer(0, ImportService.POLL_INTERVAL).pipe(
      switchMap(() => this.getJob(id)),
      takeWhile((job) => !isFinished(job), true),
    );
  }

  /** Последний импорт, а если он ещё идёт — следить за ним до конца. */
  watchLatest(): Observable<ImportJob | null> {
    return this.getLatest().pipe(
      switchMap((job) => (job === null || isFinished(job) ? of(job) : this.watch(job.id))),
    );
  }
}
