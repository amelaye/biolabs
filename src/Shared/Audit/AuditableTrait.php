<?php
/**
 * Audit columns (created/updated, at/by) for auditable entities
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Shared\Audit;

use App\Account\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Audit columns to include in every entity implementing {@see AuditableInterface}.
 *
 * Authors are null when the write does not come from a signed-in person
 * (console command, import); they are never replaced by a fake value.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
trait AuditableTrait
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    /**
     * Date and time the entity was created.
     *
     * @return \DateTimeImmutable|null The creation timestamp, null until the first flush
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Person who created the entity.
     *
     * @return User|null The author, null when the write did not come from a signed-in person
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Date and time of the last modification.
     *
     * @return \DateTimeImmutable|null The last update timestamp, null until the first flush
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Person who made the last modification.
     *
     * @return User|null The author of the last update, null when it did not come from a signed-in person
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    /**
     * Records the creation of the entity: the creation date and author never change afterwards,
     * and the update date and author start with the same values.
     *
     * @param \DateTimeImmutable $at Moment of the creation
     * @param User|null $by Signed-in person, null for a console command or an import
     * @return void
     */
    public function recordCreation(\DateTimeImmutable $at, ?User $by): void
    {
        if (null !== $this->createdAt) {
            return; // the creation timestamp is immutable
        }

        $this->createdAt = $at;
        $this->createdBy = $by;
        $this->updatedAt = $at;
        $this->updatedBy = $by;
    }

    /**
     * Records a modification of the entity (date and author of the last update).
     *
     * @param \DateTimeImmutable $at Moment of the modification
     * @param User|null $by Signed-in person, null for a console command or an import
     * @return void
     */
    public function recordUpdate(\DateTimeImmutable $at, ?User $by): void
    {
        $this->updatedAt = $at;
        $this->updatedBy = $by;
    }
}
