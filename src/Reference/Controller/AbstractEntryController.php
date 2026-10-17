<?php
/**
 * Common logic of the list screens (list, create, edit, archive, restore)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Controller;

use App\Reference\Entity\AbstractReference;
use App\Reference\Service\ReferenceCatalog;
use App\Reference\Service\ReferenceRegistry;
use App\Reference\Service\ReferenceUsage;
use App\Reference\Service\ReferenceWarnings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Behaviour shared by the reference lists and the target lists. The routes are declared in the
 * subclasses (their names and prefixes differ); the differences between lists live in
 * {@see ReferenceRegistry}. There is deliberately no delete action: an entry is archived.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
abstract class AbstractEntryController extends AbstractController
{
    /**
     * AbstractEntryController constructor.
     *
     * @param ReferenceRegistry $registry Entity and form of each list
     * @param ReferenceCatalog $catalog Listing and search
     * @param ReferenceUsage $usage Usage counter
     * @param ReferenceWarnings $warnings Non-blocking warnings after a save
     * @param EntityManagerInterface $em Entity manager
     * @param TranslatorInterface $translator Translates the flash messages
     */
    public function __construct(
        protected readonly ReferenceRegistry $registry,
        protected readonly ReferenceCatalog $catalog,
        protected readonly ReferenceUsage $usage,
        protected readonly ReferenceWarnings $warnings,
        protected readonly EntityManagerInterface $em,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Group of lists served by the controller.
     *
     * @return string ReferenceRegistry::GROUP_REFERENCE, GROUP_TARGET or GROUP_CLONING
     */
    abstract protected function group(): string;

    /**
     * List screen with search and status filter.
     *
     * @param string $type URL slug of the list
     * @param Request $request Request carrying the q and status parameters
     * @return Response The rendered list
     */
    protected function listEntries(string $type, Request $request): Response
    {
        $status = $request->query->getString('status', ReferenceCatalog::STATUS_ACTIVE);
        if (!\in_array($status, [ReferenceCatalog::STATUS_ACTIVE, ReferenceCatalog::STATUS_ARCHIVED, ReferenceCatalog::STATUS_ALL], true)) {
            $status = ReferenceCatalog::STATUS_ACTIVE;
        }
        $query = $request->query->getString('q');

        $entries = $this->catalog->search($this->registry->entityClass($type), $query, $status);
        $usages = [];
        foreach ($entries as $entry) {
            $usages[$entry->getId()] = $this->usage->count($entry);
        }

        return $this->render('reference/index.html.twig', [
            'group' => $this->group(),
            'type' => $type,
            'types' => $this->registry->slugs($this->group()),
            'entries' => $entries,
            'usages' => $usages,
            'q' => $query,
            'status' => $status,
        ]);
    }

    /**
     * Create or edit screen: displays the form, saves a valid submission and flashes the warnings.
     *
     * @param string $type URL slug of the list
     * @param AbstractReference $entry Entry to edit, or a new one
     * @param Request $request Request carrying the submitted form
     * @param bool $isNew True when the entry is being created
     * @return Response The form (HTTP 422 if invalid) or a redirection to the list
     */
    protected function saveEntry(string $type, AbstractReference $entry, Request $request, bool $isNew): Response
    {
        $before = $entry->significantValues();
        $form = $this->createForm($this->registry->formType($type), $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prepareEntry($entry);
            $this->em->persist($entry);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans($isNew ? 'reference.flash.created' : 'reference.flash.updated', ['%name%' => $entry->getName()]));
            foreach ($this->warnings->for($entry, $isNew ? [] : $before) as $warning) {
                $this->addFlash('warning', $this->translator->trans($warning['key'], $warning['params']));
            }

            return $this->redirectToRoute('app_'.$this->group().'_index', ['type' => $type]);
        }

        return $this->render('reference/form.html.twig', [
            'group' => $this->group(),
            'type' => $type,
            'types' => $this->registry->slugs($this->group()),
            'entry' => $entry,
            'form' => $form,
            'is_new' => $isNew,
            'usage' => $isNew ? 0 : $this->usage->count($entry),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Hook called on a valid entry just before it is stored: lets a controller apply what the person never types
     * (derived status, catalogue spelling). Does nothing by default.
     *
     * @param AbstractReference $entry Validated entry about to be stored
     * @return void
     */
    protected function prepareEntry(AbstractReference $entry): void
    {
    }

    /**
     * Archives or restores an entry, after checking the CSRF token.
     *
     * @param string $type URL slug of the list
     * @param int $id Identifier of the entry
     * @param Request $request Request carrying the _token field
     * @param bool $archive True to archive, false to restore
     * @return Response A redirection to the list
     * @throws AccessDeniedException If the CSRF token is invalid
     */
    protected function changeState(string $type, int $id, Request $request, bool $archive): Response
    {
        $entry = $this->findEntry($type, $id);
        if (!$this->isCsrfTokenValid('reference_state_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $archive ? $entry->archive() : $entry->restore();
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans($archive ? 'reference.flash.archived' : 'reference.flash.restored', ['%name%' => $entry->getName()]));

        return $this->redirectToRoute('app_'.$this->group().'_index', ['type' => $type]);
    }

    /**
     * Finds an entry of a list.
     *
     * @param string $type URL slug of the list
     * @param int $id Identifier of the entry
     * @return AbstractReference The entry
     * @throws NotFoundHttpException If there is no such entry
     */
    protected function findEntry(string $type, int $id): AbstractReference
    {
        return $this->em->getRepository($this->registry->entityClass($type))->find($id)
            ?? throw $this->createNotFoundException();
    }
}
