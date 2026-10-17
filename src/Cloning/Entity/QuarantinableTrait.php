<?php
/**
 * Quarantine columns
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Quarantine columns of an entity implementing {@see QuarantinableInterface}: a status, the
 * reason and the raw value set aside, on the entity itself (no separate table). Nothing is
 * corrected: the doubtful value is kept as received.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
trait QuarantinableTrait
{
    #[ORM\Column(type: 'boolean')]
    private bool $quarantined = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $quarantineReason = null;

    #[ORM\Column(type: 'text', length: 16777215, nullable: true)]
    private ?string $quarantineRawValue = null;

    /**
     * Tells whether the entity is in quarantine.
     *
     * @return bool True if in quarantine
     */
    public function isQuarantined(): bool
    {
        return $this->quarantined;
    }

    /**
     * Why the entity is in quarantine.
     *
     * @return string|null The reason, null if the entity was never in quarantine
     */
    public function getQuarantineReason(): ?string
    {
        return $this->quarantineReason;
    }

    /**
     * Value that was set aside, as it was received.
     *
     * @return string|null The raw value, null if none was kept
     */
    public function getQuarantineRawValue(): ?string
    {
        return $this->quarantineRawValue;
    }

    /**
     * Puts the entity in quarantine. The reason is a short text that must not contain a sequence.
     *
     * @param string $reason Short reason (255 characters at most)
     * @param string|null $rawValue Doubtful value, kept as received
     * @return static This entity
     */
    public function placeInQuarantine(string $reason, ?string $rawValue = null): static
    {
        $this->quarantined = true;
        $this->quarantineReason = mb_substr(trim($reason), 0, 255);
        $this->quarantineRawValue = $rawValue;

        return $this;
    }

    /**
     * Takes the entity out of quarantine. The reason and the raw value stay on the entity as a record of what was set aside.
     *
     * @return static This entity
     */
    public function releaseFromQuarantine(): static
    {
        $this->quarantined = false;

        return $this;
    }
}
