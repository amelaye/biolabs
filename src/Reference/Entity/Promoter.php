<?php
/**
 * Entity Promoter - transcription promoter
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Promoter of an expression vector (name and notes only; no sequence before LAB-3).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_promoter')]
#[ORM\UniqueConstraint(name: 'uniq_bl_promoter_name_key', columns: ['name_key'])]
class Promoter extends AbstractReference
{
}
