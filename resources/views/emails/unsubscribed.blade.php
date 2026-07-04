<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed · {{ config('brand.name') }}</title>
    <style>
        body { margin: 0; background: #f7f3ea; color: #1e1b16; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .card { max-width: 480px; margin: 12vh auto; background: #fff; border-top: 3px solid #c7952b; border-radius: 8px; padding: 40px 32px; text-align: center; box-shadow: 0 1px 3px rgba(30,27,22,.08); }
        .wordmark { color: #c7952b; font-weight: 800; letter-spacing: .5px; font-size: 20px; }
        p { color: #4b463d; line-height: 1.5; }
        .email { font-weight: 700; color: #1e1b16; }
    </style>
</head>
<body>
    <div class="card">
        <p class="wordmark">{{ config('brand.name') }}</p>
        <h1>You’re unsubscribed</h1>
        <p><span class="email">{{ $email }}</span> won’t receive any more marketing emails from us.</p>
        <p>Account and security emails (receipts, sign-in alerts) will still be sent.</p>
    </div>
</body>
</html>
