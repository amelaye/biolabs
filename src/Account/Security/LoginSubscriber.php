<?php
/**
 * Records the last login date
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Records the last login date (outside the audit: see UserRepository::touchLastLogin).
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class LoginSubscriber implements EventSubscriberInterface
{
    /**
     * LoginSubscriber constructor.
     *
     * @param UserRepository $users Repository used to record the login date
     * @param ClockInterface $clock Source of the current time
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Records the date of a successful login.
     *
     * @param LoginSuccessEvent $event Security event, gives the signed-in user
     * @return void
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->users->touchLastLogin($user, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }

    /**
     * Events this subscriber listens to.
     *
     * @return array<class-string, string> Method name by event class
     */
    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }
}
