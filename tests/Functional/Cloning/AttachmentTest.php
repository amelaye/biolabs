<?php
/**
 * Functional tests of the files attached to vectors
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Cloning;

use App\Cloning\Entity\Attachment;
use App\Cloning\Entity\Vector;
use App\Cloning\Service\AttachmentStorage;
use App\Tests\Support\CloningFixtures;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Synthetic files only: a minimal PDF skeleton built in the tests.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class AttachmentTest extends DatabaseWebTestCase
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
     * A minimal PDF skeleton, optionally with extra raw content.
     *
     * @param string $extra Raw bytes added in the body of the file
     * @return string The file content
     */
    private function pdf(string $extra = ''): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n".$extra."\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /**
     * Writes content to a temporary file and wraps it as an upload.
     *
     * @param string $content File content
     * @param string $clientName Name sent by the browser
     * @return UploadedFile The upload, in test mode
     */
    private function upload(string $content, string $clientName = 'pET-map.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
    }

    /**
     * Posts an upload to a vector page, with the CSRF token of that page.
     *
     * @param Vector $vector Target vector
     * @param UploadedFile $file Upload
     * @return void
     */
    private function post(Vector $vector, UploadedFile $file): void
    {
        $crawler = $this->client->request('GET', '/cloning/vectors/'.$vector->getId());
        $token = $crawler->filter('.upload-form input[name=_token]')->attr('value');
        $this->client->request('POST', '/cloning/vectors/'.$vector->getId().'/attachments', ['_token' => $token], ['file' => $file]);
    }

    /**
     * Tests that a PDF is stored outside public/ under a random name, with its metadata, and listed on the page.
     *
     * @return void
     */
    public function testPdfIsStoredOutsidePublicUnderARandomName(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $vector = $this->vector();
        $content = $this->pdf();

        $this->post($vector, $this->upload($content, '../../etc/pET map<1>.PDF'));
        self::assertResponseRedirects('/cloning/vectors/'.$vector->getId());

        $attachment = $this->em->getRepository(Attachment::class)->findOneBy(['vector' => $vector]);
        self::assertNotNull($attachment);
        self::assertSame('application/pdf', $attachment->getMimeType());
        self::assertSame(\strlen($content), $attachment->getSizeBytes());
        self::assertSame(hash('sha256', $content), $attachment->getSha256());
        self::assertSame($user->getId(), $attachment->getUploadedBy()?->getId());
        self::assertSame('pET map1.pdf', $attachment->getOriginalName());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $attachment->getStorageId());

        $path = static::getContainer()->get(AttachmentStorage::class)->path($attachment);
        self::assertFileExists($path);
        self::assertStringNotContainsString('/public/', $path);
        self::assertStringNotContainsString('pET', basename($path));
        self::assertSame($content, file_get_contents($path));

        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'pET map1.pdf');
    }

    /**
     * Tests that the download is an attachment with nosniff, for a member only, and that a missing file is flagged then answers 404.
     *
     * @return void
     */
    public function testDownloadIsAnAuthenticatedAttachmentAndAMissingFileIsFlagged(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector();
        $this->post($vector, $this->upload($this->pdf()));
        $attachment = $this->em->getRepository(Attachment::class)->findOneBy(['vector' => $vector]);

        $this->client->request('GET', '/cloning/attachments/'.$attachment->getId());
        self::assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        self::assertSame('application/pdf', $headers->get('Content-Type'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertStringStartsWith('attachment;', (string) $headers->get('Content-Disposition'));
        self::assertStringContainsString("sandbox", (string) $headers->get('Content-Security-Policy'));

        unlink(static::getContainer()->get(AttachmentStorage::class)->path($attachment));
        $this->client->request('GET', '/cloning/vectors/'.$vector->getId());
        self::assertSelectorTextContains('main', 'fichier manquant');
        $this->client->request('GET', '/cloning/attachments/'.$attachment->getId());
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Tests that an anonymous visitor cannot download a file.
     *
     * @return void
     */
    public function testAnonymousVisitorCannotDownload(): void
    {
        $vector = $this->vector();
        $attachment = new Attachment($vector, '11111111-1111-4111-8111-111111111111', 'x.pdf', 'application/pdf', 10, str_repeat('a', 64), null, new \DateTimeImmutable());
        $this->em->persist($attachment);
        $this->em->flush();

        $this->client->request('GET', '/cloning/attachments/'.$attachment->getId());

        self::assertResponseRedirects('/login');
    }

    /**
     * Tests that a refused file is not stored and not recorded: wrong content, wrong size, encrypted, scripts, launch actions.
     *
     * @return void
     */
    public function testRefusedFilesAreNotStored(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector();

        $cases = [
            'text renamed .pdf' => $this->upload("hello, I am not a PDF\n", 'fake.pdf'),
            'image renamed .pdf' => $this->upload("\x89PNG\r\n\x1a\n".str_repeat("\0", 64), 'fake.pdf'),
            'too large' => $this->upload($this->pdf(str_repeat('x', AttachmentStorage::MAX_SIZE_BYTES)), 'big.pdf'),
            'empty' => $this->upload('', 'empty.pdf'),
            'encrypted' => $this->upload($this->pdf('<< /Encrypt 5 0 R >>'), 'enc.pdf'),
            'javascript' => $this->upload($this->pdf('<< /S /JavaScript /JS (app.alert\(1\)) >>'), 'js.pdf'),
            'short JS key' => $this->upload($this->pdf('<< /JS 7 0 R >>'), 'js2.pdf'),
            'launch action' => $this->upload($this->pdf('<< /S /Launch /F (calc.exe) >>'), 'launch.pdf'),
        ];
        foreach ($cases as $label => $file) {
            $this->post($vector, $file);
            self::assertResponseRedirects('/cloning/vectors/'.$vector->getId(), null, $label);
            $this->client->followRedirect();
            self::assertSelectorExists('.notice-error', $label);
        }

        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
        self::assertSame([], glob($this->attachmentsDir().'/*') ?: []);
    }

    /**
     * Tests that the type is detected from the content: a PDF sent as .txt with a wrong declared type is accepted and named .pdf.
     *
     * @return void
     */
    public function testTypeComesFromTheContentNotFromTheName(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector();

        $this->post($vector, $this->upload($this->pdf(), 'datasheet.txt'));

        $attachment = $this->em->getRepository(Attachment::class)->findOneBy(['vector' => $vector]);
        self::assertNotNull($attachment);
        self::assertSame('datasheet.txt.pdf', $attachment->getOriginalName());
    }

    /**
     * Tests that a missing or invalid CSRF token is refused and nothing is stored.
     *
     * @return void
     */
    public function testUploadNeedsTheCsrfToken(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector();

        $this->client->request('POST', '/cloning/vectors/'.$vector->getId().'/attachments', ['_token' => 'wrong'], ['file' => $this->upload($this->pdf())]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->em->getRepository(Attachment::class)->findAll());
    }

    /**
     * Tests that the original file name is cleaned of paths, control characters and markup.
     *
     * @return void
     */
    public function testOriginalNameIsCleaned(): void
    {
        self::assertSame('map.pdf', AttachmentStorage::cleanName('C:\\Users\\me\\map.pdf'));
        self::assertSame('abc.pdf', AttachmentStorage::cleanName("a\x00b\nc"));
        self::assertSame('script.pdf', AttachmentStorage::cleanName('<script>alert(1)</script>'), 'basename first, then markup characters removed');
        self::assertSame('attachment.pdf', AttachmentStorage::cleanName('...'));
        self::assertSame('pET-28a (+) map.pdf', AttachmentStorage::cleanName('pET-28a (+)   map.PDF'));
        self::assertSame(104, mb_strlen(AttachmentStorage::cleanName(str_repeat('é', 300).'.pdf')));
    }
}
