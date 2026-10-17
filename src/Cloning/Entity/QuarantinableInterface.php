<?php
/**
 * Quarantine state of an entity
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

/**
 * Entity that can be put in quarantine: an object whose data is doubtful (typically imported
 * from the legacy base) and that must not become the parent of another object.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
interface QuarantinableInterface
{
    /**
     * Tells whether the entity is in quarantine.
     *
     * @return bool True if in quarantine
     */
    public function isQuarantined(): bool;

    /**
     * Why the entity is in quarantine.
     *
     * @return string|null The reason, null outside quarantine
     */
    public function getQuarantineReason(): ?string;

    /**
     * Value that was set aside, as it was received.
     *
     * @return string|null The raw value, null if none was kept
     */
    public function getQuarantineRawValue(): ?string;
}
