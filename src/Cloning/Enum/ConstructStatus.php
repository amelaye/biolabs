<?php
/**
 * Status of a construct (derived, never typed)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Enum;

/**
 * Status of a construct. It is derived by the application: "designed" when the vector sequence
 * is known and every site of the insert cuts it exactly once, "not evaluable" otherwise.
 * Neither means that the construct is biologically valid: assembly and reading frame are not
 * computed yet, so "calculated" and "verified" do not exist in this version.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
enum ConstructStatus: string
{
    case Designed = 'designed';
    case NotEvaluable = 'not_evaluable';
}
