<?php
/**
 * Constraint: enzyme name present in the biophp restriction enzyme catalogue
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
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class KnownRestrictionEnzyme extends Constraint
{
    public string $message = 'cloning.enzyme.unknown';
}
