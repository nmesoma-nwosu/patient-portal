<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole('patient');

require_once "../config/specialties.php";

$pageTitle = "Book Appointment";


$specialtyIcons = [

    "General Practice"        => "bi-clipboard2-pulse",
    "Cardiology"              => "bi-heart-pulse",
    "Dermatology"             => "bi-bandaid",
    "Pediatrics"              => "bi-emoji-smile",
    "Orthopedics"             => "bi-activity",
    "Neurology"               => "bi-cpu",
    "Psychiatry"              => "bi-chat-heart",
    "Dentistry"               => "bi-emoji-laughing",
    "ENT (Ear, Nose & Throat)" => "bi-soundwave",
    "Ophthalmology"           => "bi-eye",

];


// ── Helper: compute available slots ─────────────────────────

function getAvailableSlots($connection, $doctorId, $date)
{

    // Generate all possible 1-hour slots
    // from 09:00 to 18:00

    $slots = [];

    $current = new DateTime('09:00');
    $end = new DateTime('18:00');


    while (true) {

        $slotEnd = clone $current;

        $slotEnd->modify('+1 hour');


        if ($slotEnd > $end) {
            break;
        }


        $slots[] = $current->format('H:i');


        // 1 hour appointment + 15 minute break

        $current->modify('+1 hour 15 minutes');
    }


    // ── Remove booked slots ─────────────────────────────────

    $bookedStmt = $connection->prepare("

        SELECT TIME_FORMAT(appointment_time, '%H:%i')

        FROM appointments

        WHERE doctor_id = ?
          AND appointment_date = ?
          AND status = 'booked'

    ");

    $bookedStmt->execute([
        $doctorId,
        $date
    ]);


    $booked = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);


    $slots = array_values(
        array_diff($slots, $booked)
    );


    // ── Remove doctor's blocked times ───────────────────────

    $blockedStmt = $connection->prepare("

        SELECT
            start_time,
            end_time

        FROM doctor_blocked_time

        WHERE doctor_id = ?
          AND blocked_date = ?

    ");

    $blockedStmt->execute([
        $doctorId,
        $date
    ]);


    $blockedTimes =
        $blockedStmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($blockedTimes as $blocked) {

        $bStart = new DateTime($blocked['start_time']);

        $bEnd = new DateTime($blocked['end_time']);


        foreach ($slots as $key => $slot) {

            $sStart = new DateTime($slot);

            $sEnd = clone $sStart;

            $sEnd->modify('+1 hour');


            // Check whether the appointment overlaps
            // the doctor's blocked period

            if (
                $sStart < $bEnd &&
                $sEnd > $bStart
            ) {

                unset($slots[$key]);
            }
        }
    }


    return array_values($slots);
}


// ── POST: book an appointment ───────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $doctorId =
        (int)($_POST['doctor_id'] ?? 0);

    $date =
        $_POST['date'] ?? '';

    $time =
        $_POST['time'] ?? '';

    $specialty =
        $_POST['specialty'] ?? '';


    // ── Check that doctor exists ────────────────────────────

    $doctorCheck = $connection->prepare("

        SELECT id

        FROM doctors

        WHERE id = ?
          AND specialty = ?

    ");

    $doctorCheck->execute([
        $doctorId,
        $specialty
    ]);


    if (!$doctorCheck->fetch()) {

        $errorMsg =
            urlencode("Invalid doctor selection.");


        header(
            "Location: book_appointment.php?specialty=" .
            urlencode($specialty) .
            "&date=" .
            urlencode($date) .
            "&error=" .
            $errorMsg
        );

        exit;
    }


    // ── Check that selected slot is actually available ──────

    $validSlots =
        getAvailableSlots(
            $connection,
            $doctorId,
            $date
        );


    if (!in_array($time, $validSlots, true)) {

        $errorMsg = urlencode(
            "That time slot is no longer available. Please choose another."
        );


        header(
            "Location: book_appointment.php?specialty=" .
            urlencode($specialty) .
            "&date=" .
            urlencode($date) .
            "&error=" .
            $errorMsg
        );

        exit;
    }


    // ── Final double-booking check ──────────────────────────

    $check = $connection->prepare("

        SELECT COUNT(*)

        FROM appointments

        WHERE doctor_id = ?
          AND appointment_date = ?
          AND appointment_time = ?
          AND status = 'booked'

    ");

    $check->execute([
        $doctorId,
        $date,
        $time
    ]);


    if ($check->fetchColumn() > 0) {

        $errorMsg = urlencode(
            "That time slot was just taken. Please pick another."
        );


        header(
            "Location: book_appointment.php?specialty=" .
            urlencode($specialty) .
            "&date=" .
            urlencode($date) .
            "&error=" .
            $errorMsg
        );

        exit;
    }


    // ── Book the appointment ────────────────────────────────

    $insert = $connection->prepare("

        INSERT INTO appointments
        (
            patient_id,
            doctor_id,
            doctor_specialty,
            appointment_date,
            appointment_time,
            status
        )

        VALUES
        (?, ?, ?, ?, ?, 'booked')

    ");


    $insert->execute([

        $_SESSION['user_id'],

        $doctorId,

        $specialty,

        $date,

        $time

    ]);


    header("Location: dashboard.php?booked=1");

    exit;
}


// ── GET parameters ─────────────────────────────────────────

$selectedSpecialty =
    $_GET['specialty'] ?? '';

$selectedDate =
    $_GET['date'] ?? '';

$selectedDoctor =
    $_GET['doctor_id'] ?? '';

$errorMsg =
    $_GET['error'] ?? '';


// ── Find patient's last doctor in this specialty ────────────

$primaryDoctor = null;


if ($selectedSpecialty) {

    $stmt = $connection->prepare("

        SELECT
            d.id,
            u.name

        FROM appointments a

        JOIN doctors d
            ON a.doctor_id = d.id

        JOIN users u
            ON d.user_id = u.id

        WHERE a.patient_id = ?
          AND d.specialty = ?

        ORDER BY
            a.appointment_date DESC,
            a.appointment_time DESC

        LIMIT 1

    ");


    $stmt->execute([

        $_SESSION['user_id'],

        $selectedSpecialty

    ]);


    $primaryDoctor =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


// ── Build doctor availability ───────────────────────────────

$availableDoctors = [];

$specificDoc = null;

$specificDoctorSlots = [];


// ── Specific doctor flow ───────────────────────────────────

if (
    $selectedSpecialty &&
    $selectedDate &&
    $selectedDoctor
) {

    $docStmt = $connection->prepare("

        SELECT
            d.id,
            u.name

        FROM doctors d

        JOIN users u
            ON d.user_id = u.id

        WHERE d.id = ?
          AND d.specialty = ?

    ");


    $docStmt->execute([

        $selectedDoctor,

        $selectedSpecialty

    ]);


    $specificDoc =
        $docStmt->fetch(PDO::FETCH_ASSOC);


    if ($specificDoc) {

        $specificDoctorSlots =
            getAvailableSlots(
                $connection,
                $specificDoc['id'],
                $selectedDate
            );
    }


// ── Free booking flow ──────────────────────────────────────

} elseif (
    $selectedSpecialty &&
    $selectedDate
) {

    $docList = $connection->prepare("

        SELECT
            d.id,
            u.name

        FROM doctors d

        JOIN users u
            ON d.user_id = u.id

        WHERE d.specialty = ?

    ");


    $docList->execute([
        $selectedSpecialty
    ]);


    $doctors =
        $docList->fetchAll(PDO::FETCH_ASSOC);


    foreach ($doctors as $doctor) {

        $slots =
            getAvailableSlots(
                $connection,
                $doctor['id'],
                $selectedDate
            );


        if (!empty($slots)) {

            $availableDoctors[] = [

                'doctor_id' =>
                    $doctor['id'],

                'name' =>
                    $doctor['name'],

                'earliest_slot' =>
                    $slots[0],

                'slots' =>
                    $slots

            ];
        }
    }


    usort(
        $availableDoctors,
        function ($a, $b) {

            return strcmp(
                $a['earliest_slot'],
                $b['earliest_slot']
            );
        }
    );
}


require_once "../includes/header.php";

?>


<?php if ($errorMsg): ?>

    <div
        class="alert-care mb-3"
        role="alert"
    >

        <?php
        echo htmlspecialchars($errorMsg);
        ?>

    </div>

<?php endif; ?>


<?php if (empty($selectedSpecialty)): ?>


    <!-- STEP 1: choose specialty -->

    <div
        class="page-card"
        style="max-width:100%;"
    >

        <h2>
            What kind of care do you need?
        </h2>


        <p class="sub">

            Not sure?
            General Practice is a good place to start.

        </p>


        <div class="specialty-grid">

            <?php foreach ($specialties as $s): ?>

                <a
                    href="book_appointment.php?specialty=<?php echo urlencode($s); ?>"
                    class="specialty-card"
                >

                    <i
                        class="bi <?php echo $specialtyIcons[$s] ?? 'bi-hospital'; ?>"
                    ></i>


                    <span>
                        <?php echo htmlspecialchars($s); ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </div>

    </div>


<?php elseif ($selectedDoctor && empty($selectedDate)): ?>


    <!-- BOOK WITH LAST DOCTOR: pick date -->

    <div class="page-card">

        <div class="eyebrow">

            <?php
            echo htmlspecialchars($selectedSpecialty);
            ?>

        </div>


        <h2>

            Pick a date for Dr.
            <?php
            echo htmlspecialchars(
                $primaryDoctor['name'] ?? ''
            );
            ?>

        </h2>


        <p class="sub">

            <a
                href="book_appointment.php?specialty=<?php echo urlencode($selectedSpecialty); ?>"
                class="altlink"
            >

                &larr; Choose a different option

            </a>

        </p>


        <form
            method="get"
            class="option-row"
            style="align-items:center;"
        >

            <input
                type="hidden"
                name="specialty"
                value="<?php echo htmlspecialchars($selectedSpecialty); ?>"
            >


            <input
                type="hidden"
                name="doctor_id"
                value="<?php echo (int)$selectedDoctor; ?>"
            >


            <i class="bi bi-calendar3"></i>


            <div class="flex-grow-1">

                <strong>
                    Select a date
                </strong>


                <input
                    type="date"
                    name="date"
                    class="form-control form-control-sm mt-2"
                    required
                    style="max-width:220px;"
                >

            </div>


            <button
                type="submit"
                class="btn btn-care btn-sm"
            >

                Go

            </button>

        </form>

    </div>


<?php elseif ($selectedDoctor && $selectedDate): ?>


    <!-- BOOK WITH LAST DOCTOR: show time slots -->

    <div class="page-card">

        <div class="eyebrow">

            <?php
            echo htmlspecialchars($selectedSpecialty);
            ?>

            &middot;

            <?php
            echo htmlspecialchars($selectedDate);
            ?>

        </div>


        <h2>

            Available times for Dr.
            <?php
            echo htmlspecialchars(
                $specificDoc['name'] ?? ''
            );
            ?>

        </h2>


        <p class="sub">

            <a
                href="book_appointment.php?specialty=<?php echo urlencode($selectedSpecialty); ?>&doctor_id=<?php echo (int)$selectedDoctor; ?>"
                class="altlink"
            >

                &larr; Pick a different date

            </a>

        </p>


        <?php if (empty($specificDoctorSlots)): ?>

            <p class="sub">

                No available slots on this date.
                Try another date.

            </p>


        <?php else: ?>


            <div class="time-slots-grid">

                <?php foreach ($specificDoctorSlots as $slot): ?>


                    <form
                        method="post"
                        style="display:inline;"
                    >

                        <input
                            type="hidden"
                            name="doctor_id"
                            value="<?php echo (int)$selectedDoctor; ?>"
                        >


                        <input
                            type="hidden"
                            name="date"
                            value="<?php echo htmlspecialchars($selectedDate); ?>"
                        >


                        <input
                            type="hidden"
                            name="time"
                            value="<?php echo htmlspecialchars($slot); ?>"
                        >


                        <input
                            type="hidden"
                            name="specialty"
                            value="<?php echo htmlspecialchars($selectedSpecialty); ?>"
                        >


                        <button
                            type="submit"
                            class="time-slot-btn"
                        >

                            <?php
                            echo htmlspecialchars($slot);
                            ?>

                        </button>

                    </form>


                <?php endforeach; ?>

            </div>


        <?php endif; ?>

    </div>


<?php elseif (empty($selectedDate)): ?>


    <!-- STEP 2: pick how to book -->

    <div class="page-card">

        <div class="eyebrow">

            <?php
            echo htmlspecialchars($selectedSpecialty);
            ?>

        </div>


        <h2>
            How would you like to book?
        </h2>


        <p class="sub">

            <a
                href="book_appointment.php"
                class="altlink"
            >

                &larr; Choose a different specialty

            </a>

        </p>


        <div class="d-flex flex-column gap-2 mt-3">


            <?php if ($primaryDoctor): ?>


                <a
                    href="book_appointment.php?specialty=<?php echo urlencode($selectedSpecialty); ?>&doctor_id=<?php echo (int)$primaryDoctor['id']; ?>"
                    class="option-row"
                >

                    <i class="bi bi-person-check"></i>


                    <div>

                        <strong>

                            Book with
                            <?php
                            echo htmlspecialchars(
                                $primaryDoctor['name']
                            );
                            ?>

                        </strong>


                        <div
                            class="sub"
                            style="margin:0;"
                        >

                            Your usual doctor for this

                        </div>

                    </div>

                </a>


            <?php endif; ?>


            <form
                method="get"
                class="option-row"
                style="align-items:center;"
            >

                <input
                    type="hidden"
                    name="specialty"
                    value="<?php echo htmlspecialchars($selectedSpecialty); ?>"
                >


                <i class="bi bi-calendar3"></i>


                <div class="flex-grow-1">

                    <strong>
                        Pick a date
                    </strong>


                    <div
                        class="sub"
                        style="margin:0;"
                    >

                        To see a list of all doctors
                        and slots available

                    </div>


                    <input
                        type="date"
                        name="date"
                        class="form-control form-control-sm mt-2"
                        required
                        style="max-width:220px;"
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-care btn-sm"
                >

                    Go

                </button>

            </form>

        </div>

    </div>


<?php else: ?>


    <!-- STEP 3: available doctors -->

    <div
        class="page-card"
        style="max-width:100%;"
    >

        <div class="eyebrow">

            <?php
            echo htmlspecialchars($selectedSpecialty);
            ?>

            &middot;

            <?php
            echo htmlspecialchars($selectedDate);
            ?>

        </div>


        <h2>
            Available doctors
        </h2>


        <p class="sub">

            <a
                href="book_appointment.php?specialty=<?php echo urlencode($selectedSpecialty); ?>"
                class="altlink"
            >

                &larr; Pick a different date

            </a>

        </p>


        <?php if (empty($availableDoctors)): ?>


            <p class="sub">

                No doctors in
                <?php
                echo htmlspecialchars($selectedSpecialty);
                ?>
                have an open slot on this date.

            </p>


        <?php else: ?>


            <div
                class="d-flex flex-column gap-2 mt-2"
            >


                <?php foreach ($availableDoctors as $idx => $doc): ?>


                    <div class="doctor-accordion">


                        <div
                            class="option-row doctor-toggle"
                            data-target="slots-<?php echo $idx; ?>"
                            style="cursor:pointer; align-items:center;"
                        >


                            <i
                                class="bi bi-person-badge"
                            ></i>


                            <div class="flex-grow-1">


                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $doc['name']
                                    );
                                    ?>

                                </strong>


                                <div
                                    class="sub"
                                    style="margin:0;"
                                >

                                    <?php
                                    echo count($doc['slots']);
                                    ?>

                                    slot<?php
                                    echo count($doc['slots']) !== 1
                                        ? 's'
                                        : '';
                                    ?>

                                    available

                                    &middot;

                                    Earliest:

                                    <?php
                                    echo htmlspecialchars(
                                        $doc['earliest_slot']
                                    );
                                    ?>

                                </div>

                            </div>


                            <i
                                class="bi bi-chevron-down accordion-chevron"
                            ></i>

                        </div>


                        <div
                            class="doctor-slots"
                            id="slots-<?php echo $idx; ?>"
                            style="display:none;"
                        >


                            <div class="time-slots-grid">


                                <?php foreach ($doc['slots'] as $slot): ?>


                                    <form
                                        method="post"
                                        style="display:inline;"
                                    >


                                        <input
                                            type="hidden"
                                            name="doctor_id"
                                            value="<?php echo (int)$doc['doctor_id']; ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="date"
                                            value="<?php echo htmlspecialchars($selectedDate); ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="time"
                                            value="<?php echo htmlspecialchars($slot); ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="specialty"
                                            value="<?php echo htmlspecialchars($selectedSpecialty); ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="time-slot-btn"
                                        >

                                            <?php
                                            echo htmlspecialchars($slot);
                                            ?>

                                        </button>


                                    </form>


                                <?php endforeach; ?>


                            </div>

                        </div>

                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>

    </div>


<?php endif; ?>


<script>

// Accordion toggle for doctor cards

document
    .querySelectorAll('.doctor-toggle')
    .forEach(toggle => {

        toggle.addEventListener('click', () => {

            const targetId =
                toggle.getAttribute('data-target');

            const target =
                document.getElementById(targetId);

            const chevron =
                toggle.querySelector(
                    '.accordion-chevron'
                );


            // Close all other open panels

            document
                .querySelectorAll('.doctor-slots')
                .forEach(panel => {

                    if (panel.id !== targetId) {

                        panel.style.display = 'none';


                        const otherToggle =
                            panel
                                .closest('.doctor-accordion')
                                .querySelector(
                                    '.accordion-chevron'
                                );


                        if (otherToggle) {

                            otherToggle.classList
                                .remove('open');
                        }
                    }
                });


            // Toggle this panel

            const isOpen =
                target.style.display !== 'none';


            target.style.display =
                isOpen ? 'none' : 'block';


            chevron.classList.toggle(
                'open',
                !isOpen
            );

        });

    });

</script>


<?php require_once "../includes/footer.php"; ?>