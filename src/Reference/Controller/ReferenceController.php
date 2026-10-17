<?php
/**
 * Generic screens of the reference lists (list, create, edit, archive, restore)
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One controller for the seven lists; the differences live in {@see ReferenceRegistry}.
 * There is deliberately no delete action: an entry is archived, never removed.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[IsGranted('ROLE_MEMBER')]
#[Route('/references')]
final class ReferenceController extends AbstractController
{
    public function __construct(
        private readonly ReferenceRegistry $registry,
        private readonly ReferenceCatalog $catalog,
        private readonly ReferenceUsage $usage,
        private readonly ReferenceWarnings $warnings,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_reference_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_reference_index', ['type' => $this->registry->slugs()[0]]);
    }

    #[Route('/{type}', name: 'app_reference_index', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN], methods: ['GET'])]
    public function index(string $type, Request $request): Response
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
            'type' => $type,
            'types' => $this->registry->slugs(),
            'entries' => $entries,
            'usages' => $usages,
            'q' => $query,
            'status' => $status,
        ]);
    }

    #[Route('/{type}/new', name: 'app_reference_new', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN], methods: ['GET', 'POST'])]
    public function new(string $type, Request $request): Response
    {
        return $this->save($type, $this->registry->newEntity($type), $request, true);
    }

    #[Route('/{type}/{id}/edit', name: 'app_reference_edit', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN, 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(string $type, int $id, Request $request): Response
    {
        return $this->save($type, $this->find($type, $id), $request, false);
    }

    #[Route('/{type}/{id}/archive', name: 'app_reference_archive', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
    public function archive(string $type, int $id, Request $request): Response
    {
        return $this->changeState($type, $id, $request, true);
    }

    #[Route('/{type}/{id}/restore', name: 'app_reference_restore', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
    public function restore(string $type, int $id, Request $request): Response
    {
        return $this->changeState($type, $id, $request, false);
    }

    private function save(string $type, AbstractReference $entry, Request $request, bool $isNew): Response
    {
        $before = $entry->significantValues();
        $form = $this->createForm($this->registry->formType($type), $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($entry);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans($isNew ? 'reference.flash.created' : 'reference.flash.updated', ['%name%' => $entry->getName()]));
            foreach ($this->warnings->for($entry, $isNew ? [] : $before) as $warning) {
                $this->addFlash('warning', $this->translator->trans($warning['key'], $warning['params']));
            }

            return $this->redirectToRoute('app_reference_index', ['type' => $type]);
        }

        return $this->render('reference/form.html.twig', [
            'type' => $type,
            'types' => $this->registry->slugs(),
            'entry' => $entry,
            'form' => $form,
            'is_new' => $isNew,
            'usage' => $isNew ? 0 : $this->usage->count($entry),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function changeState(string $type, int $id, Request $request, bool $archive): Response
    {
        $entry = $this->find($type, $id);
        if (!$this->isCsrfTokenValid('reference_state_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $archive ? $entry->archive() : $entry->restore();
        $this->em->flush();
        $this->addFlash('success', $this->translator->trans($archive ? 'reference.flash.archived' : 'reference.flash.restored', ['%name%' => $entry->getName()]));

        return $this->redirectToRoute('app_reference_index', ['type' => $type]);
    }

    private function find(string $type, int $id): AbstractReference
    {
        return $this->em->getRepository($this->registry->entityClass($type))->find($id)
            ?? throw $this->createNotFoundException();
    }
}
