<?php
// ADMIN DASHBOARD - booking tracker + analytics
require __DIR__ . '/../config.php';
require_login('admin');

// ---------- Headline numbers ----------
$stats = [
    'bookings'  => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status <> 'cancelled'")->fetchColumn(),
    'today'     => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status <> 'cancelled' AND DATE(booked_at) = CURDATE()")->fetchColumn(),
    'sales'     => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'")->fetchColumn(),
    'buses'     => (int)$pdo->query("SELECT COUNT(*) FROM buses WHERE status = 'available'")->fetchColumn(),
    'fleet'     => (int)$pdo->query('SELECT COUNT(*) FROM buses')->fetchColumn(),
    'customers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'new_cust'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
];

// ---------- Daily trend: last 14 days ----------
$days  = 14;
$start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

$st = $pdo->prepare("SELECT DATE(paid_at) AS d, SUM(amount) AS total FROM payments
                     WHERE status = 'paid' AND paid_at >= ? GROUP BY DATE(paid_at)");
$st->execute([$start]);
$salesByDay = array_column($st->fetchAll(), 'total', 'd');

$st = $pdo->prepare("SELECT DATE(booked_at) AS d, COUNT(*) AS n FROM bookings
                     WHERE status <> 'cancelled' AND booked_at >= ? GROUP BY DATE(booked_at)");
$st->execute([$start]);
$bookingsByDay = array_column($st->fetchAll(), 'n', 'd');

$trend = ['labels' => [], 'sales' => [], 'bookings' => []];
for ($i = 0; $i < $days; $i++) {
    $d = date('Y-m-d', strtotime($start . " +$i days"));
    $trend['labels'][]   = date('M j', strtotime($d));
    $trend['sales'][]    = round((float)($salesByDay[$d] ?? 0), 2);
    $trend['bookings'][] = (int)($bookingsByDay[$d] ?? 0);
}
$sales7      = array_sum(array_slice($trend['sales'], -7));
$bookings7   = array_sum(array_slice($trend['bookings'], -7));
$bookingsP7  = array_sum(array_slice($trend['bookings'], 0, 7));
$yesterday   = $trend['bookings'][$days - 2];

// Percentage change helper for the stat tiles
function delta_html($now, $before, $label)
{
    if ($before == 0) {
        return $now > 0 ? '<span class="up"><i class="bi bi-arrow-up-right"></i>New</span> ' . e($label)
                        : '<i class="bi bi-dash"></i>No change ' . e($label);
    }
    $pct = ($now - $before) / $before * 100;
    $cls = $pct >= 0 ? 'up' : 'down';
    $ico = $pct >= 0 ? 'arrow-up-right' : 'arrow-down-right';
    return '<span class="' . $cls . '"><i class="bi bi-' . $ico . '"></i>' . number_format(abs($pct)) . '%</span> ' . e($label);
}

// ---------- Revenue by route (top 6) ----------
$routeRows = $pdo->query("SELECT CONCAT(o.location_name, ' → ', d.location_name) AS route,
                                 SUM(bk.total_amount) AS revenue, SUM(bk.num_seats) AS seats
                          FROM bookings bk
                          JOIN schedules s ON s.schedule_id = bk.schedule_id
                          JOIN routes r    ON r.route_id = s.route_id
                          JOIN locations o ON o.location_id = r.origin_id
                          JOIN locations d ON d.location_id = r.destination_id
                          WHERE bk.status <> 'cancelled'
                          GROUP BY r.route_id
                          ORDER BY revenue DESC
                          LIMIT 6")->fetchAll();

// ---------- Seat occupancy: next 6 upcoming trips ----------
$occupancy = $pdo->query("SELECT v.bus_number, v.origin, v.destination, v.departure_time, v.total_seats, v.seats_booked
                          FROM vw_booking_summary v
                          JOIN schedules s ON s.schedule_id = v.schedule_id
                          WHERE s.status IN ('open','full')
                          ORDER BY v.departure_time
                          LIMIT 6")->fetchAll();
$seatsTotal  = array_sum(array_column($occupancy, 'total_seats'));
$seatsBooked = array_sum(array_column($occupancy, 'seats_booked'));
$avgOcc      = $seatsTotal ? round($seatsBooked / $seatsTotal * 100) : 0;

// ---------- Part-to-whole splits ----------
$payRows = array_column($pdo->query("SELECT payment_method, SUM(amount) AS total FROM payments
                                     WHERE status = 'paid' GROUP BY payment_method")->fetchAll(), 'total', 'payment_method');
$payments = [
    ['label' => 'GCash', 'icon' => 'phone',       'value' => (float)($payRows['gcash'] ?? 0), 'color' => 'var(--series-1)', 'light' => false],
    ['label' => 'Card',  'icon' => 'credit-card', 'value' => (float)($payRows['card'] ?? 0),  'color' => 'var(--series-2)', 'light' => false],
];
$statusRows = array_column($pdo->query('SELECT status, COUNT(*) AS n FROM bookings GROUP BY status')->fetchAll(), 'n', 'status');
$statuses = [
    ['label' => 'Confirmed', 'icon' => 'check-circle-fill',       'value' => (int)($statusRows['confirmed'] ?? 0), 'color' => 'var(--status-good)',     'light' => false],
    ['label' => 'Pending',   'icon' => 'hourglass-split',         'value' => (int)($statusRows['pending'] ?? 0),   'color' => 'var(--status-warning)',  'light' => true],
    ['label' => 'Cancelled', 'icon' => 'x-circle-fill',           'value' => (int)($statusRows['cancelled'] ?? 0), 'color' => 'var(--status-critical)', 'light' => false],
];

// Renders one segmented bar + legend
function split_bar($title, $parts, $money)
{
    $total = array_sum(array_column($parts, 'value'));
    echo '<div class="split"><div class="split-title">' . e($title) . '</div>';
    if ($total <= 0) {
        echo '<div class="split-bar"></div><div class="split-legend"><span class="muted">No data yet</span></div></div>';
        return;
    }
    echo '<div class="split-bar" role="img" aria-label="' . e($title) . '">';
    foreach ($parts as $p) {
        if ($p['value'] <= 0) continue;
        $pct = $p['value'] / $total * 100;
        $tip = $p['label'] . ': ' . ($money ? '₱' . number_format($p['value'], 2) : $p['value']) . ' (' . round($pct) . '%)';
        echo '<div class="split-seg' . ($p['light'] ? ' on-light' : '') . '" style="flex:' . $pct . ' 1 0;background:' . $p['color'] . '" title="' . e($tip) . '">'
            . ($pct >= 14 ? round($pct) . '%' : '') . '</div>';
    }
    echo '</div><div class="split-legend">';
    foreach ($parts as $p) {
        echo '<span><span class="swatch" style="background:' . $p['color'] . '"></span><i class="bi bi-' . $p['icon'] . '"></i>'
            . e($p['label']) . ' &middot; <strong>' . ($money ? peso($p['value']) : (int)$p['value']) . '</strong></span>';
    }
    echo '</div></div>';
}

// ---------- Tracker table ----------
$showAll = isset($_GET['all']);
$sql = "SELECT v.*, s.status
        FROM vw_booking_summary v
        JOIN schedules s ON s.schedule_id = v.schedule_id";
$sql .= $showAll ? ' ORDER BY v.departure_time DESC' : " WHERE s.status IN ('open','full') ORDER BY v.departure_time";
$trips = $pdo->query($sql)->fetchAll();

$recent = $pdo->query("SELECT bk.booking_id, bk.booking_code, bk.num_seats, bk.total_amount, bk.status, bk.booked_at,
                              u.full_name, o.location_name AS origin, d.location_name AS destination
                       FROM bookings bk
                       JOIN users u     ON u.user_id = bk.user_id
                       JOIN schedules s ON s.schedule_id = bk.schedule_id
                       JOIN routes r    ON r.route_id = s.route_id
                       JOIN locations o ON o.location_id = r.origin_id
                       JOIN locations d ON d.location_id = r.destination_id
                       ORDER BY bk.booked_at DESC LIMIT 6")->fetchAll();

$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$title  = 'Admin Dashboard';
$nav    = 'admin';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <div class="hero-date"><i class="bi bi-calendar3"></i><?= date('l, F j, Y') ?></div>
    <h1 class="page-title"><span class="title-icon"><i class="bi bi-speedometer2"></i></span><?= e($greeting) ?>, <?= e($_SESSION['name'] ?? 'Admin') ?>!</h1>
    <p class="subtitle">Here's what's happening with your trips and bookings.</p>
  </div>
  <div class="hero-actions">
    <a href="<?= url('admin/schedules.php') ?>" class="btn btn-light"><i class="bi bi-plus-lg"></i>Add Trip</a>
    <a href="<?= url('admin/routes.php') ?>" class="btn btn-outline"><i class="bi bi-signpost-split"></i>Routes</a>
    <a href="<?= url('admin/bookings.php') ?>" class="btn btn-outline"><i class="bi bi-journal-text"></i>Bookings</a>
  </div>
</div>

<!-- Stat tiles (numbers count up from 0) -->
<div class="grid grid-4 mb">
  <div class="stat" style="--accent:#2563eb">
    <div class="stat-top"><div class="label">Total Bookings</div><span class="stat-icon"><i class="bi bi-ticket-perforated"></i></span></div>
    <div class="value" data-count="<?= $stats['bookings'] ?>"><?= number_format($stats['bookings']) ?></div>
    <div class="stat-sub"><?= delta_html($bookings7, $bookingsP7, 'vs previous 7 days') ?></div>
  </div>
  <div class="stat" style="--accent:#0891b2">
    <div class="stat-top"><div class="label">Bookings Today</div><span class="stat-icon"><i class="bi bi-calendar-check"></i></span></div>
    <div class="value" data-count="<?= $stats['today'] ?>"><?= number_format($stats['today']) ?></div>
    <div class="stat-sub"><?= delta_html($stats['today'], $yesterday, 'vs yesterday') ?></div>
  </div>
  <div class="stat" style="--accent:#15803d">
    <div class="stat-top"><div class="label">Total Sales</div><span class="stat-icon"><i class="bi bi-cash-coin"></i></span></div>
    <div class="value" data-count="<?= $stats['sales'] ?>" data-decimals="2" data-prefix="&#8369;"><?= peso($stats['sales']) ?></div>
    <div class="stat-sub"><i class="bi bi-graph-up"></i><?= peso($sales7) ?> in the last 7 days</div>
  </div>
  <div class="stat" style="--accent:#d97706">
    <div class="stat-top"><div class="label">Available Buses</div><span class="stat-icon"><i class="bi bi-bus-front"></i></span></div>
    <div class="value" data-count="<?= $stats['buses'] ?>"><?= number_format($stats['buses']) ?></div>
    <div class="stat-sub"><i class="bi bi-truck-front"></i>of <?= $stats['fleet'] ?> in the fleet</div>
  </div>
  <div class="stat" style="--accent:#7c3aed">
    <div class="stat-top"><div class="label">Customers</div><span class="stat-icon"><i class="bi bi-people"></i></span></div>
    <div class="value" data-count="<?= $stats['customers'] ?>"><?= number_format($stats['customers']) ?></div>
    <div class="stat-sub"><i class="bi bi-person-plus"></i><?= $stats['new_cust'] ?> new this month</div>
  </div>
</div>

<!-- Analytics row 1: trend + occupancy -->
<div class="grid-analytics">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><span class="h-icon"><i class="bi bi-graph-up-arrow"></i></span><span id="trend-title">Sales &middot; last 14 days</span></h2>
        <div class="chart-total mt" id="trend-total"><?= peso(array_sum($trend['sales'])) ?><small>total</small></div>
      </div>
      <div class="seg" role="group" aria-label="Choose measure">
        <button type="button" class="seg-btn active" data-metric="sales" aria-pressed="true"><i class="bi bi-cash"></i> Sales</button>
        <button type="button" class="seg-btn" data-metric="bookings" aria-pressed="false"><i class="bi bi-ticket"></i> Bookings</button>
      </div>
    </div>
    <div class="chart-box"><canvas id="trendChart" aria-label="Daily trend chart" role="img"></canvas></div>
    <details class="mt">
      <summary class="muted" style="cursor:pointer"><i class="bi bi-table"></i> View as table</summary>
      <div class="table-wrap mt">
        <table>
          <thead><tr><th>Date</th><th>Sales</th><th>Bookings</th></tr></thead>
          <tbody>
          <?php foreach ($trend['labels'] as $i => $lbl): ?>
            <tr><td><?= e($lbl) ?></td><td><?= peso($trend['sales'][$i]) ?></td><td><?= $trend['bookings'][$i] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </div>

  <div class="card">
    <div class="card-head">
      <h2><span class="h-icon"><i class="bi bi-person-workspace"></i></span>Seat Occupancy</h2>
      <span class="muted">Avg. <strong><?= $avgOcc ?>%</strong></span>
    </div>
    <?php if (!$occupancy): ?>
      <div class="chart-empty" style="height:220px"><i class="bi bi-calendar-x"></i>No upcoming trips</div>
    <?php else: ?>
      <div class="meter-list">
      <?php foreach ($occupancy as $o):
          $pct = $o['total_seats'] ? round($o['seats_booked'] / $o['total_seats'] * 100) : 0;
          $cls = $pct >= 100 ? ' is-full' : ($pct >= 80 ? ' is-high' : ''); ?>
        <div class="meter-row" title="<?= e($o['seats_booked'] . ' of ' . $o['total_seats'] . ' seats booked') ?>">
          <div class="meter-name"><?= e($o['origin']) ?> &rarr; <?= e($o['destination']) ?>
            <small><i class="bi bi-bus-front"></i> <?= e($o['bus_number']) ?> &middot; <?= date('M j, g:i A', strtotime($o['departure_time'])) ?></small></div>
          <div class="meter-fig"><?= (int)$o['seats_booked'] ?>/<?= (int)$o['total_seats'] ?> &middot; <strong><?= $pct ?>%</strong></div>
          <div class="meter-track"><div class="meter-fill<?= $cls ?>" data-width="<?= min($pct, 100) ?>"></div></div>
        </div>
      <?php endforeach; ?>
      </div>
      <p class="muted mt" style="font-size:.78rem"><i class="bi bi-info-circle"></i> Amber = 80%+ full, red = sold out.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Analytics row 2: revenue by route + splits -->
<div class="grid-analytics-2">
  <div class="card">
    <div class="card-head">
      <h2><span class="h-icon"><i class="bi bi-signpost-2"></i></span>Revenue by Route</h2>
      <span class="muted">Top routes by sales</span>
    </div>
    <div class="chart-box sm">
      <?php if ($routeRows): ?>
        <canvas id="routeChart" role="img" aria-label="Revenue by route bar chart"></canvas>
      <?php else: ?>
        <div class="chart-empty"><i class="bi bi-bar-chart"></i>No bookings yet</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h2><span class="h-icon"><i class="bi bi-pie-chart"></i></span>Breakdown</h2>
    </div>
    <?php split_bar('Sales by payment method', $payments, true); ?>
    <?php split_bar('Bookings by status', $statuses, false); ?>
  </div>
</div>

<!-- Booking tracker -->
<div class="card">
  <div class="card-head">
    <h2><span class="h-icon"><i class="bi bi-clipboard-data"></i></span>Booking Tracker <span class="muted">(<?= $showAll ? 'all trips' : 'upcoming trips' ?>)</span></h2>
    <div>
      <?php if ($showAll): ?>
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-sm btn-outline"><i class="bi bi-calendar-event"></i>Show upcoming only</a>
      <?php else: ?>
        <a href="<?= url('admin/dashboard.php') ?>?all=1" class="btn btn-sm btn-outline"><i class="bi bi-calendar3"></i>Show all trips</a>
      <?php endif; ?>
      <a href="<?= url('admin/schedules.php') ?>" class="btn btn-sm"><i class="bi bi-plus-lg"></i>Add Trip</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Bus</th><th>Route</th><th>Departure</th><th>Fare</th>
          <th>Bookings</th><th>Seats Booked</th><th>Seats Left</th><th>Sales</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$trips): ?>
        <tr><td colspan="10" class="empty">No trips yet. <a href="<?= url('admin/schedules.php') ?>">Add a trip</a></td></tr>
      <?php endif; ?>
      <?php foreach ($trips as $t):
          $pct = $t['total_seats'] ? min(100, round($t['seats_booked'] / $t['total_seats'] * 100)) : 0; ?>
        <tr>
          <td><strong><?= e($t['bus_number']) ?></strong></td>
          <td><?= e($t['origin']) ?> &rarr; <?= e($t['destination']) ?></td>
          <td><?= fmt_dt($t['departure_time']) ?></td>
          <td><?= peso($t['fare']) ?></td>
          <td><strong><?= (int)$t['total_bookings'] ?></strong></td>
          <td>
            <div class="seatbar" title="<?= $pct ?>% booked">
              <div class="seatbar-track"><div class="seatbar-fill" style="width:<?= $pct ?>%"></div></div>
              <span class="seatbar-text"><?= (int)$t['seats_booked'] ?> / <?= (int)$t['total_seats'] ?></span>
            </div>
          </td>
          <td><?= (int)$t['available_seats'] ?></td>
          <td><?= peso($t['total_sales']) ?></td>
          <td><?= status_badge($t['status']) ?></td>
          <td><a href="<?= url('admin/bookings.php') ?>?schedule_id=<?= (int)$t['schedule_id'] ?>" class="btn btn-sm btn-outline btn-icon" title="View bookings" aria-label="View bookings"><i class="bi bi-eye"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2><span class="h-icon"><i class="bi bi-clock-history"></i></span>Recent Bookings</h2>
    <a href="<?= url('admin/bookings.php') ?>" class="btn btn-sm btn-outline">See all<i class="bi bi-arrow-right" style="margin:0 0 0 6px"></i></a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Code</th><th>Customer</th><th>Route</th><th>Seats</th><th>Amount</th><th>Booked</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="7" class="empty">No bookings yet.</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><a href="<?= url('customer/ticket.php') ?>?id=<?= (int)$r['booking_id'] ?>"><?= e($r['booking_code']) ?></a></td>
          <td><?= e($r['full_name']) ?></td>
          <td><?= e($r['origin']) ?> &rarr; <?= e($r['destination']) ?></td>
          <td><?= (int)$r['num_seats'] ?></td>
          <td><?= peso($r['total_amount']) ?></td>
          <td><?= fmt_dt($r['booked_at']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var TREND  = <?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var ROUTES = <?= json_encode($routeRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var css    = getComputedStyle(document.documentElement);
  var C = {
    series: css.getPropertyValue('--series-1').trim() || '#2a78d6',
    grid:   css.getPropertyValue('--grid').trim() || '#e1e0d9',
    muted:  css.getPropertyValue('--axis-muted').trim() || '#898781',
    ink2:   css.getPropertyValue('--ink-2').trim() || '#52514e'
  };
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var peso = function (n, d) { return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: d, maximumFractionDigits: d }); };
  var compactPeso = function (n) { return n >= 1000 ? '₱' + (n / 1000).toLocaleString('en-PH', { maximumFractionDigits: 1 }) + 'k' : '₱' + n; };

  // Seat-occupancy meters grow in
  requestAnimationFrame(function () {
    document.querySelectorAll('.meter-fill[data-width]').forEach(function (el) { el.style.width = el.getAttribute('data-width') + '%'; });
  });

  if (!window.Chart) return; // CDN unreachable: page still works, table view still available

  Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
  Chart.defaults.color = C.muted;
  if (reduce) Chart.defaults.animation = false;

  var tooltip = {
    backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0',
    padding: 10, cornerRadius: 8, displayColors: false, titleFont: { weight: '600' }
  };

  // Vertical crosshair on hover
  var crosshair = {
    id: 'crosshair',
    afterDatasetsDraw: function (chart) {
      var act = chart.tooltip && chart.tooltip.getActiveElements();
      if (!act || !act.length) return;
      var x = act[0].element.x, a = chart.chartArea, ctx = chart.ctx;
      ctx.save();
      ctx.strokeStyle = 'rgba(15,23,42,.28)'; ctx.lineWidth = 1; ctx.setLineDash([4, 4]);
      ctx.beginPath(); ctx.moveTo(x, a.top); ctx.lineTo(x, a.bottom); ctx.stroke();
      ctx.restore();
    }
  };

  // ---- Trend chart (one measure at a time: no dual axis) ----
  var trendCtx = document.getElementById('trendChart');
  var metric = 'sales';
  function areaFill(ctx) {
    var area = ctx.chart.chartArea;
    if (!area) return 'rgba(42,120,214,.12)';
    var g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
    g.addColorStop(0, 'rgba(42,120,214,.28)');
    g.addColorStop(1, 'rgba(42,120,214,0)');
    return g;
  }
  var trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
      labels: TREND.labels,
      datasets: [{
        data: TREND.sales, borderColor: C.series, borderWidth: 2, cubicInterpolationMode: 'monotone', fill: true, backgroundColor: areaFill,
        pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: C.series, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2
      }]
    },
    options: {
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: Object.assign({}, tooltip, {
          callbacks: {
            label: function (c) {
              return metric === 'sales' ? 'Sales: ' + peso(c.parsed.y, 2) : 'Bookings: ' + c.parsed.y;
            }
          }
        })
      },
      scales: {
        x: { grid: { display: false }, border: { color: '#c3c2b7' }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
        y: {
          beginAtZero: true, grid: { color: C.grid }, border: { display: false },
          ticks: { maxTicksLimit: 5, precision: 0, callback: function (v) { return metric === 'sales' ? compactPeso(v) : v; } }
        }
      }
    },
    plugins: [crosshair]
  });

  document.querySelectorAll('.seg-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      metric = btn.getAttribute('data-metric');
      document.querySelectorAll('.seg-btn').forEach(function (b) {
        var on = b === btn; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      var data = TREND[metric];
      var sum = data.reduce(function (a, b) { return a + b; }, 0);
      document.getElementById('trend-title').textContent = (metric === 'sales' ? 'Sales' : 'Bookings') + ' · last 14 days';
      document.getElementById('trend-total').innerHTML = (metric === 'sales' ? peso(sum, 2) : sum) + '<small>total</small>';
      trendChart.data.datasets[0].data = data;
      trendChart.update();
    });
  });

  // ---- Revenue by route: ranked horizontal bars, one hue ----
  var routeCtx = document.getElementById('routeChart');
  if (routeCtx && ROUTES.length) {
    new Chart(routeCtx, {
      type: 'bar',
      data: {
        labels: ROUTES.map(function (r) { return r.route; }),
        datasets: [{
          data: ROUTES.map(function (r) { return +r.revenue; }),
          backgroundColor: C.series, hoverBackgroundColor: '#1c5cab',
          borderRadius: 4, borderSkipped: 'start', maxBarThickness: 18, categoryPercentage: .7
        }]
      },
      options: {
        indexAxis: 'y', maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: Object.assign({}, tooltip, {
            callbacks: {
              label: function (c) {
                var r = ROUTES[c.dataIndex];
                return ['Revenue: ' + peso(r.revenue, 2), 'Seats sold: ' + r.seats];
              }
            }
          })
        },
        scales: {
          x: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: compactPeso } },
          y: { grid: { display: false }, border: { color: '#c3c2b7' }, ticks: { color: C.ink2 } }
        }
      }
    });
  }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
