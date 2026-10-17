<?php
/**
 * Entity FieldHistory - old and new value of a tracked field
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Shared\Audit;

use App\Account\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * One change of a tracked field (see {@see TracksFieldHistoryInterface}). Rows are only ever
 * inserted: the history is never edited nor purged by the application. Values are stored as
 * text; a sequence is stored in full here, in the database, and never in a log or a URL.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_field_history')]
#[ORM\Index(name: 'idx_bl_field_history_entity', columns: ['entity_type', 'entity_id'])]
class FieldHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $entityType;

    #[ORM\Column(type: 'integer')]
    private int $entityId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $field;

    #[ORM\Column(type: 'text', length: 16777215, nullable: true)]
    private ?string $oldValue;

    #[ORM\Column(type: 'text', length: 16777215, nullable: true)]
    private ?string $newValue;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy;

    /**
     * FieldHistory constructor.
     *
     * @param string $entityType Fully qualified class name of the entity that changed (stable key, not the short name)
     * @param int $entityId Primary key of the entity that changed
     * @param string $field Property that changed
     * @param string|null $oldValue Value before the change, null if there was none
     * @param string|null $newValue Value after the change, null if there is none
     * @param \DateTimeImmutable $changedAt Moment of the change
     * @param User|null $changedBy Signed-in person, null for a console command or an import
     */
    public function __construct(string $entityType, int $entityId, string $field, ?string $oldValue, ?string $newValue, \DateTimeImmutable $changedAt, ?User $changedBy)
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->field = $field;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->changedAt = $changedAt;
        $this->changedBy = $changedBy;
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
     * Fully qualified class name of the entity that changed.
     *
     * @return string The entity type
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * Primary key of the entity that changed.
     *
     * @return int The entity identifier
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * Property that changed.
     *
     * @return string The field name
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Value before the change.
     *
     * @return string|null The old value, null if there was none
     */
    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    /**
     * Value after the change.
     *
     * @return string|null The new value, null if there is none
     */
    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    /**
     * Moment of the change.
     *
     * @return \DateTimeImmutable The timestamp
     */
    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    /**
     * Person who made the change.
     *
     * @return User|null The author, null when the write did not come from a signed-in person
     */
    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }
}
