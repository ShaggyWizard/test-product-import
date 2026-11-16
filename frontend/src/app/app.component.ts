import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatToolbarModule, MatButtonModule, MatIconModule],
  template: `
    <mat-toolbar class="toolbar">
      <a class="brand" routerLink="/products">Импорт товаров</a>
      <nav class="nav">
        <a mat-button routerLink="/products" routerLinkActive="active">
          <mat-icon>inventory_2</mat-icon>
          Товары
        </a>
        <a mat-button routerLink="/import" routerLinkActive="active">
          <mat-icon>upload_file</mat-icon>
          Импорт
        </a>
      </nav>
    </mat-toolbar>
    <main class="content">
      <router-outlet />
    </main>
  `,
  styleUrl: './app.component.scss',
})
export class AppComponent {}
