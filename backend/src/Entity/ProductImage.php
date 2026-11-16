<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
#[ORM\UniqueConstraint(name: 'uniq_product_image_url', columns: ['product_id', 'url'])]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /** Исходная ссылка из файла импорта. */
    #[ORM\Column(length: 2048)]
    private string $url;

    /** Путь к скачанному файлу относительно public/ (media/ab/abcdef.jpg); null — скачать не удалось. */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $path;

    public function __construct(Product $product, string $url, ?string $path)
    {
        $this->product = $product;
        $this->url = $url;
        $this->path = $path;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }
}
