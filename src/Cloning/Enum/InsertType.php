<?php
/**
 * Kind of insert
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Enum;

/**
 * Origin of the DNA fragment of an insert.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum InsertType: string
{
    case PcrFragment = 'pcr_fragment';
    case RestrictionFragment = 'restriction_fragment';
}
