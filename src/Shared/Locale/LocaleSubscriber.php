<?php
/**
 * Keeps the language chosen with ?_locale in session
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Shared\Locale;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps in session the language chosen with ?_locale=fr|en.
 *
 * An unsupported language is ignored: only what can be displayed is stored.
 * Inspired by the LocaleSubscriber of biophp-demo.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * LocaleSubscriber constructor.
     *
     * @param string $defaultLocale Language used when none is stored in the session
     * @param string[] $supportedLocales Languages offered by the selector
     */
    public function __construct(
        private readonly string $defaultLocale = 'fr',
        private readonly array $supportedLocales = ['fr', 'en'],
    ) {
    }

    /**
     * Applies the language of the request: the ?_locale= parameter if it is supported (and stores it in
     * the session), otherwise the language stored in the session, otherwise the default language.
     *
     * @param RequestEvent $event Kernel request event
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $requested = $request->query->get('_locale');
        if (\is_string($requested) && \in_array($requested, $this->supportedLocales, true)) {
            $request->getSession()->set('_locale', $requested);
            $request->setLocale($requested);

            return;
        }

        $stored = $request->getSession()->get('_locale', $this->defaultLocale);
        $request->setLocale(\in_array($stored, $this->supportedLocales, true) ? $stored : $this->defaultLocale);
    }

    /**
     * Events this subscriber listens to.
     *
     * @return array<string, list<array{string, int}>> Method and priority by event name
     */
    public static function getSubscribedEvents(): array
    {
        // Priority 20: before Symfony's LocaleListener (16), which would otherwise set the default language.
        return [KernelEvents::REQUEST => [['onKernelRequest', 20]]];
    }
}
