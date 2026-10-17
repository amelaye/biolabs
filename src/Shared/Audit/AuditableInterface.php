<?php
/**
 * Contract of an entity whose creation and updates are traced
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Shared\Audit;

use App\Account\Entity\User;

/**
 * Entity whose creation and last update are traced.
 *
 * The fields are filled by {@see AuditSubscriber}; never enter them
 * in a form nor modify them by hand.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
interface AuditableInterface
{
    /**
     * Date and time the entity was created.
     *
     * @return \DateTimeImmutable|null The creation timestamp, null until the first flush
     */
    public function getCreatedAt(): ?\DateTimeImmutable;

    /**
     * Person who created the entity.
     *
     * @return User|null The author, null when the write did not come from a signed-in person
     */
    public function getCreatedBy(): ?User;

    /**
     * Date and time of the last modification.
     *
     * @return \DateTimeImmutable|null The last update timestamp, null until the first flush
     */
    public function getUpdatedAt(): ?\DateTimeImmutable;

    /**
     * Person who made the last modification.
     *
     * @return User|null The author of the last update, null when it did not come from a signed-in person
     */
    public function getUpdatedBy(): ?User;

    /**
     * Records the creation of the entity. Reserved for {@see AuditSubscriber}.
     *
     * @param \DateTimeImmutable $at Moment of the creation
     * @param User|null $by Signed-in person, null for a console command or an import
     * @return void
     */
    public function recordCreation(\DateTimeImmutable $at, ?User $by): void;

    /**
     * Records a modification of the entity. Reserved for {@see AuditSubscriber}.
     *
     * @param \DateTimeImmutable $at Moment of the modification
     * @param User|null $by Signed-in person, null for a console command or an import
     * @return void
     */
    public function recordUpdate(\DateTimeImmutable $at, ?User $by): void;
}
