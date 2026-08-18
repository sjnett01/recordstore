<?php
require __DIR__.'/app-bootstrap.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'customer_cancel_pending_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            $_SESSION['account_flash'] = ['message' => 'Order not found.', 'error' => true];
            redirect('account.php');
        }

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT id,status FROM orders WHERE id=? AND user_id=? FOR UPDATE');
            $st->execute([$orderId, (int)$u['id']]);
            $order = $st->fetch();
            if (!$order) {
                throw new RuntimeException('Order not found.');
            }
            if ((string)$order['status'] !== 'pending') {
                throw new RuntimeException('This order can no longer be cancelled because its status has changed.');
            }

            $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'")
                ->execute([$orderId, (int)$u['id']]);
            $pdo->prepare('INSERT INTO order_status_log(order_id,old_status,new_status,admin_user_id,note) VALUES(?,?,?,?,?)')
                ->execute([$orderId, 'pending', 'cancelled', null, 'Cancelled by customer from My Account.']);
            $pdo->commit();
            $_SESSION['account_flash'] = ['message' => 'Order #'.$orderId.' has been cancelled.', 'error' => false];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['account_flash'] = ['message' => $e->getMessage(), 'error' => true];
        }
        redirect('account.php');
    }
}

$accountFlash = $_SESSION['account_flash'] ?? null;
unset($_SESSION['account_flash']);

$st = $pdo->prepare(
    "SELECT
        o.id AS order_id,
        o.total_pence,
        o.created_at,
        o.paid_at,
        o.status,
        o.payment_provider,
        o.payment_reference,
        oi.id AS item_id,
        oi.download_limit,
        oi.downloads_used,
        t.title,
        t.mix_name,
        a.name AS artist_name
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     JOIN tracks t ON t.id = oi.track_id
     JOIN artists a ON a.id = t.artist_id
     WHERE o.user_id = ?
       AND (
            o.status = 'paid'
            OR (
                o.status = 'pending'
                AND o.payment_provider = 'paypal'
                AND o.payment_reference IS NOT NULL
                AND o.payment_reference <> ''
            )
       )
     ORDER BY o.id DESC, oi.id ASC"
);
$st->execute([$u['id']]);
$rows = $st->fetchAll();

$orders = [];
foreach ($rows as $row) {
    $id = (int)$row['order_id'];
    if (!isset($orders[$id])) {
        $orders[$id] = [
            'id' => $id,
            'total_pence' => (int)$row['total_pence'],
            'created_at' => $row['created_at'],
            'paid_at' => $row['paid_at'],
            'status' => $row['status'],
            'payment_provider' => $row['payment_provider'],
            'payment_reference' => $row['payment_reference'],
            'items' => [],
        ];
    }
    $orders[$id]['items'][] = $row;
}

layout_header('My Account');
?>
<div class="section-title account-title">
    <div>
        <span class="kicker">YOUR ACCOUNT</span>
        <h1>My Account</h1>
    </div>
    <div class="account-title-actions"><a href="favourites"><span class="account-action-glyph">♥</span>Favourites</a><a href="logout"><span class="account-action-glyph">↪</span>Log out</a></div>
</div>

<?php if ($accountFlash): ?>
    <div class="panel account-flash <?=!empty($accountFlash['error']) ? 'is-error' : 'is-success'?>">
        <?=e((string)$accountFlash['message'])?>
    </div>
<?php endif; ?>

<section class="panel account-history">
    <div class="account-history-head">
        <div>
            <h2>Purchase history &amp; downloads</h2>
            <p class="muted">Paid purchases appear here. Incomplete PayPal orders are only shown when payment can still be resumed.</p>
        </div>
        <span class="account-order-count"><?=count($orders)?> <?=count($orders) === 1 ? 'order' : 'orders'?></span>
    </div>

    <?php if (!$orders): ?>
        <div class="account-empty">
            <h3>No completed purchases yet</h3>
            <p class="muted">Tracks you purchase will appear here with their download allowance.</p>
            <a class="button" href="tracks">Browse tracks</a>
        </div>
    <?php else: ?>
        <div class="account-orders">
            <?php foreach ($orders as $order):
                $isPaid = $order['status'] === 'paid';
                $date = $isPaid && $order['paid_at'] ? $order['paid_at'] : $order['created_at'];
            ?>
                <article class="account-order <?= $isPaid ? 'is-paid' : 'is-pending' ?>">
                    <header class="account-order-head">
                        <div class="account-order-id">
                            <span>Order</span>
                            <strong>#<?=e((string)$order['id'])?></strong>
                        </div>
                        <div class="account-order-meta">
                            <span class="status-badge <?= $isPaid ? 'paid' : 'pending' ?>"><?= $isPaid ? 'Paid' : 'Payment pending' ?></span>
                            <span><?=e(date('j M Y, H:i', strtotime((string)$date)))?></span>
                        </div>
                        <div class="account-order-total">
                            <span>Total</span>
                            <strong><?=money((int)$order['total_pence'])?></strong>
                        </div>                        <?php if ($isPaid): ?><a class="button tiny account-receipt-link" href="receipt/<?=$order['id']?>">Receipt</a><?php endif; ?>
                        <?php if (!$isPaid): ?>
                            <div class="account-pending-actions">
                                <a class="button small account-resume" href="paypal-resume?order=<?=$order['id']?>">Complete payment</a>
                                <form method="post" data-no-async data-cancel-order-form>
                                    <input type="hidden" name="order_id" value="<?=$order['id']?>">
                                    <input type="hidden" name="action" value="customer_cancel_pending_order">
                                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                    <button class="button small danger" type="button" data-cancel-order-open data-order-id="<?=e((string)$order['id'])?>">Cancel order</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="account-items">
                        <?php foreach ($order['items'] as $item): ?>
                            <div class="account-item-row">
                                <div class="account-item-track">
                                    <strong><?=e($item['title'])?><?=!empty($item['mix_name']) ? ' <span>(' . e($item['mix_name']) . ')</span>' : ''?></strong>
                                    <small><?=e($item['artist_name'])?></small>
                                </div>
                                <div class="account-download-count" data-download-count>
                                    <strong data-download-value><?=e((string)$item['downloads_used'])?> / <?=e((string)$item['download_limit'])?></strong>
                                    <span>downloads used</span>
                                </div>
                                <div class="account-item-action">
                                    <?php if ($isPaid && (int)$item['downloads_used'] < (int)$item['download_limit']): ?>
                                        <a class="button tiny download-action" data-item="<?=(int)$item['item_id']?>" href="download.php?item=<?=(int)$item['item_id']?>" data-no-async>Download again</a>
                                    <?php elseif ($isPaid): ?>
                                        <span class="muted">Limit reached</span>
                                    <?php else: ?>
                                        <span class="muted">Available after payment</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<dialog class="recordstore-modal" id="cancel-order-modal" aria-labelledby="cancel-order-modal-title">
    <div class="recordstore-modal-card">
        <div class="recordstore-modal-icon" aria-hidden="true">!</div>
        <div class="recordstore-modal-copy">
            <span class="kicker">Pending order</span>
            <h2 id="cancel-order-modal-title">Cancel order <span data-cancel-order-number></span>?</h2>
            <p>This will close the pending order and you will no longer be able to complete its payment. No payment has been taken.</p>
        </div>
        <div class="recordstore-modal-actions">
            <button class="button secondary" type="button" data-cancel-order-close>Keep order</button>
            <button class="button danger" type="button" data-cancel-order-confirm>Cancel order</button>
        </div>
    </div>
</dialog>

</section>
<?php layout_footer();
