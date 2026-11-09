<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('doctor');

$pageTitle = "Add Medical Record";

$appointmentId = (int)($_GET['appointment_id'] ?? 0);


$doctorStmt = $connection -> prepare("SELECT id FROM  doctors WHERE user_id = ?");
$doctorStmt -> execute([$_SESSION['user_id']]);
$doctorRow = $doctorStmt -> fetch();
$doctorId = $doctorRow ? $doctorRow['id'] :null;

$ownStmt = $connection -> prepare (" SELECT a.*, u.name AS patient_name 
                                    FROM appointments a 
                                    JOIN users u ON a.patient_id= u.id 
                                    WHERE a.id = ? AND a.doctor_id = ? 
                                    AND a.status = 'completed'");

$ownStmt -> execute([$appointmentId, $doctorId]);

$recordStmt = $connection -> prepare(" SELECT * FROM medical_records mr WHERE appointment_id = ?");
$recordStmt-> execute([$appointmentId]);
$record = $recordStmt -> fetch();


$appointment = $ownStmt-> fetch();
if (!$appointment) {
    header("Location: appointments.php");
    exit;
}
$patientId = (int)$appointment['patient_id'];


$errors = [];


if($_SERVER['REQUEST_METHOD'] === 'POST'){
  
    $blood_pressure = trim($_POST['blood_pressure']);
    $heart_rate = trim($_POST['heart_rate']);
    $temperature = trim($_POST['temperature']);
    $weight = trim($_POST['weight']);
    $complaint = trim($_POST['complaint']);
    $diagnosis = trim($_POST['diagnosis']);

    $errors= [];

    if ($complaint === ''){
        $errors[]= "input patient complaint";

    }
    if ($diagnosis === ''){
        $errors[]= "input patient diagnosis";

    }

    if(empty($errors)){
        if(!$record){
             $insertStmt = $connection->prepare("
                INSERT INTO medical_records
                (appointment_id, patient_id, doctor_id, blood_pressure, heart_rate, temperature, weight, complaint, diagnosis)
                VALUES (?, ?, ?, ?, ?, ?, ?,?,?)
            ");

            $insertStmt->execute([
                $appointmentId,
                $patientId,
                $doctorId,
                $blood_pressure,
                $heart_rate,
                $temperature,
                $weight,
                $complaint,
                $diagnosis
            ]);



        }
        else{
             $updateStmt = $connection->prepare("
                UPDATE medical_records
                SET blood_pressure = ?,
                    heart_rate = ?,
                    temperature = ?,
                    weight = ?,
                    complaint = ?,
                    diagnosis = ?
                WHERE appointment_id = ?
            ");

            $updateStmt->execute([
                $blood_pressure,
                $heart_rate,
                $temperature,
                $weight,
                $complaint,
                $diagnosis,
                $appointmentId
            ]);
        }

        header("Location: appointments.php?record_success=1");
        exit;
    }

}


require_once "../includes/header.php";
?>

<div class="page-card">
  <h2><?php echo $record ? 'Edit' : 'Add'; ?> Medical Record</h2>
  <p class="sub">
    <?php echo htmlspecialchars($appointment['patient_name']);  ?> &middot;
    <?php  echo htmlspecialchars($appointment['appointment_date']);  ?>
  </p>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert-care mb-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" novalidate>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="blood_pressure">Blood pressure</label>
        <input type="text" class="form-control" id="blood_pressure" name="blood_pressure"
               placeholder="e.g. 120/80"
               value="<?php  echo htmlspecialchars($record['blood_pressure'] ?? '');  ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="heart_rate">Heart rate (bpm)</label>
        <input type="number" class="form-control" id="heart_rate" name="heart_rate"
               value="<?php  echo htmlspecialchars($record['heart_rate'] ?? '');  ?>">
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="temperature">Temperature</label>
        <input type="number" step="0.1" class="form-control" id="temperature" name="temperature"
               value="<?php  echo htmlspecialchars($record['temperature'] ?? '');  ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="weight">Weight</label>
        <input type="number" step="0.1" class="form-control" id="weight" name="weight"
               value="<?php echo htmlspecialchars($record['weight'] ?? ''); ?>">
      </div>
    </div>
     <div class="mb-3">
      <label class="form-label" for="complaint"> Patient Complaint</label>
      <textarea class="form-control" id="complaint" name="complaint" rows="5"
                required><?php  echo htmlspecialchars($record['complaint'] ?? '');  ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label" for="diagnosis">Diagnosis / notes</label>
      <textarea class="form-control" id="diagnosis" name="diagnosis" rows="5"
                required><?php  echo htmlspecialchars($record['diagnosis'] ?? '');  ?></textarea>
    </div>

    <button type="submit" class="btn btn-care"><?php echo $record ? 'Save changes' : 'Save record'; ?></button>
    <a href="appointments.php" class="btn-mini btn-mini-outline" style="margin-left:8px; vertical-align:middle;">Cancel</a>

  </form>
</div>

<?php require_once "../includes/footer.php"; ?>