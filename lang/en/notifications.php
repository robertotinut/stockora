<?php

return [
    'invitation' => [
        'subject' => 'You are invited to join :company on Stockora',
        'greeting' => 'Hello!',
        'line1' => ':inviter has invited you to join **:company** on Stockora with the **:role** role.',
        'personal_note' => 'Personal note: ":note"',
        'action' => 'Review & Accept Invitation',
        'expires' => 'This invitation expires on :date.',
        'ignore' => 'If you did not expect this invitation, you can safely ignore this message.',
    ],

    'welcome' => [
        'subject' => 'Your :company workspace on Stockora is ready',
        'greeting' => 'Welcome to Stockora, :name!',
        'line1' => 'A dedicated company workspace has been provisioned for **:company** (`:code`).',
        'action' => 'Set Your Password & Sign In',
        'expires' => 'This one-time account setup link expires in 60 minutes.',
        'ignore' => 'If you did not request this account, please contact Stockora support immediately.',
    ],

    'password_reset' => [
        'subject' => 'Reset Your Stockora Password',
        'greeting' => 'Hello :name,',
        'line1' => 'We received a request to reset the password for your Stockora account (:email).',
        'action' => 'Reset Password',
        'expires' => 'This password reset link will expire in :minutes minutes and can only be used once.',
        'ignore' => 'If you did not request a password reset, you can safely ignore this email — your password will remain unchanged.',
    ],
];
