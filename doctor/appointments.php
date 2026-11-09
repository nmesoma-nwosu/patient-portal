<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('doctor');

$pageTitle = "My Appointments";

$doctorStmt = $connection->prepare("SELECT id FROM doctors WHERE user_id = ?");
$doctorStmt->execute([$_SESSION['user_id']]);
$doctorRow = $doctorStmt->fetch();
$doctorId = $doctorRow ? $doctorRow['id'] : null;

$cancelErrors = [];

// ------------------------------------------------------------
// Handle actions: confirm / complete / no_show / request_cancellation
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctorId) {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $ownStmt = $connection->prepare("SELECT id, status, confirmed FROM appointments WHERE id = ? AND doctor_id = ?");
    $ownStmt->execute([$appointmentId, $doctorId]);
    $appt = $ownStmt->fetch();

    if ($appt) {
        $auditAction = null;
        $auditDetails = null;

        if ($action === 'confirm' && $appt['status'] === 'booked' && !$appt['confirmed']) {
            $upd = $connection->prepare("UPDATE appointments SET confirmed = 1 WHERE id = ?");
            $upd->execute([$appointmentId]);
            $auditAction = 'appointment_confirmed';

        } elseif ($action === 'complete' && $appt['status'] === 'booked' && $appt['confirmed']) {
            $upd = $connection->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
            $upd->execute([$appointmentId]);
            $auditAction = 'appointment_status_changed';
            $auditDetails = 'booked -> completed';

        } elseif ($action === 'no_show' && $appt['status'] === 'booked' && $appt['confirmed']) {
            $upd = $connection->prepare("UPDATE appointments SET status = 'no_show' WHERE id = ?");
            $upd->execute([$appointmentId]);
            $auditAction = 'appointment_status_changed';
            $auditDetails = 'booked -> no_show';

        } elseif ($action === 'request_cancellation' && $appt['status'] === 'booked') {
            $reason = trim($_POST['reason'] ?? '');

            if (empty($reason)) {
                $cancelErrors[] = "Please enter a reason for the cancellation request.";
            } else {
                // Don't allow a second pending request for the same appointment.
                $dupStmt = $connection->prepare(
                    "SELECT id FROM cancellation_requests WHERE appointment_id = ? AND status = 'pending'"
                );
                $dupStmt->execute([$appointmentId]);

                if (!$dupStmt->fetch()) {
                    $insStmt = $connection->prepare(
                        "INSERT INTO cancellation_requests (appointment_id, doctor_id, reason, status)
                         VALUES (?, ?, ?, 'pending')"
                    );
                    $insStmt->execute([$appointmentId, $doctorId, $reason]);
                    $auditAction = 'cancellation_requested';
                    $auditDetails = $reason;
                }
            }
        }

        if ($auditAction) {
            $logStmt = $connection->prepare(
                "INSERT INTO audit_log (user_id, action, table_affected, record_id, details)
                 VALUES (?, ?, 'appointments', ?, ?)"
            );
            $logStmt->execute([$_SESSION['user_id'], $auditAction, $appointmentId, $auditDetails]);
        }
    }

    if (empty($cancelErrors)) {
        header("Location: appointments.php");
        exit;
    }
    // if there were errors, fall through and re-render the page with $cancelErrors set
}

// ------------------------------------------------------------
// Fetch this doctor's appointments for the selected filter,
// plus whether each already has a pending cancellation request.
// ------------------------------------------------------------
$appointments = [];
$filter = $_GET['filter'] ?? 'all';

if ($doctorId) {
    $where = "a.doctor_id = ?";
    $params = [$doctorId];

    switch ($filter) {
         case 'all':
            break;
        case 'upcoming':
            $where .= " AND a.status = 'booked' AND a.confirmed = 1";
            break;
        case 'completed':
            $where .= " AND a.status = 'completed'";
            break;
        case 'cancelled':
            $where .= " AND a.status IN ('cancelled', 'no_show')";
            break;
       
        case 'needs_confirmation':
            $where .= " AND a.status = 'booked' AND a.confirmed = 0";
            break;
    
       
            
    }
    $sql = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.confirmed,
                   u.name AS patient_name,
                   cr.id AS pending_cancel_id,
                   mr.id AS has_record
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            LEFT JOIN cancellation_requests cr
                   ON cr.appointment_id = a.id AND cr.status = 'pending'
            LEFT JOIN medical_records mr
                   ON mr.appointment_id = a.id
            WHERE $where
            ORDER BY a.appointment_date ASC, a.appointment_time ASC";

    $apptStmt = $connection->prepare($sql);
    $apptStmt->execute($params);
    $appointments = $apptStmt->fetchAll();
}

require_once "../includes/header.php";
?>

<div class="page-card" style="max-width:100%;">
  <h2>My Appointments</h2>
  <p class="sub">Confirm new bookings, then update status once a visit happens.</p>

  <?php if (!empty($cancelErrors)): ?>
    <?php foreach ($cancelErrors as $error): ?>
      <div class="alert-care mb-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="filter-tabs">
    <a href="appointments.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>

    <a href="appointments.php?filter=needs_confirmation" class="filter-tab <?php echo $filter === 'needs_confirmation' ? 'active' : ''; ?>">Needs Confirmation</a>
    <a href="appointments.php?filter=upcoming" class="filter-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
    <a href="appointments.php?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
    <a href="appointments.php?filter=cancelled" class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled / No-show</a>
  </div>

  <?php if (empty($appointments)): ?>
    <p class="sub">Nothing here right now.</p>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="appt-table">
        <thead>
          <tr>
            <th>Patient</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $appt): ?>
            <tr>
              <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
              <td><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
              <td><?php echo htmlspecialchars($appt['appointment_time']); ?></td>
              <td>
                <?php if ($appt['status'] === 'booked' && !$appt['confirmed']): ?>
                  <span class="status-badge status-pending">Needs confirmation</span>
                <?php elseif ($appt['status'] === 'booked' && $appt['confirmed']): ?>
                  <span class="status-badge status-confirmed">Confirmed</span>
                <?php elseif ($appt['status'] === 'completed'): ?>
                  <span class="status-badge status-completed">Completed</span>
                <?php elseif ($appt['status'] === 'cancelled'): ?>
                  <span class="status-badge status-cancelled">Cancelled</span>
                <?php elseif ($appt['status'] === 'no_show'): ?>
                  <span class="status-badge status-noshow">No-show</span>
                <?php endif; ?>

                <?php if ($appt['pending_cancel_id']): ?>
                  <span class="status-badge status-pending">Cancellation requested</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="appt-actions">
                  <?php if ($appt['status'] === 'booked' && !$appt['confirmed']): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['id']; ?>">
                      <input type="hidden" name="action" value="confirm">
                      <button type="submit" class="btn-mini btn-mini-confirm">Confirm</button>
                    </form>
                  <?php elseif ($appt['status'] === 'booked' && $appt['confirmed']): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['id']; ?>">
                      <input type="hidden" name="action" value="complete">
                      <button type="submit" class="btn-mini btn-mini-complete">Mark Completed</button>
                    </form>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['id']; ?>">
                      <input type="hidden" name="action" value="no_show">
                      <button type="submit" class="btn-mini btn-mini-noshow">No-show</button>
                    </form>

                    <?php if (!$appt['pending_cancel_id']): ?>
                      <button type="button" class="btn-mini btn-mini-outline"
                              data-bs-toggle="modal" data-bs-target="#cancelModal"
                              data-appt-id="<?php echo (int)$appt['id']; ?>">
                        Request Cancellation
                      </button>
                    <?php endif; ?>
                  <?php elseif ($appt['status'] === 'completed'): ?>
                    <a href="record.php?appointment_id=<?php echo (int)$appt['id']; ?>" class="btn-mini btn-mini-outline"><?php echo $appt['has_record'] ? 'Edit Record' : 'Add Record'; ?></a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Shared modal for requesting a cancellation — one modal, reused for every row -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px; border:none;">
      <form method="post">
        <input type="hidden" name="appointment_id" id="cancelApptId" value="">
        <input type="hidden" name="action" value="request_cancellation">
        <div class="modal-header" style="border-bottom:1px solid var(--line);">
          <h5 class="modal-title" style="font-family:'Fraunces', serif; font-weight:600;">Request Cancellation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label" for="reason">Reason</label>
          <textarea class="form-control" id="reason" name="reason" rows="4" required
                    placeholder="e.g. Family emergency, unable to work this date"></textarea>
          <p class="sub" style="margin-top:10px; margin-bottom:0;">
            This is sent to admin for review — the appointment stays booked until they approve it.
          </p>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--line);">
          <button type="button" class="btn-mini btn-mini-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-mini btn-mini-noshow">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const cancelModal = document.getElementById('cancelModal');
  cancelModal.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    const apptId = trigger.getAttribute('data-appt-id');
    document.getElementById('cancelApptId').value = apptId;
  });
</script>

<?php require_once "../includes/footer.php"; ?>
