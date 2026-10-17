(function () {
  var KEY_PREFIX = 'sabt:';

  // Reads the visitor's active A/B assignment from localStorage (written by
  // the SimpleABTesting tag). Returns null when there is none, or when
  // localStorage is unavailable.
  function getActiveAssignment() {
      try {
          var today = new Date().toISOString().slice(0, 10);
          for (var i = 0; i < window.localStorage.length; i++) {
              var key = window.localStorage.key(i);
              if (!key || key.indexOf(KEY_PREFIX) !== 0) {
                  continue;
              }
              var rec;
              try {
                  rec = JSON.parse(window.localStorage.getItem(key));
              } catch (e) {
                  continue;
              }
              if (rec && (rec.v === '1' || rec.v === '2')
                  && /^[0-9a-f]{32}$/.test(String(rec.a))
                  && typeof rec.exp === 'string' && rec.exp >= today) {
                  return { v: rec.v, id: key.substring(KEY_PREFIX.length), a: rec.a };
              }
          }
      } catch (e) {
          // localStorage blocked
      }
      return null;
  }

  function trackingParams() {
      var assignment = getActiveAssignment();
      if (!assignment) {
          return '';
      }
      return '&sabt=' + assignment.v + '&sabi=' + encodeURIComponent(assignment.id) + '&saba=' + assignment.a;
  }

  // The same parameters on every request type the Matomo tracker sends
  // through a plugin hook, so a conversion (goal, event, link, ecommerce)
  // made without a new pageview still counts for the assignment.
  var HOOKS = ['log', 'link', 'goal', 'event', 'sitesearch', 'ecommerce', 'ping',
      'contentImpression', 'contentInteraction'];

  function init() {
      var plugin = {};
      for (var i = 0; i < HOOKS.length; i++) {
          plugin[HOOKS[i]] = trackingParams;
      }
      Matomo.addPlugin('SimpleABTesting', plugin);
  }

  // If Matomo object exists, initialize the plugin
  if ('object' === typeof window.Matomo) {
      init();
  } else {
      // Tracker might not be loaded yet
      if ('object' !== typeof window.matomoPluginAsyncInit) {
          window.matomoPluginAsyncInit = [];
      }
      window.matomoPluginAsyncInit.push(init);
  }
})();
