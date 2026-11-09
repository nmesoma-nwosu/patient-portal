<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('patient');

$pageTitle = "My Doctors";
$patientId = $_SESSION['user_id'];

// ── Get all doctors who have treated this patient ──────────
$stmt = $connection->prepare("
    SELECT d.id AS doctor_id,
           u.name,
           u.email,
           u.phone,
           d.specialty,
           COUNT(a.id) AS visit_count,
           MAX(a.appointment_date) AS last_visit
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ?
      AND a.status = 'completed'
    GROUP BY d.id, u.name, u.email, u.phone, d.specialty
    ORDER BY last_visit DESC
");
$stmt->execute([$patientId]);
$doctors = $stmt->fetchAll();

require_once "../includes/header.php";
?>

<div class="page-card" style="max-width:100%;">
  <h2>My Doctors</h2>
  <p class="sub">Doctors who have treated you in past appointments.</p>

  <?php if (empty($doctors)): ?>
    <p class="sub">You haven't had any completed appointments yet.</p>
  <?php else: ?>
    <div class="d-flex flex-column gap-2 mt-3">
      <?php foreach ($doctors as $doc): ?>
        <div class="option-row" style="cursor:default; align-items:center;">
          <i class="bi bi-person-badge" style="font-size:1.6rem;"></i>
          <div class="flex-grow-1">
            <strong><?php echo htmlspecialchars($doc['name']); ?></strong>
            <div class="sub" style="margin:0;">
              <span class="status-badge status-completed" style="margin-right:4px;"><?php echo htmlspecialchars($doc['specialty']); ?></span>
              <?php echo (int)$doc['visit_count']; ?> visit<?php echo $doc['visit_count'] != 1 ? 's' : ''; ?>
              &middot; Last seen: <?php echo htmlspecialchars($doc['last_visit']); ?>
            </div>
            <?php if ($doc['email'] || $doc['phone']): ?>
              <div style="font-size:0.78rem; color:var(--ink-soft); margin-top:4px;">
                <?php if ($doc['email']): ?>
                  <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($doc['email']); ?>
                <?php endif; ?>
                <?php if ($doc['email'] && $doc['phone']): ?> &middot; <?php endif; ?>
                <?php if ($doc['phone']): ?>
                  <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($doc['phone']); ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <a href="book_appointment.php?specialty=<?php echo urlencode($doc['specialty']); ?>&doctor_id=<?php echo (int)$doc['doctor_id']; ?>"
             class="btn-mini btn-mini-confirm" style="white-space:nowrap;">
            <i class="bi bi-calendar-plus"></i> Book Again
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>
