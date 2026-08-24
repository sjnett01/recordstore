<?php
require __DIR__.'/app-bootstrap.php';
$u = require_login();
$orderId = (int)($_GET['order'] ?? 0);

$st = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND status='pending' AND payment_provider='paypal' LIMIT 1");
$st->execute([$orderId, $u['id']]);
$order = $st->fetch();

if (!$order || empty($order['payment_reference'])) {
    $_SESSION['payment_flash'] = 'That payment can no longer be resumed.';
    redirect('account.php');
}

try {
    $paypalId = (string)$order['payment_reference'];
    $pp = paypal_request('GET', '/v2/checkout/orders/' . rawurlencode($paypalId));
    $status = strtoupper((string)($pp['status'] ?? ''));
    $pc = paypal_config();

    $finishPaid = static function (array $payload) use ($order, $paypalId, $pc): void {
        $d = paypal_capture_details($payload);
        if ($d['status'] !== 'COMPLETED' || $d['id'] === '') {
            throw new RuntimeException('PayPal has not completed this payment yet.');
        }
        if ($d['currency'] !== $pc['currency'] || $d['value'] !== paypal_money_value((int)$order['total_pence'])) {
            throw new RuntimeException('PayPal payment amount did not match the '.site_name().' order.');
        }
        mark_order_paid_from_paypal((int)$order['id'], $paypalId, $d['id'], $payload);
    };

    if ($status === 'COMPLETED') {
        $finishPaid($pp);
        $_SESSION['payment_flash'] = 'Payment completed successfully.';
        redirect('account.php');
    }

    if ($status === 'APPROVED') {
        $captured = paypal_request('POST', '/v2/checkout/orders/' . rawurlencode($paypalId) . '/capture', null, 'afd-resume-capture-' . $order['id']);
        $finishPaid($captured);
        $_SESSION['cart'] = [];
        $_SESSION['payment_flash'] = 'Payment completed successfully.';
        redirect('account.php');
    }

    $approve = '';
    foreach (($pp['links'] ?? []) as $link) {
        if (($link['rel'] ?? '') === 'payer-action' || ($link['rel'] ?? '') === 'approve') {
            $approve = (string)($link['href'] ?? '');
            break;
        }
    }

    if ($approve !== '') {
        header('Location: ' . $approve);
        exit;
    }

    if (in_array($status, ['VOIDED', 'CANCELLED'], true)) {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status='pending'")->execute([$orderId]);
    }

    throw new RuntimeException('PayPal no longer provides a payment approval link for this order.');
} catch (Throwable $e) {
    error_log(site_name() . ' PayPal resume error: ' . $e->getMessage());
    $_SESSION['payment_flash'] = 'This payment could not be resumed. Please return to your cart and start checkout again.';
    redirect('account.php');
}
