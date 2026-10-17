<?php
/**
 * Functional tests of the reference screens
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Reference;

use App\Reference\Entity\Organism;
use App\Reference\Entity\SelectionMarker;
use App\Reference\Entity\Strain;
use App\Reference\Entity\Supplier;
use App\Tests\Support\DatabaseWebTestCase;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceScreensTest extends DatabaseWebTestCase
{
    /**
     * Tests that an anonymous visitor is sent to the login page.
     *
     * @return void
     */
    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/references/strains');

        self::assertResponseRedirects('/login');
    }

    /**
     * Tests that a member reaches every list and creation form, and that the menu has the entry.
     *
     * @return void
     */
    public function testMemberSeesEveryListAndTheMenuEntry(): void
    {
        $this->client->loginUser($this->createUser());

        foreach (['organisms', 'strains', 'markers', 'promoters', 'origins', 'tags', 'suppliers'] as $slug) {
            $this->client->request('GET', '/references/'.$slug);
            self::assertResponseIsSuccessful($slug);
            $this->client->request('GET', '/references/'.$slug.'/new');
            self::assertResponseIsSuccessful($slug.' form');
        }
        self::assertSelectorExists('.main-nav a[href="/references"]');
    }

    /**
     * Tests that an unknown list gives a 404.
     *
     * @return void
     */
    public function testUnknownListIs404(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/references/enzymes');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Tests that a strain is created with its resistances and the audit author is filled.
     *
     * @return void
     */
    public function testMemberCreatesAStrainWithResistancesAndAuditIsFilled(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $tet = (new SelectionMarker())->setName('Tetracycline');
        $this->em->persist($tet);
        $this->em->flush();

        $this->client->request('GET', '/references/strains/new');
        $this->client->submitForm('Enregistrer', [
            'strain[name]' => 'XL1-Blue',
            'strain[usage]' => 'cloning',
            'strain[genotype]' => 'recA1 endA1 gyrA96',
            'strain[resistances]' => [(string) $tet->getId()],
        ]);

        self::assertResponseRedirects('/references/strains');
        $this->em->clear();
        $strain = $this->em->getRepository(Strain::class)->findOneBy(['name' => 'XL1-Blue']);
        self::assertNotNull($strain);
        self::assertSame('Tetracycline', $strain->getResistances()->first()->getName());
        self::assertSame($user->getId(), $strain->getCreatedBy()?->getId());
    }

    /**
     * Tests that a duplicate name is refused and that an archived duplicate suggests the restoration.
     *
     * @return void
     */
    public function testDuplicateNameIsRefusedAndArchivedDuplicateSuggestsRestoration(): void
    {
        $this->client->loginUser($this->createUser());
        $this->em->persist((new Strain())->setName('JM109')->archive());
        $this->em->flush();

        $this->client->request('GET', '/references/strains/new');
        $this->client->submitForm('Enregistrer', ['strain[name]' => 'jm109']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'archivée');
        self::assertCount(1, $this->em->getRepository(Strain::class)->findAll());
    }

    /**
     * Tests that an invalid organism gives a 422 with errors and is not saved.
     *
     * @return void
     */
    public function testInvalidOrganismShowsErrorsAndSavesNothing(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/references/organisms/new');
        $this->client->submitForm('Enregistrer', ['organism[name]' => 'Test organism', 'organism[taxId]' => '0', 'organism[geneticCode]' => '11']);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->em->getRepository(Organism::class)->findAll());
    }

    /**
     * Tests that archiving hides an entry from the default list and restoring brings it back.
     *
     * @return void
     */
    public function testArchiveHidesFromDefaultListAndRestoreBringsItBack(): void
    {
        $this->client->loginUser($this->createUser());
        $strain = (new Strain())->setName('Tuner');
        $this->em->persist($strain);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/references/strains');
        self::assertSelectorTextContains('table.data', 'Tuner');
        $this->client->submit($crawler->filter('form[action$="/archive"]')->form());

        $this->client->request('GET', '/references/strains');
        self::assertSelectorNotExists('table.data');
        $crawler = $this->client->request('GET', '/references/strains?status=archived');
        self::assertSelectorTextContains('table.data', 'Tuner');

        $this->client->submit($crawler->filter('form[action$="/restore"]')->form());
        $this->client->request('GET', '/references/strains');
        self::assertSelectorTextContains('table.data', 'Tuner');
    }

    /**
     * Tests that archiving with a forged token is refused (403) and changes nothing.
     *
     * @return void
     */
    public function testArchiveWithoutValidCsrfTokenIsRefused(): void
    {
        $this->client->loginUser($this->createUser());
        $strain = (new Strain())->setName('Tuner');
        $this->em->persist($strain);
        $this->em->flush();

        $this->client->request('POST', '/references/strains/'.$strain->getId().'/archive', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertTrue($this->em->find(Strain::class, $strain->getId())->isActive());
    }

    /**
     * Tests that no deletion route exists.
     *
     * @return void
     */
    public function testThereIsNoDeleteRoute(): void
    {
        $this->client->loginUser($this->createUser());
        $strain = (new Strain())->setName('Tuner');
        $this->em->persist($strain);
        $this->em->flush();

        $this->client->request('DELETE', '/references/strains/'.$strain->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/references/strains/'.$strain->getId().'/delete');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/references/strains/'.$strain->getId().'/delete');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Tests that an archived marker stays on the strain that carries it but is not offered to other strains.
     *
     * @return void
     */
    public function testArchivedMarkerStaysOnTheStrainButIsNotOfferedToOthers(): void
    {
        $this->client->loginUser($this->createUser());
        $old = (new SelectionMarker())->setName('OldMarker');
        $kept = (new Strain())->setName('Keeper')->addResistance($old);
        $other = (new Strain())->setName('Other');
        $this->em->persist($old);
        $this->em->persist($kept);
        $this->em->persist($other);
        $this->em->flush();
        $old->archive();
        $this->em->flush();

        $crawler = $this->client->request('GET', '/references/strains/'.$kept->getId().'/edit');
        self::assertCount(1, $crawler->filter('#strain_resistances option[selected]'));
        $crawler = $this->client->request('GET', '/references/strains/'.$other->getId().'/edit');
        self::assertCount(0, $crawler->filter('#strain_resistances option'));

        $crawler = $this->client->request('GET', '/references/strains');
        self::assertStringContainsString('OldMarker', $crawler->filter('table.data')->text());
    }

    /**
     * Tests that names and notes are HTML-escaped in the list.
     *
     * @return void
     */
    public function testNamesAndNotesAreEscaped(): void
    {
        $this->client->loginUser($this->createUser());
        $this->em->persist((new Strain())->setName('<script>alert(1)</script>')->setNotes('<img src=x onerror=alert(1)>'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/references/strains');

        self::assertSelectorNotExists('table.data script');
        self::assertStringContainsString('<script>alert(1)</script>', $crawler->filter('table.data')->text());
    }

    /**
     * Tests that a supplier link opens with noopener and that a non-http scheme is never rendered as a link.
     *
     * @return void
     */
    public function testSupplierLinkIsOpenedSafelyAndUnsafeSchemeIsNeverLinked(): void
    {
        $this->client->loginUser($this->createUser());
        $this->em->persist((new Supplier())->setName('Good')->setUrl('https://example.org'));
        // A legacy value inserted around the validation must still not become a link.
        $bad = (new Supplier())->setName('Bad')->setUrl('javascript:alert(1)');
        $this->em->persist($bad);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/references/suppliers');

        $links = $crawler->filter('table.data td a[target="_blank"]');
        self::assertCount(1, $links);
        self::assertStringContainsString('noopener', $links->attr('rel'));
        self::assertSelectorNotExists('a[href^="javascript:"]');
    }

    /**
     * Tests that % in the search is a literal character, not a wildcard.
     *
     * @return void
     */
    public function testSearchTreatsPercentAsLiteralText(): void
    {
        $this->client->loginUser($this->createUser());
        $this->em->persist((new Strain())->setName('Alpha'));
        $this->em->persist((new Strain())->setName('100% pure'));
        $this->em->flush();

        $this->client->request('GET', '/references/strains?q=%25');

        self::assertSelectorTextContains('table.data', '100% pure');
        self::assertSelectorTextNotContains('table.data', 'Alpha');
    }

    /**
     * Tests that changing a used marker warns with the number of uses.
     *
     * @return void
     */
    public function testRenamingAUsedMarkerWarnsWithTheNumberOfUses(): void
    {
        $this->client->loginUser($this->createUser());
        $marker = (new SelectionMarker())->setName('Kanamycin')->setWorkingConcentrationUgMl(30.0);
        $this->em->persist($marker);
        $this->em->persist((new Strain())->setName('S1')->addResistance($marker));
        $this->em->flush();

        $this->client->request('GET', '/references/markers/'.$marker->getId().'/edit');
        self::assertSelectorTextContains('.notice-warning', '1');
        $this->client->submitForm('Enregistrer', ['selection_marker[workingConcentrationUgMl]' => '50']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.notice-warning', 'utilisée 1 fois');
    }

    /**
     * Tests that an unusual concentration and a none-like name give warnings but are saved.
     *
     * @return void
     */
    public function testMarkerOutsideUsualRangeAndNoneLikeNameWarnButAreSaved(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/references/markers/new');
        $this->client->submitForm('Enregistrer', ['selection_marker[name]' => 'No antibiotic', 'selection_marker[workingConcentrationUgMl]' => '5000']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('main', 'plage indicative');
        self::assertSelectorTextContains('main', 'aucun');
        self::assertCount(1, $this->em->getRepository(SelectionMarker::class)->findAll());
    }
}
