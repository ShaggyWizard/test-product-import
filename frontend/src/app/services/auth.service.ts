import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';

interface Session {
  token: string;
  /** Unix time, секунды. */
  expiresAt: number;
}

const STORAGE_KEY = 'product-import.session';

/** Вход по JWT. Токен хранится в localStorage, чтобы вход переживал перезагрузку страницы. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly session = signal<Session | null>(this.restore());

  readonly isLoggedIn = computed(() => {
    const session = this.session();
    return session !== null && session.expiresAt * 1000 > Date.now();
  });

  get token(): string | null {
    return this.isLoggedIn() ? (this.session()?.token ?? null) : null;
  }

  login(login: string, password: string): Observable<Session> {
    return this.http.post<Session>('/api/auth/login', { login, password }).pipe(
      tap((session) => {
        this.session.set(session);
        this.store(session);
      }),
    );
  }

  /** Выйти и вернуться на страницу входа; returnUrl — куда вернуться после входа. */
  logout(returnUrl?: string): void {
    this.session.set(null);
    this.store(null);
    void this.router.navigate(['/login'], { queryParams: returnUrl ? { returnUrl } : {} });
  }

  private restore(): Session | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? (JSON.parse(raw) as Session) : null;
    } catch {
      return null;
    }
  }

  private store(session: Session | null): void {
    try {
      if (session) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // Хранилище недоступно (приватный режим) — вход живёт до перезагрузки.
    }
  }
}
