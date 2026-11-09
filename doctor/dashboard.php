<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('doctor');

$pageTitle = "Dashboard";

$doctorStmt = $connection->prepare("SELECT id, specialty FROM doctors WHERE user_id = ?");
$doctorStmt->execute([$_SESSION['user_id']]);
$doctorRow = $doctorStmt->fetch();
$doctorId = $doctorRow ? $doctorRow['id'] : null;
$specialty = $doctorRow['specialty'] ?? '';

// ── Quick confirm action from dashboard ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctorId) {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    if (($_POST['action'] ?? '') === 'confirm') {
        $ownStmt = $connection->prepare(
            "SELECT id FROM appointments WHERE id = ? AND doctor_id = ? AND status = 'booked' AND confirmed = 0"
        );
        $ownStmt->execute([$appointmentId, $doctorId]);

        if ($ownStmt->fetch()) {
            $upd = $connection->prepare("UPDATE appointments SET confirmed = 1 WHERE id = ?");
            $upd->execute([$appointmentId]);

            $logStmt = $connection->prepare(
                "INSERT INTO audit_log (user_id, action, table_affected, record_id)
                 VALUES (?, 'appointment_confirmed', 'appointments', ?)"
            );
            $logStmt->execute([$_SESSION['user_id'], $appointmentId]);
        }
    }

    header("Location: dashboard.php");
    exit;
}

// ── Stat counts ────────────────────────────────────────────
$stats = ['needs_confirmation' => 0, 'upcoming' => 0, 'completed' => 0, 'patients' => 0];

if ($doctorId) {
    // Needs confirmation
    $s = $connection->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'booked' AND confirmed = 0");
    $s->execute([$doctorId]);
    $stats['needs_confirmation'] = (int)$s->fetchColumn();

    // Upcoming confirmed
    $s = $connection->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'booked' AND confirmed = 1 AND appointment_date >= CURDATE()");
    $s->execute([$doctorId]);
    $stats['upcoming'] = (int)$s->fetchColumn();

    // Completed all time
    $s = $connection->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'completed'");
    $s->execute([$doctorId]);
    $stats['completed'] = (int)$s->fetchColumn();

    // Unique patients
    $s = $connection->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ? AND status = 'completed'");
    $s->execute([$doctorId]);
    $stats['patients'] = (int)$s->fetchColumn();
}

// ── Today's schedule ───────────────────────────────────────
$todayAppts = [];
if ($doctorId) {
    $tStmt = $connection->prepare("
        SELECT a.id, a.appointment_time, a.status, a.confirmed,
               u.name AS patient_name
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status = 'booked'
        ORDER BY a.appointment_time ASC
    ");
    $tStmt->execute([$doctorId]);
    $todayAppts = $tStmt->fetchAll();
}

// ── Needs confirmation (top 5) ─────────────────────────────
$needsConfirmation = [];
if ($doctorId) {
    $ncStmt = $connection->prepare(
        "SELECT a.id, a.appointment_date, a.appointment_time, u.name AS patient_name
         FROM appointments a
         JOIN users u ON a.patient_id = u.id
         WHERE a.doctor_id = ? AND a.status = 'booked' AND a.confirmed = 0
         ORDER BY a.appointment_date ASC, a.appointment_time ASC
         LIMIT 5"
    );
    $ncStmt->execute([$doctorId]);
    $needsConfirmation = $ncStmt->fetchAll();
}

// ── Upcoming confirmed (next 5) ────────────────────────────
$upcoming = [];
if ($doctorId) {
    $upStmt = $connection->prepare(
        "SELECT a.id, a.appointment_date, a.appointment_time, u.name AS patient_name
         FROM appointments a
         JOIN users u ON a.patient_id = u.id
         WHERE a.doctor_id = ? AND a.status = 'booked' AND a.confirmed = 1
               AND a.appointment_date >= CURDATE()
         ORDER BY a.appointment_date ASC, a.appointment_time ASC
         LIMIT 5"
    );
    $upStmt->execute([$doctorId]);
    $upcoming = $upStmt->fetchAll();
}

// ── Pending cancellation requests ──────────────────────────
$pendingCancels = 0;
if ($doctorId) {
    $s = $connection->prepare("SELECT COUNT(*) FROM cancellation_requests WHERE doctor_id = ? AND status = 'pending'");
    $s->execute([$doctorId]);
    $pendingCancels = (int)$s->fetchColumn();
}

// Monthly scheduling nudge
$daysLeftInMonth = (int)date('t') - (int)date('j');
$showMonthlyNudge = $daysLeftInMonth <= 7;
$nextMonthName = date('F', strtotime('first day of next month'));

require_once "../includes/header.php";
?>

<!-- ===== Greeting ===== -->
<div class="dash-greeting">
  <div>
    <h2 style="margin:0;">Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>, Dr. <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
    <p class="sub" style="margin:4px 0 0;"><?php echo htmlspecialchars($specialty); ?> &middot; <?php echo date('l, M j, Y'); ?></p>
  </div>
</div>

<!-- ===== Stat cards ===== -->
<div class="dash-stats">
  <a href="appointments.php?filter=needs_confirmation" class="dash-stat-card">
    <i class="bi bi-clock-history" style="color:#E65100;"></i>
    <span class="dash-stat-num"><?php echo $stats['needs_confirmation']; ?></span>
    <span class="dash-stat-label">Needs Confirmation</span>
  </a>
  <a href="appointments.php?filter=upcoming" class="dash-stat-card">
    <i class="bi bi-calendar-check" style="color:var(--teal);"></i>
    <span class="dash-stat-num"><?php echo $stats['upcoming']; ?></span>
    <span class="dash-stat-label">Upcoming</span>
  </a>
  <a href="appointments.php?filter=completed" class="dash-stat-card">
    <i class="bi bi-check-circle" style="color:var(--purple);"></i>
    <span class="dash-stat-num"><?php echo $stats['completed']; ?></span>
    <span class="dash-stat-label">Completed</span>
  </a>
  <a href="patients.php" class="dash-stat-card">
    <i class="bi bi-people" style="color:var(--teal-dark);"></i>
    <span class="dash-stat-num"><?php echo $stats['patients']; ?></span>
    <span class="dash-stat-label">Patients</span>
  </a>
</div>

<!-- ===== Alerts row ===== -->
<?php if (!empty($needsConfirmation) || $pendingCancels > 0 || $showMonthlyNudge): ?>
<div class="dash-alerts">

  <?php if ($pendingCancels > 0): ?>
    <div class="dash-alert-card" style="border-color:var(--err-line); background:var(--err-bg);">
      <i class="bi bi-exclamation-triangle" style="color:var(--err); font-size:1.2rem;"></i>
      <div class="flex-grow-1">
        <strong><?php echo $pendingCancels; ?> pending cancellation request<?php echo $pendingCancels !== 1 ? 's' : ''; ?></strong>
        <div class="sub" style="margin:0;">A patient or you have requested to cancel.</div>
      </div>
      <a href="appointments.php" class="btn-mini btn-mini-outline">Review</a>
    </div>
  <?php endif; ?>

  <?php if ($showMonthlyNudge): ?>
    <div class="dash-alert-card" style="border-color:var(--purple); background:var(--lavender);">
      <i class="bi bi-calendar3" style="color:var(--purple); font-size:1.2rem;"></i>
      <div class="flex-grow-1">
        <strong style="color:var(--purple-dark);">Plan ahead</strong>
        <div class="sub" style="margin:0;">Block off unavailable time for <?php echo htmlspecialchars($nextMonthName); ?> before patients book.</div>
      </div>
      <a href="availability.php" class="btn-mini btn-mini-complete">Manage</a>
    </div>
  <?php endif; ?>

</div>
<?php endif; ?>

<!-- ===== Main grid: Today's Schedule + Needs Confirmation ===== -->
<div class="dash-grid">

  <!-- Today's Schedule -->
  <div class="page-card" style="margin:0;">
    <div class="d-flex align-items-center justify-content-between">
      <h2 style="margin:0; font-size:1.1rem;">Today's Schedule</h2>
      <span class="status-badge status-confirmed"><?php echo count($todayAppts); ?> appointment<?php echo count($todayAppts) !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if (empty($todayAppts)): ?>
      <p class="sub mt-3">No appointments today. Enjoy the break!</p>
    <?php else: ?>
      <div class="d-flex flex-column gap-2 mt-3">
        <?php foreach ($todayAppts as $appt): ?>
          <div class="dash-schedule-row">
            <div class="dash-schedule-time"><?php echo htmlspecialchars(substr($appt['appointment_time'], 0, 5)); ?></div>
            <div class="flex-grow-1">
              <strong><?php echo htmlspecialchars($appt['patient_name']); ?></strong>
              <?php if (!$appt['confirmed']): ?>
                <span class="status-badge status-pending" style="margin-left:6px;">Unconfirmed</span>
              <?php endif; ?>
            </div>
            <?php if (!$appt['confirmed']): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['id']; ?>">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn-mini btn-mini-confirm">Confirm</button>
              </form>
            <?php else: ?>
              <span class="status-badge status-confirmed">Confirmed</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Needs Confirmation -->
  <div class="page-card" style="margin:0;">
    <div class="d-flex align-items-center justify-content-between">
      <h2 style="margin:0; font-size:1.1rem;">Awaiting Confirmation</h2>
      <?php if ($stats['needs_confirmation'] > 0): ?>
        <span class="status-badge status-pending"><?php echo $stats['needs_confirmation']; ?></span>
      <?php endif; ?>
    </div>

    <?php if (empty($needsConfirmation)): ?>
      <p class="sub mt-3">All caught up — nothing to confirm.</p>
    <?php else: ?>
      <div class="d-flex flex-column gap-2 mt-3">
        <?php foreach ($needsConfirmation as $appt): ?>
          <div class="dash-schedule-row">
            <div class="dash-schedule-date">
              <span class="record-row-day" style="font-size:1rem;"><?php echo (new DateTime($appt['appointment_date']))->format('d'); ?></span>
              <span class="record-row-month"><?php echo (new DateTime($appt['appointment_date']))->format('M'); ?></span>
            </div>
            <div class="flex-grow-1">
              <strong><?php echo htmlspecialchars($appt['patient_name']); ?></strong>
              <div class="sub" style="margin:0;"><?php echo htmlspecialchars(substr($appt['appointment_time'], 0, 5)); ?></div>
            </div>
            <form method="post" style="display:inline;">
              <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['id']; ?>">
              <input type="hidden" name="action" value="confirm">
              <button type="submit" class="btn-mini btn-mini-confirm">Confirm</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($stats['needs_confirmation'] > 5): ?>
        <a href="appointments.php?filter=needs_confirmation" class="altlink d-block mt-3">See all <?php echo $stats['needs_confirmation']; ?> &rarr;</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<!-- ===== Upcoming appointments ===== -->
<div class="page-card" style="max-width:100%;">
  <div class="d-flex align-items-center justify-content-between">
    <h2 style="margin:0; font-size:1.1rem;">Upcoming Appointments</h2>
  </div>

  <?php if (empty($upcoming)): ?>
    <p class="sub mt-3">No upcoming confirmed visits.</p>
  <?php else: ?>
    <div style="overflow-x:auto;" class="mt-3">
      <table class="appt-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Patient</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $appt): ?>
            <tr>
              <td style="white-space:nowrap;"><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
              <td style="white-space:nowrap;"><?php echo htmlspecialchars(substr($appt['appointment_time'], 0, 5)); ?></td>
              <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
              <td><span class="status-badge status-confirmed">Confirmed</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a href="appointments.php?filter=upcoming" class="altlink d-block mt-3">View all appointments &rarr;</a>
  <?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>
