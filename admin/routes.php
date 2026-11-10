<?php
// ADMIN - change rates per destination, manage routes and locations
require __DIR__ . '/../config.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_fare') {
            $fare  = (float)($_POST['fare'] ?? 0);
            $hours = trim($_POST['travel_hours'] ?? '');
            if ($fare <= 0) {
                flash('error', 'Fare must be greater than zero.');
            } else {
                $pdo->prepare('UPDATE routes SET fare = ?, travel_hours = ? WHERE route_id = ?')
                    ->execute([$fare, $hours === '' ? null : (float)$hours, (int)$_POST['route_id']]);
                flash('success', 'Rate updated. The new fare applies to new bookings.');
            }
        } elseif ($action === 'add_route') {
            $origin = (int)($_POST['origin_id'] ?? 0);
            $dest   = (int)($_POST['destination_id'] ?? 0);
            $fare   = (float)($_POST['fare'] ?? 0);
            $hours  = trim($_POST['travel_hours'] ?? '');
            if (!$origin || !$dest) {
                flash('error', 'Please choose an origin and a destination.');
            } elseif ($origin === $dest) {
                flash('error', 'Origin and destination must be different.');
            } elseif ($fare <= 0) {
                flash('error', 'Fare must be greater than zero.');
            } else {
                $pdo->prepare('INSERT INTO routes (origin_id, destination_id, fare, travel_hours) VALUES (?, ?, ?, ?)')
                    ->execute([$origin, $dest, $fare, $hours === '' ? null : (float)$hours]);
                flash('success', 'Route added.');
            }
        } elseif ($action === 'delete_route') {
            $pdo->prepare('DELETE FROM routes WHERE route_id = ?')->execute([(int)$_POST['route_id']]);
            flash('success', 'Route deleted.');
        } elseif ($action === 'add_location') {
            $name = trim($_POST['location_name'] ?? '');
            $prov = trim($_POST['province'] ?? '');
            if ($name === '') {
                flash('error', 'Location name is required.');
            } else {
                $pdo->prepare('INSERT INTO locations (location_name, province) VALUES (?, ?)')
                    ->execute([$name, $prov === '' ? null : $prov]);
                flash('success', 'Location "' . $name . '" added.');
            }
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', $action === 'delete_route'
                ? 'This route cannot be deleted because it already has trips.'
                : 'That record already exists.');
        } else {
            flash('error', 'Database error: ' . $ex->getMessage());
        }
    }
    redirect('admin/routes.php');
}

$locations = $pdo->query('SELECT * FROM locations ORDER BY location_name')->fetchAll();
$routes = $pdo->query("SELECT r.*, o.location_name AS origin, d.location_name AS destination,
                              (SELECT COUNT(*) FROM schedules s WHERE s.route_id = r.route_id) AS trip_count
                       FROM routes r
                       JOIN locations o ON o.location_id = r.origin_id
                       JOIN locations d ON d.location_id = r.destination_id
                       ORDER BY o.location_name, d.location_name")->fetchAll();

$title  = 'Rates & Routes';
$nav    = 'admin';
$active = 'routes';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-signpost-split"></i></span>Rates &amp; Routes</h1>
    <p class="subtitle">Change the fare per destination. Existing bookings keep the price they paid.</p>
  </div>
</div>

<div class="card">
  <h2><span class="h-icon"><i class="bi bi-cash-stack"></i></span>Fares per Destination</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Route</th><th>Trips</th><th>Last Updated</th><th>Fare (&#8369;) / Travel Hours</th><th></th></tr></thead>
      <tbody>
      <?php if (!$routes): ?><tr><td colspan="5" class="empty">No routes yet.</td></tr><?php endif; ?>
      <?php foreach ($routes as $r): ?>
        <tr>
          <td><strong><?= e($r['origin']) ?> &rarr; <?= e($r['destination']) ?></strong></td>
          <td><?= (int)$r['trip_count'] ?></td>
          <td class="muted"><?= fmt_dt($r['updated_at']) ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_fare">
              <input type="hidden" name="route_id" value="<?= (int)$r['route_id'] ?>">
              <input type="number" name="fare" value="<?= e($r['fare']) ?>" step="0.01" min="1" style="width:110px" required>
              <input type="number" name="travel_hours" value="<?= e($r['travel_hours']) ?>" step="0.5" min="0" style="width:80px" placeholder="hrs">
              <button class="btn btn-sm btn-icon" title="Save fare" aria-label="Save fare"><i class="bi bi-check-lg"></i></button>
            </form>
          </td>
          <td>
            <form method="post" class="inline-form" data-confirm="Delete route <?= e($r['origin'] . ' to ' . $r['destination']) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_route">
              <input type="hidden" name="route_id" value="<?= (int)$r['route_id'] ?>">
              <button class="btn btn-sm btn-danger btn-icon" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2><span class="h-icon"><i class="bi bi-plus-lg"></i></span>Add Route</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_route">
      <div class="row">
        <div class="form-group">
          <label>From</label>
          <select name="origin_id" required>
            <option value="">Select origin</option>
            <?php foreach ($locations as $l): ?><option value="<?= $l['location_id'] ?>"><?= e($l['location_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>To</label>
          <select name="destination_id" required>
            <option value="">Select destination</option>
            <?php foreach ($locations as $l): ?><option value="<?= $l['location_id'] ?>"><?= e($l['location_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row">
        <div class="form-group">
          <label>Fare (&#8369;)</label>
          <input type="number" name="fare" step="0.01" min="1" required>
        </div>
        <div class="form-group">
          <label>Travel Hours</label>
          <input type="number" name="travel_hours" step="0.5" min="0" placeholder="e.g. 6">
        </div>
      </div>
      <button class="btn"><i class="bi bi-plus-lg"></i>Add Route</button>
    </form>
  </div>

  <div class="card">
    <h2><span class="h-icon"><i class="bi bi-geo-alt"></i></span>Locations / Terminals</h2>
    <form method="post" class="row mb">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_location">
      <div class="form-group" style="margin-bottom:0">
        <label>Location Name</label>
        <input type="text" name="location_name" maxlength="100" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Province</label>
        <input type="text" name="province" maxlength="100">
      </div>
      <button class="btn"><i class="bi bi-plus-lg"></i>Add</button>
    </form>
    <div class="muted">
      <?php foreach ($locations as $l): ?>
        <span class="badge badge-info" style="margin:2px"><?= e($l['location_name']) ?><?= $l['province'] ? ', ' . e($l['province']) : '' ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
