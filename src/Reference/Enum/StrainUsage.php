<?php
/**
 * How a strain is used in the laboratory
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Enum;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum StrainUsage: string
{
    case Cloning = 'cloning';
    case Expression = 'expression';
    case Both = 'both';
}
