<?php
/**
 * Refuses inactive and system accounts at login
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses the login of a deactivated account or of a system account.
 *
 * The message does not tell the two cases apart: it does not reveal that a system account exists.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class UserChecker implements UserCheckerInterface
{
    /**
     * Refuses the login of a deactivated or system account, before the password is checked.
     *
     * @param UserInterface $user Account that tries to sign in
     * @return void
     * @throws CustomUserMessageAccountStatusException When the account cannot sign in
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->canLogin()) {
            throw new CustomUserMessageAccountStatusException('security.account_unavailable');
        }
    }

    /**
     * Nothing to check after the authentication.
     *
     * @param UserInterface $user Account that just signed in
     * @return void
     */
    public function checkPostAuth(UserInterface $user): void
    {
    }
}
