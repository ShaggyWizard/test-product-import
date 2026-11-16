import { AsyncPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { Observable, Subject, catchError, map, of, startWith, switchMap } from 'rxjs';

import { errorMessage } from '../../models/api-error.model';
import { ImportJob } from '../../models/import-job.model';
import { ImportService } from '../../services/import.service';
import { ImportStatusComponent } from '../../shared/import-status/import-status.component';

/** Что показывает страница. */
type ImportView =
  | { phase: 'idle'; last: ImportJob | null }
  | { phase: 'uploading'; percent: number }
  | { phase: 'job'; job: ImportJob }
  | { phase: 'error'; error: string };

/** Те же ограничения, что на сервере: проверяем заранее, чтобы не гонять заведомо плохой файл. */
const MAX_BYTES = 10 * 1024 * 1024;

@Component({
  selector: 'app-import',
  imports: [AsyncPipe, RouterLink, MatCardModule, MatButtonModule, MatIconModule, MatProgressBarModule, ImportStatusComponent],
  templateUrl: './import.component.html',
  styleUrl: './import.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ImportComponent {
  private readonly importService = inject(ImportService);

  /** Каждый выбранный файл запускает новый импорт; прошлый polling отменяется (switchMap). */
  private readonly files$ = new Subject<File | null>();

  readonly selectedFile = signal<File | null>(null);
  readonly fileError = signal<string | null>(null);
  readonly dragOver = signal(false);

  readonly view$: Observable<ImportView> = this.files$.pipe(
    startWith(null),
    switchMap((file) => (file === null ? this.lastImport() : this.runImport(file))),
  );

  onFileInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.pick(input.files?.[0] ?? null);
    // Сброс, чтобы повторный выбор того же файла снова вызвал change.
    input.value = '';
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(false);
    this.pick(event.dataTransfer?.files?.[0] ?? null);
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(true);
  }

  start(): void {
    const file = this.selectedFile();
    if (file) {
      this.files$.next(file);
    }
  }

  private pick(file: File | null): void {
    this.fileError.set(null);
    this.selectedFile.set(null);
    if (!file) {
      return;
    }
    if (!file.name.toLowerCase().endsWith('.xlsx')) {
      this.fileError.set('Нужен файл .xlsx.');
      return;
    }
    if (file.size > MAX_BYTES) {
      this.fileError.set('Файл больше 10 МБ.');
      return;
    }
    this.selectedFile.set(file);
  }

  /** Загрузка файла, затем опрос статуса задачи до конца. */
  private runImport(file: File): Observable<ImportView> {
    return this.importService.upload(file).pipe(
      switchMap((event) =>
        event.type === 'progress'
          ? of<ImportView>({ phase: 'uploading', percent: event.percent })
          : this.importService.watch(event.job.id).pipe(
              startWith(event.job),
              map((job): ImportView => ({ phase: 'job', job })),
            ),
      ),
      startWith<ImportView>({ phase: 'uploading', percent: 0 }),
      catchError((err: unknown) => of<ImportView>({ phase: 'error', error: errorMessage(err) })),
    );
  }

  /** До загрузки показываем последний импорт (и следим за ним, если он ещё идёт). */
  private lastImport(): Observable<ImportView> {
    return this.importService.watchLatest().pipe(
      map((last): ImportView => ({ phase: 'idle', last })),
      catchError(() => of<ImportView>({ phase: 'idle', last: null })),
    );
  }
}
