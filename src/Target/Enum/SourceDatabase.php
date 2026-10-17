<?php
/**
 * Database a gene sequence comes from
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Enum;

/**
 * Source database of a CDS (the legacy "db_name"; "ncbi/genbank" becomes "genbank").
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum SourceDatabase: string
{
    case Embl = 'embl';
    case Genbank = 'genbank';
    case Ddbj = 'dbj';
    case Sgd = 'sgd';
    case Swissprot = 'swissprot';
}
