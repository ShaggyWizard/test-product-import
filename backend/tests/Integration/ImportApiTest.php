<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\ImportMessage;
use App\Message\ImportMessageHandler;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * POST /api/import → сообщение в очереди → обработчик → статус done в GET /api/import/{id}.
 */
final class ImportApiTest extends ApiTestCase
{
    private function upload(string $fixture, string $clientName): UploadedFile
    {
        // Копия: UploadedFile::moveTo переносит файл, оригинал фикстуры трогать нельзя.
        $tmp = tempnam(sys_get_temp_dir(), 'upload');
        copy(self::fixture($fixture), (string) $tmp);

        return new UploadedFile((string) $tmp, $clientName, null, filesize((string) $tmp), \UPLOAD_ERR_OK);
    }

    private function transport(): InMemoryTransport
    {
        /** @var \Psr\Container\ContainerInterface $transports */
        $transports = $this->container->get('messenger.transports');
        /** @var InMemoryTransport $transport */
        $transport = $transports->get('async');

        return $transport;
    }

    public function testUploadQueuesJobAndWorkerCompletesIt(): void
    {
        $response = $this->request('POST', '/api/import', files: ['file' => $this->upload('import-example.xlsx', 'Товары.xlsx')]);

        self::assertSame(202, $response->getStatusCode());
        $job = $this->json($response);
        self::assertSame('pending', $job['status']);
        self::assertSame('Товары.xlsx', $job['fileName']);

        // В очереди одно сообщение с id задачи; товаров ещё нет — импорт не выполнялся в запросе.
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(ImportMessage::class, $message);
        self::assertSame($job['id'], $message->jobId);
        self::assertSame(0, $this->rows('products'));

        // Воркер обрабатывает сообщение.
        $this->service(ImportMessageHandler::class)($message);

        $status = $this->json($this->request('GET', '/api/import/' . $job['id']));
        self::assertSame('done', $status['status']);
        self::assertSame(100, $status['progress']);
        self::assertSame(40, $status['created']);
        self::assertSame(40, $this->rows('products'));

        // Повторная доставка того же сообщения импорт не повторяет.
        $this->service(ImportMessageHandler::class)($message);
        self::assertSame(40, $this->json($this->request('GET', '/api/import/' . $job['id']))['created']);
    }

    public function testRejectsFileThatIsNotXlsx(): void
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tmp, 'not a spreadsheet');
        $file = new UploadedFile($tmp, 'fake.xlsx', null, filesize($tmp), \UPLOAD_ERR_OK);

        $response = $this->request('POST', '/api/import', files: ['file' => $file]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('file', $this->json($response)['details']);
        self::assertCount(0, $this->transport()->getSent());
    }

    public function testRejectsWrongExtension(): void
    {
        $response = $this->request('POST', '/api/import', files: ['file' => $this->upload('import-example.xlsx', 'data.csv')]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testRequiresFile(): void
    {
        self::assertSame(422, $this->request('POST', '/api/import')->getStatusCode());
    }

    public function testUnknownJobIs404(): void
    {
        self::assertSame(404, $this->request('GET', '/api/import/0190a1b2-0000-7000-8000-000000000000')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/import/not-a-uuid')->getStatusCode());
    }

    public function testLatestReturnsMostRecentJob(): void
    {
        self::assertSame(404, $this->request('GET', '/api/import/latest')->getStatusCode());

        $job = $this->json($this->request('POST', '/api/import', files: ['file' => $this->upload('broken-rows.xlsx', 'b.xlsx')]));

        self::assertSame($job['id'], $this->json($this->request('GET', '/api/import/latest'))['id']);
    }
}
