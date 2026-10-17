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
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function getCreatedBy(): ?User;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function getUpdatedBy(): ?User;

    /**
     * Reserved for {@see AuditSubscriber}.
     */
    public function recordCreation(\DateTimeImmutable $at, ?User $by): void;

    /**
     * Reserved for {@see AuditSubscriber}.
     */
    public function recordUpdate(\DateTimeImmutable $at, ?User $by): void;
}
