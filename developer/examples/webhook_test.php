<?php
/**
 * Test tool for the example payment plugin: sends a signed payment report
 * (the webhook) to your site, so you can try the flow without a payment service.
 *
 * Start it with:   php -S localhost:8081 webhook_test.php
 * Then open:       http://localhost:8081
 *
 * Development tool only: it refuses to run on anything but localhost.
 */

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Local use only.');
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$in = [
    'site'     => $_POST['site'] ?? 'https://your-site',
    'order'    => $_POST['order'] ?? '',
    'secret'   => $_POST['secret'] ?? '',
    'amount'   => $_POST['amount'] ?? '12.50',
    'status'   => $_POST['status'] ?? 'paid',
    'provider' => $_POST['provider'] ?? 'example',
];

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order = (int) $in['order'];
    $body  = json_encode([
        'id'       => 'ex_' . $order,
        'order'    => $order,
        'status'   => $in['status'],
        'amount'   => $in['amount'],
        'currency' => 'EUR',
        'method'   => 'example',
    ], JSON_UNESCAPED_SLASHES);

    $sig = hash_hmac('sha256', $body, $in['secret']);
    $url = rtrim($in['site'], '/') . '/index.php?' . http_build_query([
        'option'     => 'com_ticketstation',
        'controller' => 'payment',
        'task'       => 'webhook',
        'provider'   => $in['provider'],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['X-Signature: ' . $sig],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        // Local Laragon sites often use a self-signed certificate.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $response = curl_exec($ch);

    $result = [
        'url'      => $url,
        'body'     => $body,
        'sig'      => $sig,
        'http'     => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'error'    => $response === false ? curl_error($ch) : '',
        'response' => (string) $response,
    ];
    curl_close($ch);
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Ticketstation webhook test</title>
<style>
    body { font: 15px system-ui, sans-serif; max-width: 700px; margin: 2em auto; padding: 0 1em; }
    label { display: block; margin-top: .8em; font-weight: 600; }
    input, select { width: 100%; padding: .4em; box-sizing: border-box; }
    button { margin-top: 1.2em; padding: .5em 1.4em; }
    pre { background: #f4f4f4; padding: .8em; overflow: auto; white-space: pre-wrap; word-break: break-all; }
</style>
<h1>Webhook test</h1>
<form method="post">
    <label>Site URL <input name="site" value="<?= $h($in['site']) ?>"></label>
    <label>Order code (order id) <input name="order" value="<?= $h($in['order']) ?>" required></label>
    <label>Secret of the plugin <input name="secret" value="<?= $h($in['secret']) ?>" required></label>
    <label>Amount <input name="amount" value="<?= $h($in['amount']) ?>"></label>
    <label>Status
        <select name="status">
            <?php foreach (['paid', 'failed', 'cancelled', 'expired'] as $s) : ?>
                <option<?= $s === $in['status'] ? ' selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Provider name <input name="provider" value="<?= $h($in['provider']) ?>"></label>
    <button>Send report</button>
</form>
<?php if ($result) : ?>
    <h2>Result: HTTP <?= $h($result['http']) ?></h2>
    <?php if ($result['error']) : ?><p><strong>curl error:</strong> <?= $h($result['error']) ?></p><?php endif; ?>
    <p>Response</p><pre><?= $h($result['response']) ?></pre>
    <p>Sent to</p><pre><?= $h($result['url']) ?></pre>
    <p>Body</p><pre><?= $h($result['body']) ?></pre>
    <p>X-Signature</p><pre><?= $h($result['sig']) ?></pre>
<?php endif; ?>
