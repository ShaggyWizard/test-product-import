<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Задача импорта: статус, прогресс и отчёт. По ней фронт опрашивает ход импорта.
 */
#[ORM\Entity(repositoryClass: ImportJobRepository::class)]
#[ORM\Table(name: 'import_jobs')]
class ImportJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'original_filename', length: 255)]
    private string $originalFilename;

    /** Путь к сохранённому файлу внутри var/uploads. */
    #[ORM\Column(name: 'file_path', length: 512)]
    private string $filePath;

    #[ORM\Column(name: 'total_rows', type: Types::INTEGER)]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows', type: Types::INTEGER)]
    private int $processedRows = 0;

    #[ORM\Column(name: 'created_count', type: Types::INTEGER)]
    private int $createdCount = 0;

    #[ORM\Column(name: 'updated_count', type: Types::INTEGER)]
    private int $updatedCount = 0;

    /** @var list<array{row: int, externalCode: ?string, field: ?string, message: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    /** @var list<array{row: int, externalCode: ?string, field: ?string, message: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $warnings = [];

    #[ORM\Column(name: 'failure_reason', type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(string $originalFilename, string $filePath)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->originalFilename = $originalFilename;
        $this->filePath = $filePath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function start(int $totalRows): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->totalRows = $totalRows;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function advance(int $processedRows): void
    {
        $this->processedRows = $processedRows;
    }

    public function countCreated(): void
    {
        ++$this->createdCount;
    }

    public function countUpdated(): void
    {
        ++$this->updatedCount;
    }

    public function addError(int $row, ?string $externalCode, ?string $field, string $message): void
    {
        $this->errors[] = ['row' => $row, 'externalCode' => $externalCode, 'field' => $field, 'message' => $message];
    }

    public function addWarning(int $row, ?string $externalCode, ?string $field, string $message): void
    {
        $this->warnings[] = ['row' => $row, 'externalCode' => $externalCode, 'field' => $field, 'message' => $message];
    }

    public function finish(): void
    {
        $this->status = self::STATUS_DONE;
        $this->processedRows = $this->totalRows;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function fail(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $reason;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_DONE || $this->status === self::STATUS_FAILED;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    /** @return list<array{row: int, externalCode: ?string, field: ?string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<array{row: int, externalCode: ?string, field: ?string, message: string}> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
