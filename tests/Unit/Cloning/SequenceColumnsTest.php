<?php
/**
 * Tests that the sequence columns can hold the longest validated sequence
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Unit\Cloning;

use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Shared\Audit\FieldHistory;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;

/**
 * A plain MySQL TEXT stops at 65 535 bytes while a vector accepts 300 000 characters: the
 * columns must declare a length that makes Doctrine create MEDIUMTEXT.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class SequenceColumnsTest extends TestCase
{
    /**
     * Tests that every column that can hold a sequence is declared with a length of at least the longest validated sequence.
     *
     * @return void
     */
    public function testColumnsHoldTheLongestValidatedSequence(): void
    {
        $columns = [
            [Vector::class, 'sequenceRaw'],
            [Vector::class, 'sequence'],
            [Oligo::class, 'sequenceRaw'],
            [Oligo::class, 'sequence'],
            [FieldHistory::class, 'oldValue'],
            [FieldHistory::class, 'newValue'],
        ];
        foreach ([Oligo::class, Vector::class, Pcr::class, Insert::class, Construct::class] as $class) {
            $columns[] = [$class, 'quarantineRawValue'];
        }

        foreach ($columns as [$class, $property]) {
            $attributes = (new \ReflectionProperty($class, $property))->getAttributes(ORM\Column::class);
            self::assertCount(1, $attributes, $class.'::'.$property);
            $length = $attributes[0]->newInstance()->length;
            self::assertGreaterThanOrEqual(Vector::MAX_SEQUENCE_LENGTH, $length, $class.'::'.$property.' would truncate a valid sequence on MySQL');
        }
    }

    /**
     * Tests that such a length gives MEDIUMTEXT on MySQL (without needing a MySQL server), and a 64 KB length stays TEXT (the bug this guards against).
     *
     * @return void
     */
    public function testLengthGivesMediumTextOnMysql(): void
    {
        $platform = new MySQLPlatform();
        $type = Type::getType(Types::TEXT);

        self::assertSame('MEDIUMTEXT', $type->getSQLDeclaration(['length' => Vector::TEXT_COLUMN_LENGTH], $platform));
        self::assertSame('TEXT', $type->getSQLDeclaration(['length' => 65535], $platform));
    }
}
