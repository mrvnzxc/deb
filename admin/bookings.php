<?php
// ADMIN - all bookings, with search and filters
require __DIR__ . '/../config.php';
require_login('admin');

$q          = trim($_GET['q'] ?? '');
$status     = $_GET['status'] ?? '';
$method     = $_GET['method'] ?? '';
$scheduleId = (int)($_GET['schedule_id'] ?? 0);

$sql = "SELECT bk.booking_id, bk.booking_code, bk.num_seats, bk.total_amount, bk.status, bk.booked_at,
               u.full_name, u.email, s.departure_time, b.bus_number,
               o.location_name AS origin, d.location_name AS destination,
               p.payment_method, p.reference_no, p.status AS payment_status
        FROM bookings bk
        JOIN users u     ON u.user_id = bk.user_id
        JOIN schedules s ON s.schedule_id = bk.schedule_id
        JOIN buses b     ON b.bus_id = s.bus_id
        JOIN routes r    ON r.route_id = s.route_id
        JOIN locations o ON o.location_id = r.origin_id
        JOIN locations d ON d.location_id = r.destination_id
        LEFT JOIN payments p ON p.booking_id = bk.booking_id
        WHERE 1 = 1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (bk.booking_code LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, ['pending', 'confirmed', 'cancelled'], true)) { $sql .= ' AND bk.status = ?';         $params[] = $status; }
if (in_array($method, ['gcash', 'card'], true))                     { $sql .= ' AND p.payment_method = ?';  $params[] = $method; }
if ($scheduleId)                                                    { $sql .= ' AND bk.schedule_id = ?';    $params[] = $scheduleId; }
$sql .= ' ORDER BY bk.booked_at DESC';

$st = $pdo->prepare($sql);
$st->execute($params);
$bookings = $st->fetchAll();

$totalSeats = 0;
$totalSales = 0;
foreach ($bookings as $b) {
    if ($b['status'] !== 'cancelled') {
        $totalSeats += $b['num_seats'];
        $totalSales += $b['total_amount'];
    }
}

$title  = 'Bookings';
$nav    = 'admin';
$active = 'bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-journal-text"></i></span>Bookings</h1>
    <p class="subtitle">
      <?= count($bookings) ?> booking(s) &middot; <?= $totalSeats ?> seat(s) &middot; <?= peso($totalSales) ?> (excluding cancelled)
      <?php if ($scheduleId): ?> &middot; filtered by trip #<?= $scheduleId ?><?php endif; ?>
    </p>
  </div>
</div>

<div class="card">
  <form method="get" class="row">
    <?php if ($scheduleId): ?><input type="hidden" name="schedule_id" value="<?= $scheduleId ?>"><?php endif; ?>
    <div class="form-group" style="flex:2"><label>Search</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Booking code, name or email"></div>
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['confirmed', 'pending', 'cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Payment</label>
      <select name="method">
        <option value="">All</option>
        <option value="gcash" <?= $method === 'gcash' ? 'selected' : '' ?>>GCash</option>
        <option value="card" <?= $method === 'card' ? 'selected' : '' ?>>Card</option>
      </select>
    </div>
    <div class="form-group" style="flex:0 0 auto">
      <button class="btn"><i class="bi bi-funnel"></i>Filter</button>
      <a href="<?= url('admin/bookings.php') ?>" class="btn btn-outline"><i class="bi bi-arrow-counterclockwise"></i>Reset</a>
    </div>
  </form>
</div>

<div class="card table-wrap">
  <table>
    <thead>
      <tr><th>Code</th><th>Customer</th><th>Route</th><th>Departure</th><th>Bus</th><th>Seats</th><th>Amount</th><th>Payment</th><th>Booked</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php if (!$bookings): ?><tr><td colspan="10" class="empty">No bookings found.</td></tr><?php endif; ?>
    <?php foreach ($bookings as $b): ?>
      <tr>
        <td><a href="<?= url('customer/ticket.php') ?>?id=<?= (int)$b['booking_id'] ?>"><strong><?= e($b['booking_code']) ?></strong></a></td>
        <td><?= e($b['full_name']) ?><br><span class="muted"><?= e($b['email']) ?></span></td>
        <td><?= e($b['origin']) ?> &rarr; <?= e($b['destination']) ?></td>
        <td><?= fmt_dt($b['departure_time']) ?></td>
        <td><?= e($b['bus_number']) ?></td>
        <td><?= (int)$b['num_seats'] ?></td>
        <td><?= peso($b['total_amount']) ?></td>
        <td>
          <?= e(strtoupper($b['payment_method'] ?? '-')) ?> <?= $b['payment_status'] ? status_badge($b['payment_status']) : '' ?>
          <?php if ($b['reference_no']): ?><br><span class="muted"><?= e($b['reference_no']) ?></span><?php endif; ?>
        </td>
        <td><?= fmt_dt($b['booked_at']) ?></td>
        <td><?= status_badge($b['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
