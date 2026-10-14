<?php
/**
 * Template for config/firebase_service_account.php (gitignored, real
 * secret). Download this from Firebase Console -> Project Settings ->
 * Service Accounts -> Generate new private key, then fill in the values
 * below from that downloaded JSON.
 */
return [
    'type' => 'service_account',
    'project_id' => 'your-project-id',
    'private_key_id' => 'xxx',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nXXX\n-----END PRIVATE KEY-----\n",
    'client_email' => 'firebase-adminsdk-xxx@your-project-id.iam.gserviceaccount.com',
    'client_id' => 'xxx',
    'token_uri' => 'https://oauth2.googleapis.com/token',
];
