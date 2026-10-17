<?php
/**
 * Screens of the reference lists
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Controller;

use App\Reference\Service\ReferenceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[IsGranted('ROLE_MEMBER')]
#[Route('/references')]
final class ReferenceController extends AbstractEntryController
{
    /**
     * Group of lists served by this controller.
     *
     * @return string ReferenceRegistry::GROUP_REFERENCE
     */
    protected function group(): string
    {
        return ReferenceRegistry::GROUP_REFERENCE;
    }

    /**
     * Entry point of the menu: redirects to the first list.
     *
     * @return Response A redirection to the first list
     */
    #[Route('', name: 'app_reference_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_reference_index', ['type' => $this->registry->slugs($this->group())[0]]);
    }

    /**
     * List of the entries of a list.
     *
     * @param string $type URL slug of the list
     * @param Request $request Request carrying the q and status parameters
     * @return Response The rendered list
     */
    #[Route('/{type}', name: 'app_reference_index', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN], methods: ['GET'])]
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
    #[Route('/{type}/new', name: 'app_reference_new', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN], methods: ['GET', 'POST'])]
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
    #[Route('/{type}/{id}/edit', name: 'app_reference_edit', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN, 'id' => '\d+'], methods: ['GET', 'POST'])]
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
    #[Route('/{type}/{id}/archive', name: 'app_reference_archive', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
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
    #[Route('/{type}/{id}/restore', name: 'app_reference_restore', requirements: ['type' => ReferenceRegistry::SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
    public function restore(string $type, int $id, Request $request): Response
    {
        return $this->changeState($type, $id, $request, false);
    }
}
