<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ImportJob;
use App\Repository\ProductRepository;
use App\Service\Import\ImportService;
use App\Service\Import\UploadStorage;

/**
 * Импорт целиком на реальной БД: файл-пример от заказчика и файл с плохими строками.
 */
final class ImportServiceTest extends IntegrationTestCase
{
    private function import(string $fixture): ImportJob
    {
        $job = new ImportJob($fixture, $this->service(UploadStorage::class)->store(self::fixture($fixture)));
        $this->em->persist($job);
        $this->em->flush();

        return $this->service(ImportService::class)->run($job->getId());
    }

    public function testImportsExampleFile(): void
    {
        $job = $this->import('import-example.xlsx');

        self::assertSame(ImportJob::STATUS_DONE, $job->getStatus());
        self::assertSame(40, $job->getTotalRows());
        self::assertSame(40, $job->getCreatedCount());
        self::assertSame([], $job->getErrors());

        self::assertSame(40, $this->rows('products'));
        // 91 ссылка на фото в файле, у 40 товаров — 20 уникальных файлов.
        self::assertSame(91, $this->rows('product_images'));
        self::assertSame(0, $this->rows('product_images WHERE path IS NULL'));

        $product = $this->service(ProductRepository::class)->findOneByExternalCode('3UHfAid1jaMiwgBuNvnsf3');
        self::assertNotNull($product);
        self::assertSame('1320.00', $product->getPrice());
        self::assertSame('33.33', $product->getDiscount());
        self::assertCount(16, $product->getAttributes());
    }

    public function testRepeatedImportUpdatesWithoutDuplicates(): void
    {
        $this->import('import-example.xlsx');
        $attributes = $this->rows('product_attributes');

        $this->em->clear();
        $job = $this->import('import-example.xlsx');

        self::assertSame(0, $job->getCreatedCount());
        self::assertSame(40, $job->getUpdatedCount());
        self::assertSame(40, $this->rows('products'));
        self::assertSame($attributes, $this->rows('product_attributes'));
        self::assertSame(91, $this->rows('product_images'));
    }

    public function testBadRowsGoToReportAndDoNotStopImport(): void
    {
        $job = $this->import('broken-rows.xlsx');

        self::assertSame(ImportJob::STATUS_DONE, $job->getStatus());
        self::assertSame(6, $job->getTotalRows(), 'Пустая строка пропущена');
        self::assertSame(2, $job->getCreatedCount());

        $errorRows = array_column($job->getErrors(), 'row');
        sort($errorRows);
        self::assertSame([3, 4, 5, 8], $errorRows);
        self::assertSame(2, $this->rows('products'));
    }

    public function testFailedImageDownloadKeepsProductAndWarns(): void
    {
        $job = $this->import('broken-image.xlsx');

        self::assertSame(1, $job->getCreatedCount());
        self::assertCount(1, $job->getWarnings());
        self::assertStringContainsString('не скачана', $job->getWarnings()[0]['message']);
        // Ссылка сохранена без локального файла.
        self::assertSame(1, $this->rows('product_images WHERE path IS NULL'));
        self::assertSame(1, $this->rows('product_images WHERE path IS NOT NULL'));
    }

    public function testFileWithoutRequiredColumnsFails(): void
    {
        $job = $this->import('no-price-column.xlsx');

        self::assertSame(ImportJob::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('Цена: Цена продажи', (string) $job->getFailureReason());
        self::assertSame(0, $this->rows('products'));
    }
}
