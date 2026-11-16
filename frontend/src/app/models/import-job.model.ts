export type ImportStatus = 'pending' | 'running' | 'done' | 'failed';

/** Замечание к строке файла. */
export interface ImportIssue {
  /** Номер строки в файле (как в Excel). */
  row: number;
  externalCode: string | null;
  /** Столбец. */
  field: string | null;
  message: string;
}

/** Задача импорта: статус, прогресс и отчёт. */
export interface ImportJob {
  id: string;
  status: ImportStatus;
  fileName: string;
  totalRows: number;
  processedRows: number;
  /** 0–100. */
  progress: number;
  created: number;
  updated: number;
  /** Строки, которые не записаны. */
  errors: ImportIssue[];
  /** Записано, но с замечаниями (например, не скачалась картинка). */
  warnings: ImportIssue[];
  failureReason: string | null;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export function isFinished(job: ImportJob): boolean {
  return job.status === 'done' || job.status === 'failed';
}
