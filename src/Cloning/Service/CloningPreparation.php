<?php
/**
 * Derivations applied to a cloning object just before it is saved
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use App\Cloning\Entity\Insert;
use App\Reference\Entity\AbstractReference;

/**
 * Applies what the person never types: the catalogue spelling of the enzymes of an insert. (The status of a construct is
 * not stored: it is derived on display.)
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CloningPreparation
{
    /**
     * CloningPreparation constructor.
     *
     * @param VectorSiteCounter $sites Gives the catalogue spelling of an enzyme
     */
    public function __construct(private readonly VectorSiteCounter $sites)
    {
    }

    /**
     * Prepares a validated entry for storage.
     *
     * @param AbstractReference $entry Entry about to be saved
     * @return void
     */
    public function prepare(AbstractReference $entry): void
    {
        if ($entry instanceof Insert) {
            $entry->setFivePrimeSite($this->canonical($entry->getFivePrimeSite()));
            $entry->setThreePrimeSite($this->canonical($entry->getThreePrimeSite()));
        }
    }

    /**
     * Catalogue spelling of an enzyme name (the alias the person used, with the catalogue's case).
     *
     * @param string|null $name Name as typed
     * @return string|null The catalogue spelling, the input when unknown (validation refuses it), null for none
     */
    private function canonical(?string $name): ?string
    {
        return null === $name ? null : ($this->sites->spelling($name) ?? $name);
    }
}
