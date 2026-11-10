<?php
// ADMIN - trips (which bus runs which route and when)
require __DIR__ . '/../config.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_trip') {
        $busId   = (int)($_POST['bus_id'] ?? 0);
        $routeId = (int)($_POST['route_id'] ?? 0);
        $depart  = str_replace('T', ' ', $_POST['departure_time'] ?? '');

        $bus = $pdo->prepare('SELECT total_seats FROM buses WHERE bus_id = ?');
        $bus->execute([$busId]);
        $bus = $bus->fetch();

        $route = $pdo->prepare('SELECT route_id FROM routes WHERE route_id = ?');
        $route->execute([$routeId]);

        if (!$bus || !$route->fetch()) {
            flash('error', 'Please choose a valid bus and route.');
        } elseif (!strtotime($depart) || strtotime($depart) <= time()) {
            flash('error', 'Departure must be a future date and time.');
        } else {
            // Same bus can't have another trip within 1 hour of this one
            $clash = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE bus_id = ? AND status IN ('open','full')
                                    AND ABS(TIMESTAMPDIFF(MINUTE, departure_time, ?)) < 60");
            $clash->execute([$busId, $depart]);
            if ($clash->fetchColumn() > 0) {
                flash('error', 'This bus already has a trip within 1 hour of that time.');
            } else {
                $pdo->prepare("INSERT INTO schedules (bus_id, route_id, departure_time, available_seats, status) VALUES (?, ?, ?, ?, 'open')")
                    ->execute([$busId, $routeId, date('Y-m-d H:i:s', strtotime($depart)), $bus['total_seats']]);
                flash('success', 'Trip added. Customers can now book it.');
            }
        }
    } elseif ($action === 'cancel_trip') {
        $sid = (int)($_POST['schedule_id'] ?? 0);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE payments p JOIN bookings b ON b.booking_id = p.booking_id
                       SET p.status = 'refunded'
                       WHERE b.schedule_id = ? AND b.status = 'confirmed' AND p.status = 'paid'")->execute([$sid]);
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE schedule_id = ? AND status <> 'cancelled'")->execute([$sid]);
        $pdo->prepare("UPDATE schedules SET status = 'cancelled' WHERE schedule_id = ? AND status IN ('open','full')")->execute([$sid]);
        $pdo->commit();
        flash('success', 'Trip cancelled. All its bookings were cancelled and marked for refund.');
    }
    redirect('admin/schedules.php');
}

$buses  = $pdo->query("SELECT bus_id, bus_number, bus_type, total_seats FROM buses WHERE status = 'available' ORDER BY bus_number")->fetchAll();
$routes = $pdo->query("SELECT r.route_id, r.fare, o.location_name AS origin, d.location_name AS destination
                       FROM routes r
                       JOIN locations o ON o.location_id = r.origin_id
                       JOIN locations d ON d.location_id = r.destination_id
                       ORDER BY o.location_name, d.location_name")->fetchAll();

$filter = $_GET['filter'] ?? 'upcoming';
$sql = "SELECT v.*, s.status, b.status AS bus_status
        FROM vw_booking_summary v
        JOIN schedules s ON s.schedule_id = v.schedule_id
        JOIN buses b     ON b.bus_id = s.bus_id";
$sql .= $filter === 'all' ? ' ORDER BY v.departure_time DESC' : " WHERE s.status IN ('open','full') ORDER BY v.departure_time";
$trips = $pdo->query($sql)->fetchAll();

$title  = 'Trips';
$nav    = 'admin';
$active = 'trips';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-calendar-event"></i></span>Trips</h1>
    <p class="subtitle">Schedule buses on routes. Seats are taken from the bus's total seats.</p>
  </div>
</div>

<div class="card">
  <h2><span class="h-icon"><i class="bi bi-plus-lg"></i></span>Add Trip</h2>
  <?php if (!$buses || !$routes): ?>
    <div class="alert alert-error">You need at least one <a href="<?= url('admin/buses.php') ?>">available bus</a> and one <a href="<?= url('admin/routes.php') ?>">route</a> first.</div>
  <?php else: ?>
  <form method="post" class="row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_trip">
    <div class="form-group">
      <label>Bus</label>
      <select name="bus_id" required>
        <?php foreach ($buses as $b): ?>
          <option value="<?= $b['bus_id'] ?>"><?= e($b['bus_number']) ?> - <?= e($b['bus_type']) ?> (<?= (int)$b['total_seats'] ?> seats)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Route</label>
      <select name="route_id" required>
        <?php foreach ($routes as $r): ?>
          <option value="<?= $r['route_id'] ?>"><?= e($r['origin']) ?> &rarr; <?= e($r['destination']) ?> (&#8369;<?= number_format($r['fare'], 2) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Departure</label>
      <input type="datetime-local" name="departure_time" min="<?= date('Y-m-d\TH:i') ?>" required>
    </div>
    <div class="form-group" style="flex:0 0 auto"><button class="btn"><i class="bi bi-plus-lg"></i>Add Trip</button></div>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <div class="page-head" style="margin-bottom:12px">
    <h2 style="margin:0"><span class="h-icon"><i class="bi bi-calendar3"></i></span><?= $filter === 'all' ? 'All Trips' : 'Upcoming Trips' ?></h2>
    <div>
      <a href="<?= url('admin/schedules.php') ?>" class="btn btn-sm <?= $filter === 'all' ? 'btn-outline' : '' ?>"><i class="bi bi-calendar-event"></i>Upcoming</a>
      <a href="<?= url('admin/schedules.php') ?>?filter=all" class="btn btn-sm <?= $filter === 'all' ? '' : 'btn-outline' ?>"><i class="bi bi-calendar3"></i>All</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Bus</th><th>Route</th><th>Departure</th><th>Fare</th><th>Bookings</th><th>Seats Left</th><th>Sales</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$trips): ?><tr><td colspan="9" class="empty">No trips found.</td></tr><?php endif; ?>
      <?php foreach ($trips as $t): ?>
        <tr>
          <td>
            <strong><?= e($t['bus_number']) ?></strong>
            <?php if ($t['bus_status'] !== 'available'): ?><br><?= status_badge($t['bus_status']) ?><?php endif; ?>
          </td>
          <td><?= e($t['origin']) ?> &rarr; <?= e($t['destination']) ?></td>
          <td><?= fmt_dt($t['departure_time']) ?></td>
          <td><?= peso($t['fare']) ?></td>
          <td><?= (int)$t['total_bookings'] ?> <span class="muted">(<?= (int)$t['seats_booked'] ?> seats)</span></td>
          <td><?= (int)$t['available_seats'] ?> / <?= (int)$t['total_seats'] ?></td>
          <td><?= peso($t['total_sales']) ?></td>
          <td><?= status_badge($t['status']) ?></td>
          <td style="white-space:nowrap">
            <a href="<?= url('admin/bookings.php') ?>?schedule_id=<?= (int)$t['schedule_id'] ?>" class="btn btn-sm btn-outline btn-icon" title="View bookings" aria-label="View bookings"><i class="bi bi-people"></i></a>
            <?php if (in_array($t['status'], ['open', 'full'], true)): ?>
              <form method="post" class="inline-form" data-confirm="Cancel this trip? All its bookings will be cancelled and refunded.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_trip">
                <input type="hidden" name="schedule_id" value="<?= (int)$t['schedule_id'] ?>">
                <button class="btn btn-sm btn-danger btn-icon" title="Cancel trip" aria-label="Cancel trip"><i class="bi bi-x-circle"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
