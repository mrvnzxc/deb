<?php
// CUSTOMER - booking history + cancel
require __DIR__ . '/../config.php';
require_login('customer');

// Cancel a booking (only before departure)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    check_csrf();
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT bk.booking_id, bk.num_seats, bk.schedule_id
                         FROM bookings bk JOIN schedules s ON s.schedule_id = bk.schedule_id
                         WHERE bk.booking_id = ? AND bk.user_id = ? AND bk.status = 'confirmed'
                           AND s.departure_time > NOW() AND s.status IN ('open','full')
                         FOR UPDATE");
    $st->execute([$bookingId, $_SESSION['user_id']]);
    $bk = $st->fetch();

    if ($bk) {
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?")->execute([$bookingId]);
        $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE booking_id = ? AND status = 'paid'")->execute([$bookingId]);
        $pdo->prepare("UPDATE schedules SET available_seats = available_seats + ?, status = 'open' WHERE schedule_id = ?")
            ->execute([$bk['num_seats'], $bk['schedule_id']]);
        $pdo->commit();
        flash('success', 'Your booking was cancelled and your payment will be refunded.');
    } else {
        $pdo->rollBack();
        flash('error', 'This booking can no longer be cancelled.');
    }
    redirect('customer/bookings.php');
}

$st = $pdo->prepare("SELECT bk.booking_id, bk.booking_code, bk.num_seats, bk.total_amount, bk.status, bk.booked_at,
                            s.departure_time, s.status AS trip_status,
                            b.bus_number, o.location_name AS origin, d.location_name AS destination,
                            p.payment_method, p.status AS payment_status
                     FROM bookings bk
                     JOIN schedules s ON s.schedule_id = bk.schedule_id
                     JOIN buses b     ON b.bus_id = s.bus_id
                     JOIN routes r    ON r.route_id = s.route_id
                     JOIN locations o ON o.location_id = r.origin_id
                     JOIN locations d ON d.location_id = r.destination_id
                     LEFT JOIN payments p ON p.booking_id = bk.booking_id
                     WHERE bk.user_id = ?
                     ORDER BY bk.booked_at DESC");
$st->execute([$_SESSION['user_id']]);
$bookings = $st->fetchAll();

$title  = 'My Bookings';
$nav    = 'customer';
$active = 'bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-ticket-perforated"></i></span>My Bookings</h1>
    <p class="subtitle">Your tickets and booking history</p>
  </div>
  <a href="<?= url('customer/dashboard.php') ?>" class="btn"><i class="bi bi-plus-lg"></i>Book a Trip</a>
</div>

<div class="card table-wrap">
  <table>
    <thead>
      <tr>
        <th>Booking Code</th><th>Route</th><th>Departure</th><th>Bus</th>
        <th>Seats</th><th>Amount</th><th>Payment</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$bookings): ?>
      <tr><td colspan="9" class="empty">You have no bookings yet. <a href="<?= url('customer/dashboard.php') ?>">Find a bus</a></td></tr>
    <?php endif; ?>
    <?php foreach ($bookings as $b):
        $canCancel = $b['status'] === 'confirmed' && in_array($b['trip_status'], ['open', 'full'], true)
                     && strtotime($b['departure_time']) > time(); ?>
      <tr>
        <td><strong><?= e($b['booking_code']) ?></strong></td>
        <td><?= e($b['origin']) ?> &rarr; <?= e($b['destination']) ?></td>
        <td><?= fmt_dt($b['departure_time']) ?></td>
        <td><?= e($b['bus_number']) ?></td>
        <td><?= (int)$b['num_seats'] ?></td>
        <td><?= peso($b['total_amount']) ?></td>
        <td><?= e(strtoupper($b['payment_method'] ?? '-')) ?> <?= $b['payment_status'] ? status_badge($b['payment_status']) : '' ?></td>
        <td><?= status_badge($b['trip_status'] === 'departed' && $b['status'] === 'confirmed' ? 'departed' : $b['status']) ?></td>
        <td style="white-space:nowrap">
          <a href="<?= url('customer/ticket.php') ?>?id=<?= (int)$b['booking_id'] ?>" class="btn btn-sm btn-outline btn-icon" title="View ticket" aria-label="View ticket"><i class="bi bi-ticket-perforated"></i></a>
          <?php if ($canCancel): ?>
            <form method="post" class="inline-form" data-confirm="Cancel booking <?= e($b['booking_code']) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
              <button class="btn btn-sm btn-danger btn-icon" title="Cancel booking" aria-label="Cancel booking"><i class="bi bi-x-circle"></i></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
