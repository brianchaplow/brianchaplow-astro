(function() {
  if (typeof umami === 'undefined') return;

  // Scroll depth
  const scrollDepths = [25, 50, 75, 100];
  const trackedDepths = new Set();
  window.addEventListener('scroll', function() {
    const h = document.documentElement;
    const percent = Math.round((h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100);
    scrollDepths.forEach(depth => {
      if (percent >= depth && !trackedDepths.has(depth)) {
        trackedDepths.add(depth);
        umami.track('scroll-depth', { depth: depth + '%' });
      }
    });
  }, { passive: true });

  // Time on page
  const timeMarks = [30, 60, 120, 300];
  const trackedTimes = new Set();
  const startTime = Date.now();
  setInterval(function() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    timeMarks.forEach(mark => {
      if (elapsed >= mark && !trackedTimes.has(mark)) {
        trackedTimes.add(mark);
        umami.track('time-on-page', { seconds: mark });
      }
    });
  }, 5000);

  // Resume downloads
  document.querySelectorAll('a[href*="resume"], a[href*="Resume"], a[href*=".pdf"]').forEach(link => {
    link.addEventListener('click', function() {
      umami.track('resume-download', { file: this.href.split('/').pop() });
    });
  });

  // Contact email
  document.querySelectorAll('a[href^="mailto:"]').forEach(link => {
    link.addEventListener('click', function() {
      umami.track('contact-email-click');
    });
  });

  // Social/outbound links
  document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (!link || !link.href) return;
    if (link.href.includes('linkedin.com')) umami.track('social-click', { platform: 'linkedin' });
    else if (link.href.includes('github.com')) umami.track('social-click', { platform: 'github' });
    else if (link.href.includes('bytesbourbonbbq.com')) umami.track('social-click', { platform: 'bbbq-crosslink' });
    else if (link.hostname !== window.location.hostname) {
      umami.track('outbound-link', { url: link.href, domain: link.hostname });
    }
  });

  // Project clicks
  document.querySelectorAll('a[href*="/projects/"]').forEach(el => {
    el.addEventListener('click', function() {
      umami.track('project-click', { project: this.href.split('/').filter(Boolean).pop() });
    });
  });

  // Nav clicks
  document.querySelectorAll('nav a').forEach(link => {
    link.addEventListener('click', function() {
      umami.track('nav-click', { item: this.textContent.trim() });
    });
  });

  // Mobile menu
  document.getElementById('navToggle')?.addEventListener('click', function() {
    umami.track('mobile-menu-toggle');
  });

  // Exit intent
  let exitTracked = false;
  document.addEventListener('mouseleave', function(e) {
    if (e.clientY < 10 && !exitTracked) {
      exitTracked = true;
      umami.track('exit-intent');
    }
  });
})();