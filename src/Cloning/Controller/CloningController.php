<?php
/**
 * Screens of the cloning lists (oligos, vectors, PCRs, inserts, constructs)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Cloning\Controller;

use App\Account\Entity\User;
use App\Cloning\Entity\Attachment;
use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Cloning\Service\AttachmentRejectedException;
use App\Cloning\Service\AttachmentStorage;
use App\Cloning\Service\CloningChecks;
use App\Cloning\Service\CloningPreparation;
use App\Cloning\Service\OligoTm;
use App\Cloning\Service\VectorSiteCounter;
use App\Reference\Controller\AbstractEntryController;
use App\Reference\Entity\AbstractReference;
use App\Reference\Service\ReferenceCatalog;
use App\Reference\Service\ReferenceRegistry;
use App\Reference\Service\ReferenceUsage;
use App\Reference\Service\ReferenceWarnings;
use App\Shared\Audit\FieldHistory;
use App\Shared\Audit\TracksFieldHistoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Same screens as the reference and target lists, plus detail pages: an oligo (melting
 * temperature computed by biophp, and everything that uses it), a vector (attachments, tag
 * capabilities, history) and a construct (derived status). Sequences only travel in POST bodies
 * and are never put in an URL, a log or an error message.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[IsGranted('ROLE_MEMBER')]
#[Route('/cloning')]
final class CloningController extends AbstractEntryController
{
    /**
     * CloningController constructor.
     *
     * @param ReferenceRegistry $registry Entity and form of each list
     * @param ReferenceCatalog $catalog Listing and search
     * @param ReferenceUsage $usage Usage counter
     * @param ReferenceWarnings $warnings Non-blocking warnings after a save
     * @param EntityManagerInterface $em Entity manager
     * @param TranslatorInterface $translator Translates the flash messages
     * @param CloningPreparation $preparation Derivations applied before a save
     */
    public function __construct(
        ReferenceRegistry $registry,
        ReferenceCatalog $catalog,
        ReferenceUsage $usage,
        ReferenceWarnings $warnings,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        private readonly CloningPreparation $preparation,
    ) {
        parent::__construct($registry, $catalog, $usage, $warnings, $em, $translator);
    }

    /**
     * Group of lists served by this controller.
     *
     * @return string ReferenceRegistry::GROUP_CLONING
     */
    protected function group(): string
    {
        return ReferenceRegistry::GROUP_CLONING;
    }

    /**
     * Applies the derived status of a construct and the catalogue spelling of the enzymes of an insert.
     *
     * @param AbstractReference $entry Validated entry about to be stored
     * @return void
     */
    protected function prepareEntry(AbstractReference $entry): void
    {
        $this->preparation->prepare($entry);
    }

    /**
     * Entry point of the menu: redirects to the first list.
     *
     * @return Response A redirection to the first list
     */
    #[Route('', name: 'app_cloning_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_cloning_index', ['type' => $this->registry->slugs($this->group())[0]]);
    }

    /**
     * List of the entries of a list.
     *
     * @param string $type URL slug of the list
     * @param Request $request Request carrying the q and status parameters
     * @return Response The rendered list
     */
    #[Route('/{type}', name: 'app_cloning_index', requirements: ['type' => ReferenceRegistry::CLONING_SLUG_PATTERN], methods: ['GET'])]
    public function index(string $type, Request $request): Response
    {
        return $this->listEntries($type, $request);
    }

    /**
     * Creation form.
     *
     * @param string $type URL slug of the list
     * @param Request $request Request carrying the submitted form
     * @return Response The form or a redirection to the list
     */
    #[Route('/{type}/new', name: 'app_cloning_new', requirements: ['type' => ReferenceRegistry::CLONING_SLUG_PATTERN], methods: ['GET', 'POST'])]
    public function new(string $type, Request $request): Response
    {
        return $this->saveEntry($type, $this->registry->newEntity($type), $request, true);
    }

    /**
     * Edition form.
     *
     * @param string $type URL slug of the list
     * @param int $id Identifier of the entry
     * @param Request $request Request carrying the submitted form
     * @return Response The form or a redirection to the list
     */
    #[Route('/{type}/{id}/edit', name: 'app_cloning_edit', requirements: ['type' => ReferenceRegistry::CLONING_SLUG_PATTERN, 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(string $type, int $id, Request $request): Response
    {
        return $this->saveEntry($type, $this->findEntry($type, $id), $request, false);
    }

    /**
     * Archives an entry (POST with a CSRF token).
     *
     * @param string $type URL slug of the list
     * @param int $id Identifier of the entry
     * @param Request $request Request carrying the _token field
     * @return Response A redirection to the list
     */
    #[Route('/{type}/{id}/archive', name: 'app_cloning_archive', requirements: ['type' => ReferenceRegistry::CLONING_SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
    public function archive(string $type, int $id, Request $request): Response
    {
        return $this->changeState($type, $id, $request, true);
    }

    /**
     * Restores an archived entry (POST with a CSRF token).
     *
     * @param string $type URL slug of the list
     * @param int $id Identifier of the entry
     * @param Request $request Request carrying the _token field
     * @return Response A redirection to the list
     */
    #[Route('/{type}/{id}/restore', name: 'app_cloning_restore', requirements: ['type' => ReferenceRegistry::CLONING_SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
    public function restore(string $type, int $id, Request $request): Response
    {
        return $this->changeState($type, $id, $request, false);
    }

    /**
     * Detail page of an oligo: sequence, melting temperature computed by biophp with its conditions, reverse tracing and history.
     *
     * @param int $id Identifier of the oligo
     * @param OligoTm $tm Melting temperature of the oligo (biophp)
     * @return Response The rendered page
     */
    #[Route('/oligos/{id}', name: 'app_cloning_oligo_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showOligo(int $id, OligoTm $tm): Response
    {
        $oligo = $this->findEntry('oligos', $id);
        \assert($oligo instanceof Oligo);

        $pcrs = $this->em->createQuery('SELECT p FROM '.Pcr::class.' p WHERE p.forwardOligo = :o OR p.reverseOligo = :o ORDER BY p.nameKey')
            ->setParameter('o', $oligo)
            ->getResult();
        $inserts = [];
        $constructs = [];
        if ([] !== $pcrs) {
            $inserts = $this->em->createQuery('SELECT i FROM '.Insert::class.' i WHERE i.pcr IN (:pcrs) ORDER BY i.nameKey')->setParameter('pcrs', $pcrs)->getResult();
        }
        if ([] !== $inserts) {
            $constructs = $this->em->createQuery('SELECT c FROM '.Construct::class.' c WHERE c.insert IN (:inserts) ORDER BY c.nameKey')->setParameter('inserts', $inserts)->getResult();
        }

        return $this->render('cloning/oligo_show.html.twig', $this->context('oligos') + [
            'oligo' => $oligo,
            'tm' => $tm->forOligo($oligo),
            'tm_reason' => $tm->unavailableReason($oligo),
            'pcrs' => $pcrs,
            'inserts' => $inserts,
            'constructs' => $constructs,
            'history' => $this->history($oligo),
        ]);
    }

    /**
     * Detail page of a vector: references, tag capabilities, attached files, constructs and history.
     *
     * @param int $id Identifier of the vector
     * @param AttachmentStorage $storage Tells whether a stored file is still on the disk
     * @return Response The rendered page
     */
    #[Route('/vectors/{id}', name: 'app_cloning_vector_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showVector(int $id, AttachmentStorage $storage): Response
    {
        $vector = $this->findEntry('vectors', $id);
        \assert($vector instanceof Vector);

        $attachments = [];
        foreach ($this->em->getRepository(Attachment::class)->findBy(['vector' => $vector], ['id' => 'ASC']) as $attachment) {
            $attachments[] = ['attachment' => $attachment, 'present' => $storage->exists($attachment)];
        }

        return $this->render('cloning/vector_show.html.twig', $this->context('vectors') + [
            'vector' => $vector,
            'attachments' => $attachments,
            'constructs' => $this->em->getRepository(Construct::class)->findBy(['vector' => $vector], ['nameKey' => 'ASC']),
            'max_size_mb' => AttachmentStorage::MAX_SIZE_BYTES / 1024 / 1024,
            'history' => $this->history($vector),
        ]);
    }

    /**
     * Detail page of a construct: parents, derived status, the cuts of the insert enzymes in the vector, warnings and history.
     *
     * @param int $id Identifier of the construct
     * @param CloningChecks $checks Derived status and warnings
     * @param VectorSiteCounter $sites Cuts of each enzyme in the vector (biophp)
     * @return Response The rendered page
     */
    #[Route('/constructs/{id}', name: 'app_cloning_construct_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showConstruct(int $id, CloningChecks $checks, VectorSiteCounter $sites): Response
    {
        $construct = $this->findEntry('constructs', $id);
        \assert($construct instanceof Construct);

        $cuts = [];
        $vector = $construct->getVector();
        foreach (null === $construct->getInsert() ? [] : $checks->distinctSites($construct->getInsert()) as $enzyme) {
            $cuts[$enzyme] = null === $vector ? null : $sites->countCuts($vector, $enzyme);
        }
        $warnings = array_map(
            fn (array $warning): string => $this->translator->trans($warning['key'], $warning['params']),
            $checks->for($construct),
        );

        return $this->render('cloning/construct_show.html.twig', $this->context('constructs') + [
            'construct' => $construct,
            'status' => $checks->status($construct),
            'cuts' => $cuts,
            'warnings' => $warnings,
            'history' => $this->history($construct),
        ]);
    }

    /**
     * Uploads a PDF to a vector (POST with a CSRF token; the content is checked by {@see AttachmentStorage}).
     *
     * @param int $id Identifier of the vector
     * @param Request $request Request carrying the _token field and the file
     * @param AttachmentStorage $storage Checks and stores the file
     * @return Response A redirection to the page of the vector
     * @throws AccessDeniedException If the CSRF token is invalid
     */
    #[Route('/vectors/{id}/attachments', name: 'app_cloning_attachment_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadAttachment(int $id, Request $request, AttachmentStorage $storage): Response
    {
        $vector = $this->findEntry('vectors', $id);
        \assert($vector instanceof Vector);
        if (!$this->isCsrfTokenValid('attachment_upload_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', $this->translator->trans('attachment.error.upload'));
        } else {
            $user = $this->getUser();
            try {
                $attachment = $storage->store($file, $vector, $user instanceof User ? $user : null);
                $this->addFlash('success', $this->translator->trans('attachment.flash.uploaded', ['%name%' => $attachment->getOriginalName()]));
            } catch (AttachmentRejectedException $e) {
                $this->addFlash('error', $this->translator->trans($e->getMessage()));
            }
        }

        return $this->redirectToRoute('app_cloning_vector_show', ['id' => $id]);
    }

    /**
     * Sends a stored file as a download, never inline. Only an authenticated member reaches it.
     *
     * @param int $id Identifier of the attachment
     * @param AttachmentStorage $storage Gives the path of the stored file
     * @return Response The file, or a 404 when the entry or the file is missing
     */
    #[Route('/attachments/{id}', name: 'app_cloning_attachment_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadAttachment(int $id, AttachmentStorage $storage): Response
    {
        $attachment = $this->em->getRepository(Attachment::class)->find($id) ?? throw $this->createNotFoundException();
        if (!$storage->exists($attachment)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($storage->path($attachment));
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName(), 'attachment.pdf');

        return $response;
    }

    /**
     * Variables shared by the detail pages (menu group, tabs, list slug).
     *
     * @param string $type URL slug of the list the page belongs to
     * @return array<string, mixed> The Twig variables
     */
    private function context(string $type): array
    {
        return ['group' => $this->group(), 'type' => $type, 'types' => $this->registry->slugs($this->group())];
    }

    /**
     * Recorded changes of the tracked fields of an entity, most recent first.
     *
     * @param TracksFieldHistoryInterface $entity Oligo, vector or construct
     * @return list<FieldHistory> The history rows
     */
    private function history(TracksFieldHistoryInterface $entity): array
    {
        return $this->em->getRepository(FieldHistory::class)->findBy(
            ['entityType' => $this->em->getClassMetadata($entity::class)->getName(), 'entityId' => $entity->getId()],
            ['id' => 'DESC'],
        );
    }
}
