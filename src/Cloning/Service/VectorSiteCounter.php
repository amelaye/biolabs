<?php
/**
 * Number of cuts of a restriction enzyme in a vector, counted by biophp
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use Amelaye\BioPHP\Domain\Cloning\Aggregate\Plasmid;
use Amelaye\BioPHP\Domain\Cloning\Interfaces\RestrictionDigestInterface;
use Amelaye\BioPHP\Domain\Sequence\Exception\InvalidSequenceException;
use Amelaye\BioPHP\Domain\Sequence\Interfaces\RestrictionEnzymeCatalogInterface;
use Amelaye\BioPHP\Domain\Sequence\ValueObject\CircularDnaSequence;
use App\Cloning\Entity\Vector;

/**
 * Converts a biolabs vector to biophp's circular {@see Plasmid} and asks the circular digest
 * manager for the cuts: the search on both strands and across the origin is biophp's.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class VectorSiteCounter
{
    /**
     * VectorSiteCounter constructor.
     *
     * @param RestrictionEnzymeCatalogInterface $catalog biophp restriction enzyme catalogue
     * @param RestrictionDigestInterface $digest biophp circular digest manager
     */
    public function __construct(
        private readonly RestrictionEnzymeCatalogInterface $catalog,
        private readonly RestrictionDigestInterface $digest,
    ) {
    }

    /**
     * Catalogue spelling of an enzyme name: the name or alias of the catalogue that matches the typed text
     * ignoring case ("ncoi" gives "NcoI"). An alias is NOT replaced by the canonical name, which the
     * biologist may not use ("NcoI" is an alias of "Bsp19I" in the catalogue).
     *
     * @param string $name Enzyme name or alias as typed
     * @return string|null The catalogue spelling, null if the enzyme is unknown
     */
    public function spelling(string $name): ?string
    {
        $definition = $this->catalog->findByName($name);
        if (null === $definition) {
            return null;
        }
        foreach ([$definition->getName(), ...$definition->getAliases()] as $candidate) {
            if (0 === strcasecmp($candidate, trim($name))) {
                return $candidate;
            }
        }

        return $definition->getName();
    }

    /**
     * Canonical name of an enzyme in the biophp catalogue: isoschizomers and aliases share it
     * ("NcoI" and "Bsp19I" both give "Bsp19I"). Used to compare enzymes, never to rewrite the stored name.
     *
     * @param string $name Enzyme name or alias, any case
     * @return string|null The canonical name, null if the enzyme is unknown
     */
    public function canonicalName(string $name): ?string
    {
        return $this->catalog->findByName($name)?->getName();
    }

    /**
     * Number of distinct cuts of one enzyme in the circular vector.
     *
     * @param Vector $vector Vector with its sequence
     * @param string $enzyme Enzyme name (catalogue)
     * @return int|null The number of cuts, null when it cannot be counted (no sequence, invalid sequence, unknown enzyme)
     */
    public function countCuts(Vector $vector, string $enzyme): ?int
    {
        $definition = $this->catalog->findByName($enzyme);
        if (null === $definition || !$vector->hasSequence()) {
            return null;
        }
        try {
            $plasmid = new Plasmid(trim($vector->getName()) ?: 'vector', new CircularDnaSequence((string) $vector->getSequence()));
        } catch (InvalidSequenceException) {
            return null;
        }

        $count = 0;
        foreach ($this->digest->digest($plasmid, [$definition])->getCuts() as $cut) {
            if (0 === strcasecmp($cut->getEnzymeName(), $definition->getName())) {
                ++$count;
            }
        }

        return $count;
    }
}
