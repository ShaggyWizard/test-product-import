# syntax=docker/dockerfile:1
# Сборка из корня проекта: docker build -f frontend/Dockerfile .

# ---------- build: Angular в статические файлы ----------
FROM node:22-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npx ng build --configuration production

# ---------- runtime: nginx отдаёт фронт и проксирует /api в PHP-FPM ----------
FROM nginx:1.27-alpine AS runtime
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/frontend/browser /usr/share/nginx/html
