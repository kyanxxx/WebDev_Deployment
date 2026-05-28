<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ApiDeviceTokenController extends AbstractController
{
    #[Route('/api/mobile/device-token', name: 'api_mobile_device_token', methods: ['POST'])]
    public function upsertDeviceToken(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $tokenRaw = $payload['token'] ?? null;
        if (null !== $tokenRaw && !is_string($tokenRaw)) {
            return $this->json(['message' => 'token must be a string or null'], Response::HTTP_BAD_REQUEST);
        }

        $token = trim((string) ($tokenRaw ?? ''));
        if ($token !== '' && strlen($token) > 255) {
            return $this->json(['message' => 'token is too long'], Response::HTTP_BAD_REQUEST);
        }

        $user->setMobilePushToken($token !== '' ? $token : null);
        $entityManager->flush();

        return $this->json([
            'message' => $token !== '' ? 'Device token registered.' : 'Device token cleared.',
        ]);
    }
}
