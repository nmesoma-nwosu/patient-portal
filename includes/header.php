<?php

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';

$navLinks = [];
if ($role === 'patient') {
    $navLinks = [
        ['label' => 'Dashboard',          'icon' => 'bi-grid',                 'href' => 'dashboard.php'],
        ['label' => 'Book Appointment',   'icon' => 'bi-calendar-plus',        'href' => 'book_appointment.php'],
        ['label' => 'My Appointments',    'icon' => 'bi-calendar-check',       'href' => 'appointments.php'],
        ['label' => 'My Doctors',         'icon' => 'bi-people',               'href' => 'my-doctors.php'],
        ['label' => 'My Records',         'icon' => 'bi-file-earmark-medical', 'href' => 'records.php'],
        ['label' => 'My Profile',         'icon' => 'bi-person-circle',        'href' => 'profile.php'],
    ];
} elseif ($role === 'doctor') {
    $navLinks = [
        ['label' => 'Dashboard',           'icon' => 'bi-grid',                 'href' => 'dashboard.php'],
        ['label' => 'My Appointments',     'icon' => 'bi-calendar-check',       'href' => 'appointments.php'],
        ['label' => 'My Patients',         'icon' => 'bi-people',               'href' => 'patients.php'],
        ['label' => 'Manage Availability', 'icon' => 'bi-clock-history',        'href' => 'availability.php'],
        ['label' => 'My Profile',          'icon' => 'bi-person-circle',        'href' => 'profile.php'],
    ];
} elseif ($role === 'admin') {
    $navLinks = [
        ['label' => 'Dashboard',            'icon' => 'bi-grid',                 'href' => 'dashboard.php'],
        ['label' => 'Manage Doctors',       'icon' => 'bi-person-badge',         'href' => 'doctors.php'],
        ['label' => 'Manage Patients',      'icon' => 'bi-people',               'href' => 'patients.php'],
        ['label' => 'Cancellation Requests','icon' => 'bi-x-circle',             'href' => 'cancellations.php'],
        ['label' => 'All Appointments',     'icon' => 'bi-calendar-week',        'href' => 'appointments.php'],
        ['label' => 'All Records',          'icon' => 'bi-file-earmark-medical', 'href' => 'records.php'],
        ['label' => 'Audit Log',            'icon' => 'bi-journal-text',         'href' => 'audit-log.php'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareTime<?php echo isset($pageTitle) ? ' — ' . htmlspecialchars($pageTitle) : ''; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>
<div class="app-shell">

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="sidebar-wordmark">Care<span>Time</span></span>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($navLinks as $link): ?>
        <a href="<?php echo $link['href']; ?>" class="sidebar-link <?php echo $currentPage === $link['href'] ? 'active' : ''; ?>">
          <i class="bi <?php echo $link['icon']; ?>"></i>
          <span class="sidebar-label"><?php echo $link['label']; ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <a href="<?php echo BASE_URL; ?>includes/logout.php" class="sidebar-link sidebar-logout">
      <i class="bi bi-box-arrow-right"></i>
      <span class="sidebar-label">Log out</span>
    </a>
  </aside>

  <div class="main-area">
    <header class="topnav">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
      </button>
      <div class="topnav-title"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : ''; ?></div>
      <div class="topnav-user">
        <i class="bi bi-person-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? ucfirst($role)); ?></span>
      </div>
    </header>
    <main class="content">