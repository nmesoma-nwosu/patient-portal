<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole("doctor");

$pageTitle = "My Patients";


$doctorStmt = $connection->prepare("SELECT id FROM doctors WHERE user_id = ?");
$doctorStmt->execute([$_SESSION['user_id']]);
$doctorRow = $doctorStmt->fetch();
$doctorId = $doctorRow ? $doctorRow['id'] : null;


$viewPatientId = isset($_GET['view']) ? (int)$_GET['view'] : null;

$patients = [];
if ($doctorId) {
    $pStmt = $connection->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.phone, u.gender, u.dob,
               COUNT(a.id) AS visit_count,
               MAX(a.appointment_date) AS last_visit
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ?
          AND a.status = 'completed'
        GROUP BY u.id, u.name, u.email, u.phone, u.gender, u.dob
        ORDER BY last_visit DESC
    ");
    $pStmt->execute([$doctorId]);
    $patients = $pStmt->fetchAll();
}

$patientRecords = [];
$viewPatientName = '';
if ($viewPatientId && $doctorId) {

    // Verify this doctor has actually seen this patient
    $checkStmt = $connection->prepare("
        SELECT COUNT(*) FROM appointments
        WHERE doctor_id = ? AND patient_id = ? AND status = 'completed'
    ");
    $checkStmt->execute([$doctorId, $viewPatientId]);

    if ($checkStmt->fetchColumn() > 0) {

        // Get patient name
        $nameStmt = $connection->prepare("SELECT name FROM users WHERE id = ?");
        $nameStmt->execute([$viewPatientId]);
        $viewPatientName = $nameStmt->fetchColumn();

        // Get ALL records for this patient (from any doctor)
        $recStmt = $connection->prepare("
            SELECT mr.*,
                   a.appointment_date,
                   a.appointment_time,
                   a.doctor_specialty,
                   doc_user.name AS doctor_name,
                   d.id AS record_doctor_id
            FROM medical_records mr
            JOIN appointments a ON mr.appointment_id = a.id
            JOIN doctors d ON mr.doctor_id = d.id
            JOIN users doc_user ON d.user_id = doc_user.id
            WHERE mr.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $recStmt->execute([$viewPatientId]);
        $patientRecords = $recStmt->fetchAll();
    }
}

require_once "../includes/header.php";
?>

<?php if ($viewPatientId && $viewPatientName): ?>

  <!-- ===== Patient records view ===== -->
  <div class="page-card" style="max-width:100%;">
    <p class="sub" style="margin-bottom:4px;">
      <a href="patients.php" class="altlink">&larr; Back to all patients</a>
    </p>
    <h2><?php echo htmlspecialchars($viewPatientName); ?>'s Records</h2>
    <p class="sub">All medical records for this patient. You can only edit records you created.</p>

    <?php if (empty($patientRecords)): ?>
      <p class="sub">No medical records found for this patient.</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="appt-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Specialty</th>
              <th>Doctor</th>
              <th>Complaint</th>
              <th>Diagnosis</th>
              <th>Vitals</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($patientRecords as $rec): ?>
              <tr>
                <td style="white-space:nowrap;"><?php echo htmlspecialchars($rec['appointment_date']); ?></td>
                <td><?php echo htmlspecialchars($rec['doctor_specialty']); ?></td>
                <td>
                  <?php echo htmlspecialchars($rec['doctor_name']); ?>
                  <?php if ((int)$rec['record_doctor_id'] === $doctorId): ?>
                    <span class="status-badge status-confirmed" style="margin-left:4px;">You</span>
                  <?php endif; ?>
                </td>
                <td style="max-width:200px;"><?php echo htmlspecialchars(mb_strimwidth($rec['complaint'], 0, 80, '...')); ?></td>
                <td style="max-width:200px;"><?php echo htmlspecialchars(mb_strimwidth($rec['diagnosis'], 0, 80, '...')); ?></td>
                <td style="white-space:nowrap; font-size:0.78rem;">
                  <?php if ($rec['blood_pressure']): ?>
                    <span title="Blood Pressure">BP: <?php echo htmlspecialchars($rec['blood_pressure']); ?></span><br>
                  <?php endif; ?>
                  <?php if ($rec['heart_rate']): ?>
                    <span title="Heart Rate">HR: <?php echo $rec['heart_rate']; ?> bpm</span><br>
                  <?php endif; ?>
                  <?php if ($rec['temperature']): ?>
                    <span title="Temperature">T: <?php echo $rec['temperature']; ?>°</span><br>
                  <?php endif; ?>
                  <?php if ($rec['weight']): ?>
                    <span title="Weight">W: <?php echo $rec['weight']; ?> kg</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$rec['record_doctor_id'] === $doctorId): ?>
                    <a href="record.php?appointment_id=<?php echo (int)$rec['appointment_id']; ?>"
                       class="btn-mini btn-mini-outline">Edit Record</a>
                  <?php else: ?>
                    <span class="sub" style="font-size:0.75rem;">View only</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php else: ?>

  <!-- ===== Patient list ===== -->
  <div class="page-card" style="max-width:100%;">
    <h2>My Patients</h2>
    <p class="sub">Patients you've seen in completed appointments.</p>

    <?php if (empty($patients)): ?>
      <p class="sub">No patients yet.</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="appt-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Gender</th>
              <th>DOB</th>
              <th>Contact</th>
              <th>Visits</th>
              <th>Last Visit</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($patients as $p): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                <td><?php echo htmlspecialchars(ucfirst($p['gender'] ?? '—')); ?></td>
                <td style="white-space:nowrap;"><?php echo htmlspecialchars($p['dob'] ?? '—'); ?></td>
                <td>
                  <?php if ($p['email']): ?>
                    <span style="font-size:0.8rem;"><?php echo htmlspecialchars($p['email']); ?></span><br>
                  <?php endif; ?>
                  <?php if ($p['phone']): ?>
                    <span style="font-size:0.8rem;"><?php echo htmlspecialchars($p['phone']); ?></span>
                  <?php endif; ?>
                </td>
                <td><?php echo (int)$p['visit_count']; ?></td>
                <td style="white-space:nowrap;"><?php echo htmlspecialchars($p['last_visit']); ?></td>
                <td>
                  <a href="patients.php?view=<?php echo (int)$p['id']; ?>" class="btn-mini btn-mini-outline">
                    <i class="bi bi-folder2-open"></i> View Records
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>