<?php
/**
 * Storage of the files attached to vectors, outside public/
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Service;

use App\Account\Entity\User;
use App\Cloning\Entity\Attachment;
use App\Cloning\Entity\Vector;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Accepts PDF files only, on an active vector that is not in quarantine, ten per vector at most,
 * the same content once per vector. The type is detected from the content (never from the name or the
 * declared type), encrypted PDFs and PDFs with JavaScript or launch actions are refused, the file
 * is stored under a random identifier in a directory that is not served by the web server, and it
 * is only read back through an authenticated controller.
 *
 * The scan for active content looks for the PDF keywords in the raw bytes (after decoding the
 * #xx escapes of names; /JS, /JavaScript, /Launch, /OpenAction, /AA, /EmbeddedFile, /RichMedia,
 * /GoToR, /SubmitForm are refused): it stops the usual
 * cases, but a PDF that hides them in compressed object streams is not detected. That is why the
 * file is never served inline and always with a "nosniff" and sandbox policy.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class AttachmentStorage
{
    /** Largest accepted file, bytes (5 MB, to be reviewed after measuring the blobs of the legacy dump). */
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /** Most files attached to one vector. */
    public const MAX_PER_VECTOR = 10;

    /**
     * Name keywords that make a PDF active or dangerous (compared after the #xx escapes of PDF
     * names are decoded: "/J#53" is "/JS"). "/Encrypt" has its own message.
     */
    private const ACTIVE_KEYWORDS = ['JS', 'JavaScript', 'Launch', 'OpenAction', 'AA', 'EmbeddedFiles?', 'RichMedia', 'GoToR', 'SubmitForm'];

    /**
     * AttachmentStorage constructor.
     *
     * @param string $directory Directory of the stored files, outside public/
     * @param EntityManagerInterface $em Entity manager that stores the metadata
     * @param ClockInterface $clock Source of the current time
     */
    public function __construct(
        #[Autowire('%app.attachments_dir%')] private readonly string $directory,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Checks and stores an uploaded PDF, and records its metadata.
     *
     * @param UploadedFile $file Uploaded file
     * @param Vector $vector Vector the file is attached to (stored)
     * @param User|null $author Signed-in person
     * @return Attachment The stored attachment
     * @throws AttachmentRejectedException If the file is refused or cannot be stored (the message is a translation key)
     */
    public function store(UploadedFile $file, Vector $vector, ?User $author): Attachment
    {
        if ($vector->isQuarantined() || !$vector->isActive()) {
            throw new AttachmentRejectedException('attachment.error.vector_unavailable');
        }
        if ($this->countFor($vector) >= self::MAX_PER_VECTOR) {
            throw new AttachmentRejectedException('attachment.error.too_many');
        }
        if (!$file->isValid()) {
            throw new AttachmentRejectedException('attachment.error.upload');
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_SIZE_BYTES) {
            throw new AttachmentRejectedException('attachment.error.size');
        }
        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            throw new AttachmentRejectedException('attachment.error.upload');
        }
        if (Attachment::MIME_PDF !== (new \finfo(\FILEINFO_MIME_TYPE))->buffer($content) || !str_starts_with($content, '%PDF-')) {
            throw new AttachmentRejectedException('attachment.error.type');
        }
        $names = self::decodeNameEscapes($content);
        if (1 === preg_match('~/Encrypt(?![A-Za-z0-9])~', $names)) {
            throw new AttachmentRejectedException('attachment.error.encrypted');
        }
        if (1 === preg_match('~/(?:'.implode('|', self::ACTIVE_KEYWORDS).')(?![A-Za-z0-9])~', $names)) {
            throw new AttachmentRejectedException('attachment.error.active_content');
        }
        $sha256 = hash('sha256', $content);
        if ($this->em->getRepository(Attachment::class)->count(['vector' => $vector, 'sha256' => $sha256]) > 0) {
            throw new AttachmentRejectedException('attachment.error.duplicate');
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new AttachmentRejectedException('attachment.error.storage');
        }
        $storageId = self::uuid();
        $path = $this->directory.'/'.$storageId.'.pdf';
        if (false === file_put_contents($path, $content, \LOCK_EX) || !@chmod($path, 0640)) {
            @unlink($path);
            throw new AttachmentRejectedException('attachment.error.storage');
        }

        $attachment = new Attachment(
            $vector,
            $storageId,
            self::cleanName($file->getClientOriginalName()),
            Attachment::MIME_PDF,
            \strlen($content),
            $sha256,
            null !== $author?->getId() ? $this->em->getReference(User::class, $author->getId()) : null,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        try {
            $this->em->persist($attachment);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Same file attached concurrently: the unique (vector, sha256) constraint is the last line of defence.
            @unlink($path);
            throw new AttachmentRejectedException('attachment.error.duplicate');
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return $attachment;
    }

    /**
     * Decodes the "#xx" hexadecimal escapes inside PDF names, so that "/J#53" is read as "/JS"
     * before the keyword scan. Only the bytes of names (a slash followed by regular characters)
     * are touched.
     *
     * @param string $content Raw bytes of the PDF
     * @return string The bytes with the escapes of names decoded
     */
    public static function decodeNameEscapes(string $content): string
    {
        return (string) preg_replace_callback(
            '~/[^\x00-\x20/<>\[\](){}%]*#[0-9A-Fa-f]{2}[^\x00-\x20/<>\[\](){}%]*~',
            static fn (array $m): string => (string) preg_replace_callback('~#([0-9A-Fa-f]{2})~', static fn (array $h): string => \chr((int) hexdec($h[1])), $m[0]),
            $content,
        );
    }

    /**
     * Number of files already attached to a vector.
     *
     * @param Vector $vector Vector
     * @return int The count
     */
    private function countFor(Vector $vector): int
    {
        return $this->em->getRepository(Attachment::class)->count(['vector' => $vector]);
    }

    /**
     * Path of the stored file of an attachment.
     *
     * @param Attachment $attachment Attachment
     * @return string The absolute path (the file may be missing)
     */
    public function path(Attachment $attachment): string
    {
        return $this->directory.'/'.$attachment->getStorageId().'.pdf';
    }

    /**
     * Tells whether the stored file of an attachment is still on the disk.
     *
     * @param Attachment $attachment Attachment
     * @return bool True if the file exists
     */
    public function exists(Attachment $attachment): bool
    {
        return is_file($this->path($attachment));
    }

    /**
     * Cleans an original file name: no path, no control character, only letters, digits, spaces and . _ ( ) + -, and a .pdf extension.
     *
     * @param string $name Name sent by the browser
     * @return string The cleaned name (100 characters plus the extension at most)
     */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/\.pdf$/i', '', $name);
        $name = (string) preg_replace('/[^\p{L}\p{N} ._()+-]+/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), ' .');
        $name = mb_substr($name, 0, 100);

        return ('' === $name ? 'attachment' : $name).'.pdf';
    }

    /**
     * Random UUID version 4.
     *
     * @return string The UUID in its canonical text form
     */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
