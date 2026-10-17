<?php
/**
 * Knowledge of the tag capabilities of a vector
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Enum;

/**
 * "None" (the vector has no tag capability, a fact) is not "unknown" (nobody checked, a gap).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum TagCapabilityState: string
{
    case Unknown = 'unknown';
    case None = 'none';
    case Listed = 'listed';
}
