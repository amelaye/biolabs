<?php
/**
 * Entity Attachment - PDF file attached to a vector (map, datasheet)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use App\Account\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Metadata of a file stored outside the database and outside public/. The file is stored under
 * a random identifier, never under the original name; the original name is only displayed, after
 * cleaning. The SHA-256 lets one check that the stored file is the uploaded one.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_cloning_attachment')]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_attachment_storage_id', columns: ['storage_id'])]
#[ORM\UniqueConstraint(name: 'uniq_bl_cloning_attachment_vector_sha256', columns: ['vector_id', 'sha256'])]
class Attachment
{
    public const MIME_PDF = 'application/pdf';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vector::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Vector $vector;

    /** Random identifier (UUID v4) of the stored file. */
    #[ORM\Column(type: 'string', length: 36)]
    private string $storageId;

    /** Original file name, cleaned. */
    #[ORM\Column(type: 'string', length: 150)]
    private string $originalName;

    #[ORM\Column(type: 'string', length: 100)]
    private string $mimeType;

    /** Size in bytes. */
    #[ORM\Column(type: 'integer')]
    private int $sizeBytes;

    #[ORM\Column(type: 'string', length: 64)]
    private string $sha256;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    /**
     * Attachment constructor.
     *
     * @param Vector $vector Vector the file is attached to
     * @param string $storageId Random identifier of the stored file
     * @param string $originalName Cleaned original name
     * @param string $mimeType Type detected from the content
     * @param int $sizeBytes Size in bytes
     * @param string $sha256 SHA-256 of the content, hexadecimal
     * @param User|null $uploadedBy Signed-in person
     * @param \DateTimeImmutable $uploadedAt Moment of the upload
     */
    public function __construct(Vector $vector, string $storageId, string $originalName, string $mimeType, int $sizeBytes, string $sha256, ?User $uploadedBy, \DateTimeImmutable $uploadedAt)
    {
        $this->vector = $vector;
        $this->storageId = $storageId;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->sha256 = $sha256;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = $uploadedAt;
    }

    /**
     * Primary key.
     *
     * @return int|null The identifier, null until stored
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Vector the file is attached to.
     *
     * @return Vector The vector
     */
    public function getVector(): Vector
    {
        return $this->vector;
    }

    /**
     * Random identifier of the stored file.
     *
     * @return string The identifier
     */
    public function getStorageId(): string
    {
        return $this->storageId;
    }

    /**
     * Original file name, cleaned.
     *
     * @return string The name to display and to propose when downloading
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Type detected from the content.
     *
     * @return string The media type
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Size of the file.
     *
     * @return int Size in bytes
     */
    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    /**
     * SHA-256 of the content.
     *
     * @return string Hexadecimal digest
     */
    public function getSha256(): string
    {
        return $this->sha256;
    }

    /**
     * Person who uploaded the file.
     *
     * @return User|null The author, null if unknown
     */
    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    /**
     * Moment of the upload.
     *
     * @return \DateTimeImmutable The timestamp
     */
    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
