<?php
/**
 * Entity ReplicationOrigin - origin of replication
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Origin of replication of a plasmid (ColE1, p15A...). The legacy placeholder "Unknown?!" is not an entry.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[ORM\Entity]
#[ORM\Table(name: 'bl_replication_origin')]
#[ORM\UniqueConstraint(name: 'uniq_bl_replication_origin_name_key', columns: ['name_key'])]
class ReplicationOrigin extends AbstractReference
{
}
