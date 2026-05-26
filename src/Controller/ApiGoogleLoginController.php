<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\GoogleAccountService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiGoogleLoginController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private GoogleAccountService $googleAccountService,
        private JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_ID)%')]
        private string $googleClientId,
    ) {
    }

    #[Route('/api/login/google', name: 'api_login_google', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $idToken = trim((string) ($data['idToken'] ?? ''));
        if ($idToken === '') {
            return $this->json(['message' => 'Missing idToken'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $this->verifyIdToken($idToken);
            $user = $this->googleAccountService->findOrCreateCustomerFromEmail((string) $payload['email']);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isStaffOrAdmin($user)) {
            return $this->json(
                ['message' => 'This app is for customers only. Staff and admin accounts cannot sign in here.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $this->json([
            'token' => $this->jwtManager->create($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyIdToken(string $idToken): array
    {
        $response = $this->httpClient->request(
            'GET',
            'https://oauth2.googleapis.com/tokeninfo',
            ['query' => ['id_token' => $idToken]],
        );

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \InvalidArgumentException('Invalid Google token');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray();

        if ($this->googleClientId !== '' && isset($payload['aud']) && $payload['aud'] !== $this->googleClientId) {
            throw new \InvalidArgumentException('Google token audience does not match this app');
        }

        if (empty($payload['email']) || !is_string($payload['email'])) {
            throw new \InvalidArgumentException('Google account did not return an email');
        }

        $emailVerified = $payload['email_verified'] ?? true;
        if ($emailVerified === 'false' || $emailVerified === false) {
            throw new \InvalidArgumentException('Google email is not verified');
        }

        return $payload;
    }

    private function isStaffOrAdmin(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true);
    }
}
