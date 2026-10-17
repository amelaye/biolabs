<?php
/**
 * Constraint: the two ends of a directional insert use two different enzymes
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Class constraint of {@see \App\Cloning\Entity\Insert}: enzymes are compared by canonical
 * catalogue name, so isoschizomers (NcoI and Bsp19I) count as the same enzyme.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class InsertDistinctSites extends Constraint
{
    /** Translation key of the violation. */
    public string $message = 'insert.sites.same_enzyme';

    /**
     * The constraint applies to the whole insert (class constraint).
     *
     * @return string|array The constraint target
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
