import { DatePipe, NgTemplateOutlet } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';

import { ImportJob } from '../../models/import-job.model';

const STATUS_TEXT: Record<ImportJob['status'], string> = {
  pending: 'В очереди',
  running: 'Идёт импорт',
  done: 'Готово',
  failed: 'Ошибка',
};

/** Прогресс импорта и отчёт: ошибки (строка не записана) и предупреждения (записана с замечанием). */
@Component({
  selector: 'app-import-status',
  imports: [DatePipe, NgTemplateOutlet, MatProgressBarModule, MatTableModule, MatExpansionModule, MatIconModule],
  templateUrl: './import-status.component.html',
  styleUrl: './import-status.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ImportStatusComponent {
  readonly job = input.required<ImportJob>();

  /** Компактный вид для карточки товара: списки ошибок свёрнуты. */
  readonly compact = input(false);

  readonly issueColumns = ['row', 'externalCode', 'field', 'message'];

  readonly statusText = computed(() => STATUS_TEXT[this.job().status]);

  /** Пока воркер не прочитал файл, число строк неизвестно — полоса «бегущая». */
  readonly progressMode = computed(() =>
    this.job().status === 'pending' || (this.job().status === 'running' && this.job().totalRows === 0)
      ? 'indeterminate'
      : 'determinate',
  );

  readonly running = computed(() => this.job().status === 'pending' || this.job().status === 'running');
}
