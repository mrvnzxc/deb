<?php
// CUSTOMER HOME - search available buses by location and destination
require __DIR__ . '/../config.php';
require_login('customer');

$locations = $pdo->query('SELECT location_id, location_name, province FROM locations ORDER BY location_name')->fetchAll();

$origin      = (int)($_GET['origin'] ?? 0);
$destination = (int)($_GET['destination'] ?? 0);
$travelDate  = $_GET['date'] ?? '';
if ($travelDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $travelDate)) $travelDate = '';

$sql = "SELECT s.schedule_id, s.departure_time, s.available_seats,
               b.bus_number, b.bus_type, b.total_seats,
               r.fare, r.travel_hours,
               o.location_name AS origin, d.location_name AS destination
        FROM schedules s
        JOIN buses b     ON b.bus_id = s.bus_id
        JOIN routes r    ON r.route_id = s.route_id
        JOIN locations o ON o.location_id = r.origin_id
        JOIN locations d ON d.location_id = r.destination_id
        WHERE s.status = 'open' AND s.departure_time > NOW()
          AND b.status = 'available' AND s.available_seats > 0";
$params = [];
if ($origin)      { $sql .= ' AND r.origin_id = ?';             $params[] = $origin; }
if ($destination) { $sql .= ' AND r.destination_id = ?';        $params[] = $destination; }
if ($travelDate)  { $sql .= ' AND DATE(s.departure_time) = ?';  $params[] = $travelDate; }
$sql .= ' ORDER BY s.departure_time';

$st = $pdo->prepare($sql);
$st->execute($params);
$trips = $st->fetchAll();

$title  = 'Find a Bus';
$nav    = 'customer';
$active = 'home';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-geo-alt-fill"></i></span>Hello, <?= e($_SESSION['name']) ?>!</h1>
    <p class="subtitle">Where are you going today?</p>
  </div>
</div>

<div class="card">
  <h2><span class="h-icon"><i class="bi bi-search"></i></span>Search Trips</h2>
  <form method="get" class="row">
    <div class="form-group">
      <label for="origin">Current Location (From)</label>
      <select id="origin" name="origin">
        <option value="0">Any location</option>
        <?php foreach ($locations as $l): ?>
          <option value="<?= $l['location_id'] ?>" <?= $origin == $l['location_id'] ? 'selected' : '' ?>>
            <?= e($l['location_name']) ?><?= $l['province'] ? ', ' . e($l['province']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="btn btn-outline swap-btn" id="swap" title="Swap origin and destination" aria-label="Swap origin and destination"><i class="bi bi-arrow-left-right"></i></button>
    <div class="form-group">
      <label for="destination">Destination (To)</label>
      <select id="destination" name="destination">
        <option value="0">Any destination</option>
        <?php foreach ($locations as $l): ?>
          <option value="<?= $l['location_id'] ?>" <?= $destination == $l['location_id'] ? 'selected' : '' ?>>
            <?= e($l['location_name']) ?><?= $l['province'] ? ', ' . e($l['province']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="date">Travel Date</label>
      <input type="date" id="date" name="date" value="<?= e($travelDate) ?>" min="<?= date('Y-m-d') ?>">
    </div>
    <div class="form-group" style="flex:0 0 auto">
      <button type="submit" class="btn"><i class="bi bi-search"></i>Search</button>
      <a href="<?= url('customer/dashboard.php') ?>" class="btn btn-outline"><i class="bi bi-x-lg"></i>Clear</a>
    </div>
  </form>
</div>

<h2 class="mb" style="display:flex;align-items:center;gap:10px"><span class="h-icon"><i class="bi bi-bus-front"></i></span>Available Buses <span class="muted">(<?= count($trips) ?> found)</span></h2>

<?php if (!$trips): ?>
  <div class="card empty">No available buses found for your search. Try another location or date.</div>
<?php endif; ?>

<?php foreach ($trips as $t): ?>
  <div class="card trip">
    <div>
      <div class="route"><?= e($t['origin']) ?><span class="arrow">&rarr;</span><?= e($t['destination']) ?></div>
      <div class="trip-meta muted">
        <span>&#128197; <?= fmt_dt($t['departure_time']) ?></span>
        <?php if ($t['travel_hours']): ?><span>&#9201; ~<?= e((float)$t['travel_hours']) ?> hrs</span><?php endif; ?>
        <span>&#128652; <?= e($t['bus_number']) ?> &middot; <?= e($t['bus_type']) ?></span>
        <span>&#128186; <strong><?= (int)$t['available_seats'] ?></strong> / <?= (int)$t['total_seats'] ?> seats left</span>
      </div>
    </div>
    <div style="text-align:right">
      <div class="price"><?= peso($t['fare']) ?></div>
      <div class="muted mb">per seat</div>
      <a href="<?= url('customer/book.php') ?>?schedule_id=<?= (int)$t['schedule_id'] ?>" class="btn"><i class="bi bi-ticket-perforated"></i>Book Now</a>
    </div>
  </div>
<?php endforeach; ?>

<script>
document.getElementById('swap').addEventListener('click', function () {
  var o = document.getElementById('origin'), d = document.getElementById('destination');
  var tmp = o.value; o.value = d.value; d.value = tmp;
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
