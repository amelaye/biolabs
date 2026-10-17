<?php
/**
 * Melting temperature of an oligo, computed by biophp
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use Amelaye\BioPHP\Domain\Tools\Interfaces\MeltingTemperatureInterface;
use App\Cloning\Entity\Oligo;

/**
 * Thin layer over biophp's nearest-neighbour Tm: no thermodynamic computation lives here. The
 * result is never stored; it is recomputed with its conditions each time it is displayed.
 * The conditions below are indicative defaults, to be confirmed by the laboratory. The Tm is
 * that of the WHOLE oligo: the hybridising part is not annotated in this version, so an oligo
 * with a clamp or a site has a lower real annealing Tm than the value shown.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class OligoTm
{
    /** Oligo concentration of the computation, µM. */
    public const OLIGO_CONCENTRATION_UM = 0.5;

    /** Monovalent cations (Na+, K+) concentration, mM. */
    public const MONOVALENT_MM = 50.0;

    /** Free Mg2+ concentration, mM. */
    public const MAGNESIUM_MM = 1.5;

    /**
     * OligoTm constructor.
     *
     * @param MeltingTemperatureInterface $calculator biophp melting temperature calculator
     */
    public function __construct(private readonly MeltingTemperatureInterface $calculator)
    {
    }

    /** Reason code: fewer than two bases. */
    public const REASON_LENGTH = 'length';

    /** Reason code: degenerate IUPAC base (N, R, Y...). */
    public const REASON_DEGENERATE = 'degenerate';

    /** Reason code: unknown base X. */
    public const REASON_UNKNOWN_BASE = 'unknown_base';

    /**
     * Why the Tm of an oligo cannot be computed: this is a classification of the characters of the
     * sequence (no thermodynamic computation), used only to tell the person what to fix.
     *
     * @param Oligo $oligo Oligo to inspect
     * @return string|null One of the REASON_* codes, null when the Tm can be computed
     */
    public function unavailableReason(Oligo $oligo): ?string
    {
        $sequence = $oligo->getSequence();
        if (\strlen($sequence) < 2) {
            return self::REASON_LENGTH;
        }
        if (str_contains($sequence, 'X')) {
            return self::REASON_UNKNOWN_BASE;
        }
        if (1 === preg_match('/[^ACGT]/', $sequence)) {
            return self::REASON_DEGENERATE;
        }

        return null;
    }

    /**
     * Nearest-neighbour Tm of an oligo under the default conditions.
     *
     * @param Oligo $oligo Oligo to evaluate
     * @return array{tm: float, oligoUm: float, monovalentMm: float, magnesiumMm: float}|null Tm in °C and the conditions used, null when it cannot be computed (see unavailableReason())
     */
    public function forOligo(Oligo $oligo): ?array
    {
        if (null !== $this->unavailableReason($oligo)) {
            return null;
        }
        try {
            // biophp takes the strand concentration in nM and the salts in mM.
            $result = $this->calculator->calculateNearestNeighborTm(
                $oligo->getSequence(),
                self::OLIGO_CONCENTRATION_UM * 1000,
                self::MONOVALENT_MM,
                self::MAGNESIUM_MM,
            );
        } catch (\InvalidArgumentException) {
            return null;
        }

        return [
            'tm' => $result->getTm(),
            'oligoUm' => self::OLIGO_CONCENTRATION_UM,
            'monovalentMm' => self::MONOVALENT_MM,
            'magnesiumMm' => self::MAGNESIUM_MM,
        ];
    }
}
