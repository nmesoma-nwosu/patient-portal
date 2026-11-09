<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('patient');

$pageTitle = "My Records";
$patientId = $_SESSION['user_id'];

$specialtyIcons = [
    "General Practice"          => "bi-clipboard2-pulse",
    "Cardiology"                => "bi-heart-pulse",
    "Dermatology"               => "bi-bandaid",
    "Pediatrics"                => "bi-emoji-smile",
    "Orthopedics"               => "bi-activity",
    "Neurology"                 => "bi-cpu",
    "Psychiatry"                => "bi-chat-heart",
    "Dentistry"                 => "bi-emoji-laughing",
    "ENT (Ear, Nose & Throat)"  => "bi-soundwave",
    "Ophthalmology"             => "bi-eye",
];

// ── Which specialty folder is open? ────────────────────────
$selectedSpecialty = $_GET['specialty'] ?? '';

// ── Get record counts grouped by specialty ─────────────────
$folderStmt = $connection->prepare("
    SELECT a.doctor_specialty, COUNT(mr.id) AS record_count
    FROM medical_records mr
    JOIN appointments a ON mr.appointment_id = a.id
    WHERE mr.patient_id = ?
    GROUP BY a.doctor_specialty
    ORDER BY a.doctor_specialty
");
$folderStmt->execute([$patientId]);
$folders = $folderStmt->fetchAll();

// ── If a specialty is selected, fetch records ──────────────
$records = [];
if ($selectedSpecialty) {
    $recStmt = $connection->prepare("
        SELECT mr.*,
               a.appointment_date,
               a.appointment_time,
               a.doctor_specialty,
               doc_user.name AS doctor_name
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN doctors d ON mr.doctor_id = d.id
        JOIN users doc_user ON d.user_id = doc_user.id
        WHERE mr.patient_id = ? AND a.doctor_specialty = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $recStmt->execute([$patientId, $selectedSpecialty]);
    $records = $recStmt->fetchAll();
}

require_once "../includes/header.php";
?>

<?php if ($selectedSpecialty): ?>

  <!-- ===== Records list for a specialty ===== -->
  <div class="page-card" style="max-width:100%;">
    <p class="sub" style="margin-bottom:4px;">
      <a href="records.php" class="altlink">&larr; All folders</a>
    </p>
    <h2>
      <i class="bi <?php echo $specialtyIcons[$selectedSpecialty] ?? 'bi-folder2'; ?>" style="color:var(--teal);"></i>
      <?php echo htmlspecialchars($selectedSpecialty); ?>
    </h2>
    <p class="sub"><?php echo count($records); ?> record<?php echo count($records) !== 1 ? 's' : ''; ?></p>

    <?php if (empty($records)): ?>
      <p class="sub">No records found.</p>
    <?php else: ?>
      <div class="d-flex flex-column gap-2 mt-3">
        <?php foreach ($records as $rec): ?>
          <a href="view-record.php?appointment_id=<?php echo (int)$rec['appointment_id']; ?>" class="record-row">
            <div class="record-row-date">
              <span class="record-row-day"><?php echo (new DateTime($rec['appointment_date']))->format('d'); ?></span>
              <span class="record-row-month"><?php echo (new DateTime($rec['appointment_date']))->format('M Y'); ?></span>
            </div>
            <div class="record-row-info">
              <strong>Dr. <?php echo htmlspecialchars($rec['doctor_name']); ?></strong>
              <div class="sub" style="margin:0;">
                <?php echo htmlspecialchars(mb_strimwidth($rec['complaint'], 0, 100, '...')); ?>
              </div>
              <div style="margin-top:4px; font-size:0.76rem; color:var(--ink-soft);">
                <?php if ($rec['blood_pressure']): ?>BP: <?php echo htmlspecialchars($rec['blood_pressure']); ?> &middot; <?php endif; ?>
                <?php if ($rec['heart_rate']): ?>HR: <?php echo $rec['heart_rate']; ?> bpm &middot; <?php endif; ?>
                <?php if ($rec['temperature']): ?>T: <?php echo $rec['temperature']; ?>° &middot; <?php endif; ?>
                <?php if ($rec['weight']): ?>W: <?php echo $rec['weight']; ?> kg<?php endif; ?>
              </div>
            </div>
            <i class="bi bi-chevron-right" style="color:var(--ink-soft);"></i>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php else: ?>

  <!-- ===== Specialty folders ===== -->
  <div class="page-card" style="max-width:100%;">
    <h2>My Records</h2>
    <p class="sub">Your medical records organised by area of care.</p>

    <?php if (empty($folders)): ?>
      <p class="sub">No records yet. Records will appear here after your doctor adds them following a completed appointment.</p>
    <?php else: ?>
      <div class="specialty-grid mt-3">
        <?php foreach ($folders as $folder): ?>
          <a href="records.php?specialty=<?php echo urlencode($folder['doctor_specialty']); ?>" class="specialty-card">
            <i class="bi <?php echo $specialtyIcons[$folder['doctor_specialty']] ?? 'bi-folder2'; ?>"></i>
            <span><?php echo htmlspecialchars($folder['doctor_specialty']); ?></span>
            <span class="folder-count"><?php echo (int)$folder['record_count']; ?> record<?php echo $folder['record_count'] != 1 ? 's' : ''; ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>
