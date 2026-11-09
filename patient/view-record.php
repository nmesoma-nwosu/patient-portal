<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('patient');

$pageTitle = "View Record";
$patientId = $_SESSION['user_id'];
$appointmentId = (int)($_GET['appointment_id'] ?? 0);

// Fetch the record — only if it belongs to this patient
$stmt = $connection->prepare("
    SELECT mr.*,
           a.appointment_date,
           a.appointment_time,
           a.doctor_specialty,
           doc_user.name AS doctor_name
    FROM medical_records mr
    JOIN appointments a ON mr.appointment_id = a.id
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users doc_user ON d.user_id = doc_user.id
    WHERE mr.appointment_id = ? AND mr.patient_id = ?
");
$stmt->execute([$appointmentId, $patientId]);
$record = $stmt->fetch();

if (!$record) {
    header("Location: appointments.php");
    exit;
}

require_once "../includes/header.php";
?>

<div class="page-card">
  <p class="sub" style="margin-bottom:4px;">
    <a href="appointments.php" class="altlink">&larr; Back to appointments</a>
  </p>
  <h2>Medical Record</h2>
  <p class="sub">
    <?php echo htmlspecialchars($record['appointment_date']); ?> &middot;
    Dr. <?php echo htmlspecialchars($record['doctor_name']); ?> &middot;
    <?php echo htmlspecialchars($record['doctor_specialty']); ?>
  </p>

  <div class="record-details mt-3">

    <div class="record-section">
      <h6>Vitals</h6>
      <div class="record-vitals-grid">
        <div class="record-vital">
          <span class="record-vital-label">Blood Pressure</span>
          <span class="record-vital-value"><?php echo htmlspecialchars($record['blood_pressure'] ?: '—'); ?></span>
        </div>
        <div class="record-vital">
          <span class="record-vital-label">Heart Rate</span>
          <span class="record-vital-value"><?php echo $record['heart_rate'] ? $record['heart_rate'] . ' bpm' : '—'; ?></span>
        </div>
        <div class="record-vital">
          <span class="record-vital-label">Temperature</span>
          <span class="record-vital-value"><?php echo $record['temperature'] ? $record['temperature'] . '°C' : '—'; ?></span>
        </div>
        <div class="record-vital">
          <span class="record-vital-label">Weight</span>
          <span class="record-vital-value"><?php echo $record['weight'] ? $record['weight'] . ' kg' : '—'; ?></span>
        </div>
      </div>
    </div>

    <div class="record-section">
      <h6>Complaint</h6>
      <p><?php echo nl2br(htmlspecialchars($record['complaint'])); ?></p>
    </div>

    <div class="record-section">
      <h6>Diagnosis / Notes</h6>
      <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
    </div>

  </div>
</div>

<?php require_once "../includes/footer.php"; ?>
