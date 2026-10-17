<?php
/**
 * Exception: the sequence of an oligo used by a PCR cannot be changed
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

/**
 * Raised at flush time when a write path other than the form tries to change a locked oligo.
 * The message never contains a sequence.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class LockedOligoException extends \DomainException
{
}
