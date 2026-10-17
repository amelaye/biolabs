<?php
/**
 * Tests of the cloning entities: normalisation, blocking rules, warnings, derived status
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Cloning;

use Amelaye\BioPHP\Domain\Tools\Interfaces\MeltingTemperatureInterface;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Cloning\Enum\ConstructStatus;
use App\Cloning\Enum\InsertType;
use App\Cloning\Enum\TagCapabilityState;
use App\Cloning\Service\CloningChecks;
use App\Cloning\Service\CloningPreparation;
use App\Cloning\Service\OligoTm;
use App\Cloning\Service\VectorSiteCounter;
use App\Reference\Entity\Tag;
use App\Reference\Enum\TagPurpose;
use App\Reference\Service\ReferenceWarnings;
use App\Tests\Support\CloningFixtures;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Synthetic sequences only.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CloningRulesTest extends DatabaseWebTestCase
{
    use CloningFixtures;

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
     * Translation keys of the warnings of an entry.
     *
     * @param object $entry Saved entry
     * @return list<string> Warning keys
     */
    private function warningKeys(object $entry): array
    {
        return array_column(static::getContainer()->get(ReferenceWarnings::class)->for($entry), 'key');
    }

    /**
     * Tests that the raw sequence of an oligo is kept and the normalised one comes from biophp (no whitespace, uppercase).
     *
     * @return void
     */
    public function testOligoKeepsTheRawSequenceAndNormalisesIt(): void
    {
        $oligo = (new Oligo())->setName('F1')->setSequenceInput("gtc ccatgg\nGC");

        self::assertSame("gtc ccatgg\nGC", $oligo->getSequenceRaw());
        self::assertSame('GTCCCATGGGC', $oligo->getSequence());
        self::assertSame([], $this->errors($oligo));
    }

    /**
     * Tests the blocking alphabet rules of an oligo: empty, a digit, a U (RNA), and a length above 255.
     *
     * @return void
     */
    public function testOligoAlphabetAndLengthAreBlocking(): void
    {
        self::assertContains('sequenceInput:oligo.sequence.empty', $this->errors((new Oligo())->setName('A')->setSequenceInput('  ')));
        self::assertContains('sequenceInput:oligo.sequence.invalid_symbol', $this->errors((new Oligo())->setName('B')->setSequenceInput('ATG1CC')));
        self::assertContains('sequenceInput:oligo.sequence.invalid_symbol', $this->errors((new Oligo())->setName('C')->setSequenceInput('AUGCC')));
        self::assertContains('sequenceInput:oligo.sequence.too_long', $this->errors((new Oligo())->setName('D')->setSequenceInput(str_repeat('A', 256))));
        self::assertSame([], $this->errors((new Oligo())->setName('E')->setSequenceInput('ACGTRYSWKMBDHVN')));
    }

    /**
     * Tests that an oligo name is unique after normalisation, archived oligos included.
     *
     * @return void
     */
    public function testOligoNameIsUniqueEvenWhenTheOtherIsArchived(): void
    {
        $this->oligo('ATGGCC', 'Primer-A')->archive();
        $this->em->flush();

        self::assertContains('name:reference.name.already_used_archived', $this->errors((new Oligo())->setName(' primer-a ')->setSequenceInput('ATGGCC')));
    }

    /**
     * Tests that the sequence of an oligo is locked once a PCR uses it, and free before.
     *
     * @return void
     */
    public function testOligoSequenceIsLockedOnceAPcrUsesIt(): void
    {
        $free = $this->oligo('ATGGCCAAAT');
        $free->setSequenceInput('ATGGCCAAAC');
        self::assertSame([], $this->errors($free), 'unused oligo: free to change');
        $this->em->clear();

        $pcr = $this->pcr();
        $used = $pcr->getForwardOligo();
        $id = $used->getId();
        $this->em->clear();
        $used = $this->em->find(Oligo::class, $id);
        $originalRaw = $used->getSequenceRaw();
        $used->setSequenceInput('GGGGGGGGGG');
        self::assertContains('sequenceInput:oligo.sequence.locked', $this->errors($used));

        $used->setSequenceInput($originalRaw);
        $used->setNotes('only the notes changed');
        self::assertSame([], $this->errors($used), 'the same sequence is not a change');
    }

    /**
     * Tests the warnings of an oligo: length outside 15-100 nt.
     *
     * @return void
     */
    public function testOligoLengthOutsideTheUsualRangeIsAWarning(): void
    {
        self::assertContains('cloning.warning.oligo_length', $this->warningKeys($this->oligo('ATGGCCAAAT')));
        self::assertNotContains('cloning.warning.oligo_length', $this->warningKeys($this->oligo('ATGGCCAAATAAGCTAGCTAGGATCC')));
    }

    /**
     * Tests that the Tm is biophp's nearest-neighbour result with the µM to nM conversion, and is null for a degenerate oligo.
     *
     * @return void
     */
    public function testOligoTmIsTheBiophpNearestNeighbourValue(): void
    {
        $oligo = $this->oligo('ATGGCCAAATAAGCTAGCTAGGATCC');
        $direct = static::getContainer()->get(MeltingTemperatureInterface::class)->calculateNearestNeighborTm($oligo->getSequence(), 500.0, 50.0, 1.5);
        $tm = static::getContainer()->get(OligoTm::class)->forOligo($oligo);

        self::assertNotNull($tm);
        self::assertSame($direct->getTm(), $tm['tm']);
        self::assertSame(0.5, $tm['oligoUm']);
        self::assertSame(50.0, $tm['monovalentMm']);
        self::assertSame(1.5, $tm['magnesiumMm']);
        self::assertIsFloat($tm['tm']);
        self::assertNull(static::getContainer()->get(OligoTm::class)->forOligo($this->oligo('ATGNCCAAATAAGCTAGC')));
    }

    /**
     * Tests the blocking rules of a PCR: both oligos, exactly one template.
     *
     * @return void
     */
    public function testPcrNeedsBothOligosAndExactlyOneTemplate(): void
    {
        $pcr = (new Pcr())->setName('P1');
        $errors = $this->errors($pcr);
        self::assertContains('forwardOligo:pcr.forward.required', $errors);
        self::assertContains('reverseOligo:pcr.reverse.required', $errors);
        self::assertContains('templateGene:pcr.template.exactly_one', $errors);

        $pcr->setForwardOligo($this->oligo())->setReverseOligo($this->oligo())->setTemplateGene($this->gene())->setTemplateVector($this->vector());
        self::assertContains('templateGene:pcr.template.exactly_one', $this->errors($pcr), 'gene and vector together');

        $pcr->setTemplateVector(null);
        self::assertSame([], $this->errors($pcr));
    }

    /**
     * Tests that the program of a PCR refuses non-positive values and that an unusual volume is only a warning.
     *
     * @return void
     */
    public function testPcrProgramValuesArePositiveAndUnusualVolumeIsAWarning(): void
    {
        $pcr = $this->pcr();
        $pcr->setCycles(0)->setAnnealingTempC(-5.0);
        $paths = array_map(static fn (string $e): string => explode(':', $e)[0], $this->errors($pcr));
        self::assertContains('cycles', $paths);
        self::assertContains('annealingTempC', $paths);

        $pcr->setCycles(30)->setAnnealingTempC(55.5)->setReactionVolumeUl(5.0);
        self::assertSame([], $this->errors($pcr));
        self::assertContains('cloning.warning.pcr_volume', $this->warningKeys($pcr));
    }

    /**
     * Tests the warnings of a PCR: same oligo twice, Tm gap above 5 degrees between the oligos.
     *
     * @return void
     */
    public function testPcrWarnsOnSameOligoAndOnTmGap(): void
    {
        $same = $this->oligo();
        $pcr = $this->pcr()->setForwardOligo($same)->setReverseOligo($same);
        self::assertContains('cloning.warning.pcr_same_oligo', $this->warningKeys($pcr));

        $pcr->setForwardOligo($this->oligo('GCGCGCGCGCGCGCGCGCGCGCGC'))->setReverseOligo($this->oligo('ATATATATATATATATATATATAT'));
        self::assertContains('cloning.warning.tm_difference', $this->warningKeys($pcr));
    }

    /**
     * Tests the rules of an insert: a PCR fragment needs its PCR, unknown enzymes are refused, a directional insert needs two enzymes.
     *
     * @return void
     */
    public function testInsertRules(): void
    {
        $insert = (new Insert())->setName('I1')->setType(InsertType::PcrFragment);
        self::assertContains('pcr:insert.pcr.required', $this->errors($insert));

        $insert->setPcr($this->pcr())->setFivePrimeSite('NotAnEnzyme');
        self::assertContains('fivePrimeSite:cloning.enzyme.unknown', $this->errors($insert));

        $insert->setFivePrimeSite('ncoi')->setThreePrimeSite('NCOI');
        self::assertContains('threePrimeSite:insert.sites.same_enzyme', $this->errors($insert));

        $insert->setDirectional(false);
        self::assertSame([], $this->errors($insert), 'case is ignored by the catalogue; undirected cloning may use one enzyme');
    }

    /**
     * Tests that the catalogue spelling of the enzymes is applied before saving.
     *
     * @return void
     */
    public function testEnzymeNamesAreStoredWithTheCatalogueSpelling(): void
    {
        $insert = $this->insert('ncoi', 'noti');
        static::getContainer()->get(CloningPreparation::class)->prepare($insert);

        self::assertSame('NcoI', $insert->getFivePrimeSite(), 'the alias typed by the person is kept, with the catalogue case');
        self::assertSame('NotI', $insert->getThreePrimeSite());
    }

    /**
     * Tests that a vector with a one-cut site of each enzyme gives a "designed" construct with no violation.
     *
     * @return void
     */
    public function testConstructWithSingleCutsIsDesigned(): void
    {
        $construct = $this->construct($this->vector($this->vectorSequence()), $this->insert());

        self::assertSame([], $this->errors($construct));
        self::assertSame(ConstructStatus::Designed, static::getContainer()->get(CloningChecks::class)->status($construct));
    }

    /**
     * Tests that each enzyme is evaluated on its own: a second NcoI site is reported even though NotI is fine.
     *
     * @return void
     */
    public function testEachEnzymeIsEvaluatedIndependently(): void
    {
        $twoNcoI = $this->vectorSequence().'CCATGG'.str_repeat('AT', 5);
        $construct = $this->construct($this->vector($twoNcoI), $this->insert('NcoI', 'NotI'));

        self::assertContains('vector:construct.site.cuts', $this->errors($construct));
        $problems = static::getContainer()->get(CloningChecks::class)->siteProblems($construct);
        self::assertSame([['enzyme' => 'NcoI', 'count' => 2]], $problems);
        self::assertSame(ConstructStatus::NotEvaluable, static::getContainer()->get(CloningChecks::class)->status($construct));

        $noNotI = $this->construct($this->vector(str_repeat('AT', 80).'CCATGG'.str_repeat('AT', 40)), $this->insert('NcoI', 'NotI'));
        self::assertSame([['enzyme' => 'NotI', 'count' => 0]], static::getContainer()->get(CloningChecks::class)->siteProblems($noNotI), 'the second enzyme is checked even though the first is fine');
    }

    /**
     * Tests that a site straddling the origin of the circular vector is found by biophp (one cut, not zero).
     *
     * @return void
     */
    public function testSiteStraddlingTheOriginIsFound(): void
    {
        $vector = $this->vector($this->originSpanningSequence());

        self::assertSame(1, static::getContainer()->get(VectorSiteCounter::class)->countCuts($vector, 'NcoI'));
        self::assertSame(1, static::getContainer()->get(VectorSiteCounter::class)->countCuts($vector, 'NotI'));
        self::assertNull(static::getContainer()->get(VectorSiteCounter::class)->countCuts($vector, 'NotAnEnzyme'));
        self::assertNull(static::getContainer()->get(VectorSiteCounter::class)->countCuts($this->vector(), 'NcoI'));
    }

    /**
     * Tests that a construct on a vector without sequence is saved but "not evaluable", with a warning.
     *
     * @return void
     */
    public function testConstructOnAVectorWithoutSequenceIsNotEvaluable(): void
    {
        $construct = $this->construct($this->vector(), $this->insert());

        self::assertSame([], $this->errors($construct), 'nothing can be refused without a sequence');
        self::assertSame(ConstructStatus::NotEvaluable, static::getContainer()->get(CloningChecks::class)->status($construct));
        self::assertContains('cloning.warning.vector_no_sequence', array_column(static::getContainer()->get(CloningChecks::class)->for($construct), 'key'));
    }

    /**
     * Tests that a construct is not "designed" when its insert has no site to check.
     *
     * @return void
     */
    public function testConstructWithoutInsertSitesIsNotEvaluable(): void
    {
        $construct = $this->construct($this->vector($this->vectorSequence()), $this->insert(null, null));

        self::assertSame(ConstructStatus::NotEvaluable, static::getContainer()->get(CloningChecks::class)->status($construct));
    }

    /**
     * Tests the warning when the product of the insert is not linked to the construct.
     *
     * @return void
     */
    public function testConstructWarnsWhenNoProductIsLinked(): void
    {
        $construct = $this->construct($this->vector($this->vectorSequence()), $this->insert());

        self::assertContains('cloning.warning.product_not_linked', array_column(static::getContainer()->get(CloningChecks::class)->for($construct), 'key'));
    }

    /**
     * Tests that an object in quarantine cannot become the parent of a PCR, an insert or a construct.
     *
     * @return void
     */
    public function testQuarantinedObjectsCannotBeParents(): void
    {
        $oligo = $this->oligo()->placeInQuarantine('legacy value doubtful', 'raw value');
        $pcr = (new Pcr())->setName('PQ')->setForwardOligo($oligo)->setReverseOligo($this->oligo())->setTemplateVector($this->vector());
        self::assertContains('forwardOligo:cloning.parent.quarantined', $this->errors($pcr));

        $quarantinedPcr = $this->pcr()->placeInQuarantine('doubtful');
        $insert = (new Insert())->setName('IQ')->setType(InsertType::PcrFragment)->setPcr($quarantinedPcr);
        self::assertContains('pcr:cloning.parent.quarantined', $this->errors($insert));

        $vector = $this->vector($this->vectorSequence())->placeInQuarantine('doubtful');
        self::assertContains('vector:cloning.parent.quarantined', $this->errors($this->construct($vector, $this->insert())));

        self::assertTrue($oligo->isQuarantined());
        self::assertSame('raw value', $oligo->getQuarantineRawValue());
        $oligo->releaseFromQuarantine();
        self::assertFalse($oligo->isQuarantined());
        self::assertSame('raw value', $oligo->getQuarantineRawValue(), 'the record of what was set aside stays');
    }

    /**
     * Tests the vector rules: the sequence alphabet, and the three tag states agree with the listed tags.
     *
     * @return void
     */
    public function testVectorTagStatesAndSequenceAlphabet(): void
    {
        $vector = (new Vector())->setName('V1')->setSequenceInput('ATGC1');
        self::assertContains('sequenceInput:vector.sequence.invalid_symbol', $this->errors($vector));

        $vector->setSequenceInput(null);
        self::assertFalse($vector->hasSequence());
        self::assertNull($vector->getSequenceRaw());
        self::assertSame(TagCapabilityState::Unknown, $vector->getTagState(), 'a new vector has unknown tags, not none');
        self::assertSame([], $this->errors($vector));

        $vector->setTagState(TagCapabilityState::Listed);
        self::assertContains('tagState:vector.tags.listed_without_tag', $this->errors($vector));

        $tag = (new Tag())->setName('6xHis')->setPurpose(TagPurpose::Purification);
        $this->em->persist($tag);
        $vector->setNTerminalTag($tag)->setCTerminalTag($tag);
        self::assertSame([], $this->errors($vector));
        self::assertCount(2, $vector->getTagCapabilities());

        $vector->setTagState(TagCapabilityState::None);
        self::assertContains('tagState:vector.tags.tag_without_listed', $this->errors($vector));

        $vector->setNTerminalTag(null);
        self::assertCount(1, $vector->getTagCapabilities());
    }

    /**
     * Tests that a vector sequence is stored raw and normalised and that the circular sequence is the biophp one.
     *
     * @return void
     */
    public function testVectorSequenceIsStoredRawAndNormalised(): void
    {
        $vector = $this->vector("atgc\nGGcc");

        self::assertSame("atgc\nGGcc", $vector->getSequenceRaw());
        self::assertSame('ATGCGGCC', $vector->getSequence());
        self::assertSame(8, $vector->getSequenceLength());
    }
}
