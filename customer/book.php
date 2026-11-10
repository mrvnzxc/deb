<?php
// BOOK A TRIP + PAYMENT (GCash or Card)
require __DIR__ . '/../config.php';
require_login('customer');

$scheduleId = (int)($_GET['schedule_id'] ?? ($_POST['schedule_id'] ?? 0));

$st = $pdo->prepare("SELECT s.schedule_id, s.departure_time, s.available_seats,
                            b.bus_number, b.bus_type, r.fare, r.travel_hours,
                            o.location_name AS origin, d.location_name AS destination
                     FROM schedules s
                     JOIN buses b     ON b.bus_id = s.bus_id
                     JOIN routes r    ON r.route_id = s.route_id
                     JOIN locations o ON o.location_id = r.origin_id
                     JOIN locations d ON d.location_id = r.destination_id
                     WHERE s.schedule_id = ? AND s.status = 'open' AND s.departure_time > NOW()
                       AND b.status = 'available' AND s.available_seats > 0");
$st->execute([$scheduleId]);
$trip = $st->fetch();

if (!$trip) {
    flash('error', 'That trip is no longer available.');
    redirect('customer/dashboard.php');
}

$maxSeats = min(10, (int)$trip['available_seats']);
$errors = [];
$old = [
    'num_seats' => 1, 'payment_method' => 'gcash', 'gcash_number' => '',
    'card_name' => '', 'card_number' => '', 'card_expiry' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $old['num_seats']      = (int)($_POST['num_seats'] ?? 1);
    $old['payment_method'] = ($_POST['payment_method'] ?? '') === 'card' ? 'card' : 'gcash';
    $old['gcash_number']   = trim($_POST['gcash_number'] ?? '');
    $old['card_name']      = trim($_POST['card_name'] ?? '');
    $old['card_number']    = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $old['card_expiry']    = trim($_POST['card_expiry'] ?? '');
    $cvv                   = trim($_POST['card_cvv'] ?? '');

    $seats = $old['num_seats'];
    if ($seats < 1 || $seats > $maxSeats) $errors[] = "You can book 1 to $maxSeats seat(s) for this trip.";

    if ($old['payment_method'] === 'gcash') {
        if (!preg_match('/^09\d{9}$/', $old['gcash_number'])) $errors[] = 'Enter a valid GCash number (11 digits, starts with 09).';
    } else {
        if ($old['card_name'] === '') $errors[] = 'Enter the name on the card.';
        if (!preg_match('/^\d{13,19}$/', $old['card_number']) || !luhn_valid($old['card_number'])) $errors[] = 'Enter a valid card number.';
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $old['card_expiry'], $m)) {
            $errors[] = 'Enter the card expiry as MM/YY.';
        } elseif (date('Y-m-t', strtotime('20' . $m[2] . '-' . $m[1] . '-01')) < date('Y-m-d')) {
            $errors[] = 'This card has expired.';
        }
        if (!preg_match('/^\d{3,4}$/', $cvv)) $errors[] = 'Enter a valid CVV.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the trip row so two people can't take the same last seats
            $lock = $pdo->prepare('SELECT s.available_seats, r.fare FROM schedules s JOIN routes r ON r.route_id = s.route_id
                                   WHERE s.schedule_id = ? AND s.status = \'open\' FOR UPDATE');
            $lock->execute([$scheduleId]);
            $row = $lock->fetch();

            if (!$row || $row['available_seats'] < $seats) {
                $pdo->rollBack();
                $errors[] = 'Sorry, not enough seats left. Please choose fewer seats.';
            } else {
                $total       = $row['fare'] * $seats;
                $bookingCode = 'BTB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

                $pdo->prepare("INSERT INTO bookings (booking_code, user_id, schedule_id, num_seats, total_amount, status)
                               VALUES (?, ?, ?, ?, ?, 'confirmed')")
                    ->execute([$bookingCode, $_SESSION['user_id'], $scheduleId, $seats, $total]);
                $bookingId = (int)$pdo->lastInsertId();

                // Simulated payment (connect a real gateway like PayMongo here)
                if ($old['payment_method'] === 'gcash') {
                    $ref = 'GC' . date('ymd') . random_int(100000, 999999);
                    $pdo->prepare("INSERT INTO payments (booking_id, payment_method, amount, reference_no, gcash_number, status, paid_at)
                                   VALUES (?, 'gcash', ?, ?, ?, 'paid', NOW())")
                        ->execute([$bookingId, $total, $ref, $old['gcash_number']]);
                } else {
                    $ref = 'CD' . date('ymd') . random_int(100000, 999999);
                    $pdo->prepare("INSERT INTO payments (booking_id, payment_method, amount, reference_no, card_name, card_last4, status, paid_at)
                                   VALUES (?, 'card', ?, ?, ?, ?, 'paid', NOW())")
                        ->execute([$bookingId, $total, $ref, $old['card_name'], substr($old['card_number'], -4)]);
                }

                $left = $row['available_seats'] - $seats;
                $pdo->prepare('UPDATE schedules SET available_seats = ?, status = ? WHERE schedule_id = ?')
                    ->execute([$left, $left === 0 ? 'full' : 'open', $scheduleId]);

                $pdo->commit();
                flash('success', 'Booking confirmed! Your payment was successful.');
                redirect('customer/ticket.php?id=' . $bookingId);
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Something went wrong while booking. Please try again.';
        }
    }
}

$title  = 'Book Trip';
$nav    = 'customer';
$active = 'home';
include __DIR__ . '/../includes/header.php';
?>
<p class="mb"><a href="<?= url('customer/dashboard.php') ?>"><i class="bi bi-arrow-left"></i> Back to available buses</a></p>
<div class="page-head"><div><h1 class="page-title"><span class="title-icon"><i class="bi bi-ticket-detailed"></i></span>Book Your Trip</h1><p class="subtitle">Choose how many seats you need, then pay to confirm your booking.</p></div></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" id="book-form">
  <?= csrf_field() ?>
  <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">

  <div class="grid grid-2">
    <div>
      <div class="card">
        <h2><span class="h-icon"><i class="bi bi-info-circle"></i></span>Trip Details</h2>
        <div class="route mb"><?= e($trip['origin']) ?><span class="arrow">&rarr;</span><?= e($trip['destination']) ?></div>
        <div class="summary-row"><span class="muted">Departure</span><strong><?= fmt_dt($trip['departure_time']) ?></strong></div>
        <?php if ($trip['travel_hours']): ?>
        <div class="summary-row"><span class="muted">Travel time</span><span>~<?= e((float)$trip['travel_hours']) ?> hours</span></div>
        <?php endif; ?>
        <div class="summary-row"><span class="muted">Bus</span><span><?= e($trip['bus_number']) ?> (<?= e($trip['bus_type']) ?>)</span></div>
        <div class="summary-row"><span class="muted">Seats left</span><span><?= (int)$trip['available_seats'] ?></span></div>
        <div class="summary-row"><span class="muted">Fare per seat</span><span><?= peso($trip['fare']) ?></span></div>

        <div class="form-group mt">
          <label for="num_seats">Number of Seats</label>
          <input type="number" id="num_seats" name="num_seats" min="1" max="<?= $maxSeats ?>" value="<?= (int)$old['num_seats'] ?>" required>
          <span class="muted">Maximum of <?= $maxSeats ?> seat(s) per booking</span>
        </div>

        <div class="summary-row summary-total"><span>Total</span><span id="total"><?= peso($trip['fare'] * $old['num_seats']) ?></span></div>
      </div>
    </div>

    <div>
      <div class="card">
        <h2><span class="h-icon"><i class="bi bi-wallet2"></i></span>Payment Method</h2>
        <div class="pay-options">
          <label class="pay-option <?= $old['payment_method'] === 'gcash' ? 'selected' : '' ?>">
            <input type="radio" name="payment_method" value="gcash" <?= $old['payment_method'] === 'gcash' ? 'checked' : '' ?>>
            &#128241; GCash
          </label>
          <label class="pay-option <?= $old['payment_method'] === 'card' ? 'selected' : '' ?>">
            <input type="radio" name="payment_method" value="card" <?= $old['payment_method'] === 'card' ? 'checked' : '' ?>>
            &#128179; Credit / Debit Card
          </label>
        </div>

        <div id="gcash-fields" class="<?= $old['payment_method'] === 'gcash' ? '' : 'hidden' ?>">
          <div class="form-group">
            <label for="gcash_number">GCash Number</label>
            <input type="text" id="gcash_number" name="gcash_number" value="<?= e($old['gcash_number']) ?>" placeholder="09XXXXXXXXX" maxlength="11">
          </div>
        </div>

        <div id="card-fields" class="<?= $old['payment_method'] === 'card' ? '' : 'hidden' ?>">
          <div class="form-group">
            <label for="card_name">Name on Card</label>
            <input type="text" id="card_name" name="card_name" value="<?= e($old['card_name']) ?>" autocomplete="cc-name">
          </div>
          <div class="form-group">
            <label for="card_number">Card Number</label>
            <input type="text" id="card_number" name="card_number" inputmode="numeric" placeholder="1234 5678 9012 3456" maxlength="23" autocomplete="cc-number">
          </div>
          <div class="row">
            <div class="form-group">
              <label for="card_expiry">Expiry (MM/YY)</label>
              <input type="text" id="card_expiry" name="card_expiry" value="<?= e($old['card_expiry']) ?>" placeholder="MM/YY" maxlength="5" autocomplete="cc-exp">
            </div>
            <div class="form-group">
              <label for="card_cvv">CVV</label>
              <input type="password" id="card_cvv" name="card_cvv" placeholder="123" maxlength="4" inputmode="numeric" autocomplete="cc-csc">
            </div>
          </div>
          <p class="muted mb">&#128274; We only keep the last 4 digits of your card.</p>
        </div>

        <button type="submit" class="btn btn-block btn-success"><i class="bi bi-shield-check"></i>Pay &amp; Confirm Booking</button>
      </div>
    </div>
  </div>
</form>

<script>
var fare = <?= json_encode((float)$trip['fare']) ?>;
var seatsInput = document.getElementById('num_seats');

function updateTotal() {
  var n = parseInt(seatsInput.value, 10) || 0;
  document.getElementById('total').textContent =
    '₱' + (fare * n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
seatsInput.addEventListener('input', updateTotal);

// Show GCash or Card fields
document.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
  radio.addEventListener('change', function () {
    document.querySelectorAll('.pay-option').forEach(function (o) { o.classList.remove('selected'); });
    radio.parentElement.classList.add('selected');
    document.getElementById('gcash-fields').classList.toggle('hidden', radio.value !== 'gcash');
    document.getElementById('card-fields').classList.toggle('hidden', radio.value !== 'card');
  });
});

// Card number: groups of 4 digits
document.getElementById('card_number').addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
});
// Expiry: auto-add the slash
document.getElementById('card_expiry').addEventListener('input', function () {
  var v = this.value.replace(/\D/g, '').slice(0, 4);
  this.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
});
// Digits only
['gcash_number', 'card_cvv'].forEach(function (id) {
  document.getElementById(id).addEventListener('input', function () { this.value = this.value.replace(/\D/g, ''); });
});

// Prevent double submit
document.getElementById('book-form').addEventListener('submit', function (ev) {
  if (!ev.defaultPrevented) {
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i>Processing payment...';
  }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
