<?php
/**
 * Functional tests of the cloning screens
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 18 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional\Cloning;

use App\Cloning\Entity\Construct;
use App\Cloning\Entity\Insert;
use App\Cloning\Entity\Oligo;
use App\Cloning\Entity\Pcr;
use App\Cloning\Entity\Vector;
use App\Cloning\Enum\ConstructStatus;
use App\Cloning\Enum\TagCapabilityState;
use App\Cloning\Service\CloningChecks;
use App\Reference\Entity\Tag;
use App\Reference\Enum\TagPurpose;
use App\Reference\Service\ReferenceUsage;
use App\Shared\Audit\FieldHistory;
use App\Tests\Support\CloningFixtures;
use App\Tests\Support\DatabaseWebTestCase;

/**
 * Synthetic sequences only.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class CloningScreensTest extends DatabaseWebTestCase
{
    use CloningFixtures;

    /**
     * Tests that an anonymous visitor is sent to the login page.
     *
     * @return void
     */
    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/cloning/oligos');

        self::assertResponseRedirects('/login');
    }

    /**
     * Tests that a member reaches every cloning list and form, and that the menu has the entry.
     *
     * @return void
     */
    public function testMemberSeesEveryCloningListAndTheMenuEntry(): void
    {
        $this->client->loginUser($this->createUser());

        foreach (['oligos', 'vectors', 'pcrs', 'inserts', 'constructs'] as $slug) {
            $this->client->request('GET', '/cloning/'.$slug);
            self::assertResponseIsSuccessful($slug);
            $this->client->request('GET', '/cloning/'.$slug.'/new');
            self::assertResponseIsSuccessful($slug.' form');
        }
        self::assertSelectorExists('.main-nav a[href="/cloning"]');
        $this->client->request('GET', '/cloning');
        self::assertResponseRedirects('/cloning/oligos');
        $this->client->request('GET', '/cloning/genes');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/targets/oligos');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Tests that an oligo is created with its raw and normalised sequence, its audit author, and its page shows the biophp Tm.
     *
     * @return void
     */
    public function testMemberCreatesAnOligoAndSeesItsTm(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/cloning/oligos/new');
        $this->client->submitForm('Enregistrer', ['oligo_form[name]' => 'F-test', 'oligo_form[sequenceInput]' => "gtc ccatgg\nGCATGGCCAAATAAGC"]);
        self::assertResponseRedirects('/cloning/oligos');

        $oligo = $this->em->getRepository(Oligo::class)->findOneBy(['name' => 'F-test']);
        self::assertNotNull($oligo);
        self::assertSame("gtc ccatgg\nGCATGGCCAAATAAGC", $oligo->getSequenceRaw());
        self::assertSame('GTCCCATGGGCATGGCCAAATAAGC', $oligo->getSequence());
        self::assertSame($user->getId(), $oligo->getCreatedBy()?->getId());

        $this->client->request('GET', '/cloning/oligos/'.$oligo->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '°C');
        self::assertSelectorTextContains('main', '0.5 µM');
        self::assertSelectorTextContains('pre.sequence', 'GTCCCATGGG CATGGCCAAA');
        self::assertSelectorTextContains('main', 'Tm de l\'oligo entier');
    }

    /**
     * Tests that an invalid oligo gives a 422 and is not saved, and that the sequence appears in no URL.
     *
     * @return void
     */
    public function testInvalidOligoIsRefusedAndNothingIsSaved(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/cloning/oligos/new');
        $this->client->submitForm('Enregistrer', ['oligo_form[name]' => 'BAD', 'oligo_form[sequenceInput]' => 'ATG1CC']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'code nucléotidique');
        self::assertCount(0, $this->em->getRepository(Oligo::class)->findAll());
        self::assertStringNotContainsString('ATG1CC', (string) $this->client->getRequest()->getUri());
    }

    /**
     * Tests that no link of the oligo and vector pages carries a sequence, and that the forms use POST.
     *
     * @return void
     */
    public function testNoSequenceIsPutInAnUrl(): void
    {
        $this->client->loginUser($this->createUser());
        $oligo = $this->oligo('ATGGCCAAATAAGCTAGCTAGGATCC');
        $vector = $this->vector($this->vectorSequence());

        foreach (['/cloning/oligos/'.$oligo->getId(), '/cloning/vectors/'.$vector->getId(), '/cloning/oligos', '/cloning/vectors'] as $url) {
            $crawler = $this->client->request('GET', $url);
            foreach ($crawler->filter('a[href], form[action]') as $node) {
                $target = $node->getAttribute('href').$node->getAttribute('action');
                self::assertStringNotContainsString('ATGGCCAAAT', $target);
                self::assertStringNotContainsString('CCATGG', $target);
            }
        }
        $crawler = $this->client->request('GET', '/cloning/oligos/new');
        self::assertSame('POST', strtoupper($crawler->filter('form.ref-form')->attr('method')));
    }

    /**
     * Tests that changing the sequence of an oligo records old and new values and the author in the history.
     *
     * @return void
     */
    public function testChangingAnOligoSequenceIsRecordedInTheHistory(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $oligo = $this->oligo('ATGGCCAAATAAGCTAGC');

        $this->client->request('GET', '/cloning/oligos/'.$oligo->getId().'/edit');
        $this->client->submitForm('Enregistrer', ['oligo_form[sequenceInput]' => 'ATGGCCAAATAAGCTAGG']);
        self::assertResponseRedirects('/cloning/oligos');

        $rows = $this->em->getRepository(FieldHistory::class)->findBy(['entityType' => Oligo::class, 'entityId' => $oligo->getId(), 'field' => 'sequence']);
        self::assertCount(1, $rows);
        self::assertCount(1, $this->em->getRepository(FieldHistory::class)->findBy(['entityType' => Oligo::class, 'entityId' => $oligo->getId(), 'field' => 'sequenceRaw']), 'the text as typed is tracked too');
        self::assertSame('ATGGCCAAATAAGCTAGC', $rows[0]->getOldValue());
        self::assertSame('ATGGCCAAATAAGCTAGG', $rows[0]->getNewValue());
        self::assertSame($user->getId(), $rows[0]->getChangedBy()?->getId());

        $this->client->request('GET', '/cloning/oligos/'.$oligo->getId());
        self::assertSelectorTextContains('table.history', '18 nt → 18 nt');
        self::assertStringNotContainsString('ATGGCCAAATAAGC', $this->client->getCrawler()->filter('table.history')->text(), 'the history shows lengths, not sequences');
    }

    /**
     * Tests that the sequence of an oligo used by a PCR is refused when changed through the form.
     *
     * @return void
     */
    public function testLockedOligoSequenceIsRefusedByTheForm(): void
    {
        $this->client->loginUser($this->createUser());
        $pcr = $this->pcr();
        $oligo = $pcr->getForwardOligo();

        $this->client->request('GET', '/cloning/oligos/'.$oligo->getId().'/edit');
        $this->client->submitForm('Enregistrer', ['oligo_form[sequenceInput]' => 'GGGGGGGGGGGG']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'ne peut pas être modifiée');
    }

    /**
     * Tests that an oligo page lists the PCRs, inserts and constructs that use it, and that the usage counter counts the PCR.
     *
     * @return void
     */
    public function testReverseTracingFromAnOligo(): void
    {
        $this->client->loginUser($this->createUser());
        $insert = $this->insert();
        $construct = $this->construct($this->vector($this->vectorSequence()), $insert);
        $this->em->persist($construct);
        $this->em->flush();
        $oligo = $insert->getPcr()->getForwardOligo();

        $this->client->request('GET', '/cloning/oligos/'.$oligo->getId());
        self::assertSelectorTextContains('main', $insert->getPcr()->getName());
        self::assertSelectorTextContains('main', $insert->getName());
        self::assertSelectorTextContains('main', $construct->getName());
        self::assertSame(1, static::getContainer()->get(ReferenceUsage::class)->count($oligo));
        self::assertSame(1, static::getContainer()->get(ReferenceUsage::class)->count($insert->getPcr()));
        self::assertSame(1, static::getContainer()->get(ReferenceUsage::class)->count($insert));
        self::assertSame(1, static::getContainer()->get(ReferenceUsage::class)->count($construct->getVector()));
    }

    /**
     * Tests that a vector is created without sequence ("not available") and with unknown tags by default.
     *
     * @return void
     */
    public function testVectorWithoutSequenceIsExplicitlyNotAvailable(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/cloning/vectors/new');
        $this->client->submitForm('Enregistrer', ['vector_form[name]' => 'pSYN1']);
        self::assertResponseRedirects('/cloning/vectors');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.notice-warning', 'pas de séquence');

        $vector = $this->em->getRepository(Vector::class)->findOneBy(['name' => 'pSYN1']);
        self::assertNull($vector->getSequence());
        self::assertSame(TagCapabilityState::Unknown, $vector->getTagState());
        $this->client->request('GET', '/cloning/vectors/'.$vector->getId());
        self::assertSelectorTextContains('main', 'Séquence non disponible');
    }

    /**
     * Tests that a vector keeps its tag capabilities (N and C) and refuses a state that contradicts them.
     *
     * @return void
     */
    public function testVectorTagCapabilitiesThroughTheForm(): void
    {
        $this->client->loginUser($this->createUser());
        $his = (new Tag())->setName('6xHis')->setPurpose(TagPurpose::Purification);
        $this->em->persist($his);
        $this->em->flush();

        $this->client->request('GET', '/cloning/vectors/new');
        $this->client->submitForm('Enregistrer', [
            'vector_form[name]' => 'pSYN2', 'vector_form[tagState]' => 'none', 'vector_form[nTerminalTag]' => (string) $his->getId(),
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->submitForm('Enregistrer', [
            'vector_form[name]' => 'pSYN2', 'vector_form[tagState]' => 'listed',
            'vector_form[nTerminalTag]' => (string) $his->getId(), 'vector_form[cTerminalTag]' => (string) $his->getId(),
        ]);
        self::assertResponseRedirects('/cloning/vectors');
        $vector = $this->em->getRepository(Vector::class)->findOneBy(['name' => 'pSYN2']);
        self::assertCount(2, $vector->getTagCapabilities());
        $this->client->request('GET', '/cloning/vectors/'.$vector->getId());
        self::assertSelectorTextContains('main', 'N-terminal');
        self::assertSelectorTextContains('main', 'C-terminal');
    }

    /**
     * Tests that changing the marker of a vector is recorded in the history.
     *
     * @return void
     */
    public function testChangingAVectorMarkerIsRecorded(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector();
        $marker = (new \App\Reference\Entity\SelectionMarker())->setName('Kanamycin');
        $this->em->persist($marker);
        $this->em->flush();

        $this->client->request('GET', '/cloning/vectors/'.$vector->getId().'/edit');
        $this->client->submitForm('Enregistrer', ['vector_form[marker]' => (string) $marker->getId()]);

        $rows = $this->em->getRepository(FieldHistory::class)->findBy(['entityType' => Vector::class, 'field' => 'marker']);
        self::assertCount(1, $rows);
        self::assertNull($rows[0]->getOldValue());
        self::assertSame('Kanamycin', $rows[0]->getNewValue());
    }

    /**
     * Tests the PCR form: created with an annealing temperature, and refused with both templates.
     *
     * @return void
     */
    public function testMemberCreatesAPcrAndTheTemplateIsExclusive(): void
    {
        $this->client->loginUser($this->createUser());
        $forward = $this->oligo();
        $reverse = $this->oligo();
        $gene = $this->gene();
        $vector = $this->vector();

        $this->client->request('GET', '/cloning/pcrs/new');
        $this->client->submitForm('Enregistrer', [
            'pcr_form[name]' => 'PCR-X', 'pcr_form[forwardOligo]' => (string) $forward->getId(), 'pcr_form[reverseOligo]' => (string) $reverse->getId(),
            'pcr_form[templateGene]' => (string) $gene->getId(), 'pcr_form[templateVector]' => (string) $vector->getId(),
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'exactement une matrice');

        $this->client->submitForm('Enregistrer', [
            'pcr_form[name]' => 'PCR-X', 'pcr_form[forwardOligo]' => (string) $forward->getId(), 'pcr_form[reverseOligo]' => (string) $reverse->getId(),
            'pcr_form[templateGene]' => (string) $gene->getId(), 'pcr_form[templateVector]' => '',
            'pcr_form[annealingTempC]' => '55.5', 'pcr_form[cycles]' => '30', 'pcr_form[reactionVolumeUl]' => '50',
        ]);
        self::assertResponseRedirects('/cloning/pcrs');
        $pcr = $this->em->getRepository(Pcr::class)->findOneBy(['name' => 'PCR-X']);
        self::assertSame(55.5, $pcr->getAnnealingTempC());
        self::assertSame(30, $pcr->getCycles());
        self::assertNull($pcr->getTemplateVector());
    }

    /**
     * Tests that an archived or quarantined oligo is not offered to a new PCR.
     *
     * @return void
     */
    public function testArchivedAndQuarantinedParentsAreNotOffered(): void
    {
        $this->client->loginUser($this->createUser());
        $archived = $this->oligo('ATGGCCAAAT', 'ARCHIVED-ONE');
        $archived->archive();
        $quarantined = $this->oligo('ATGGCCAAAC', 'QUARANTINED-ONE')->placeInQuarantine('doubtful');
        $this->oligo('ATGGCCAAAG', 'USABLE-ONE');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/cloning/pcrs/new');
        $options = $crawler->filter('#pcr_form_forwardOligo option')->each(static fn ($node): string => $node->text());
        self::assertContains('USABLE-ONE', $options);
        self::assertNotContains('ARCHIVED-ONE', $options);
        self::assertNotContains('QUARANTINED-ONE', $options);
        self::assertNotNull($quarantined->getId());

        $crawler = $this->client->request('GET', '/cloning/oligos?status=all');
        self::assertSelectorTextContains('table.data', 'Quarantaine');
    }

    /**
     * Tests the insert form: the enzyme typed in lowercase is stored with the catalogue spelling, an unknown one is refused.
     *
     * @return void
     */
    public function testInsertFormChecksEnzymesAgainstTheCatalogue(): void
    {
        $this->client->loginUser($this->createUser());
        $pcr = $this->pcr();

        $this->client->request('GET', '/cloning/inserts/new');
        $this->client->submitForm('Enregistrer', [
            'insert_form[name]' => 'INS-X', 'insert_form[type]' => 'pcr_fragment', 'insert_form[pcr]' => (string) $pcr->getId(),
            'insert_form[fivePrimeSite]' => 'Zzz999I',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'catalogue');

        $this->client->submitForm('Enregistrer', [
            'insert_form[name]' => 'INS-X', 'insert_form[type]' => 'pcr_fragment', 'insert_form[pcr]' => (string) $pcr->getId(),
            'insert_form[fivePrimeSite]' => 'ncoi', 'insert_form[threePrimeSite]' => ' NOTI ',
        ]);
        self::assertResponseRedirects('/cloning/inserts');
        $insert = $this->em->getRepository(Insert::class)->findOneBy(['name' => 'INS-X']);
        self::assertSame('NcoI', $insert->getFivePrimeSite());
        self::assertSame('NotI', $insert->getThreePrimeSite());
    }

    /**
     * Tests that a construct is created with a derived status, that the page says the validity is not evaluated, and that no form field sets the status.
     *
     * @return void
     */
    public function testConstructStatusIsDerivedAndTheValidityIsNotClaimed(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector($this->vectorSequence());
        $insert = $this->insert();

        $crawler = $this->client->request('GET', '/cloning/constructs/new');
        self::assertCount(0, $crawler->filter('[name*="status"]'));
        $this->client->submitForm('Enregistrer', ['construct_form[name]' => 'C-X', 'construct_form[vector]' => (string) $vector->getId(), 'construct_form[insert]' => (string) $insert->getId()]);
        self::assertResponseRedirects('/cloning/constructs');

        $construct = $this->em->getRepository(Construct::class)->findOneBy(['name' => 'C-X']);
        self::assertSame(ConstructStatus::Designed, static::getContainer()->get(CloningChecks::class)->status($construct));
        $this->client->request('GET', '/cloning/constructs/'.$construct->getId());
        self::assertSelectorTextContains('main', 'Conçue');
        self::assertSelectorTextContains('main', 'validité biologique n\'est pas évaluée');
        self::assertSelectorTextContains('main', '1 coupure(s) dans le vecteur');
        self::assertSelectorTextContains('main', 'Aucun produit');
    }

    /**
     * Tests that a construct whose insert enzyme cuts the vector twice is refused with a 422.
     *
     * @return void
     */
    public function testConstructWithAnEnzymeCuttingTwiceIsRefused(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector($this->vectorSequence().'CCATGG');
        $insert = $this->insert();

        $this->client->request('GET', '/cloning/constructs/new');
        $this->client->submitForm('Enregistrer', ['construct_form[name]' => 'C-BAD', 'construct_form[vector]' => (string) $vector->getId(), 'construct_form[insert]' => (string) $insert->getId()]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ref-form', 'NcoI coupe le vecteur 2 fois');
        self::assertCount(0, $this->em->getRepository(Construct::class)->findAll());
    }

    /**
     * Tests that the replacement of the vector of a construct is recorded in the history, and that the status
     * (derived on display, never stored) follows the new vector.
     *
     * @return void
     */
    public function testVectorChangeOfAConstructIsRecordedAndTheStatusFollows(): void
    {
        $this->client->loginUser($this->createUser());
        $good = $this->vector($this->vectorSequence());
        $unknown = $this->vector();
        $insert = $this->insert();
        $construct = $this->construct($good, $insert);
        $this->em->persist($construct);
        $this->em->flush();
        $checks = static::getContainer()->get(CloningChecks::class);
        self::assertSame(ConstructStatus::Designed, $checks->status($construct));

        $this->client->request('GET', '/cloning/constructs/'.$construct->getId().'/edit');
        $this->client->submitForm('Enregistrer', ['construct_form[vector]' => (string) $unknown->getId()]);
        self::assertResponseRedirects('/cloning/constructs');

        $this->em->clear();
        self::assertSame(ConstructStatus::NotEvaluable, $checks->status($this->em->find(Construct::class, $construct->getId())));
        $rows = $this->em->getRepository(FieldHistory::class)->findBy(['entityType' => Construct::class, 'field' => 'vector']);
        self::assertCount(1, $rows);
        self::assertSame($good->getName(), $rows[0]->getOldValue());
        self::assertSame($unknown->getName(), $rows[0]->getNewValue());
        self::assertSame([], $this->em->getRepository(FieldHistory::class)->findBy(['field' => 'status']), 'the status is not a stored field any more');
    }

    /**
     * Tests that a construct stops being "designed" when the sequence of its vector later changes so that
     * the enzyme cuts twice: nothing is stored, the status is derived from the current vector.
     *
     * @return void
     */
    public function testConstructIsNoLongerDesignedWhenItsVectorLaterGetsASecondSite(): void
    {
        $this->client->loginUser($this->createUser());
        $vector = $this->vector($this->vectorSequence());
        $construct = $this->construct($vector, $this->insert());
        $this->em->persist($construct);
        $this->em->flush();
        $checks = static::getContainer()->get(CloningChecks::class);
        self::assertSame(ConstructStatus::Designed, $checks->status($construct));
        $crawler = $this->client->request('GET', '/cloning/constructs/'.$construct->getId());
        self::assertStringContainsString('Conçue (sites uniques vérifiés ; ordre, orientation, extrémités et méthylation NON vérifiés)', $crawler->filter('dl.facts')->text());

        $vector->setSequenceInput($this->vectorSequence().'CCATGG');
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->em->find(Construct::class, $construct->getId());
        self::assertSame(ConstructStatus::NotEvaluable, $checks->status($reloaded));
        $crawler = $this->client->request('GET', '/cloning/constructs/'.$construct->getId());
        self::assertStringContainsString('Non évaluable', $crawler->filter('dl.facts')->text());
        self::assertStringNotContainsString('Conçue', $crawler->filter('dl.facts')->text());
        self::assertSelectorTextContains('main', '2 coupure(s) dans le vecteur');
    }

    /**
     * Tests that names and notes of the cloning objects are HTML-escaped on the lists and detail pages.
     *
     * @return void
     */
    public function testNamesAndNotesAreEscaped(): void
    {
        $this->client->loginUser($this->createUser());
        $oligo = $this->oligo('ATGGCCAAAT', '<script>alert(1)</script>');
        $oligo->setNotes('<img src=x onerror=alert(1)>');
        $this->em->flush();

        $this->client->request('GET', '/cloning/oligos');
        self::assertSelectorNotExists('table.data script');
        $this->client->request('GET', '/cloning/oligos/'.$oligo->getId());
        self::assertSelectorNotExists('main script');
        self::assertSelectorNotExists('main img');
    }

    /**
     * Tests that no deletion route exists for oligos.
     *
     * @return void
     */
    public function testThereIsNoDeleteRoute(): void
    {
        $this->client->loginUser($this->createUser());
        $oligo = $this->oligo();

        $this->client->request('POST', '/cloning/oligos/'.$oligo->getId().'/delete');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', '/cloning/oligos/'.$oligo->getId());
        self::assertResponseStatusCodeSame(405);
    }

    /**
     * Tests that an oligo can be archived then restored from the list.
     *
     * @return void
     */
    public function testArchiveAndRestoreAnOligo(): void
    {
        $this->client->loginUser($this->createUser());
        $this->oligo('ATGGCCAAAT', 'O-ARCH');

        $crawler = $this->client->request('GET', '/cloning/oligos');
        $this->client->submit($crawler->filter('form[action$="/archive"]')->form());
        $this->client->request('GET', '/cloning/oligos');
        self::assertSelectorNotExists('table.data');
        $crawler = $this->client->request('GET', '/cloning/oligos?status=archived');
        $this->client->submit($crawler->filter('form[action$="/restore"]')->form());
        $this->client->request('GET', '/cloning/oligos');
        self::assertSelectorTextContains('table.data', 'O-ARCH');
    }
}
