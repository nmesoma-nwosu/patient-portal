document.addEventListener('DOMContentLoaded', function () {
  const sidebar  = document.getElementById('sidebar');
  const toggle   = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');

  function isMobile() {
    return window.innerWidth <= 768;
  }

  toggle.addEventListener('click', function () {
    if (isMobile()) {
      sidebar.classList.toggle('mobile-open');
      backdrop.classList.toggle('show');
    } else {
      sidebar.classList.toggle('collapsed');
    }
  });

  backdrop.addEventListener('click', function () {
    sidebar.classList.remove('mobile-open');
    backdrop.classList.remove('show');
  });
});