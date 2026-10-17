<?php
/**
 * Screens of the target lists (complexes, genes, products)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Target\Controller;

use App\Reference\Controller\AbstractEntryController;
use App\Reference\Service\ReferenceRegistry;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Service\SequenceAnalysis;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Same screens as the reference lists, plus a detail page for genes and products where the
 * sequences are derived by biophp/biotools (see {@see SequenceAnalysis}).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
#[IsGranted('ROLE_MEMBER')]
#[Route('/targets')]
final class TargetController extends AbstractEntryController
{
    /**
     * Group of lists served by this controller.
     *
     * @return string ReferenceRegistry::GROUP_TARGET
     */
    protected function group(): string
    {
        return ReferenceRegistry::GROUP_TARGET;
    }

    /**
     * Entry point of the menu: redirects to the first list.
     *
     * @return Response A redirection to the first list
     */
    #[Route('', name: 'app_target_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_target_index', ['type' => $this->registry->slugs($this->group())[0]]);
    }

    /**
     * List of the entries of a list.
     *
     * @param string $type URL slug of the list
     * @param Request $request Request carrying the q and status parameters
     * @return Response The rendered list
     */
    #[Route('/{type}', name: 'app_target_index', requirements: ['type' => ReferenceRegistry::TARGET_SLUG_PATTERN], methods: ['GET'])]
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
    #[Route('/{type}/new', name: 'app_target_new', requirements: ['type' => ReferenceRegistry::TARGET_SLUG_PATTERN], methods: ['GET', 'POST'])]
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
    #[Route('/{type}/{id}/edit', name: 'app_target_edit', requirements: ['type' => ReferenceRegistry::TARGET_SLUG_PATTERN, 'id' => '\d+'], methods: ['GET', 'POST'])]
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
    #[Route('/{type}/{id}/archive', name: 'app_target_archive', requirements: ['type' => ReferenceRegistry::TARGET_SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
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
    #[Route('/{type}/{id}/restore', name: 'app_target_restore', requirements: ['type' => ReferenceRegistry::TARGET_SLUG_PATTERN, 'id' => '\d+'], methods: ['POST'])]
    public function restore(string $type, int $id, Request $request): Response
    {
        return $this->changeState($type, $id, $request, false);
    }

    /**
     * Detail page of a gene: CDS, translation under the organism's genetic code, molecular weight and products.
     *
     * @param int $id Identifier of the gene
     * @param SequenceAnalysis $analysis Derives the translation and the mass with biophp/biotools
     * @return Response The rendered page
     */
    #[Route('/genes/{id}', name: 'app_target_gene_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showGene(int $id, SequenceAnalysis $analysis): Response
    {
        $gene = $this->findEntry('genes', $id);
        \assert($gene instanceof Gene);
        $protein = $analysis->geneProtein($gene);

        return $this->render('target/gene_show.html.twig', [
            'group' => $this->group(),
            'type' => 'genes',
            'types' => $this->registry->slugs($this->group()),
            'gene' => $gene,
            'protein' => $protein,
            'mass' => null === $protein ? null : $analysis->proteinMass($protein),
            'products' => $this->em->getRepository(Product::class)->findBy(['gene' => $gene], ['nameKey' => 'ASC']),
        ]);
    }

    /**
     * Detail page of a product: the DNA, RNA or protein sequence derived from its gene.
     *
     * @param int $id Identifier of the product
     * @param SequenceAnalysis $analysis Derives the sequences and the mass with biophp/biotools
     * @return Response The rendered page
     */
    #[Route('/products/{id}', name: 'app_target_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showProduct(int $id, SequenceAnalysis $analysis): Response
    {
        $product = $this->findEntry('products', $id);
        \assert($product instanceof Product);

        return $this->render('target/product_show.html.twig', [
            'group' => $this->group(),
            'type' => 'products',
            'types' => $this->registry->slugs($this->group()),
            'product' => $product,
            'result' => $analysis->analyseProduct($product),
        ]);
    }
}
