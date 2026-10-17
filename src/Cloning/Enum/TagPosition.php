<?php
/**
 * Position of a tag on the protein
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Enum;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum TagPosition: string
{
    case NTerminal = 'n_terminal';
    case CTerminal = 'c_terminal';
}
