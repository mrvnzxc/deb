<?php
// ADMIN - manage buses and their availability
require __DIR__ . '/../config.php';
require_login('admin');

$types    = ['Ordinary', 'Aircon', 'Deluxe'];
$statuses = ['available', 'unavailable', 'maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_bus') {
            $busNo = strtoupper(trim($_POST['bus_number'] ?? ''));
            $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
            $type  = $_POST['bus_type'] ?? '';
            $seats = (int)($_POST['total_seats'] ?? 0);
            if ($busNo === '' || $plate === '') {
                flash('error', 'Bus number and plate number are required.');
            } elseif (!in_array($type, $types, true)) {
                flash('error', 'Invalid bus type.');
            } elseif ($seats < 10 || $seats > 80) {
                flash('error', 'Seats must be between 10 and 80.');
            } else {
                $pdo->prepare('INSERT INTO buses (bus_number, plate_number, bus_type, total_seats) VALUES (?, ?, ?, ?)')
                    ->execute([$busNo, $plate, $type, $seats]);
                flash('success', "Bus $busNo added.");
            }
        } elseif ($action === 'update_status') {
            $status = $_POST['status'] ?? '';
            if (in_array($status, $statuses, true)) {
                $pdo->prepare('UPDATE buses SET status = ? WHERE bus_id = ?')->execute([$status, (int)$_POST['bus_id']]);
                flash('success', 'Bus status updated.' . ($status !== 'available' ? ' Its trips are now hidden from customers.' : ''));
            }
        } elseif ($action === 'delete_bus') {
            $pdo->prepare('DELETE FROM buses WHERE bus_id = ?')->execute([(int)$_POST['bus_id']]);
            flash('success', 'Bus deleted.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', $action === 'delete_bus'
                ? 'This bus cannot be deleted because it has trips. Set it to "unavailable" instead.'
                : 'A bus with that bus number or plate number already exists.');
        } else {
            flash('error', 'Database error: ' . $ex->getMessage());
        }
    }
    redirect('admin/buses.php');
}

$buses = $pdo->query("SELECT b.*,
                             (SELECT COUNT(*) FROM schedules s WHERE s.bus_id = b.bus_id AND s.status IN ('open','full')) AS upcoming
                      FROM buses b ORDER BY b.bus_number")->fetchAll();

$title  = 'Buses';
$nav    = 'admin';
$active = 'buses';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-bus-front"></i></span>Buses</h1>
    <p class="subtitle">Only buses marked <strong>available</strong> are shown to customers.</p>
  </div>
</div>

<div class="card">
  <h2><span class="h-icon"><i class="bi bi-plus-lg"></i></span>Add Bus</h2>
  <form method="post" class="row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_bus">
    <div class="form-group"><label>Bus Number</label><input type="text" name="bus_number" placeholder="BUS-005" maxlength="20" required></div>
    <div class="form-group"><label>Plate Number</label><input type="text" name="plate_number" placeholder="ABC 1234" maxlength="20" required></div>
    <div class="form-group">
      <label>Type</label>
      <select name="bus_type"><?php foreach ($types as $t): ?><option><?= $t ?></option><?php endforeach; ?></select>
    </div>
    <div class="form-group"><label>Total Seats</label><input type="number" name="total_seats" value="45" min="10" max="80" required></div>
    <div class="form-group" style="flex:0 0 auto"><button class="btn"><i class="bi bi-plus-lg"></i>Add Bus</button></div>
  </form>
</div>

<div class="card table-wrap">
  <table>
    <thead><tr><th>Bus No.</th><th>Plate</th><th>Type</th><th>Seats</th><th>Upcoming Trips</th><th>Status</th><th>Change Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$buses): ?><tr><td colspan="8" class="empty">No buses yet.</td></tr><?php endif; ?>
    <?php foreach ($buses as $b): ?>
      <tr>
        <td><strong><?= e($b['bus_number']) ?></strong></td>
        <td><?= e($b['plate_number']) ?></td>
        <td><?= e($b['bus_type']) ?></td>
        <td><?= (int)$b['total_seats'] ?></td>
        <td><?= (int)$b['upcoming'] ?></td>
        <td><?= status_badge($b['status']) ?></td>
        <td>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="bus_id" value="<?= (int)$b['bus_id'] ?>">
            <select name="status">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-icon" title="Save changes" aria-label="Save changes"><i class="bi bi-check-lg"></i></button>
          </form>
        </td>
        <td>
          <form method="post" class="inline-form" data-confirm="Delete bus <?= e($b['bus_number']) ?>?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_bus">
            <input type="hidden" name="bus_id" value="<?= (int)$b['bus_id'] ?>">
            <button class="btn btn-sm btn-danger btn-icon" title="Delete bus" aria-label="Delete bus"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
