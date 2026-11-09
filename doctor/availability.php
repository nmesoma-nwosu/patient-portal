<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole('doctor');

$pageTitle = "Manage Availability";
$availabilityMessage = $_GET['message'] ?? '';

// ── Find the logged-in doctor ───────────────────────────────

$docStmt = $connection->prepare(
    "SELECT id FROM doctors WHERE user_id = ?"
);

$docStmt->execute([$_SESSION['user_id']]);

$doctor = $docStmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header("Location: dashboard.php");
    exit;
}

$doctorId = $doctor['id'];


// ── Week calculation ───────────────────────────────────────

$weekOffset = (int)($_GET['week'] ?? 0);

$today = new DateTime();
$today->modify("monday this week");
$today->modify("{$weekOffset} weeks");

$weekStart = clone $today;

$weekEnd = clone $today;
$weekEnd->modify('+5 days'); // Saturday


// Build array of 6 working days (Mon–Sat)

$weekDays = [];

for ($i = 0; $i < 6; $i++) {

    $d = clone $weekStart;
    $d->modify("+{$i} days");

    $weekDays[] = $d->format('Y-m-d');
}


// ── Generate appointment slots ──────────────────────────────

$allSlots = [];

$cur = new DateTime('09:00');
$end = new DateTime('18:00');

while (true) {

    $slotEnd = clone $cur;
    $slotEnd->modify('+1 hour');

    if ($slotEnd > $end) {
        break;
    }

    $allSlots[] = [
        'start' => $cur->format('H:i'),
        'end'   => $slotEnd->format('H:i'),
    ];

    // 1 hour appointment + 15 minute break
    $cur->modify('+1 hour 15 minutes');
}


// ── Fetch existing blocks for this week ────────────────────

$blocksStmt = $connection->prepare("

    SELECT
        id,
        blocked_date,
        TIME_FORMAT(start_time, '%H:%i') AS start_time,
        TIME_FORMAT(end_time, '%H:%i') AS end_time,
        reason

    FROM doctor_blocked_time

    WHERE doctor_id = ?
      AND blocked_date BETWEEN ? AND ?

    ORDER BY blocked_date, start_time

");

$blocksStmt->execute([
    $doctorId,
    $weekDays[0],
    $weekDays[5]
]);

$rawBlocks = $blocksStmt->fetchAll(PDO::FETCH_ASSOC);


// ── Organise blocks by date ─────────────────────────────────

$blocksByDate = [];

foreach ($weekDays as $day) {
    $blocksByDate[$day] = [];
}

foreach ($rawBlocks as $b) {

    if (isset($blocksByDate[$b['blocked_date']])) {
        $blocksByDate[$b['blocked_date']][] = $b;
    }
}


// ── Determine fully blocked days and blocked slots ──────────

$fullDayBlocked = [];
$slotBlocks = [];

foreach ($weekDays as $day) {

    $fullDayBlocked[$day] = false;
    $slotBlocks[$day] = [];

    foreach ($blocksByDate[$day] as $b) {

        if (
            $b['start_time'] === '09:00' &&
            $b['end_time'] === '18:00'
        ) {

            $fullDayBlocked[$day] = true;

        } else {

            $slotBlocks[$day][] = $b;
        }
    }
}

$fullDaysBlockedCount = count(
    array_filter($fullDayBlocked)
);


// ── Fetch booked appointments for this week ─────────────────

$bookedStmt = $connection->prepare("
    SELECT
        appointment_date,
        TIME_FORMAT(appointment_time, '%H:%i') AS slot_time,
        u.name AS patient_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
      AND a.appointment_date BETWEEN ? AND ?
      AND a.status = 'booked'
    ORDER BY a.appointment_date, a.appointment_time
");

$bookedStmt->execute([
    $doctorId,
    $weekDays[0],
    $weekDays[5]
]);

$rawBooked = $bookedStmt->fetchAll(PDO::FETCH_ASSOC);

$bookedByDate = [];
foreach ($weekDays as $day) {
    $bookedByDate[$day] = [];
}
foreach ($rawBooked as $b) {
    if (isset($bookedByDate[$b['appointment_date']])) {
        $bookedByDate[$b['appointment_date']][$b['slot_time']] = $b['patient_name'];
    }
}


// ── POST: availability actions ─────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $date   = $_POST['date'] ?? '';


    // Only allow dates belonging to the currently displayed week

    if (!in_array($date, $weekDays, true)) {

        header("Location: availability.php?week={$weekOffset}");
        exit;
    }


    // ── Block an entire day ─────────────────────────────────

    if ($action === 'block_day') {

    // Check whether this day already has appointments
    $appointmentCheck = $connection->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE doctor_id = ?
          AND appointment_date = ?
          AND status = 'booked'
    ");

    $appointmentCheck->execute([
        $doctorId,
        $date
    ]);

    $appointmentCount = (int)$appointmentCheck->fetchColumn();

    if ($appointmentCount > 0) {

        $message = urlencode(
            "You have an appointment scheduled on this day. Please request cancellation from the My Appointments page ."
        );

        header(
            "Location: availability.php?week={$weekOffset}&message={$message}"
        );

        exit;
    }

    // Maximum 2 full days per week
    if ($fullDaysBlockedCount < 2) {

        $ins = $connection->prepare("
            INSERT INTO doctor_blocked_time
            (doctor_id, blocked_date, start_time, end_time, reason)
            VALUES (?, ?, '09:00:00', '18:00:00', 'Day off')
        ");

        $ins->execute([
            $doctorId,
            $date
        ]);
    }


    // ── Unblock an entire day ───────────────────────────────

    } elseif ($action === 'unblock_day') {

        // Remove all blocks for this date

        $del = $connection->prepare("

            DELETE FROM doctor_blocked_time

            WHERE doctor_id = ?
              AND blocked_date = ?

        ");

        $del->execute([
            $doctorId,
            $date
        ]);


    // ── Block an individual slot ────────────────────────────

    } elseif ($action === 'block_slot') {

    $slotStart = $_POST['slot_start'] ?? '';
    $slotEnd   = $_POST['slot_end'] ?? '';

    // Check whether this slot already has an appointment
    $appointmentCheck = $connection->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE doctor_id = ?
          AND appointment_date = ?
          AND appointment_time = ?
          AND status = 'booked'
    ");

    $appointmentCheck->execute([
        $doctorId,
        $date,
        $slotStart
    ]);

    $appointmentCount = (int)$appointmentCheck->fetchColumn();

    if ($appointmentCount > 0) {

        $message = urlencode(
            "You already have an appointment at " .
            substr($slotStart, 0, 5) .
            ". Please request cancellation from the My Appointments page before blocking this time."
        );

        header(
            "Location: availability.php?week={$weekOffset}&message={$message}"
        );

        exit;
    }

    // Maximum 2 blocked slots per day
    if (count($slotBlocks[$date] ?? []) < 2) {

        $ins = $connection->prepare("
            INSERT INTO doctor_blocked_time
            (doctor_id, blocked_date, start_time, end_time, reason)
            VALUES (?, ?, ?, ?, 'Slot blocked')
        ");

        $ins->execute([
            $doctorId,
            $date,
            $slotStart,
            $slotEnd
        ]);
    }

    // ── Unblock individual slot ─────────────────────────────

    } elseif ($action === 'unblock_slot') {

        $blockId = (int)($_POST['block_id'] ?? 0);

        $del = $connection->prepare("

            DELETE FROM doctor_blocked_time

            WHERE id = ?
              AND doctor_id = ?

        ");

        $del->execute([
            $blockId,
            $doctorId
        ]);
    }


    // ── Redirect to refresh ─────────────────────────────────

    header("Location: availability.php?week={$weekOffset}");
    exit;
}


require_once "../includes/header.php";?>



<?php if ($availabilityMessage): ?>

    <div class="alert-care mb-3" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($availabilityMessage); ?>
    </div>

<?php endif; ?>



<!-- Week navigation -->

<div class="page-card" style="max-width:100%;">

    <div class="d-flex align-items-center justify-content-between mb-3">

        <a
            href="availability.php?week=<?php echo $weekOffset - 1; ?>"
            class="btn btn-outline-secondary btn-sm"
            style="border-radius:8px;"
        >
            &larr; Prev
        </a>


        <h2 style="margin:0;">

            <?php echo $weekStart->format('M j'); ?>

            –

            <?php echo $weekEnd->format('M j, Y'); ?>

        </h2>


        <a
            href="availability.php?week=<?php echo $weekOffset + 1; ?>"
            class="btn btn-outline-secondary btn-sm"
            style="border-radius:8px;"
        >
            Next &rarr;
        </a>

    </div>


    <p class="sub">

        Block up to
        <strong>2 full days</strong>
        per week and
        <strong>2 slots</strong>
        per working day.

    </p>


    <div class="avail-week-grid">

        <?php foreach ($weekDays as $day): ?>

            <?php

            $dayObj = new DateTime($day);
            $dayName = $dayObj->format('l');
            $dayDate = $dayObj->format('M j');

            $isBlocked = $fullDayBlocked[$day];

            $daySlotBlocks = $slotBlocks[$day] ?? [];

            $blockedSlotTimes = [];

            foreach ($daySlotBlocks as $sb) {

                $blockedSlotTimes[] = $sb['start_time'];
            }

            ?>


            <div class="avail-day-card <?php echo $isBlocked ? 'day-blocked' : ''; ?>">


                <div class="avail-day-header">

                    <div class="avail-day-name">
                        <?php echo $dayName; ?>
                        <span><?php echo $dayDate; ?></span>
                    </div>


                    <form method="post" style="display:inline;">

                        <input
                            type="hidden"
                            name="date"
                            value="<?php echo $day; ?>"
                        >


                        <?php if ($isBlocked): ?>

                            <input
                                type="hidden"
                                name="action"
                                value="unblock_day"
                            >


                            <button
                                type="submit"
                                class="avail-day-toggle blocked"
                                title="Unblock this day"
                            >

                                <i class="bi bi-x-circle-fill"></i>

                                Blocked

                            </button>


                        <?php else: ?>

                            <input
                                type="hidden"
                                name="action"
                                value="block_day"
                            >


                            <button
                                type="submit"
                                class="avail-day-toggle available"
                                title="Block this day"

                                <?php
                                echo $fullDaysBlockedCount >= 2
                                    ? 'disabled'
                                    : '';
                                ?>
                            >

                                <i class="bi bi-check-circle"></i>

                                Working

                            </button>

                        <?php endif; ?>

                    </form>

                </div>


                <?php if (!$isBlocked): ?>

                  <div class="avail-day-body">
                    <div class="avail-slots">


                        <?php foreach ($allSlots as $slot): ?>

                            <?php

                            $isSlotBlocked =
                                in_array(
                                    $slot['start'],
                                    $blockedSlotTimes
                                );

                            $isBooked = isset($bookedByDate[$day][$slot['start']]);
                            $bookedPatient = $isBooked ? $bookedByDate[$day][$slot['start']] : '';

                            $blockRecord = null;


                            if ($isSlotBlocked) {

                                foreach ($daySlotBlocks as $sb) {

                                    if (
                                        $sb['start_time'] ===
                                        $slot['start']
                                    ) {

                                        $blockRecord = $sb;

                                        break;
                                    }
                                }
                            }

                            ?>


                                <?php if ($isBooked): ?>

                                    <div class="time-slot-btn slot-booked" title="Booked — <?php echo htmlspecialchars($bookedPatient); ?>">
                                        <?php echo $slot['start']; ?>
                                        <i class="bi bi-person-fill"></i>
                                    </div>

                                <?php elseif ($isSlotBlocked && $blockRecord): ?>

                                    <form method="post" style="display:inline;">
                                    <input type="hidden" name="date" value="<?php echo $day; ?>">
                                    <input type="hidden" name="action" value="unblock_slot">
                                    <input type="hidden" name="block_id" value="<?php echo $blockRecord['id']; ?>">

                                    <button
                                        type="submit"
                                        class="time-slot-btn slot-blocked"
                                        title="Unblock this slot"
                                    >
                                        <?php echo $slot['start']; ?>
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    </form>

                                <?php else: ?>

                                    <form method="post" style="display:inline;">
                                    <input type="hidden" name="date" value="<?php echo $day; ?>">
                                    <input type="hidden" name="action" value="block_slot">
                                    <input type="hidden" name="slot_start" value="<?php echo $slot['start']; ?>:00">
                                    <input type="hidden" name="slot_end" value="<?php echo $slot['end']; ?>:00">

                                    <button
                                        type="submit"
                                        class="time-slot-btn"
                                        <?php echo count($daySlotBlocks) >= 2 ? 'disabled' : ''; ?>
                                    >
                                        <?php echo $slot['start']; ?>
                                    </button>
                                    </form>

                                <?php endif; ?>


                        <?php endforeach; ?>

                    </div>


                    <?php if (count($daySlotBlocks) > 0): ?>

                        <div class="avail-slot-count">

                            <?php echo count($daySlotBlocks); ?>/2
                            slots blocked

                        </div>

                    <?php endif; ?>
                  </div>

                <?php else: ?>


                    <div class="avail-day-off">
                        <i class="bi bi-moon"></i>
                        Day off — no appointments
                    </div>


                <?php endif; ?>


            </div>


        <?php endforeach; ?>

    </div>

</div>


<?php require_once "../includes/footer.php"; ?>