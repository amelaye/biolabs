<?php
/**
 * Tests of the validation rules of the reference entities
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Reference;

use App\Reference\Entity\Organism;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use App\Reference\Entity\Supplier;
use App\Reference\Entity\Tag;
use App\Reference\Enum\TagPurpose;
use App\Tests\Support\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceValidationTest extends DatabaseWebTestCase
{
    /**
     * Validates an entity.
     *
     * @param object $entity Entity to validate
     * @return list<string> Property path of each violation
     */
    private function errors(object $entity): array
    {
        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($entity);
        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }

    /**
     * Builds an organism with the given name and genetic code.
     *
     * @param string $name Organism name
     * @param int $code NCBI genetic code table
     * @return Organism A new, not persisted organism
     */
    private function organism(string $name = 'Escherichia coli', int $code = 11): Organism
    {
        return (new Organism())->setName($name)->setGeneticCode($code);
    }

    /**
     * Tests that a complete organism is valid.
     *
     * @return void
     */
    public function testValidOrganismHasNoError(): void
    {
        self::assertSame([], $this->errors($this->organism()->setTaxId(562)));
    }

    /**
     * Tests that a genetic code table outside the supported list is refused.
     *
     * @param int $code Table number that must be refused
     * @return void
     */
    #[DataProvider('invalidGeneticCodes')]
    public function testUnknownGeneticCodeIsRefused(int $code): void
    {
        self::assertSame(['geneticCode'], $this->errors($this->organism(code: $code)));
    }

    /**
     * Data provider: table numbers that must be refused.
     *
     * @return iterable<string, array{int}> Table number by case name
     */
    public static function invalidGeneticCodes(): iterable
    {
        yield 'table 0 does not exist' => [0];
        yield 'table 7 does not exist' => [7];
        yield 'table 8 does not exist' => [8];
        yield 'table 17-20 do not exist' => [18];
        yield 'table 27 not supported by biophp' => [27];
        yield 'table 31 not supported by biophp' => [31];
    }

    /**
     * Tests that table 32, absent from older lists, is accepted.
     *
     * @return void
     */
    public function testTable32IsAccepted(): void
    {
        self::assertSame([], $this->errors($this->organism(code: 32)));
    }

    /**
     * Tests that table 11 is not a mitochondrial table while table 2 is.
     *
     * @return void
     */
    public function testMitochondrialTable11IsRefusedButTable2IsAccepted(): void
    {
        self::assertSame(['mitochondrialGeneticCode'], $this->errors($this->organism()->setMitochondrialGeneticCode(11)));
        self::assertSame([], $this->errors($this->organism('Homo sapiens', 1)->setMitochondrialGeneticCode(2)));
    }

    /**
     * Tests that an organism without genetic code is refused.
     *
     * @return void
     */
    public function testGeneticCodeIsRequired(): void
    {
        self::assertSame(['geneticCode'], $this->errors((new Organism())->setName('X')));
    }

    /**
     * Tests that a zero or negative taxid is refused.
     *
     * @param int $taxId Taxid that must be refused
     * @return void
     */
    #[DataProvider('invalidTaxIds')]
    public function testNonPositiveTaxIdIsRefused(int $taxId): void
    {
        self::assertSame(['taxId'], $this->errors($this->organism()->setTaxId($taxId)));
    }

    /**
     * Data provider: taxids that must be refused.
     *
     * @return iterable<string, array{int}> Taxid by case name
     */
    public static function invalidTaxIds(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    /**
     * Tests that a blank name is refused.
     *
     * @return void
     */
    public function testBlankNameIsRefused(): void
    {
        self::assertSame(['name'], $this->errors($this->organism("   \t ")));
    }

    /**
     * Tests that names up to 100 characters are accepted (the legacy truncated at 30) and longer ones refused.
     *
     * @return void
     */
    public function testNameOver100CharactersIsRefusedWhereLegacyTruncatedAt30(): void
    {
        self::assertSame(['name'], $this->errors($this->organism(str_repeat('a', 101))));
        self::assertSame([], $this->errors($this->organism('Clostridium thermocellum '.str_repeat('a', 40))));
    }

    /**
     * Tests that a zero or negative concentration is refused.
     *
     * @return void
     */
    public function testMarkerConcentrationMustBePositive(): void
    {
        $marker = (new SelectionMarker())->setName('Ampicillin')->setWorkingConcentrationUgMl(0.0)->setStockConcentrationMgMl(-1.0);

        self::assertEqualsCanonicalizing(['workingConcentrationUgMl', 'stockConcentrationMgMl'], $this->errors($marker));
    }

    /**
     * Tests that a marker without concentration is valid.
     *
     * @return void
     */
    public function testMarkerWithoutConcentrationIsValid(): void
    {
        self::assertSame([], $this->errors((new SelectionMarker())->setName('Zeocin')));
    }

    /**
     * Tests that the tag sequence is normalised by biophp and that degenerate or invalid symbols are refused.
     *
     * @return void
     */
    public function testTagSequenceIsNormalisedByBiophpAndRestrictedToAcgt(): void
    {
        $tag = (new Tag())->setName('6xHis')->setPurpose(TagPurpose::Purification)->setDnaSequence(" cac cac\nCAC ");
        self::assertSame('CACCACCAC', $tag->getDnaSequence());
        self::assertSame([], $this->errors($tag));

        $bad = (new Tag())->setName('Bad')->setPurpose(TagPurpose::Detection)->setDnaSequence('ACGTN');
        self::assertSame(['dnaSequence'], $this->errors($bad), 'Degenerate codes are accepted by biophp but not in a tag.');

        $junk = (new Tag())->setName('Junk')->setPurpose(TagPurpose::Detection)->setDnaSequence('AC1GT!');
        self::assertSame(['dnaSequence'], $this->errors($junk));
    }

    /**
     * Tests that the purpose of a tag is required and its sequence optional.
     *
     * @return void
     */
    public function testTagPurposeIsRequiredAndSequenceOptional(): void
    {
        self::assertSame(['purpose'], $this->errors((new Tag())->setName('T')));
        self::assertNull((new Tag())->setDnaSequence('  ')->getDnaSequence());
    }

    /**
     * Tests that only http and https addresses are accepted.
     *
     * @param string $url Address to validate
     * @param bool $valid Whether it must be accepted
     * @return void
     */
    #[DataProvider('urls')]
    public function testSupplierUrlAcceptsOnlyHttpAndHttps(string $url, bool $valid): void
    {
        $errors = $this->errors((new Supplier())->setName('Acme')->setUrl($url));

        self::assertSame($valid ? [] : ['url'], $errors);
    }

    /**
     * Data provider: addresses and whether they are accepted.
     *
     * @return iterable<string, array{string, bool}> Address and expected validity by case name
     */
    public static function urls(): iterable
    {
        yield 'https' => ['https://www.example.org/catalog', true];
        yield 'http' => ['http://example.org', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'ftp' => ['ftp://example.org', false];
        yield 'data' => ['data:text/html,x', false];
    }

    /**
     * Tests that the name is unique through its normalised key, archived entries included, and not against itself.
     *
     * @return void
     */
    public function testNameUniquenessUsesTheNormalisedKeyArchivedIncluded(): void
    {
        $this->em->persist((new Strain())->setName('DH5α'));
        $archived = (new Strain())->setName('BL21(DE3)')->archive();
        $this->em->persist($archived);
        $this->em->flush();

        self::assertSame(['name'], $this->errors((new Strain())->setName('  dh5α ')), 'case and spaces are ignored');
        self::assertSame(['name'], $this->errors((new Strain())->setName('bl21(de3)')), 'archived entries count');
        self::assertSame([], $this->errors((new Strain())->setName('DH5a')), 'accents/Greek letters are kept');
        self::assertSame([], $this->errors((new Strain())->setName('BL21 DE3')), 'parentheses are kept');
        self::assertSame([], $this->errors($archived), 'an entry does not collide with itself');
    }

    /**
     * Tests that the same name may exist in two different lists.
     *
     * @return void
     */
    public function testSameNameIsAllowedInDifferentLists(): void
    {
        $this->em->persist((new Strain())->setName('Shared'));
        $this->em->flush();

        self::assertSame([], $this->errors((new Supplier())->setName('Shared')));
    }
}
