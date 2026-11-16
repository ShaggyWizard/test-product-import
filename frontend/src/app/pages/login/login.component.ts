import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';

import { errorMessage } from '../../models/api-error.model';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, MatCardModule, MatFormFieldModule, MatInputModule, MatButtonModule],
  template: `
    <mat-card class="card" appearance="outlined">
      <mat-card-header>
        <mat-card-title>Вход</mat-card-title>
        <mat-card-subtitle>Логин и пароль администратора — в .env (ADMIN_LOGIN, ADMIN_PASSWORD)</mat-card-subtitle>
      </mat-card-header>
      <mat-card-content>
        <form [formGroup]="form" (ngSubmit)="submit()" class="form">
          <mat-form-field appearance="outline">
            <mat-label>Логин</mat-label>
            <input matInput formControlName="login" autocomplete="username" />
          </mat-form-field>
          <mat-form-field appearance="outline">
            <mat-label>Пароль</mat-label>
            <input matInput type="password" formControlName="password" autocomplete="current-password" />
          </mat-form-field>
          @if (error(); as error) {
            <div class="error-box" role="alert">{{ error }}</div>
          }
          <button mat-flat-button type="submit" [disabled]="pending()">Войти</button>
        </form>
      </mat-card-content>
    </mat-card>
  `,
  styles: `
    .card { max-width: 400px; margin: 48px auto; }
    .form { display: flex; flex-direction: column; gap: 4px; margin-top: 16px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** ?returnUrl= из адреса: куда вернуться после входа. */
  readonly returnUrl = input<string>();

  readonly pending = signal(false);
  readonly error = signal<string | null>(null);

  readonly form = inject(FormBuilder).nonNullable.group({
    login: ['', Validators.required],
    password: ['', Validators.required],
  });

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.pending.set(true);
    this.error.set(null);
    const { login, password } = this.form.getRawValue();

    // Подписка на одноразовый запрос формы: результат нужен для навигации, в шаблон он не идёт.
    this.auth
      .login(login, password)
      .pipe(finalize(() => this.pending.set(false)))
      .subscribe({
        next: () => void this.router.navigateByUrl(this.safeReturnUrl()),
        error: (err: unknown) => this.error.set(errorMessage(err)),
      });
  }

  /** Возвращаем только на свои страницы, не на чужой адрес из ссылки. */
  private safeReturnUrl(): string {
    const url = this.returnUrl();
    return url && url.startsWith('/') && !url.startsWith('//') && !url.startsWith('/login') ? url : '/products';
  }
}
