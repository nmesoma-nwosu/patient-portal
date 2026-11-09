<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('patient');

$pageTitle = "My Appointments";
$patientId = $_SESSION['user_id'];

// ── POST: cancel an appointment ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        // Only allow cancelling own booked appointments
        $ownStmt = $connection->prepare("
            SELECT id FROM appointments
            WHERE id = ? AND patient_id = ? AND status = 'booked'
        ");
        $ownStmt->execute([$appointmentId, $patientId]);

        if ($ownStmt->fetch()) {
            $upd = $connection->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $upd->execute([$appointmentId]);
        }
    }

    header("Location: appointments.php");
    exit;
}

// ── Filter ─────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'upcoming';

$where = "a.patient_id = ?";
$params = [$patientId];

switch ($filter) {
    case 'all':
        break;
    case 'upcoming':
        $where .= " AND a.status = 'booked'";
        break;
    case 'completed':
        $where .= " AND a.status = 'completed'";
        break;
    case 'cancelled':
        $where .= " AND a.status IN ('cancelled', 'no_show')";
        break;
}

$sql = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.confirmed,
               a.doctor_specialty,
               u.name AS doctor_name,
               mr.id AS has_record
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        LEFT JOIN medical_records mr ON mr.appointment_id = a.id
        WHERE $where
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $connection->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require_once "../includes/header.php";
?>

<div class="page-card" style="max-width:100%;">
  <h2>My Appointments</h2>
  <p class="sub">View and manage your appointments.</p>

  <div class="filter-tabs">
    <a href="appointments.php?filter=upcoming" class="filter-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
    <a href="appointments.php?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
    <a href="appointments.php?filter=cancelled" class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
    <a href="appointments.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
  </div>

  <?php if (empty($appointments)): ?>
    <p class="sub">Nothing here right now.</p>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="appt-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Doctor</th>
            <th>Specialty</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $appt): ?>
            <tr>
              <td style="white-space:nowrap;"><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
              <td style="white-space:nowrap;"><?php echo htmlspecialchars(substr($appt['appointment_time'], 0, 5)); ?></td>
              <td><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
              <td><?php echo htmlspecialchars($appt['doctor_specialty']); ?></td>
              <td>
                <?php if ($appt['status'] === 'booked' && !$appt['confirmed']): ?>
                  <span class="status-badge status-pending">Pending confirmation</span>
                <?php elseif ($appt['status'] === 'booked' && $appt['confirmed']): ?>
                  <span class="status-badge status-confirmed">Confirmed</span>
                <?php elseif ($appt['status'] === 'completed'): ?>
                  <span class="status-badge status-completed">Completed</span>
                <?php elseif ($appt['status'] === 'cancelled'): ?>
                  <span class="status-badge status-cancelled">Cancelled</span>
                <?php elseif ($appt['status'] === 'no_show'): ?>
                  <span class="status-badge status-noshow">No-show</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($appt['status'] === 'booked'): ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                    <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['id']; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn-mini btn-mini-noshow">Cancel</button>
                  </form>
                <?php elseif ($appt['status'] === 'completed'): ?>
                  <?php if ($appt['has_record']): ?>
                    <a href="view-record.php?appointment_id=<?php echo (int)$appt['id']; ?>" class="btn-mini btn-mini-outline">
                      <i class="bi bi-file-earmark-text"></i> View Record
                    </a>
                  <?php else: ?>
                    <span class="sub" style="font-size:0.75rem;">No record yet</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="sub" style="font-size:0.75rem;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>
