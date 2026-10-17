<?php
/**
 * Exception: an uploaded file is refused
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

/**
 * The message is a translation key of the "messages" domain, never a file content or a path.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class AttachmentRejectedException extends \RuntimeException
{
}
