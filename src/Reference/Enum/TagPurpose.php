<?php
/**
 * Purpose of a fusion tag
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Enum;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum TagPurpose: string
{
    case Purification = 'purification';
    case Detection = 'detection';
}
