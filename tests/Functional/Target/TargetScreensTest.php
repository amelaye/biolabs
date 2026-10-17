<?php
/**
 * Functional tests of the target screens
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Target;

use App\Reference\Entity\Organism;
use App\Target\Entity\Complex;
use App\Target\Entity\Gene;
use App\Target\Entity\Product;
use App\Target\Enum\ProductType;
use App\Tests\Support\DatabaseWebTestCase;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class TargetScreensTest extends DatabaseWebTestCase
{
    /**
     * Stores a synthetic organism (table 11).
     *
     * @param string $name Organism name
     * @return Organism The stored organism
     */
    private function organism(string $name = 'Synthetic organism'): Organism
    {
        $organism = (new Organism())->setName($name)->setGeneticCode(11);
        $this->em->persist($organism);
        $this->em->flush();

        return $organism;
    }

    /**
     * Stores a synthetic gene.
     *
     * @param string $name Gene name
     * @param string $cds Coding sequence
     * @param Organism|null $organism Organism, a new one if null
     * @return Gene The stored gene
     */
    private function gene(string $name = 'GENE1', string $cds = 'ATGGCCAAATAA', ?Organism $organism = null): Gene
    {
        $gene = (new Gene())->setName($name)->setOrganism($organism ?? $this->organism('Org of '.$name))->setCds($cds);
        $this->em->persist($gene);
        $this->em->flush();

        return $gene;
    }

    /**
     * Tests that an anonymous visitor is sent to the login page.
     *
     * @return void
     */
    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/targets/genes');

        self::assertResponseRedirects('/login');
    }

    /**
     * Tests that a member reaches every target list and form, and that the menu has the entry.
     *
     * @return void
     */
    public function testMemberSeesEveryTargetListAndTheMenuEntry(): void
    {
        $this->client->loginUser($this->createUser());

        foreach (['complexes', 'genes', 'products'] as $slug) {
            $this->client->request('GET', '/targets/'.$slug);
            self::assertResponseIsSuccessful($slug);
            $this->client->request('GET', '/targets/'.$slug.'/new');
            self::assertResponseIsSuccessful($slug.' form');
        }
        self::assertSelectorExists('.main-nav a[href="/targets"]');
        $this->client->request('GET', '/targets');
        self::assertResponseRedirects('/targets/complexes');
    }

    /**
     * Tests that reference lists are not served under /targets and target lists not under /references.
     *
     * @return void
     */
    public function testReferenceTypesAreNotServedUnderTargetsAndConversely(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/targets/strains');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/references/genes');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Tests that a gene is created (raw and normalised CDS, audit author) and its page shows the translation.
     *
     * @return void
     */
    public function testMemberCreatesAGeneAndSeesItsTranslationUnderTheOrganismCode(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $organism = $this->organism();

        $this->client->request('GET', '/targets/genes/new');
        $this->client->submitForm('Enregistrer', [
            'gene_form[name]' => 'YFG1',
            'gene_form[organism]' => (string) $organism->getId(),
            'gene_form[productName]' => 'Yfg1',
            'gene_form[sourceDatabase]' => 'sgd',
            'gene_form[cds]' => "atg gcc aaa\ntaa",
        ]);

        self::assertResponseRedirects('/targets/genes');
        $this->em->clear();
        $gene = $this->em->getRepository(Gene::class)->findOneBy(['name' => 'YFG1']);
        self::assertNotNull($gene);
        self::assertSame("atg gcc aaa\ntaa", $gene->getCdsSequenceRaw());
        self::assertSame('ATGGCCAAATAA', $gene->getCdsSequence());
        self::assertSame($user->getId(), $gene->getCreatedBy()?->getId());

        $this->client->request('GET', '/targets/genes/'.$gene->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('pre.sequence', 'ATGGCCAAAT AA');
        self::assertSelectorTextContains('main', 'MAK*');
    }

    /**
     * Tests that an out-of-frame CDS gives a 422 and is not saved.
     *
     * @return void
     */
    public function testInvalidCdsIsRefusedAndNothingIsSaved(): void
    {
        $this->client->loginUser($this->createUser());
        $organism = $this->organism();

        $this->client->request('GET', '/targets/genes/new');
        $this->client->submitForm('Enregistrer', ['gene_form[name]' => 'BAD', 'gene_form[organism]' => (string) $organism->getId(), 'gene_form[cds]' => 'ATGGC']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'multiple de 3');
        self::assertCount(0, $this->em->getRepository(Gene::class)->findAll());
    }

    /**
     * Tests that a gene with an unusual start and an internal stop is saved with warnings.
     *
     * @return void
     */
    public function testGeneWithAnInternalStopIsSavedWithAWarning(): void
    {
        $this->client->loginUser($this->createUser());
        $organism = $this->organism();

        $this->client->request('GET', '/targets/genes/new');
        $this->client->submitForm('Enregistrer', ['gene_form[name]' => 'ODD', 'gene_form[organism]' => (string) $organism->getId(), 'gene_form[cds]' => 'GTGTAGAAA']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('main', 'ATG');
        self::assertSelectorTextContains('main', 'stop');
        self::assertCount(1, $this->em->getRepository(Gene::class)->findAll());
    }

    /**
     * Tests that a product is created and its page shows the sequences derived from the gene.
     *
     * @return void
     */
    public function testMemberCreatesAProductAndSeesDerivedSequences(): void
    {
        $this->client->loginUser($this->createUser());
        $gene = $this->gene();

        $this->client->request('GET', '/targets/products/new');
        $this->client->submitForm('Enregistrer', [
            'product_form[name]' => 'YFG1-core',
            'product_form[type]' => 'protein',
            'product_form[gene]' => (string) $gene->getId(),
            'product_form[startNt]' => '4',
            'product_form[endNt]' => '9',
        ]);
        self::assertResponseRedirects('/targets/products');

        $product = $this->em->getRepository(Product::class)->findOneBy(['name' => 'YFG1-core']);
        $this->client->request('GET', '/targets/products/'.$product->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'GCCAAA');
        self::assertSelectorTextContains('main', 'AK');
    }

    /**
     * Tests that a protein window outside the reading frame gives a 422 and is not saved.
     *
     * @return void
     */
    public function testOutOfFrameProteinWindowIsRefused(): void
    {
        $this->client->loginUser($this->createUser());
        $gene = $this->gene();

        $this->client->request('GET', '/targets/products/new');
        $this->client->submitForm('Enregistrer', [
            'product_form[name]' => 'BAD', 'product_form[type]' => 'protein', 'product_form[gene]' => (string) $gene->getId(),
            'product_form[startNt]' => '2', 'product_form[endNt]' => '7',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->em->getRepository(Product::class)->findAll());
    }

    /**
     * Tests that a complex keeps an archived gene but other complexes are not offered it.
     *
     * @return void
     */
    public function testComplexGroupsGenesAndArchivedGeneStaysButIsNotOfferedToOthers(): void
    {
        $this->client->loginUser($this->createUser());
        $old = $this->gene('OLD');
        $kept = (new Complex())->setName('Kept')->addGene($old);
        $other = (new Complex())->setName('Other');
        $this->em->persist($kept);
        $this->em->persist($other);
        $this->em->flush();
        $old->archive();
        $this->em->flush();

        $crawler = $this->client->request('GET', '/targets/complexes/'.$kept->getId().'/edit');
        self::assertCount(1, $crawler->filter('#complex_form_genes option[selected]'));
        $crawler = $this->client->request('GET', '/targets/complexes/'.$other->getId().'/edit');
        self::assertCount(0, $crawler->filter('#complex_form_genes option'));
    }

    /**
     * Tests the usage counters of genes and organisms and the warning when a used gene is changed.
     *
     * @return void
     */
    public function testUsageCountsAndWarningWhenAUsedGeneChanges(): void
    {
        $this->client->loginUser($this->createUser());
        $gene = $this->gene();
        $this->em->persist((new Product())->setName('Prod')->setGene($gene)->setType(ProductType::Dna));
        $this->em->persist((new Complex())->setName('Cx')->addGene($gene));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/targets/genes');
        self::assertSame('2', trim($crawler->filter('table.data tbody tr td')->eq(1)->text()));
        $crawler = $this->client->request('GET', '/references/organisms');
        self::assertSame('1', trim($crawler->filter('table.data tbody tr td')->eq(1)->text()), 'an organism is counted through its genes');

        $this->client->request('GET', '/targets/genes/'.$gene->getId().'/edit');
        $this->client->submitForm('Enregistrer', ['gene_form[cds]' => 'ATGAAATAA']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.notice-warning', 'utilisée 2 fois');
    }

    /**
     * Tests that a gene can be archived then restored.
     *
     * @return void
     */
    public function testArchiveAndRestoreAGene(): void
    {
        $this->client->loginUser($this->createUser());
        $this->gene('G9');

        $crawler = $this->client->request('GET', '/targets/genes');
        $this->client->submit($crawler->filter('form[action$="/archive"]')->form());
        $this->client->request('GET', '/targets/genes');
        self::assertSelectorNotExists('table.data');
        $crawler = $this->client->request('GET', '/targets/genes?status=archived');
        $this->client->submit($crawler->filter('form[action$="/restore"]')->form());
        $this->client->request('GET', '/targets/genes');
        self::assertSelectorTextContains('table.data', 'G9');
    }

    /**
     * Tests that no deletion route exists for genes.
     *
     * @return void
     */
    public function testThereIsNoDeleteRoute(): void
    {
        $this->client->loginUser($this->createUser());
        $gene = $this->gene();

        $this->client->request('POST', '/targets/genes/'.$gene->getId().'/delete');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', '/targets/genes/'.$gene->getId());
        self::assertResponseStatusCodeSame(405);
    }

    /**
     * Tests that gene names and notes are HTML-escaped in the list and on the detail page.
     *
     * @return void
     */
    public function testNamesAndNotesAreEscaped(): void
    {
        $this->client->loginUser($this->createUser());
        $gene = $this->gene('<script>alert(1)</script>');
        $gene->setNotes('<img src=x onerror=alert(1)>');
        $this->em->flush();

        $this->client->request('GET', '/targets/genes');
        self::assertSelectorNotExists('table.data script');
        $this->client->request('GET', '/targets/genes/'.$gene->getId());
        self::assertSelectorNotExists('main script');
        self::assertSelectorNotExists('main img');
    }
}
