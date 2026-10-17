<?php
/**
 * Constraint: the sequence of an oligo used by a PCR cannot change
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class OligoSequenceLocked extends Constraint
{
    public string $message = 'oligo.sequence.locked';

    /**
     * The constraint applies to the whole oligo (class constraint).
     *
     * @return string|array The constraint target
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
