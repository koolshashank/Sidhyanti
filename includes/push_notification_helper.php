<?php
/**
 * Sends a single push notification via Firebase Cloud Messaging's HTTP v1
 * API, authenticating as the project's service account (config/
 * firebase_service_account.php -- gitignored, never committed) via the
 * standard JWT-bearer OAuth2 flow (RFC 7523), signed with openssl so no
 * Composer/Google client library is needed.
 *
 * Deliberately fails silent: a staff member with no registered device, a
 * missing/invalid service-account file, or any network/HTTP error just
 * means no push goes out -- it must never block or fail the business
 * action (an approval, a payment, etc.) that triggered the notification.
 * The in-app Notification row (see notify_staff() below) is always the
 * source of truth; the push is a best-effort nudge on top of it.
 */

define('FIREBASE_SERVICE_ACCOUNT_PATH', __DIR__ . '/../config/firebase_service_account.php');
define('FCM_TOKEN_CACHE_PATH', sys_get_temp_dir() . '/sidhyanti_fcm_token_cache.json');

/** Mints (and file-caches, since each request is a fresh PHP process) a short-lived FCM access token. */
function fcm_access_token(): ?string {
    if (file_exists(FCM_TOKEN_CACHE_PATH)) {
        $cached = json_decode((string)file_get_contents(FCM_TOKEN_CACHE_PATH), true);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time() + 60) {
            return $cached['token'];
        }
    }

    if (!file_exists(FIREBASE_SERVICE_ACCOUNT_PATH)) {
        return null;
    }
    $sa = require FIREBASE_SERVICE_ACCOUNT_PATH;

    $now = time();
    $b64 = function ($data) {
        return rtrim(strtr(base64_encode(is_string($data) ? $data : json_encode($data)), '+/', '-_'), '=');
    };
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => $sa['token_uri'],
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $unsigned = $b64($header) . '.' . $b64($claims);
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $sa['private_key'], 'SHA256')) {
        return null;
    }
    $jwt = $unsigned . '.' . $b64($signature);

    $ch = curl_init($sa['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) {
        return null;
    }
    $data = json_decode($resp, true);
    if (!isset($data['access_token'])) {
        return null;
    }
    @file_put_contents(FCM_TOKEN_CACHE_PATH, json_encode([
        'token'   => $data['access_token'],
        'expires' => $now + (int)($data['expires_in'] ?? 3600),
    ]));
    return $data['access_token'];
}

/**
 * Sends a push to whatever device last registered for this staff member
 * (notifications/register_device.php). $relatedEntityType/$relatedEntityId
 * ride along as data fields so the mobile app can deep-link on tap exactly
 * like it already does for an in-app notification tap.
 */
function send_push_notification(PDO $pdo, int $staffId, string $title, string $body, ?string $relatedEntityType = null, ?int $relatedEntityId = null): void {
    try {
        $tokenStmt = $pdo->prepare("SELECT FcmToken FROM Staff_Push_Token WHERE StaffID = ?");
        $tokenStmt->execute([$staffId]);
        $fcmToken = $tokenStmt->fetchColumn();
        if (!$fcmToken) {
            return;
        }

        $accessToken = fcm_access_token();
        if (!$accessToken) {
            return;
        }
        if (!file_exists(FIREBASE_SERVICE_ACCOUNT_PATH)) {
            return;
        }
        $sa = require FIREBASE_SERVICE_ACCOUNT_PATH;
        $projectId = $sa['project_id'];

        $payload = [
            'message' => [
                'token'        => $fcmToken,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => [
                    'title'               => $title,
                    'body'                => $body,
                    'related_entity_type' => (string)($relatedEntityType ?? ''),
                    'related_entity_id'   => (string)($relatedEntityId ?? ''),
                ],
                'android' => ['priority' => 'high'],
            ],
        ];

        $ch = curl_init("https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Throwable $e) {
        error_log('send_push_notification failed: ' . $e->getMessage());
    }
}
