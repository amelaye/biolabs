<?php
/**
 * Login and logout controller
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Account\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class SecurityController extends AbstractController
{
    /**
     * Login page; an already signed-in person is sent to the home page.
     *
     * @param AuthenticationUtils $authenticationUtils Gives the last typed email and the last error
     * @return Response The login form or a redirection to the home page
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Logout route, handled by the firewall (the method itself is never executed).
     *
     * @return never
     * @throws \LogicException Always: the request is intercepted by the firewall
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall (see security.yaml).');
    }
}
