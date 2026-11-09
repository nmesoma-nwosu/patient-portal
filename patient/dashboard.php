<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('patient');

$pageTitle = "Dashboard";
$patientId = $_SESSION['user_id'];

// ── Stat counts ────────────────────────────────────────────
$stats = ['upcoming' => 0, 'completed' => 0, 'doctors' => 0, 'records' => 0];

// Upcoming booked
$s = $connection->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'booked' AND appointment_date >= CURDATE()");
$s->execute([$patientId]);
$stats['upcoming'] = (int)$s->fetchColumn();

// Completed
$s = $connection->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'");
$s->execute([$patientId]);
$stats['completed'] = (int)$s->fetchColumn();

// Unique doctors seen
$s = $connection->prepare("SELECT COUNT(DISTINCT doctor_id) FROM appointments WHERE patient_id = ? AND status = 'completed'");
$s->execute([$patientId]);
$stats['doctors'] = (int)$s->fetchColumn();

// Medical records
$s = $connection->prepare("SELECT COUNT(*) FROM medical_records WHERE patient_id = ?");
$s->execute([$patientId]);
$stats['records'] = (int)$s->fetchColumn();

// ── Next appointment ───────────────────────────────────────
$nextStmt = $connection->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, a.confirmed,
           a.doctor_specialty,
           u.name AS doctor_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ? AND a.status = 'booked' AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 1
");
$nextStmt->execute([$patientId]);
$nextAppt = $nextStmt->fetch();

// ── Upcoming appointments (next 5) ────────────────────────
$upStmt = $connection->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, a.confirmed,
           a.doctor_specialty,
           u.name AS doctor_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ? AND a.status = 'booked' AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$upStmt->execute([$patientId]);
$upcoming = $upStmt->fetchAll();

// ── Recent records (last 3) ───────────────────────────────
$recStmt = $connection->prepare("
    SELECT mr.appointment_id, a.appointment_date, a.doctor_specialty,
           doc_user.name AS doctor_name,
           mr.complaint
    FROM medical_records mr
    JOIN appointments a ON mr.appointment_id = a.id
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users doc_user ON d.user_id = doc_user.id
    WHERE mr.patient_id = ?
    ORDER BY a.appointment_date DESC
    LIMIT 3
");
$recStmt->execute([$patientId]);
$recentRecords = $recStmt->fetchAll();

// ── Days until next appointment ────────────────────────────
$daysUntil = null;
if ($nextAppt) {
    $now = new DateTime('today');
    $apptDate = new DateTime($nextAppt['appointment_date']);
    $daysUntil = (int)$now->diff($apptDate)->days;
}

require_once "../includes/header.php";
?>

<?php if (isset($_GET['booked']) && $_GET['booked'] == '1'): ?>
  <div class="alert-care mb-3" style="background:var(--mint);border-color:var(--teal);color:var(--teal-dark);">
    ✓ Appointment booked successfully! View it in <a href="appointments.php" style="color:var(--teal-dark);font-weight:700;">My Appointments</a>.
  </div>
<?php endif; ?>

<!-- ===== Greeting ===== -->
<div class="dash-greeting">
  <div>
    <h2 style="margin:0;">Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
    <p class="sub" style="margin:4px 0 0;"><?php echo date('l, M j, Y'); ?></p>
  </div>
</div>

<!-- ===== Quick actions ===== -->
<div class="dash-quick-actions">
  <a href="book_appointment.php" class="dash-quick-btn">
    <i class="bi bi-calendar-plus"></i>
    <span>Book Appointment</span>
  </a>
  <a href="my-doctors.php" class="dash-quick-btn">
    <i class="bi bi-person-badge"></i>
    <span>My Doctors</span>
  </a>
  <a href="records.php" class="dash-quick-btn">
    <i class="bi bi-folder2-open"></i>
    <span>My Records</span>
  </a>
  <a href="profile.php" class="dash-quick-btn">
    <i class="bi bi-person-circle"></i>
    <span>My Profile</span>
  </a>
</div>

<!-- ===== Stat cards ===== -->
<div class="dash-stats">
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
</div>

<!-- ===== Main grid ===== -->
<div class="dash-grid">

  <!-- Next appointment highlight -->
  <div class="page-card" style="margin:0;">
    <div class="d-flex align-items-center justify-content-between">
      <h2 style="margin:0; font-size:1.1rem;">Next Appointment</h2>
    </div>

    <?php if ($nextAppt): ?>
      <div class="dash-next-appt mt-3">
        <div class="dash-next-date">
          <span class="dash-next-day"><?php echo (new DateTime($nextAppt['appointment_date']))->format('d'); ?></span>
          <span class="dash-next-month"><?php echo (new DateTime($nextAppt['appointment_date']))->format('M'); ?></span>
        </div>
        <div class="flex-grow-1">
          <strong>Dr. <?php echo htmlspecialchars($nextAppt['doctor_name']); ?></strong>
          <div class="sub" style="margin:0;">
            <?php echo htmlspecialchars($nextAppt['doctor_specialty']); ?> &middot;
            <?php echo htmlspecialchars(substr($nextAppt['appointment_time'], 0, 5)); ?>
          </div>
          <div style="margin-top:6px;">
            <?php if ($nextAppt['confirmed']): ?>
              <span class="status-badge status-confirmed">Confirmed</span>
            <?php else: ?>
              <span class="status-badge status-pending">Pending confirmation</span>
            <?php endif; ?>
            <?php if ($daysUntil === 0): ?>
              <span class="status-badge" style="background:var(--mint);color:var(--teal-dark);margin-left:4px;">Today!</span>
            <?php elseif ($daysUntil === 1): ?>
              <span class="status-badge" style="background:var(--lavender);color:var(--purple-dark);margin-left:4px;">Tomorrow</span>
            <?php elseif ($daysUntil <= 7): ?>
              <span class="status-badge" style="background:var(--offwhite);color:var(--ink-soft);margin-left:4px;">In <?php echo $daysUntil; ?> days</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php else: ?>
      <p class="sub mt-3">No upcoming appointments.</p>
      <a href="book_appointment.php" class="btn-mini btn-mini-confirm mt-2">
        <i class="bi bi-calendar-plus"></i> Book an appointment
      </a>
    <?php endif; ?>
  </div>

  <!-- Recent records -->
  <div class="page-card" style="margin:0;">
    <div class="d-flex align-items-center justify-content-between">
      <h2 style="margin:0; font-size:1.1rem;">Recent Records</h2>
      <?php if ($stats['records'] > 0): ?>
        <a href="records.php" class="altlink" style="font-size:0.8rem;">View all &rarr;</a>
      <?php endif; ?>
    </div>

    <?php if (empty($recentRecords)): ?>
      <p class="sub mt-3">No medical records yet. They'll appear here after your doctor adds them following a visit.</p>
    <?php else: ?>
      <div class="d-flex flex-column gap-2 mt-3">
        <?php foreach ($recentRecords as $rec): ?>
          <a href="view-record.php?appointment_id=<?php echo (int)$rec['appointment_id']; ?>" class="dash-schedule-row" style="text-decoration:none; color:var(--ink);">
            <div class="dash-schedule-date">
              <span class="record-row-day" style="font-size:1rem;"><?php echo (new DateTime($rec['appointment_date']))->format('d'); ?></span>
              <span class="record-row-month"><?php echo (new DateTime($rec['appointment_date']))->format('M'); ?></span>
            </div>
            <div class="flex-grow-1" style="min-width:0;">
              <strong>Dr. <?php echo htmlspecialchars($rec['doctor_name']); ?></strong>
              <div class="sub" style="margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?php echo htmlspecialchars($rec['doctor_specialty']); ?> &middot;
                <?php echo htmlspecialchars(mb_strimwidth($rec['complaint'], 0, 50, '...')); ?>
              </div>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--ink-soft);"></i>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ===== Upcoming appointments ===== -->
<?php if (!empty($upcoming)): ?>
<div class="page-card" style="max-width:100%;">
  <div class="d-flex align-items-center justify-content-between">
    <h2 style="margin:0; font-size:1.1rem;">Upcoming Appointments</h2>
    <a href="appointments.php?filter=upcoming" class="altlink" style="font-size:0.8rem;">View all &rarr;</a>
  </div>

  <div style="overflow-x:auto;" class="mt-3">
    <table class="appt-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Time</th>
          <th>Doctor</th>
          <th>Specialty</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $appt): ?>
          <tr>
            <td style="white-space:nowrap;"><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
            <td style="white-space:nowrap;"><?php echo htmlspecialchars(substr($appt['appointment_time'], 0, 5)); ?></td>
            <td>Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></td>
            <td><?php echo htmlspecialchars($appt['doctor_specialty']); ?></td>
            <td>
              <?php if ($appt['confirmed']): ?>
                <span class="status-badge status-confirmed">Confirmed</span>
              <?php else: ?>
                <span class="status-badge status-pending">Pending</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>
