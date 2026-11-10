<?php
// E-TICKET / RECEIPT
require __DIR__ . '/../config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT bk.*, u.full_name, u.email, u.contact_no,
               s.departure_time, s.status AS trip_status,
               b.bus_number, b.plate_number, b.bus_type,
               r.fare, r.travel_hours,
               o.location_name AS origin, d.location_name AS destination,
               p.payment_method, p.reference_no, p.gcash_number, p.card_last4,
               p.status AS payment_status, p.paid_at
        FROM bookings bk
        JOIN users u     ON u.user_id = bk.user_id
        JOIN schedules s ON s.schedule_id = bk.schedule_id
        JOIN buses b     ON b.bus_id = s.bus_id
        JOIN routes r    ON r.route_id = s.route_id
        JOIN locations o ON o.location_id = r.origin_id
        JOIN locations d ON d.location_id = r.destination_id
        LEFT JOIN payments p ON p.booking_id = bk.booking_id
        WHERE bk.booking_id = ?";
$params = [$id];
if ($_SESSION['role'] === 'customer') {
    $sql .= ' AND bk.user_id = ?';   // customers can only see their own tickets
    $params[] = $_SESSION['user_id'];
}
$st = $pdo->prepare($sql);
$st->execute($params);
$t = $st->fetch();

if (!$t) {
    flash('error', 'Booking not found.');
    redirect($_SESSION['role'] === 'admin' ? 'admin/bookings.php' : 'customer/bookings.php');
}

$title  = 'Ticket ' . $t['booking_code'];
$nav    = $_SESSION['role'];
$active = 'bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="no-print mb" style="display:flex;justify-content:space-between;max-width:620px;margin-left:auto;margin-right:auto">
  <a href="<?= url($nav === 'admin' ? 'admin/bookings.php' : 'customer/bookings.php') ?>"><i class="bi bi-arrow-left"></i> Back to bookings</a>
  <button class="btn btn-sm" onclick="window.print()"><i class="bi bi-printer"></i>Print Ticket</button>
</div>

<div class="card ticket">
  <div class="ticket-head">
    <div>
      <div class="muted">&#128652; Bus Ticket Booking &middot; E-Ticket</div>
      <div class="ticket-code"><?= e($t['booking_code']) ?></div>
    </div>
    <div><?= status_badge($t['status']) ?></div>
  </div>

  <div class="route mb"><?= e($t['origin']) ?><span class="arrow">&rarr;</span><?= e($t['destination']) ?></div>

  <div class="summary-row"><span class="muted">Passenger</span><strong><?= e($t['full_name']) ?></strong></div>
  <div class="summary-row"><span class="muted">Email</span><span><?= e($t['email']) ?></span></div>
  <?php if ($t['contact_no']): ?>
  <div class="summary-row"><span class="muted">Contact</span><span><?= e($t['contact_no']) ?></span></div>
  <?php endif; ?>
  <div class="summary-row"><span class="muted">Departure</span><strong><?= fmt_dt($t['departure_time']) ?></strong></div>
  <div class="summary-row"><span class="muted">Bus</span><span><?= e($t['bus_number']) ?> &middot; <?= e($t['plate_number']) ?> (<?= e($t['bus_type']) ?>)</span></div>
  <div class="summary-row"><span class="muted">Seats</span><span><?= (int)$t['num_seats'] ?></span></div>
  <div class="summary-row"><span class="muted">Booked on</span><span><?= fmt_dt($t['booked_at']) ?></span></div>

  <div class="summary-row mt"><span class="muted">Payment</span>
    <span>
      <?php if ($t['payment_method'] === 'gcash'): ?>
        GCash (<?= e($t['gcash_number']) ?>)
      <?php elseif ($t['payment_method'] === 'card'): ?>
        Card ending in <?= e($t['card_last4']) ?>
      <?php else: ?>
        &mdash;
      <?php endif; ?>
      <?= $t['payment_status'] ? status_badge($t['payment_status']) : '' ?>
    </span>
  </div>
  <?php if ($t['reference_no']): ?>
  <div class="summary-row"><span class="muted">Reference No.</span><span style="font-family:Consolas,monospace"><?= e($t['reference_no']) ?></span></div>
  <?php endif; ?>
  <div class="summary-row summary-total"><span>Total Paid</span><span><?= peso($t['total_amount']) ?></span></div>

  <p class="muted center mt">Please arrive at the terminal at least 30 minutes before departure and show this ticket.</p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
