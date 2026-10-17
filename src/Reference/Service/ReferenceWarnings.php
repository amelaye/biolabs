<?php
/**
 * Non-blocking warnings on a reference entry
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Organism;
use App\Reference\Entity\SelectionMarker;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Warnings shown after a save: they never block it.
 *
 * Each warning is a translation key and its parameters.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceWarnings
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReferenceUsage $usage,
    ) {
    }

    /**
     * @param array<string, scalar|null> $before significantValues() of the entry before the edition
     *
     * @return list<array{key: string, params: array<string, scalar>}>
     */
    public function for(AbstractReference $entry, array $before = []): array
    {
        $warnings = [];

        if (1 === preg_match('/^(no\b|unknown$|n\/a$)/iu', $entry->getName())) {
            $warnings[] = ['key' => 'reference.warning.none_like', 'params' => []];
        }

        foreach ($this->nearDuplicates($entry) as $other) {
            $warnings[] = ['key' => 'reference.warning.near_duplicate', 'params' => ['%name%' => $other]];
        }

        if ($entry instanceof Organism && null === $entry->getTaxId()) {
            $warnings[] = ['key' => 'reference.warning.taxid_missing', 'params' => []];
        }

        if ($entry instanceof SelectionMarker) {
            $concentration = $entry->getWorkingConcentrationUgMl();
            [$low, $high] = SelectionMarker::WORKING_CONCENTRATION_USUAL_RANGE_UG_ML;
            if (null !== $concentration && ($concentration < $low || $concentration > $high)) {
                $warnings[] = ['key' => 'reference.warning.concentration_range', 'params' => ['%low%' => $low, '%high%' => $high]];
            }
        }

        if ([] !== $before && $before !== $entry->significantValues() && ($uses = $this->usage->count($entry)) > 0) {
            $warnings[] = ['key' => 'reference.warning.used_entry_changed', 'params' => ['%count%' => $uses]];
        }

        return $warnings;
    }

    /**
     * @return list<string> names of the other entries that differ only by punctuation or spaces
     */
    private function nearDuplicates(AbstractReference $entry): array
    {
        $loose = ReferenceName::looseKey($entry->getName());
        if ('' === $loose) {
            return [];
        }

        $near = [];
        foreach ($this->em->getRepository($entry::class)->findAll() as $other) {
            if ($other !== $entry && $other->getNameKey() !== $entry->getNameKey() && ReferenceName::looseKey($other->getName()) === $loose) {
                $near[] = $other->getName();
            }
        }

        return $near;
    }
}
