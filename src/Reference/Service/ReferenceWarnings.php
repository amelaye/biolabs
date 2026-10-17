<?php
/**
 * Non-blocking warnings on a reference entry
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

use App\Cloning\Service\CloningChecks;
use App\Reference\Entity\AbstractReference;
use App\Reference\Entity\Organism;
use App\Reference\Entity\SelectionMarker;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Service\TargetChecks;
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
    /**
     * ReferenceWarnings constructor.
     *
     * @param EntityManagerInterface $em Entity manager used to look for near-duplicates
     * @param ReferenceUsage $usage Counts the objects that use an entry
     * @param TargetChecks $targetChecks Biological checks on genes and products
     * @param CloningChecks $cloningChecks Checks on oligos, vectors, PCRs and constructs
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReferenceUsage $usage,
        private readonly TargetChecks $targetChecks,
        private readonly CloningChecks $cloningChecks,
    ) {
    }

    /**
     * Non-blocking warnings on an entry that was just saved.
     *
     * @param AbstractReference $entry Saved entry
     * @param array<string, scalar|null> $before significantValues() of the entry before the edition, empty for a creation
     * @return list<array{key: string, params: array<string, scalar>}> Translation key and parameters of each warning
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

        if ($entry instanceof Gene) {
            array_push($warnings, ...$this->targetChecks->forGene($entry));
        }
        if ($entry instanceof Product) {
            array_push($warnings, ...$this->targetChecks->forProduct($entry));
        }

        array_push($warnings, ...$this->cloningChecks->for($entry));

        if ([] !== $before && $before !== $entry->significantValues() && ($uses = $this->usage->count($entry)) > 0) {
            $warnings[] = ['key' => 'reference.warning.used_entry_changed', 'params' => ['%count%' => $uses]];
        }

        return $warnings;
    }

    /**
     * Other entries of the same list that differ only by punctuation or spaces.
     *
     * @param AbstractReference $entry Entry to compare
     * @return list<string> Names of the near-duplicates
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
