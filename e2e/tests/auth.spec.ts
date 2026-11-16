import { expect, test } from '@playwright/test';

const LOGIN = process.env['E2E_LOGIN'] ?? 'admin';
const PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin';

test('без входа страницы закрыты, после входа — возврат на нужную страницу', async ({ page }) => {
  await page.goto('/products/1');
  await expect(page).toHaveURL(/\/login\?returnUrl=%2Fproducts%2F1$/);

  await page.getByLabel('Логин').fill(LOGIN);
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();

  await expect(page).toHaveURL(/\/products\/1$/);
  await expect(page.getByRole('button', { name: 'Выйти' })).toBeVisible();
});

test('неверный пароль — сообщение, без входа', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Логин').fill(LOGIN);
  await page.getByLabel('Пароль').fill('wrong-password');
  await page.getByRole('button', { name: 'Войти' }).click();

  await expect(page.getByRole('alert')).toHaveText('Неверный логин или пароль.');
  await expect(page).toHaveURL(/\/login$/);
});

test('выход закрывает страницы снова', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Логин').fill(LOGIN);
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/products$/);

  await page.getByRole('button', { name: 'Выйти' }).click();
  await expect(page).toHaveURL(/\/login$/);

  await page.goto('/import');
  await expect(page).toHaveURL(/\/login\?returnUrl=%2Fimport$/);
});
