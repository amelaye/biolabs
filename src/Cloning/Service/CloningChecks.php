<?php
/**
 * Blocking and non-blocking checks on the cloning objects
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Cloning\Enum\ConstructStatus;
use App\Reference\Entity\AbstractReference;

/**
 * LIMS rules of the cloning objects. The computations (Tm, digestion) are biophp's; this class
 * only compares their results to the laboratory's thresholds. The thresholds are indicative
 * and unsourced (see skill wet-lab-conventions): they raise warnings, never errors.
 *
 * Each warning is a translation key and its parameters.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CloningChecks
{
    /** Indicative range of the Tm of an oligo, °C. */
    public const TM_USUAL_RANGE_C = [50.0, 72.0];

    /** Largest usual difference between the Tm of the forward and reverse oligos, °C. */
    public const TM_MAX_DIFFERENCE_C = 5.0;

    /** Usual length of an oligo, nt. */
    public const OLIGO_USUAL_LENGTH_NT = [15, 100];

    /** Usual volume of a PCR, µL. */
    public const PCR_USUAL_VOLUME_UL = [10.0, 100.0];

    /**
     * CloningChecks constructor.
     *
     * @param OligoTm $tm Melting temperature of an oligo
     * @param VectorSiteCounter $sites Cuts of an enzyme in a vector
     */
    public function __construct(
        private readonly OligoTm $tm,
        private readonly VectorSiteCounter $sites,
    ) {
    }

    /**
     * Distinct enzymes of an insert, compared by canonical catalogue name: "NcoI" and "Bsp19I" (or
     * "NcoI" and "ncoi") are the same enzyme. The first stored spelling of each is kept.
     * An unknown name is compared as typed, case ignored (the validation refuses it elsewhere).
     *
     * @param Insert $insert Insert whose ends are compared
     * @return list<string> The enzymes as stored, one per distinct canonical name, in 5'->3' order
     */
    public function distinctSites(Insert $insert): array
    {
        $distinct = [];
        foreach ([$insert->getFivePrimeSite(), $insert->getThreePrimeSite()] as $enzyme) {
            if (null === $enzyme) {
                continue;
            }
            $key = strtoupper($this->sites->canonicalName($enzyme) ?? $enzyme);
            $distinct[$key] ??= $enzyme;
        }

        return array_values($distinct);
    }

    /**
     * Enzymes of the insert that do not cut the vector exactly once. Each enzyme is evaluated on its own.
     * Nothing is reported when the vector has no sequence (the construct is then "not evaluable").
     *
     * @param Construct $construct Construct to check
     * @return list<array{enzyme: string, count: int}> The enzymes with their number of cuts
     */
    public function siteProblems(Construct $construct): array
    {
        $vector = $construct->getVector();
        $insert = $construct->getInsert();
        if (null === $vector || null === $insert || !$vector->hasSequence()) {
            return [];
        }

        $problems = [];
        foreach ($this->distinctSites($insert) as $enzyme) {
            $count = $this->sites->countCuts($vector, $enzyme);
            if (null !== $count && 1 !== $count) {
                $problems[] = ['enzyme' => $enzyme, 'count' => $count];
            }
        }

        return $problems;
    }

    /**
     * Derived status of a construct, computed on display from the CURRENT vector and insert (it is
     * never stored, so it cannot be stale): "designed" when the vector sequence is known, the insert has at
     * least one site and every site cuts the vector exactly once; "not evaluable" otherwise. Order,
     * orientation, ends and methylation are NOT checked: it is never a statement of biological validity.
     *
     * @param Construct $construct Construct to evaluate
     * @return ConstructStatus The status
     */
    public function status(Construct $construct): ConstructStatus
    {
        $vector = $construct->getVector();
        $insert = $construct->getInsert();
        if (null === $vector || null === $insert || !$vector->hasSequence() || [] === $this->distinctSites($insert)) {
            return ConstructStatus::NotEvaluable;
        }
        foreach ($this->distinctSites($insert) as $enzyme) {
            if (null === $this->sites->countCuts($vector, $enzyme)) {
                return ConstructStatus::NotEvaluable;
            }
        }

        return [] === $this->siteProblems($construct) ? ConstructStatus::Designed : ConstructStatus::NotEvaluable;
    }

    /**
     * Non-blocking warnings on a cloning object.
     *
     * @param AbstractReference $entry Oligo, vector, PCR, insert or construct
     * @return list<array{key: string, params: array<string, scalar>}> Translation key and parameters of each warning
     */
    public function for(AbstractReference $entry): array
    {
        return match (true) {
            $entry instanceof Oligo => $this->forOligo($entry),
            $entry instanceof Vector => $this->forVector($entry),
            $entry instanceof Pcr => $this->forPcr($entry),
            $entry instanceof Construct => $this->forConstruct($entry),
            default => [],
        };
    }

    /**
     * Warnings on an oligo: unusual length, Tm outside the usual window.
     *
     * @param Oligo $oligo Oligo to check
     * @return list<array{key: string, params: array<string, scalar>}> The warnings
     */
    private function forOligo(Oligo $oligo): array
    {
        $warnings = [];
        [$minLength, $maxLength] = self::OLIGO_USUAL_LENGTH_NT;
        $length = $oligo->getLength();
        if ($length > 0 && ($length < $minLength || $length > $maxLength)) {
            $warnings[] = ['key' => 'cloning.warning.oligo_length', 'params' => ['%length%' => $length, '%low%' => $minLength, '%high%' => $maxLength]];
        }
        $tm = $this->tm->forOligo($oligo);
        if (null !== $tm) {
            [$low, $high] = self::TM_USUAL_RANGE_C;
            if ($tm['tm'] < $low || $tm['tm'] > $high) {
                $warnings[] = ['key' => 'cloning.warning.tm_window', 'params' => ['%tm%' => $tm['tm'], '%low%' => $low, '%high%' => $high]];
            }
        }

        return $warnings;
    }

    /**
     * Warnings on a vector: no sequence available, or ambiguous bases that make the cut count unreliable.
     *
     * @param Vector $vector Vector to check
     * @return list<array{key: string, params: array<string, scalar>}> The warnings
     */
    private function forVector(Vector $vector): array
    {
        if (!$vector->hasSequence()) {
            return [['key' => 'cloning.warning.vector_no_sequence', 'params' => []]];
        }

        return $this->ambiguityWarnings($vector);
    }

    /**
     * Warning when the vector sequence holds bases other than A, C, G, T (N, X, IUPAC codes): the
     * number of cuts of an enzyme in such a sequence is not reliable. Not blocking.
     *
     * @param Vector $vector Vector with a sequence
     * @return list<array{key: string, params: array<string, scalar>}> The warning, empty when the sequence is unambiguous
     */
    private function ambiguityWarnings(Vector $vector): array
    {
        $count = preg_match_all('/[^ACGT]/', (string) $vector->getSequence());

        return $count > 0 ? [['key' => 'cloning.warning.vector_ambiguous', 'params' => ['%count%' => $count]]] : [];
    }

    /**
     * Warnings on a PCR: same oligo twice, Tm gap between the oligos, unusual volume.
     *
     * @param Pcr $pcr PCR to check
     * @return list<array{key: string, params: array<string, scalar>}> The warnings
     */
    private function forPcr(Pcr $pcr): array
    {
        $warnings = [];
        $forward = $pcr->getForwardOligo();
        $reverse = $pcr->getReverseOligo();
        if (null !== $forward && $forward === $reverse) {
            $warnings[] = ['key' => 'cloning.warning.pcr_same_oligo', 'params' => []];
        }
        if (null !== $forward && null !== $reverse) {
            $forwardTm = $this->tm->forOligo($forward);
            $reverseTm = $this->tm->forOligo($reverse);
            if (null !== $forwardTm && null !== $reverseTm && abs($forwardTm['tm'] - $reverseTm['tm']) > self::TM_MAX_DIFFERENCE_C) {
                $warnings[] = ['key' => 'cloning.warning.tm_difference', 'params' => ['%difference%' => round(abs($forwardTm['tm'] - $reverseTm['tm']), 1), '%max%' => self::TM_MAX_DIFFERENCE_C]];
            }
        }
        $volume = $pcr->getReactionVolumeUl();
        [$low, $high] = self::PCR_USUAL_VOLUME_UL;
        if (null !== $volume && $volume > 0 && ($volume < $low || $volume > $high)) {
            $warnings[] = ['key' => 'cloning.warning.pcr_volume', 'params' => ['%volume%' => $volume, '%low%' => $low, '%high%' => $high]];
        }

        return $warnings;
    }

    /**
     * Warnings on a construct: vector without sequence, product of the insert not linked to the construct.
     *
     * @param Construct $construct Construct to check
     * @return list<array{key: string, params: array<string, scalar>}> The warnings
     */
    private function forConstruct(Construct $construct): array
    {
        $warnings = [];
        if (null !== $construct->getVector() && !$construct->getVector()->hasSequence()) {
            $warnings[] = ['key' => 'cloning.warning.vector_no_sequence', 'params' => []];
        } elseif (null !== $construct->getVector()) {
            $warnings = [...$warnings, ...$this->ambiguityWarnings($construct->getVector())];
        }
        $product = $construct->getInsert()?->getProduct();
        if ($construct->getProducts()->isEmpty() || (null !== $product && !$construct->getProducts()->contains($product))) {
            $warnings[] = ['key' => 'cloning.warning.product_not_linked', 'params' => []];
        }

        return $warnings;
    }
}
