(function () {
  'use strict';

  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initAos() {
    if (prefersReducedMotion) {
      document.querySelectorAll('[data-aos]').forEach(function (el) {
        el.removeAttribute('data-aos');
        el.style.opacity = '1';
        el.style.transform = 'none';
      });
      return;
    }

    if (typeof window.AOS === 'undefined') {
      return;
    }

    window.AOS.init({
      duration: 700,
      easing: 'ease-out-cubic',
      once: true,
      offset: 48,
    });
  }

  function initParallax() {
    if (prefersReducedMotion) {
      return;
    }

    var layers = document.querySelectorAll('[data-parallax]');
    if (!layers.length) {
      return;
    }

    var ticking = false;

    function update() {
      var scrollY = window.scrollY || window.pageYOffset;
      layers.forEach(function (layer) {
        var speed = parseFloat(layer.getAttribute('data-parallax')) || 0.15;
        layer.style.transform = 'translate3d(0, ' + (scrollY * speed) + 'px, 0)';
      });
      ticking = false;
    }

    window.addEventListener('scroll', function () {
      if (!ticking) {
        window.requestAnimationFrame(update);
        ticking = true;
      }
    }, { passive: true });

    update();
  }

  function initHeader() {
    var header = document.querySelector('.landing-header');
    if (!header) {
      return;
    }

    window.addEventListener('scroll', function () {
      header.classList.toggle('landing-header--scrolled', window.scrollY > 12);
    }, { passive: true });
  }

  function boot() {
    initParallax();
    initHeader();
    initAos();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
