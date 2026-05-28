<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MobilePushNotificationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::FCM_SERVER_KEY)%')]
        private readonly string $fcmServerKey = '',
    ) {
    }

    public function sendOrderReadyNotification(User $user, int $orderId): bool
    {
        $deviceToken = trim((string) $user->getMobilePushToken());
        if ($deviceToken === '') {
            return false;
        }

        if ($this->fcmServerKey === '') {
            $this->logger->warning('FCM_SERVER_KEY is not configured; skipping order ready push notification.', [
                'userId' => $user->getId(),
                'orderId' => $orderId,
            ]);

            return false;
        }

        $payload = [
            'to' => $deviceToken,
            'priority' => 'high',
            'notification' => [
                'title' => "Harvey's Cafe",
                'body' => 'Your order is ready for pickup.',
                'sound' => 'default',
            ],
            'data' => [
                'type' => 'ORDER_READY',
                'orderId' => (string) $orderId,
                'status' => 'SERVED',
                'message' => 'Order is Ready',
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://fcm.googleapis.com/fcm/send', [
                'headers' => [
                    'Authorization' => 'key=' . $this->fcmServerKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 8,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->error('Failed to send order ready push notification.', [
                    'statusCode' => $statusCode,
                    'userId' => $user->getId(),
                    'orderId' => $orderId,
                ]);

                return false;
            }
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Push notification transport failure.', [
                'error' => $exception->getMessage(),
                'userId' => $user->getId(),
                'orderId' => $orderId,
            ]);

            return false;
        } catch (\Throwable $exception) {
            $this->logger->error('Unexpected push notification error.', [
                'error' => $exception->getMessage(),
                'userId' => $user->getId(),
                'orderId' => $orderId,
            ]);

            return false;
        }

        return true;
    }
}
