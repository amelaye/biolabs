<?php
/**
 * Edge cases of the cloning rules: IUPAC, oligo length bounds, Tm window, origin-spanning sites, quarantine, attachments limits
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Cloning;

use App\Cloning\Entity\Attachment;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Enum\ConstructStatus;
use App\Cloning\Service\AttachmentStorage;
use App\Cloning\Service\CloningChecks;
use App\Cloning\Service\OligoTm;
use App\Reference\Service\ReferenceWarnings;
use App\Tests\Support\CloningFixtures;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Synthetic sequences and files only.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CloningEdgeCasesTest extends DatabaseWebTestCase
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
     * Oligo sequences accepted by the alphabet rule (IUPAC, any case, spaces and newlines).
     *
     * @return iterable<string, array{string, string}> Typed sequence and expected normalised sequence
     */
    public static function acceptedOligoSequences(): iterable
    {
        yield 'all IUPAC ambiguity codes' => ['ACGTRYSWKMBDHVN', 'ACGTRYSWKMBDHVN'];
        yield 'lowercase IUPAC' => ['acgtrywsn', 'ACGTRYWSN'];
        yield 'mixed case clamp and site' => ['gtcCCATGGgc', 'GTCCCATGGGC'];
        yield 'tabs, spaces and CRLF' => ["AT G\tCC\r\nAA", 'ATGCCAA'];
    }

    /**
     * Tests that IUPAC and lowercase sequences are accepted, kept raw and normalised in uppercase.
     *
     * @param string $typed Sequence as typed
     * @param string $expected Expected normalised sequence
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('acceptedOligoSequences')]
    public function testAcceptedOligoSequences(string $typed, string $expected): void
    {
        $oligo = (new Oligo())->setName('IUPAC-OK')->setSequenceInput($typed);

        self::assertSame([], $this->errors($oligo));
        self::assertSame($typed, $oligo->getSequenceRaw(), 'the raw input is never altered');
        self::assertSame($expected, $oligo->getSequence());
    }

    /**
     * Oligo sequences refused by the alphabet rule.
     *
     * @return iterable<string, array{string}> Typed sequence
     */
    public static function refusedOligoSequences(): iterable
    {
        yield 'digit' => ['ATGC5CCA'];
        yield 'RNA uracil' => ['AUGCCAAAU'];
        yield 'amino acid letter E' => ['ATGEEAAT'];
        yield 'dash' => ['ATGC-CCA'];
        yield 'asterisk (stop)' => ['ATGCC*'];
        yield 'prime marks of 5-3 notation' => ["5'-ATGCCAAT-3'"];
    }

    /**
     * Tests that invalid symbols are reported, not silently removed, and that the message does not carry the sequence.
     *
     * @param string $typed Sequence as typed
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedOligoSequences')]
    public function testRefusedOligoSequencesAreReportedWithoutLeakingTheSequence(string $typed): void
    {
        $oligo = (new Oligo())->setName('BAD-ALPHA')->setSequenceInput($typed);
        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($oligo);

        self::assertContains('sequenceInput:oligo.sequence.invalid_symbol', $this->errors($oligo));
        foreach ($violations as $violation) {
            self::assertStringNotContainsString(trim($typed, "5'-3 "), (string) $violation->getMessage(), 'no sequence in an error message');
            foreach ($violation->getParameters() as $parameter) {
                self::assertStringNotContainsString($typed, (string) $parameter, 'no sequence in a message parameter');
            }
        }
    }

    /**
     * Length boundaries of the 15-100 nt usual range.
     *
     * @return iterable<string, array{int, bool}> Length and whether a warning is expected
     */
    public static function oligoLengths(): iterable
    {
        yield '1 nt' => [1, true];
        yield '14 nt, just below' => [14, true];
        yield '15 nt, lower bound' => [15, false];
        yield '30 nt, usual' => [30, false];
        yield '100 nt, upper bound' => [100, false];
        yield '101 nt, just above' => [101, true];
        yield '255 nt, the blocking maximum' => [255, true];
    }

    /**
     * Tests that a too short or too long oligo only gets a warning, never an error (up to 255 nt).
     *
     * @param int $length Oligo length in nt
     * @param bool $expectWarning Whether the length warning is expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('oligoLengths')]
    public function testOligoLengthBoundaries(int $length, bool $expectWarning): void
    {
        $oligo = $this->oligo(substr(str_repeat('ATGCGGCTA', 30), 0, $length));

        self::assertSame($length, $oligo->getLength());
        self::assertSame([], $this->errors($oligo), 'an unusual length is not blocking');
        self::assertSame($expectWarning, \in_array('cloning.warning.oligo_length', $this->warningKeys($oligo), true));
    }

    /**
     * Tests the Tm window warning: a low-Tm AT-rich oligo and a very high-Tm GC-rich one warn, a balanced one does not.
     *
     * @return void
     */
    public function testTmOutsideTheWindowIsAWarning(): void
    {
        $low = $this->oligo('ATATATTATAATATATTA');
        $high = $this->oligo('GCGCCGGCGCGGCCGCGCCGGCGC');
        $balanced = $this->oligo('ATGGCCAAATAAGCTAGCTAGGATCC');
        $tm = static::getContainer()->get(OligoTm::class);

        self::assertLessThan(50.0, $tm->forOligo($low)['tm'], 'fixture guard: AT-rich 18-mer must be below the window');
        self::assertGreaterThan(72.0, $tm->forOligo($high)['tm'], 'fixture guard: GC-rich 24-mer must be above the window');
        self::assertContains('cloning.warning.tm_window', $this->warningKeys($low));
        self::assertContains('cloning.warning.tm_window', $this->warningKeys($high));
        self::assertGreaterThanOrEqual(50.0, $tm->forOligo($balanced)['tm']);
        self::assertLessThanOrEqual(72.0, $tm->forOligo($balanced)['tm']);
        self::assertNotContains('cloning.warning.tm_window', $this->warningKeys($balanced));
    }

    /**
     * Tests that a degenerate oligo has no Tm and therefore no Tm warning, and that a PCR with it raises no Tm-gap warning.
     *
     * @return void
     */
    public function testDegenerateOligoGivesNoTmAndNoTmWarning(): void
    {
        $degenerate = $this->oligo('ATGNCCAAATAAGCTAGCTAG');

        self::assertNull(static::getContainer()->get(OligoTm::class)->forOligo($degenerate));
        self::assertNotContains('cloning.warning.tm_window', $this->warningKeys($degenerate));
        $pcr = $this->pcr()->setForwardOligo($degenerate)->setReverseOligo($this->oligo('ATATATATATATATATATATATAT'));
        self::assertNotContains('cloning.warning.tm_difference', $this->warningKeys($pcr));
    }

    /**
     * Tests that two oligos with a close Tm raise no gap warning.
     *
     * @return void
     */
    public function testSimilarTmGivesNoGapWarning(): void
    {
        $pcr = $this->pcr()->setForwardOligo($this->oligo('ATGGCCAAATAAGCTAGCTAGGATCC'))->setReverseOligo($this->oligo('ATGGCCAAATAAGCTAGCTAGGATCG'));

        self::assertNotContains('cloning.warning.tm_difference', $this->warningKeys($pcr));
    }

    /**
     * Tests that a construct on a vector whose only NcoI site straddles the origin is "designed" with no violation.
     *
     * @return void
     */
    public function testConstructOnAnOriginSpanningVectorIsDesigned(): void
    {
        $construct = $this->construct($this->vector($this->originSpanningSequence()), $this->insert());

        self::assertSame([], $this->errors($construct));
        self::assertSame(ConstructStatus::Designed, static::getContainer()->get(CloningChecks::class)->status($construct));
    }

    /**
     * Tests that a second NcoI site, added after the origin-spanning one, is counted (two cuts, not one).
     *
     * @return void
     */
    public function testOriginSpanningSitePlusAnotherIsTwoCuts(): void
    {
        $sequence = $this->originSpanningSequence();
        $twice = substr($sequence, 0, -3).'CCATGG'.str_repeat('AT', 5).'CCA';
        $construct = $this->construct($this->vector($twice), $this->insert());

        self::assertSame([['enzyme' => 'NcoI', 'count' => 2]], static::getContainer()->get(CloningChecks::class)->siteProblems($construct));
        self::assertContains('vector:construct.site.cuts', $this->errors($construct));
    }

    /**
     * Tests that an enzyme with no site in the vector is blocking (zero cut), and that the status is not "designed".
     *
     * @return void
     */
    public function testEnzymeWithNoSiteInTheVectorIsBlocking(): void
    {
        $construct = $this->construct($this->vector(str_repeat('AT', 80)), $this->insert());
        $problems = static::getContainer()->get(CloningChecks::class)->siteProblems($construct);

        self::assertSame([['enzyme' => 'NcoI', 'count' => 0], ['enzyme' => 'NotI', 'count' => 0]], $problems);
        self::assertContains('vector:construct.site.cuts', $this->errors($construct));
        self::assertSame(ConstructStatus::NotEvaluable, static::getContainer()->get(CloningChecks::class)->status($construct));
    }

    /**
     * Tests that a circular vector sequence is not treated as linear: a site split between the end and the start is not lost
     * even when the sequence is typed with lowercase and line breaks.
     *
     * @return void
     */
    public function testOriginSpanningSiteSurvivesRawFormatting(): void
    {
        $typed = strtolower(chunk_split($this->originSpanningSequence(), 20, "\n"));
        $construct = $this->construct($this->vector($typed), $this->insert());

        self::assertSame([], static::getContainer()->get(CloningChecks::class)->siteProblems($construct));
    }

    /**
     * Tests that an insert and a template vector in quarantine cannot be used as parents.
     *
     * @return void
     */
    public function testQuarantinedInsertAndTemplateVectorAreRefusedAsParents(): void
    {
        $insert = $this->insert()->placeInQuarantine('doubtful legacy value', 'raw');
        $construct = $this->construct($this->vector($this->vectorSequence()), $insert);
        self::assertContains('insert:cloning.parent.quarantined', $this->errors($construct));

        $template = $this->vector()->placeInQuarantine('doubtful');
        $pcr = (new Pcr())->setName('PCR-Q')->setForwardOligo($this->oligo())->setReverseOligo($this->oligo())->setTemplateVector($template);
        self::assertContains('templateVector:cloning.parent.quarantined', $this->errors($pcr));
    }

    /**
     * Tests that a quarantined parent is also refused when only the reverse oligo is quarantined.
     *
     * @return void
     */
    public function testQuarantinedReverseOligoIsRefused(): void
    {
        $pcr = (new Pcr())->setName('PCR-QR')->setForwardOligo($this->oligo())
            ->setReverseOligo($this->oligo()->placeInQuarantine('doubtful'))->setTemplateVector($this->vector());

        self::assertContains('reverseOligo:cloning.parent.quarantined', $this->errors($pcr));
    }

    /**
     * Tests that a quarantine reason longer than the column (255) is cut, so the flush cannot fail, and the raw value is kept whole.
     *
     * @return void
     */
    public function testQuarantineReasonIsCutTo255CharactersAndRawValueKept(): void
    {
        $oligo = $this->oligo()->placeInQuarantine(str_repeat('x', 300), 'raw value as received');
        $this->em->flush();

        self::assertSame(255, mb_strlen((string) $oligo->getQuarantineReason()));
        self::assertSame('raw value as received', $oligo->getQuarantineRawValue());
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
     * Wraps content as an upload in test mode.
     *
     * @param string $content File content
     * @param string $name Client file name
     * @return UploadedFile The upload
     */
    private function upload(string $content, string $name = 'map.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * Stores an upload through the storage service for the given vector.
     *
     * @param string $content File content
     * @return Attachment The stored attachment
     */
    private function store(string $content): Attachment
    {
        return static::getContainer()->get(AttachmentStorage::class)->store($this->upload($content), $this->vector(), null);
    }

    /**
     * Tests that PDF keywords that only start like the forbidden ones (/JSON, /LaunchedAt, /Encoding) are not refused.
     *
     * @return void
     */
    public function testHarmlessKeywordsStartingLikeForbiddenOnesAreAccepted(): void
    {
        $attachment = $this->store($this->pdf('<< /Subtype /JSON /Encoding /WinAnsi /LaunchedAt 3 >>'));

        self::assertSame('application/pdf', $attachment->getMimeType());
    }

    /**
     * Tests the size boundary: a file of exactly the maximum size is accepted, one byte more is refused.
     *
     * @return void
     */
    public function testSizeBoundary(): void
    {
        $skeleton = $this->pdf('');
        $exact = $this->pdf(str_repeat(' ', AttachmentStorage::MAX_SIZE_BYTES - \strlen($skeleton)));
        self::assertSame(AttachmentStorage::MAX_SIZE_BYTES, \strlen($exact), 'fixture guard');
        self::assertSame(AttachmentStorage::MAX_SIZE_BYTES, $this->store($exact)->getSizeBytes());

        $this->expectException(\App\Cloning\Service\AttachmentRejectedException::class);
        $this->expectExceptionMessage('attachment.error.size');
        $this->store($exact.' ');
    }

    /**
     * Tests the rejection reason of each refused file, by translation key, and that no row is left behind.
     *
     * @return void
     */
    public function testEachRefusalHasItsOwnReason(): void
    {
        $cases = [
            'attachment.error.type' => "plain text, not a PDF\n",
            'attachment.error.encrypted' => $this->pdf('<< /Encrypt 5 0 R >>'),
            'attachment.error.active_content' => $this->pdf('<< /S /JavaScript /JS (x) >>'),
        ];
        foreach ($cases as $reason => $content) {
            try {
                $this->store($content);
                self::fail('expected a rejection: '.$reason);
            } catch (\App\Cloning\Service\AttachmentRejectedException $e) {
                self::assertSame($reason, $e->getMessage());
            }
        }

        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
    }

    /**
     * Tests that a PDF magic number preceded by other bytes is not accepted as a PDF.
     *
     * @return void
     */
    public function testPdfSignatureMustBeAtTheStart(): void
    {
        $this->expectException(\App\Cloning\Service\AttachmentRejectedException::class);
        $this->expectExceptionMessage('attachment.error.type');
        $this->store("GIF89a junk\n".$this->pdf());
    }

    /**
     * Tests that an anonymous visitor cannot upload and that nothing is stored.
     *
     * @return void
     */
    public function testAnonymousVisitorCannotUpload(): void
    {
        $vector = $this->vector();

        $this->client->request('POST', '/cloning/vectors/'.$vector->getId().'/attachments', ['_token' => 'x'], ['file' => $this->upload($this->pdf())]);

        self::assertResponseRedirects('/login');
        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
    }

    /**
     * Tests that a POST without any file is answered with an error notice and stores nothing.
     *
     * @return void
     */
    public function testUploadWithoutFileShowsAnError(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector();
        $crawler = $this->client->request('GET', '/cloning/vectors/'.$vector->getId());
        $token = $crawler->filter('.upload-form input[name=_token]')->attr('value');

        $this->client->request('POST', '/cloning/vectors/'.$vector->getId().'/attachments', ['_token' => $token]);
        self::assertResponseRedirects('/cloning/vectors/'.$vector->getId());
        $this->client->followRedirect();

        self::assertSelectorExists('.notice-error');
        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
    }

    /**
     * Tests that a download of an unknown attachment is a 404 and an upload to an unknown vector is a 404.
     *
     * @return void
     */
    public function testUnknownAttachmentAndVectorAreNotFound(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/cloning/attachments/999999');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/cloning/vectors/999999/attachments', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Tests that the stored file name and the download name never carry the sequence-like or path-like original name.
     *
     * @return void
     */
    public function testDownloadNameIsTheCleanedOriginalName(): void
    {
        $this->client->loginUser($this->createUser());
        $attachment = $this->store($this->pdf());
        $this->em->refresh($attachment);

        $this->client->request('GET', '/cloning/attachments/'.$attachment->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('map.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringNotContainsString($attachment->getStorageId(), (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }
}
