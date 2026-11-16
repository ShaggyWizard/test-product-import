import { Page, expect, test } from '@playwright/test';
import path from 'node:path';

const fixtures = path.resolve(__dirname, '../../backend/tests/data');
const LOGIN = process.env['E2E_LOGIN'] ?? 'admin';
const PASSWORD = process.env['E2E_PASSWORD'] ?? 'admin';

async function signIn(page: Page, returnTo = '/import'): Promise<void> {
  await page.goto(returnTo);
  // authGuard отправляет на вход с адресом возврата.
  await expect(page).toHaveURL(/\/login\?returnUrl=/);
  await page.getByLabel('Логин').fill(LOGIN);
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(new RegExp(returnTo + '$'));
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

/**
 * Основной сценарий: импорт файла-примера → список → поиск → карточка.
 */
test('импорт xlsx, поиск в списке и карточка товара', async ({ page }) => {
  await page.goto('/import');
  await expect(page.getByRole('heading', { name: 'Импорт товаров' })).toBeVisible();

  await page.locator('input[type=file]').setInputFiles(path.join(fixtures, 'import-example.xlsx'));
  await expect(page.getByText('import-example.xlsx').first()).toBeVisible();
  await page.getByRole('button', { name: 'Импортировать' }).click();

  // Импорт идёт в воркере; страница опрашивает статус, пока не станет «Готово».
  const result = page.locator('.result');
  await expect(result.getByText('Готово')).toBeVisible({ timeout: 45_000 });
  await expect(result.getByText('Строк', { exact: true }).locator('..')).toContainText('40');
  await expect(result.getByText('Ошибок', { exact: true }).locator('..')).toContainText('0');

  // Список: поиск без учёта регистра и фильтр цены считаются на сервере.
  await result.getByRole('link', { name: 'Смотреть товары' }).click();
  await expect(page).toHaveURL(/\/products$/);
  await page.getByLabel('Название').fill('бермуды мужские');
  await page.getByLabel('Цена от, ₽').fill('1000');
  await page.getByLabel('Цена до, ₽').fill('1400');
  await page.getByRole('button', { name: 'Найти' }).click();

  const link = page.getByRole('link', { name: /Бермуды мужские, Grigio\/Verde, OMSA, 46\(M\)/ });
  await expect(link).toBeVisible();

  // Карточка: цена, скидка, атрибуты, картинка.
  await link.click();
  await expect(page).toHaveURL(/\/products\/\d+$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Бермуды мужские');
  await expect(page.getByText('33,33', { exact: false })).toBeVisible();
  await expect(page.getByRole('cell', { name: 'Состав' })).toBeVisible();
  await expect(page.getByRole('cell', { name: '100% Хлопок' })).toBeVisible();
  await expect(page.locator('img.main-image')).toBeVisible();

  // Назад — список с тем же фильтром.
  await page.getByRole('link', { name: 'К списку товаров' }).click();
  await expect(page.getByLabel('Название')).toHaveValue('бермуды мужские');
});

test('плохие строки попадают в отчёт с номером строки', async ({ page }) => {
  await page.goto('/import');
  await page.locator('input[type=file]').setInputFiles(path.join(fixtures, 'broken-rows.xlsx'));
  await page.getByRole('button', { name: 'Импортировать' }).click();

  const result = page.locator('.result');
  await expect(result.getByText('Готово')).toBeVisible({ timeout: 45_000 });
  await expect(result.getByText('Ошибки: строки не записаны (4)')).toBeVisible();
  await expect(result.getByRole('cell', { name: 'Пустой внешний код.' })).toBeVisible();
  await expect(result.getByRole('cell', { name: /«сто рублей» не является числом/ })).toBeVisible();
});

test('не-xlsx файл отклоняется до отправки', async ({ page }) => {
  await page.goto('/import');
  await page.locator('input[type=file]').setInputFiles({
    name: 'data.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from('a;b\n1;2'),
  });

  await expect(page.getByRole('alert')).toHaveText('Нужен файл .xlsx.');
  await expect(page.getByRole('button', { name: 'Импортировать' })).toBeDisabled();
});
