/**
 * Header Interactivity
 * Gère le menu burger et les dropdowns utilisateur
 */

document.addEventListener('DOMContentLoaded', function() {
  // Éléments du DOM
  const navbarToggle = document.getElementById('navbarToggle');
  const navLinks = document.getElementById('navLinks');
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');

  // Menu burger (mobile)
  if (navbarToggle) {
    navbarToggle.addEventListener('click', function() {
      navbarToggle.classList.toggle('active');
      navLinks.classList.toggle('active');
    });

    // Fermer le menu quand on clique sur un lien
    const navItems = navLinks.querySelectorAll('.nav-link:not(.user-button)');
    navItems.forEach(item => {
      item.addEventListener('click', function() {
        navbarToggle.classList.remove('active');
        navLinks.classList.remove('active');
      });
    });
  }

  // Menu utilisateur (dropdown)
  if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      userDropdown.classList.toggle('active');
      userMenuBtn.querySelector('.dropdown-icon').style.transform = 
        userDropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
    });

    // Fermer le dropdown au clic dehors
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.user-menu')) {
        userDropdown.classList.remove('active');
        userMenuBtn.querySelector('.dropdown-icon').style.transform = 'rotate(0)';
      }
    });

    // Fermer le dropdown au clic sur un lien
    const dropdownLinks = userDropdown.querySelectorAll('.dropdown-link');
    dropdownLinks.forEach(link => {
      link.addEventListener('click', function() {
        userDropdown.classList.remove('active');
        userMenuBtn.querySelector('.dropdown-icon').style.transform = 'rotate(0)';
      });
    });
  }

  // Fermer le menu mobile au scroll
  let lastScrollTop = 0;
  window.addEventListener('scroll', function() {
    if (navbarToggle && navLinks.classList.contains('active')) {
      const st = window.pageYOffset || document.documentElement.scrollTop;
      if (Math.abs(st - lastScrollTop) > 50) {
        navbarToggle.classList.remove('active');
        navLinks.classList.remove('active');
      }
      lastScrollTop = st <= 0 ? 0 : st;
    }
  });
});
