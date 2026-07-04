<?php

/*
 * Registry of every customizable transactional email. Each entry describes the
 * placeholders available for that event and the starting content shown when a
 * super_admin first opens the editor (App\Notifications\Concerns\CustomizableMail
 * substitutes these same tokens into a saved override at send time). Framework
 * emails (verify-email, password-reset) aren't listed here — they're rendered by
 * Laravel's own notification classes and stay code-only.
 */
return [
    'welcome' => [
        'label' => 'Welcome (post-verify)',
        'category' => 'Auth',
        'trigger' => 'User verifies their email address',
        'placeholders' => [
            '{{brand_name}}' => 'Platform name',
            '{{brand_tagline}}' => 'Locked platform tagline',
            '{{brand_url}}' => 'Link to the app',
        ],
        'default' => [
            'subject' => 'Welcome to {{brand_name}}',
            'greeting' => 'Ẹ ku àbọ̀ — welcome!',
            'body' => "Your account is verified and ready. {{brand_tagline}}\n\nSet up a learner profile and pick a language to begin.",
            'action_text' => 'Start learning',
            'action_url' => '{{brand_url}}',
        ],
    ],
    'login_alert' => [
        'label' => 'New device sign-in alert',
        'category' => 'Auth',
        'trigger' => 'Sign-in from a device not seen before for that account',
        'placeholders' => [
            '{{brand_name}}' => 'Platform name',
            '{{brand_url}}' => 'Link to the app',
            '{{ip}}' => 'IP address of the sign-in',
            '{{device}}' => 'Device/browser string',
        ],
        'default' => [
            'subject' => 'New sign-in to your {{brand_name}} account',
            'greeting' => 'New sign-in detected',
            'body' => "Your account was just signed into from a device we haven't seen before.\n\nIP address: {{ip}}\nDevice: {{device}}\n\nIf this was you, no action is needed.\nIf it wasn't, reset your password right away.",
            'action_text' => 'Reset your password',
            'action_url' => '{{brand_url}}/forgot-password',
        ],
    ],
    'wallet_funded' => [
        'label' => 'Wallet top-up receipt',
        'category' => 'Billing',
        'trigger' => 'A wallet funding payment settles',
        'placeholders' => [
            '{{brand_name}}' => 'Platform name',
            '{{brand_url}}' => 'Link to the app',
            '{{amount}}' => 'Amount credited, formatted (e.g. ₦5,000.00)',
        ],
        'default' => [
            'subject' => 'Your {{brand_name}} wallet has been topped up',
            'greeting' => 'Payment received',
            'body' => "₦{{amount}} has been added to your wallet.\n\nThis message is your receipt. You can spend it on subscriptions and family features.",
            'action_text' => 'Open your wallet',
            'action_url' => '{{brand_url}}/wallet',
        ],
    ],
    'subscription_activated' => [
        'label' => 'Subscription activated',
        'category' => 'Billing',
        'trigger' => 'A subscription payment is confirmed by the gateway webhook',
        'placeholders' => [
            '{{plan_name}}' => 'Subscribed plan name',
            '{{amount}}' => 'Plan price, formatted (e.g. ₦2,500.00)',
        ],
        'default' => [
            'subject' => 'Your Mahadum.360 subscription is active',
            'greeting' => 'Thank you!',
            'body' => "Your {{plan_name}} plan is now active.\n\nAmount: ₦{{amount}}\n\nThis message is your receipt.",
            'action_text' => null,
            'action_url' => null,
        ],
    ],
    'payment_failed' => [
        'label' => 'Payment failed (dunning)',
        'category' => 'Billing',
        'trigger' => 'A subscription charge fails',
        'placeholders' => [
            '{{brand_name}}' => 'Platform name',
            '{{brand_url}}' => 'Link to the app',
            '{{plan_name}}' => 'Subscribed plan name',
        ],
        'default' => [
            'subject' => 'Your {{brand_name}} payment didn\'t go through',
            'greeting' => 'Payment problem',
            'body' => "We couldn't process the payment for your {{plan_name}} subscription.\n\nPlease update your payment method to keep your access without a lapse.",
            'action_text' => 'Retry payment',
            'action_url' => '{{brand_url}}/billing',
        ],
    ],
    'subscription_renewal_reminder' => [
        'label' => 'Renewal reminder',
        'category' => 'Billing',
        'trigger' => 'Scheduled reminder before a subscription renews',
        'placeholders' => [
            '{{plan_name}}' => 'Subscribed plan name',
            '{{renews_on}}' => 'Renewal date, formatted (e.g. 12 Jul 2026)',
            '{{amount}}' => 'Plan price, formatted (e.g. ₦2,500.00)',
        ],
        'default' => [
            'subject' => 'Your Mahadum.360 plan renews soon',
            'greeting' => 'Hi there,',
            'body' => "Your {{plan_name}} plan renews on {{renews_on}}.\n\nAmount: ₦{{amount}}\n\nMake sure your payment method is ready so your access continues uninterrupted.",
            'action_text' => null,
            'action_url' => null,
        ],
    ],
    'promo_redeemed' => [
        'label' => 'Promo code redeemed',
        'category' => 'Billing',
        'trigger' => 'A promo code is applied at checkout',
        'placeholders' => [
            '{{brand_url}}' => 'Link to the app',
            '{{code}}' => 'Promo code',
            '{{plan_name}}' => 'Subscribed plan name',
        ],
        'default' => [
            'subject' => 'Your promo code was applied',
            'greeting' => 'Discount applied 🎉',
            'body' => "Promo code {{code}} was applied to your {{plan_name}} subscription.\n\nThis message is your confirmation.",
            'action_text' => 'View your billing',
            'action_url' => '{{brand_url}}/billing',
        ],
    ],
    'telco_billing_receipt' => [
        'label' => 'Airtime billing receipt',
        'category' => 'Billing',
        'trigger' => 'Daily telco (airtime) billing succeeds',
        'placeholders' => [
            '{{amount}}' => 'Amount charged, formatted (e.g. ₦50.00)',
            '{{operator}}' => 'Telco operator (e.g. MTN)',
            '{{plan_name}}' => 'Subscribed plan name',
        ],
        'default' => [
            'subject' => 'Your airtime billing receipt',
            'greeting' => 'Payment received',
            'body' => "₦{{amount}} was charged to your {{operator}} airtime balance for your {{plan_name}} plan.\n\nThis message is your receipt.",
            'action_text' => null,
            'action_url' => null,
        ],
    ],
    'payout_approved' => [
        'label' => 'Payout approved',
        'category' => 'Referrals',
        'trigger' => 'An admin approves a referral payout',
        'placeholders' => [
            '{{amount}}' => 'Payout amount, formatted (e.g. ₦15,000.00)',
        ],
        'default' => [
            'subject' => 'Your payout was approved',
            'greeting' => null,
            'body' => 'Your payout of ₦{{amount}} has been approved and is being processed.',
            'action_text' => null,
            'action_url' => null,
        ],
    ],
    'organization_seat_assigned' => [
        'label' => 'School invite / seat assigned',
        'category' => 'Schools',
        'trigger' => 'An org admin account is created for a school',
        'placeholders' => [
            '{{brand_name}}' => 'Platform name',
            '{{brand_url}}' => 'Link to the app',
            '{{organization_name}}' => 'School/organization name',
            '{{role}}' => 'Role granted (e.g. School Admin)',
        ],
        'default' => [
            'subject' => "You've been added to {{organization_name}} on {{brand_name}}",
            'greeting' => 'Welcome to the team',
            'body' => "You've been granted {{role}} access to {{organization_name}} on {{brand_name}}.\n\nA separate email with a link to set your password is on its way — use it to activate your account.",
            'action_text' => 'Visit {{brand_name}}',
            'action_url' => '{{brand_url}}/login',
        ],
    ],
    'chore_approved' => [
        'label' => 'Chore approved (coins released)',
        'category' => 'Family',
        'trigger' => 'A parent approves a chore',
        'placeholders' => [
            '{{brand_url}}' => 'Link to the app',
            '{{chore_title}}' => 'Chore title',
            '{{coins}}' => 'Coins released',
        ],
        'default' => [
            'subject' => 'Your chore was approved 🎉',
            'greeting' => 'Great job!',
            'body' => 'Your chore "{{chore_title}}" was approved.\n\n{{coins}} coins have been added to your wallet.',
            'action_text' => 'View your wallet',
            'action_url' => '{{brand_url}}/wallet',
        ],
    ],
    'assignment_approved' => [
        'label' => 'Assignment approved (coins released)',
        'category' => 'Family',
        'trigger' => 'A parent approves an assignment submission',
        'placeholders' => [
            '{{brand_url}}' => 'Link to the app',
            '{{coins}}' => 'Coins released',
        ],
        'default' => [
            'subject' => 'Your assignment was approved 🎉',
            'greeting' => 'Great job!',
            'body' => 'Your submitted assignment has been reviewed and approved.\n\n{{coins}} coins have been added to your wallet.',
            'action_text' => 'View your wallet',
            'action_url' => '{{brand_url}}/wallet',
        ],
    ],
    'invoice_paid' => [
        'label' => 'School invoice paid',
        'category' => 'Schools',
        'trigger' => 'A school invoice (seats/registration) is settled by the gateway webhook',
        'placeholders' => [
            '{{brand_url}}' => 'Link to the app',
            '{{invoice_id}}' => 'Invoice number',
            '{{amount}}' => 'Amount received, formatted (e.g. ₦45,000.00)',
        ],
        'default' => [
            'subject' => 'Your school invoice was paid',
            'greeting' => 'Payment received',
            'body' => "₦{{amount}} was received for invoice #{{invoice_id}}.\n\nThis message is your receipt.",
            'action_text' => 'View invoices',
            'action_url' => '{{brand_url}}/school/invoices',
        ],
    ],
    'class_assignment_graded' => [
        'label' => 'Class assignment graded',
        'category' => 'Schools',
        'trigger' => 'A teacher grades a class assignment submission',
        'placeholders' => [
            '{{brand_url}}' => 'Link to the app',
            '{{assignment_title}}' => 'Assignment title',
            '{{coins}}' => 'Coins released (0 if not passed)',
        ],
        'default' => [
            'subject' => 'Your assignment was graded',
            'greeting' => 'Assignment graded',
            'body' => 'Your teacher graded "{{assignment_title}}".\n\n{{coins}} coins have been added to your wallet.',
            'action_text' => 'View assignment',
            'action_url' => '{{brand_url}}/assignments',
        ],
    ],
    'support_reply' => [
        'label' => 'Support ticket reply',
        'category' => 'Support',
        'trigger' => 'Support staff reply to a ticket',
        'placeholders' => [
            '{{brand_url}}' => 'Link to the app',
            '{{ticket_subject}}' => "The ticket's subject line",
            '{{reply}}' => "Support's reply message",
        ],
        'default' => [
            'subject' => 'Re: {{ticket_subject}}',
            'greeting' => 'We\'ve replied to your request',
            'body' => "\"{{ticket_subject}}\"\n\n{{reply}}\n\nReply from that page and we'll pick it back up.",
            'action_text' => 'View the conversation',
            'action_url' => '{{brand_url}}/support',
        ],
    ],
];
