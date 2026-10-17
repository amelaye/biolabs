<?php
/**
 * Contract of an entity whose old and new values are kept for some fields
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Shared\Audit;

/**
 * Entity for which every change of some fields is recorded in {@see FieldHistory}.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
interface TracksFieldHistoryInterface
{
    /**
     * Names of the entity properties whose changes are recorded.
     *
     * @return list<string> Property names, as known by Doctrine
     */
    public function trackedFields(): array;

    /**
     * Identifier of the entity.
     *
     * @return int|null The primary key, null until the entity is stored
     */
    public function getId(): ?int;
}
