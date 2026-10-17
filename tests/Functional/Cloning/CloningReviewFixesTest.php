<?php
/**
 * Tests of the fixes that followed the LAB-3 reviews
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 18 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Cloning;

use App\Cloning\Entity\Attachment;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Vector;
use App\Cloning\Entity\VectorTagCapability;
use App\Cloning\Enum\ConstructStatus;
use App\Cloning\Enum\InsertType;
use App\Cloning\Enum\TagCapabilityState;
use App\Cloning\Service\AttachmentRejectedException;
use App\Cloning\Service\AttachmentStorage;
use App\Cloning\Service\CloningChecks;
use App\Cloning\Service\LockedOligoException;
use App\Cloning\Service\OligoTm;
use App\Cloning\Service\VectorSiteCounter;
use App\Reference\Entity\Tag;
use App\Reference\Enum\TagPurpose;
use App\Reference\Service\ReferenceWarnings;
use App\Shared\Audit\FieldHistory;
use App\Tests\Support\CloningFixtures;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Synthetic data only. One test per review finding.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CloningReviewFixesTest extends DatabaseWebTestCase
{
    use CloningFixtures;

    /**
     * Removes the files stored by the test, then the test directory itself.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $directory = $this->attachmentsDir();
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
        parent::tearDown();
    }

    /**
     * Violations of an entity as 'path:message key'.
     *
     * @param object $entity Entity to validate
     * @return list<string> The violations
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
     * A minimal PDF skeleton.
     *
     * @param string $extra Raw bytes added in the body
     * @return string The file content
     */
    private function pdf(string $extra = ''): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n".$extra."\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /**
     * Stores a PDF through the storage service.
     *
     * @param string $content File content
     * @param Vector $vector Target vector
     * @return Attachment The stored attachment
     */
    private function store(string $content, Vector $vector): Attachment
    {
        $path = tempnam(sys_get_temp_dir(), 'bl');
        file_put_contents($path, $content);

        return static::getContainer()->get(AttachmentStorage::class)->store(new UploadedFile($path, 'map.pdf', null, null, true), $vector, null);
    }

    /**
     * Reason of the rejection of a file, null when it is accepted.
     *
     * @param string $content File content
     * @param Vector $vector Target vector
     * @return string|null The translation key of the reason
     */
    private function rejection(string $content, Vector $vector): ?string
    {
        try {
            $this->store($content, $vector);
        } catch (AttachmentRejectedException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Tests that the two ends of a directional insert must be different enzymes by canonical name: isoschizomers and case variants are refused, stored names are kept.
     *
     * @return void
     */
    public function testIsoschizomersCountAsTheSameEnzymeInDirectionalCloning(): void
    {
        $catalog = static::getContainer()->get(VectorSiteCounter::class);
        self::assertSame($catalog->canonicalName('NcoI'), $catalog->canonicalName('Bsp19I'), 'fixture guard: NcoI and Bsp19I are the same enzyme in the catalogue');

        foreach ([['NcoI', 'Bsp19I'], ['NcoI', 'ncoi'], ['Bsp19I', 'NcoI']] as [$five, $three]) {
            $insert = (new Insert())->setName('INS-'.$five.$three)->setType(InsertType::RestrictionFragment)->setFivePrimeSite($five)->setThreePrimeSite($three);
            self::assertContains('threePrimeSite:insert.sites.same_enzyme', $this->errors($insert), $five.'/'.$three);
        }
        $other = (new Insert())->setName('INS-OK')->setType(InsertType::RestrictionFragment)->setFivePrimeSite('NcoI')->setThreePrimeSite('NotI');
        self::assertNotContains('threePrimeSite:insert.sites.same_enzyme', $this->errors($other));
        $single = (new Insert())->setName('INS-ONE')->setType(InsertType::RestrictionFragment)->setFivePrimeSite('NcoI')->setThreePrimeSite('Bsp19I')->setDirectional(false);
        self::assertNotContains('threePrimeSite:insert.sites.same_enzyme', $this->errors($single), 'non-directional: the same enzyme twice is allowed');
        self::assertSame('Bsp19I', $single->getThreePrimeSite(), 'the stored name is never replaced by the canonical one');
    }

    /**
     * Tests that isoschizomers are evaluated once: a single NcoI site is one cut, not two, and the construct is designed.
     *
     * @return void
     */
    public function testIsoschizomersAreCountedOnceInTheCutCheck(): void
    {
        $insert = $this->insert('NcoI', 'Bsp19I')->setDirectional(false);
        $construct = $this->construct($this->vector($this->vectorSequence()), $insert);
        $checks = static::getContainer()->get(CloningChecks::class);

        self::assertSame(['NcoI'], $checks->distinctSites($insert));
        self::assertSame([], $checks->siteProblems($construct));
        self::assertSame(ConstructStatus::Designed, $checks->status($construct));
    }

    /**
     * Tests that ambiguous bases in a vector give a non-blocking warning on the vector and its constructs.
     *
     * @return void
     */
    public function testAmbiguousBasesInAVectorRaiseAWarningWithoutBlocking(): void
    {
        $vector = $this->vector($this->vectorSequence().'NNNX');
        $construct = $this->construct($vector, $this->insert());

        self::assertSame([], $this->errors($vector), 'not blocking');
        self::assertContains('cloning.warning.vector_ambiguous', $this->warningKeys($vector));
        self::assertContains('cloning.warning.vector_ambiguous', $this->warningKeys($construct));
        self::assertNotContains('cloning.warning.vector_ambiguous', $this->warningKeys($this->vector($this->vectorSequence())));
    }

    /**
     * Tests that a change of case alone on an oligo is recorded in the history (the lowercase marks non-hybridising bases).
     *
     * @return void
     */
    public function testRawCaseChangeIsTrackedForOligoAndVector(): void
    {
        $oligo = $this->oligo('GTCccatggATGGCC');
        $oligo->setSequenceInput('GTCCCATGGATGGCC');
        $vector = $this->vector('ATATATCCATGGATAT');
        $vector->setSequenceInput('atatatccatggatat');
        $this->em->flush();

        foreach ([[Oligo::class, $oligo->getId()], [Vector::class, $vector->getId()]] as [$class, $id]) {
            $rows = $this->em->getRepository(FieldHistory::class)->findBy(['entityType' => $class, 'entityId' => $id]);
            self::assertSame(['sequenceRaw'], array_map(static fn (FieldHistory $r): string => $r->getField(), $rows), $class.': the raw text changed, the normalised sequence did not');
        }
    }

    /**
     * Tests that the oligo lock also applies outside the form: a flush that changes the sequence of an oligo used by a PCR is refused,
     * with a message that does not contain the sequence; a case-only change is allowed.
     *
     * @return void
     */
    public function testOligoLockIsEnforcedOnAnyWritePath(): void
    {
        $used = $this->pcr()->getForwardOligo();
        $used->setSequenceInput(strtolower($used->getSequenceRaw()));
        $this->em->flush();
        self::assertSame($used->getSequence(), strtoupper($used->getSequenceRaw()));

        $used->setSequenceInput('GGGGGGGGGGGG');
        try {
            $this->em->flush();
            self::fail('expected a LockedOligoException');
        } catch (LockedOligoException $e) {
            self::assertStringNotContainsString('GGGGGG', $e->getMessage());
        }
    }

    /**
     * Tests the reason why a Tm cannot be computed: fewer than two bases, degenerate base, X.
     *
     * @return void
     */
    public function testTmUnavailableReasons(): void
    {
        $tm = static::getContainer()->get(OligoTm::class);

        self::assertSame(OligoTm::REASON_DEGENERATE, $tm->unavailableReason($this->oligo('ATGNCCAAATAAGCTAGC')));
        self::assertSame(OligoTm::REASON_UNKNOWN_BASE, $tm->unavailableReason($this->oligo('ATGXCCAAATAAGCTAGC')));
        self::assertSame(OligoTm::REASON_LENGTH, $tm->unavailableReason($this->oligo('A')));
        self::assertNull($tm->unavailableReason($this->oligo('ATGGCCAAATAAGCTAGC')));
    }

    /**
     * Tests an oligo of one nucleotide: no Tm, no exception, a length warning.
     *
     * @return void
     */
    public function testOneNucleotideOligoHasNoTmAndALengthWarning(): void
    {
        $oligo = $this->oligo('A');

        self::assertNull(static::getContainer()->get(OligoTm::class)->forOligo($oligo));
        self::assertContains('cloning.warning.oligo_length', $this->warningKeys($oligo));
        self::assertNotContains('cloning.warning.tm_window', $this->warningKeys($oligo));
    }

    /**
     * Tests that the Tm does not depend on the case typed.
     *
     * @return void
     */
    public function testTmDoesNotDependOnCase(): void
    {
        $tm = static::getContainer()->get(OligoTm::class);
        $upper = $tm->forOligo($this->oligo('GTCCCATGGATGGCCAAATAAGC'));
        $mixed = $tm->forOligo($this->oligo('gtcCCATGGatggccAAATAAGC'));

        self::assertNotNull($upper);
        self::assertSame($upper['tm'], $mixed['tm']);
    }

    /**
     * Tests a non-palindromic (type IIS) site on the reverse strand that straddles the origin: BsaI (GGTCTC) read as GAGACC across the end and the start.
     *
     * @return void
     */
    public function testNonPalindromicSiteOnTheReverseStrandAcrossTheOrigin(): void
    {
        $counter = static::getContainer()->get(VectorSiteCounter::class);
        $reverse = $this->vector('ACC'.str_repeat('AT', 60).'GAG', 'V-REV');
        $forward = $this->vector('CTC'.str_repeat('AT', 60).'GGT', 'V-FWD');
        $none = $this->vector(str_repeat('AT', 60), 'V-NONE');

        self::assertSame(1, $counter->countCuts($forward, 'BsaI'), 'forward strand, across the origin');
        self::assertSame(1, $counter->countCuts($reverse, 'BsaI'), 'reverse strand, across the origin');
        self::assertSame(0, $counter->countCuts($none, 'BsaI'));
    }

    /**
     * Tests that PDF name escapes (#xx) cannot hide a keyword, and that the extra active-content keywords are refused.
     *
     * @return void
     */
    public function testPdfEscapedNamesAndExtraActiveKeywordsAreRefused(): void
    {
        $vector = $this->vector();
        $cases = [
            'attachment.error.active_content' => [
                '/J#53 as JS' => '<< /J#53 7 0 R >>',
                '/#4AavaScript' => '<< /S /#4AavaScript >>',
                '/OpenAction' => '<< /OpenAction 3 0 R >>',
                '/AA' => '<< /AA << /O 4 0 R >> >>',
                '/EmbeddedFile' => '<< /Type /EmbeddedFile >>',
                '/EmbeddedFiles' => '<< /EmbeddedFiles 9 0 R >>',
                '/RichMedia' => '<< /Subtype /RichMedia >>',
                '/GoToR' => '<< /S /GoToR >>',
                '/SubmitForm' => '<< /S /SubmitForm >>',
            ],
            'attachment.error.encrypted' => ['/Encr#79pt' => '<< /Encr#79pt 5 0 R >>'],
        ];
        foreach ($cases as $reason => $variants) {
            foreach ($variants as $label => $extra) {
                self::assertSame($reason, $this->rejection($this->pdf($extra), $vector), $label);
            }
        }
        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
        self::assertNull($this->rejection($this->pdf('<< /AAPL 1 /Type /Page >>'), $vector), 'a longer key that merely starts with AA is harmless');
    }

    /**
     * Tests that the stored file is readable by its owner and group only (0640).
     *
     * @return void
     */
    public function testStoredFileHasRestrictedPermissions(): void
    {
        $attachment = $this->store($this->pdf(), $this->vector());
        $path = static::getContainer()->get(AttachmentStorage::class)->path($attachment);

        self::assertSame(0640, fileperms($path) & 0777);
    }

    /**
     * Tests that nothing can be attached to an archived vector or one in quarantine.
     *
     * @return void
     */
    public function testNoUploadOnArchivedOrQuarantinedVector(): void
    {
        $archived = $this->vector(null, 'V-ARCHIVED')->archive();
        $quarantined = $this->vector(null, 'V-QUARANTINE')->placeInQuarantine('doubtful');
        $this->em->flush();

        self::assertSame('attachment.error.vector_unavailable', $this->rejection($this->pdf(), $archived));
        self::assertSame('attachment.error.vector_unavailable', $this->rejection($this->pdf(), $quarantined));
        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
    }

    /**
     * Tests the ceiling of attachments per vector.
     *
     * @return void
     */
    public function testAttachmentsPerVectorAreLimited(): void
    {
        $vector = $this->vector();
        for ($i = 0; $i < AttachmentStorage::MAX_PER_VECTOR; ++$i) {
            $this->store($this->pdf('% file '.$i), $vector);
        }

        self::assertSame('attachment.error.too_many', $this->rejection($this->pdf('% one more'), $vector));
        self::assertCount(AttachmentStorage::MAX_PER_VECTOR, $this->em->getRepository(Attachment::class)->findBy(['vector' => $vector]));
    }

    /**
     * Tests that the same content is attached once per vector (but may be attached to another vector), and leaves no orphan file.
     *
     * @return void
     */
    public function testSameContentIsAttachedOncePerVector(): void
    {
        $first = $this->vector();
        $second = $this->vector();
        $this->store($this->pdf('% same'), $first);

        self::assertSame('attachment.error.duplicate', $this->rejection($this->pdf('% same'), $first));
        self::assertNull($this->rejection($this->pdf('% same'), $second));
        self::assertCount(2, glob($this->attachmentsDir().'/*') ?: []);
    }

    /**
     * Tests the unique constraint on the tag position of a vector, and that changing the tag of an end updates the row in place.
     *
     * @return void
     */
    public function testOneTagPerEndOfAVector(): void
    {
        $his = (new Tag())->setName('6xHis')->setPurpose(TagPurpose::Purification);
        $gst = (new Tag())->setName('GST')->setPurpose(TagPurpose::Purification);
        $this->em->persist($his);
        $this->em->persist($gst);
        $vector = $this->vector(null, 'V-TAGS')->setTagState(TagCapabilityState::Listed)->setNTerminalTag($his);
        $this->em->flush();

        $vector->setNTerminalTag($gst);
        $this->em->flush();
        $rows = $this->em->getRepository(VectorTagCapability::class)->findBy(['vector' => $vector]);
        self::assertCount(1, $rows);
        self::assertSame('GST', $rows[0]->getTag()->getName());

        $this->em->persist(new VectorTagCapability($vector, $his, $rows[0]->getPosition()));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }
}
