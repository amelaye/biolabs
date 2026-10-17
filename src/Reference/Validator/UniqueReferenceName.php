<?php
/**
 * Constraint: reference name unique after normalisation, archived entries included
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueReferenceName extends Constraint
{
    public string $message = 'reference.name.already_used';
    public string $archivedMessage = 'reference.name.already_used_archived';

    /**
     * The constraint applies to the whole entry (class constraint).
     *
     * @return string|array The constraint target
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
