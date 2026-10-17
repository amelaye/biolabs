<?php
/**
 * Tests of the target entities: normalisation, validation, derived sequences
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Target;

use App\Reference\Entity\Organism;
use App\Reference\Service\ReferenceWarnings;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Enum\ProductType;
use App\Target\Service\SequenceAnalysis;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Synthetic sequences only.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class TargetRulesTest extends DatabaseWebTestCase
{
    private int $organismCounter = 0;

    /**
     * Stores a synthetic organism with a unique name.
     *
     * @param int $code NCBI genetic code table
     * @return Organism The stored organism
     */
    private function organism(int $code = 11): Organism
    {
        $organism = (new Organism())->setName('Synthetic organism '.++$this->organismCounter)->setGeneticCode($code);
        $this->em->persist($organism);
        $this->em->flush();

        return $organism;
    }

    /**
     * Builds a gene.
     *
     * @param string $cds Coding sequence as typed
     * @param Organism|null $organism Organism, a new one under table 11 if null
     * @param string $name Gene name
     * @return Gene A new, not persisted gene
     */
    private function gene(string $cds, ?Organism $organism = null, string $name = 'GENE1'): Gene
    {
        return (new Gene())->setName($name)->setOrganism($organism ?? $this->organism())->setCds($cds);
    }

    /**
     * Builds a product with a unique name.
     *
     * @param Gene $gene Gene of the product
     * @param ProductType $type Kind of molecule
     * @param int|null $start First nucleotide of the window
     * @param int|null $end Last nucleotide of the window
     * @return Product A new, not persisted product
     */
    private function product(Gene $gene, ProductType $type = ProductType::Protein, ?int $start = null, ?int $end = null): Product
    {
        return (new Product())->setName('P-'.$gene->getName().'-'.random_int(1, 99999))->setGene($gene)->setType($type)->setStartNt($start)->setEndNt($end);
    }

    /**
     * Validates an entity.
     *
     * @param object $entity Entity to validate
     * @return list<string> 'path:message key' of each violation
     */
    private function errors(object $entity): array
    {
        $out = [];
        foreach (static::getContainer()->get(ValidatorInterface::class)->validate($entity) as $violation) {
            $out[] = $violation->getPropertyPath().':'.$violation->getMessageTemplate();
        }

        return $out;
    }

    /**
     * Tests that the CDS keeps the raw text and the normalised form is uppercase without whitespace.
     *
     * @return void
     */
    public function testCdsIsKeptRawAndStoredNormalised(): void
    {
        $gene = $this->gene("atg gcc\naaa  taa ");

        self::assertSame("atg gcc\naaa  taa ", $gene->getCdsSequenceRaw());
        self::assertSame('ATGGCCAAATAA', $gene->getCdsSequence());
        self::assertSame(12, $gene->getCdsLength());
        self::assertSame([], $this->errors($gene));
    }

    /**
     * Tests that an empty CDS, a length that is not a multiple of 3 and an invalid symbol (digit, U) are refused.
     *
     * @return void
     */
    public function testGeneRefusesAnEmptyCdsALengthOutOfFrameAndAnInvalidSymbol(): void
    {
        self::assertSame(['cds:gene.cds.empty'], $this->errors($this->gene('  ')));
        self::assertSame(['cds:gene.cds.not_in_frame'], $this->errors($this->gene('ATGGC')));
        self::assertSame(['cds:gene.cds.invalid_symbol'], $this->errors($this->gene('ATG1CC')));
        self::assertSame(['cds:gene.cds.invalid_symbol'], $this->errors($this->gene('ATGUCC')), 'U is RNA, not DNA');
    }

    /**
     * Tests that a gene without organism is refused.
     *
     * @return void
     */
    public function testGeneRequiresAnOrganism(): void
    {
        $gene = (new Gene())->setName('G')->setCds('ATGAAATAA');

        self::assertSame(['organism:This value should not be null.'], $this->errors($gene));
    }

    /**
     * Tests the window rules: both bounds or none, start before end, end inside the CDS, positive start.
     *
     * @return void
     */
    public function testProductWindowRules(): void
    {
        $gene = $this->gene('ATGGCCAAATAA'); // 12 nt

        self::assertSame([], $this->errors($this->product($gene)), 'whole CDS');
        self::assertSame([], $this->errors($this->product($gene, ProductType::Protein, 4, 9)));
        self::assertSame(['endNt:product.window.both_or_none'], $this->errors($this->product($gene, ProductType::Protein, 4, null)));
        self::assertSame(['startNt:product.window.start_after_end'], $this->errors($this->product($gene, ProductType::Dna, 9, 4)));
        self::assertSame(['endNt:product.window.beyond_cds'], $this->errors($this->product($gene, ProductType::Dna, 1, 13)));
        self::assertContains('startNt:This value should be positive.', $this->errors($this->product($gene, ProductType::Dna, 0, 6)));
    }

    /**
     * Tests that only a protein window must be on codon boundaries.
     *
     * @return void
     */
    public function testProteinWindowMustBeInFrameButRnaAndDnaNeedNotBe(): void
    {
        $gene = $this->gene('ATGGCCAAATAA');

        self::assertSame(['startNt:product.window.out_of_frame'], $this->errors($this->product($gene, ProductType::Protein, 2, 7)), 'start not on a codon');
        self::assertSame(['startNt:product.window.out_of_frame'], $this->errors($this->product($gene, ProductType::Protein, 1, 7)), 'length not a multiple of 3');
        self::assertSame([], $this->errors($this->product($gene, ProductType::Rna, 2, 7)));
        self::assertSame([], $this->errors($this->product($gene, ProductType::Dna, 2, 7)));
    }

    /**
     * Tests that the translation uses the organism's table (stops differ between tables 1 and 2) and marks ambiguous codons.
     *
     * @return void
     */
    public function testTranslationFollowsTheOrganismGeneticCode(): void
    {
        $analysis = static::getContainer()->get(SequenceAnalysis::class);

        self::assertSame('M*R', $analysis->translate('ATGTGAAGA', 1), 'standard: TGA stop, AGA Arg');
        self::assertSame('MW*', $analysis->translate('ATGTGAAGA', 2), 'vertebrate mitochondrial: TGA Trp, AGA stop');
        self::assertSame('MW*', $analysis->geneProtein($this->gene('ATGTGAAGA', $this->organism(2))));
        self::assertSame('M*R', $analysis->geneProtein($this->gene('ATGTGAAGA', $this->organism(1), 'GENE2')));
        self::assertSame('MX', $analysis->translate('ATGNNN', 1), 'ambiguous codon');
    }

    /**
     * Tests the DNA, RNA, protein and mass derived from a product window.
     *
     * @return void
     */
    public function testProductSequencesAreDerivedFromTheGene(): void
    {
        $analysis = static::getContainer()->get(SequenceAnalysis::class);
        $gene = $this->gene('ATGGCCAAATAA'); // M A K *

        $protein = $analysis->analyseProduct($this->product($gene, ProductType::Protein, 1, 9));
        self::assertSame('ATGGCCAAA', $protein['dna']);
        self::assertSame('MAK', $protein['protein']);
        self::assertNotNull($protein['mass']);
        self::assertEqualsWithDelta(348.46, $protein['mass']['max'], 0.1, 'Met+Ala+Lys free amino acids minus two waters');

        $whole = $analysis->analyseProduct($this->product($gene, ProductType::Protein));
        self::assertSame('MAK*', $whole['protein'], 'no window = whole CDS, stop included');

        $rna = $analysis->analyseProduct($this->product($gene, ProductType::Rna, 1, 6));
        self::assertSame('AUGGCC', $rna['rna']);
        self::assertNull($rna['protein']);

        $dna = $analysis->analyseProduct($this->product($gene, ProductType::Dna, 4, 6));
        self::assertSame('GCC', $dna['dna']);
    }

    /**
     * Tests that no sequence is derived when the window is invalid.
     *
     * @return void
     */
    public function testNoSequenceIsDerivedFromAnInvalidWindow(): void
    {
        $analysis = static::getContainer()->get(SequenceAnalysis::class);
        $gene = $this->gene('ATGGCCAAATAA');

        self::assertNull($analysis->analyseProduct($this->product($gene, ProductType::Dna, 1, 40)));
        self::assertNull($analysis->analyseProduct($this->product($gene, ProductType::Dna, 9, 4)));
    }

    /**
     * Warnings of an entry.
     *
     * @param object $entry Gene or product
     * @return list<string> Translation key of each warning
     */
    private function warningKeys(object $entry): array
    {
        return array_column(static::getContainer()->get(ReferenceWarnings::class)->for($entry), 'key');
    }

    /**
     * Tests the warnings on start codon, stop codons and ambiguity codes.
     *
     * @return void
     */
    public function testGeneWarnings(): void
    {
        self::assertSame([], $this->warningKeys($this->gene('ATGGCCAAATAA')));

        $keys = $this->warningKeys($this->gene('GTGTAGAAA', null, 'ODD'));
        self::assertContains('target.warning.no_start_codon', $keys);
        self::assertContains('target.warning.internal_stop', $keys);
        self::assertContains('target.warning.no_stop_codon', $keys);

        self::assertContains('target.warning.ambiguity_codes', $this->warningKeys($this->gene('ATGNNNTAA', null, 'AMB')));
    }

    /**
     * Tests that TGA is an internal stop under table 11 but not under table 4.
     *
     * @return void
     */
    public function testStopIsReadUnderTheOrganismTable(): void
    {
        // TGA is a stop in table 11 but tryptophan in table 4 (Mycoplasma): no internal stop there.
        self::assertContains('target.warning.internal_stop', $this->warningKeys($this->gene('ATGTGAAAATAA', $this->organism(11), 'T11')));
        self::assertNotContains('target.warning.internal_stop', $this->warningKeys($this->gene('ATGTGAAAATAA', $this->organism(4), 'T4')));
    }

    /**
     * Tests that a protein window with a stop codon warns and that DNA products and stop-free windows do not.
     *
     * @return void
     */
    public function testProductWindowContainingAStopWarns(): void
    {
        $gene = $this->gene('ATGGCCAAATAA');

        self::assertContains('target.warning.window_has_stop', $this->warningKeys($this->product($gene)));
        self::assertNotContains('target.warning.window_has_stop', $this->warningKeys($this->product($gene, ProductType::Protein, 1, 9)));
        self::assertNotContains('target.warning.window_has_stop', $this->warningKeys($this->product($gene, ProductType::Dna)));
    }
}
