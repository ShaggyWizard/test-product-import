<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_products_name', columns: ['name'])]
#[ORM\Index(name: 'idx_products_price', columns: ['price'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', length: 64, unique: true)]
    private string $externalCode;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Цена продажи. DECIMAL хранится в Doctrine строкой — без потерь точности float. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price = '0.00';

    /** Скидка, % = (цена продажи − закупочная) / цена продажи × 100. */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $discount = '0.00';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['key' => 'ASC'])]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $images;

    public function __construct(string $externalCode, string $name)
    {
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function setDiscount(string $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /**
     * Приводит атрибуты к переданному набору: меняет значения, добавляет новые, удаляет лишние.
     *
     * @param array<string, string> $values ключ => значение
     */
    public function syncAttributes(array $values): void
    {
        foreach ($this->attributes as $attribute) {
            if (!\array_key_exists($attribute->getKey(), $values)) {
                $this->attributes->removeElement($attribute);
                continue;
            }
            $attribute->setValue($values[$attribute->getKey()]);
            unset($values[$attribute->getKey()]);
        }

        foreach ($values as $key => $value) {
            $this->attributes->add(new ProductAttribute($this, (string) $key, $value));
        }
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    /**
     * Приводит картинки к переданному набору по исходному URL.
     *
     * @param array<string, string|null> $urlToPath исходный URL => локальный путь (null, если скачать не удалось)
     */
    public function syncImages(array $urlToPath): void
    {
        foreach ($this->images as $image) {
            if (!\array_key_exists($image->getUrl(), $urlToPath)) {
                $this->images->removeElement($image);
                continue;
            }
            $path = $urlToPath[$image->getUrl()];
            if ($path !== null) {
                $image->setPath($path);
            }
            unset($urlToPath[$image->getUrl()]);
        }

        foreach ($urlToPath as $url => $path) {
            $this->images->add(new ProductImage($this, (string) $url, $path));
        }
    }
}
