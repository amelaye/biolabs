<?php
/**
 * Kind of molecule a product is
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Enum;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum ProductType: string
{
    case Protein = 'protein';
    case Rna = 'rna';
    case Dna = 'dna';
}
