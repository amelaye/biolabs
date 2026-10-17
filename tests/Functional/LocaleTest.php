<?php
/**
 * Tests of the language selection
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseWebTestCase;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class LocaleTest extends DatabaseWebTestCase
{
    /**
     * Tests that the interface is in French when nothing was chosen.
     *
     * @return void
     */
    public function testDefaultLanguageIsFrench(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorTextContains('h1', 'Connexion');
        self::assertSelectorExists('html[lang="fr"]');
    }

    /**
     * Tests that a language chosen with ?_locale= is kept for the next requests.
     *
     * @return void
     */
    public function testLanguageChoiceIsKeptInSession(): void
    {
        $this->client->request('GET', '/login?_locale=en');
        self::assertSelectorTextContains('h1', 'Sign in');

        $this->client->request('GET', '/login');
        self::assertSelectorTextContains('h1', 'Sign in');
        self::assertSelectorExists('html[lang="en"]');
    }

    /**
     * Tests that an unsupported ?_locale= value does not change the language.
     *
     * @return void
     */
    public function testUnsupportedLanguageIsIgnored(): void
    {
        $this->client->request('GET', '/login?_locale=xx');

        self::assertSelectorExists('html[lang="fr"]');
    }

    /**
     * Tests that the language selector marks the current language.
     *
     * @return void
     */
    public function testLanguageSwitchMarksTheCurrentLanguage(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorExists('.lang-switch a[hreflang="fr"][aria-current="true"]');
        self::assertSelectorNotExists('.lang-switch a[hreflang="en"][aria-current="true"]');
    }
}
