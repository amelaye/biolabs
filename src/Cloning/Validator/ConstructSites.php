<?php
/**
 * Constraint: each enzyme of the insert cuts the vector exactly once
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
final class ConstructSites extends Constraint
{
    /**
     * The constraint applies to the whole construct (class constraint).
     *
     * @return string|array The constraint target
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
