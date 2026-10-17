(function () {
  return function (parameters, TagManager) {
      this.fire = function () {
          const experiment = parameters.get("experiment");
          const matomoOrigin = parameters.get("matomoOrigin");
          const parts = String(experiment || "").split(",");
          const _paq = (window._paq = window._paq || []);
          const ORIGINAL = "1";
          const VARIANT = "2";
          const KEY_PREFIX = "sabt:";

          if (!experiment) return;

          if (parts.length >= 6) {
              // Legacy format: "id,name,from,to,css,js," — the full
              // snapshot baked in at selection time by an older version of
              // this tag. Kept working as-is (no live fetch) so tags
              // created before this change keep firing; re-select the
              // experiment in this tag (or recreate it) to upgrade it to
              // the live-fetch format below.
              const expId = parts[0];
              const expName = parts[1];
              const start = parts[2] + "T00:00:00Z";
              const stop = parts[3] + "T23:59:00Z";
              const css = decodeURIComponent(parts[4].replace(/\+/g, "%20"));
              const js = decodeURIComponent(parts[5].replace(/\+/g, "%20"));
              initExp(_paq, start, stop, parts[3], js, css, expId, expName);
              return;
          }

          // Current format: "id,idSite" — a stable reference. The actual
          // name/dates/css/js are fetched fresh on every page load, so
          // editing the experiment takes effect immediately without
          // republishing this tag.
          const expId = parts[0];
          const expSiteId = parts[1];
          if (!expId || !expSiteId || !matomoOrigin) return;

          fetchExperiment(matomoOrigin, expId, expSiteId, function (data) {
              if (!data || !data.found) return;
              const start = data.from_date + "T00:00:00Z";
              const stop = data.to_date + "T23:59:00Z";
              initExp(_paq, start, stop, data.to_date, data.js_insert || "", data.css_insert || "", expId, data.name);
          });

          function fetchExperiment(origin, id, idSite, callback) {
              var url = origin + "/index.php?module=SimpleABTesting&action=getExperimentPublic&format=json"
                  + "&idSite=" + encodeURIComponent(idSite) + "&id=" + encodeURIComponent(id);
              try {
                  if (window.fetch) {
                      fetch(url, { method: "GET", credentials: "omit" })
                          .then(function (res) { return res.json(); })
                          .then(callback)
                          .catch(function () { /* silent — no experiment shown this load */ });
                  } else {
                      var xhr = new XMLHttpRequest();
                      xhr.open("GET", url, true);
                      xhr.onload = function () {
                          if (xhr.status === 200) {
                              try { callback(JSON.parse(xhr.responseText)); } catch (e) { /* ignore */ }
                          }
                      };
                      xhr.send();
                  }
              } catch (e) {
                  // ignore — no experiment shown this load
              }
          }

          function randomHex32() {
              var bytes = crypto.getRandomValues(new Uint8Array(16));
              var out = "";
              for (var i = 0; i < bytes.length; i++) {
                  out += (bytes[i] < 16 ? "0" : "") + bytes[i].toString(16);
              }
              return out;
          }

          // Remove the assignments of every other experiment. One test runs
          // per site, so another sabt:* entry is stale (e.g. a concluded test
          // whose stored exp still lies in the future) and must not keep
          // riding along on this visitor's requests via tracker.js.
          // Best effort: failures here never block the experiment.
          function removeOtherAssignments(exceptKey) {
              try {
                  var others = [];
                  for (var i = 0; i < localStorage.length; i++) {
                      var k = localStorage.key(i);
                      if (k && k.indexOf(KEY_PREFIX) === 0 && k !== exceptKey) others.push(k);
                  }
                  for (var j = 0; j < others.length; j++) localStorage.removeItem(others[j]);
              } catch (e) { /* ignore */ }
          }

          // Returns {v, a} for this experiment, creating and persisting a
          // new assignment when needed. Returns null when localStorage is
          // unavailable, so the visitor is neither assigned nor counted.
          function getAssignment(expId, toDate) {
              var key = KEY_PREFIX + expId;
              try {
                  var rec = null;
                  var raw = localStorage.getItem(key); // throws when blocked
                  try {
                      rec = JSON.parse(raw);
                  } catch (e) { rec = null; }
                  if (!(rec && (rec.v === ORIGINAL || rec.v === VARIANT) && /^[0-9a-f]{32}$/.test(String(rec.a)))) {
                      rec = { v: Math.random() < 0.5 ? ORIGINAL : VARIANT, a: randomHex32() };
                  }
                  localStorage.setItem(key, JSON.stringify({ v: rec.v, a: rec.a, exp: toDate }));
                  removeOtherAssignments(key);
                  return { v: rec.v, a: rec.a };
              } catch (e) {
                  return null;
              }
          }

          function initExp(_paq, testStartDate, testEndDate, toDate, scriptText, cssText, expId, expName) {
              const currentDate = new Date();
              const startDate = new Date(testStartDate);
              const endDate = new Date(testEndDate);

              if (currentDate >= startDate && currentDate <= endDate) {
                  const assignment = getAssignment(expId, toDate);
                  // No usable localStorage: show the original, send nothing.
                  if (!assignment) return;

                  // Send tracking parameters to Matomo
                  _paq.push(['appendToTrackingUrl', 'sabt=' + assignment.v + '&sabi=' + expId + '&sabn=' + encodeURIComponent(expName) + '&saba=' + assignment.a]);

                  if (assignment.v === VARIANT) {
                      try {
                          insertCSS(cssText);
                          insertJS(scriptText);
                      } catch (e) {
                          console.error("Error in script execution", e);
                      }
                  }

                  // Exposure ping, for both variants, after the parameters are set.
                  _paq.push(['ping']);
              }
          }

          /**
           * Function to insert CSS into the document head
           */
          function insertCSS(cssText) {
              const style = document.createElement("style");
              style.type = "text/css";
              style.textContent = cssText;
              document.head.appendChild(style);
          }

          /**
           * Function to insert JS script into the document head
           */
          function insertJS(scriptText) {
              const script = document.createElement("script");
              script.type = "text/javascript";
              script.text = scriptText;
              document.head.appendChild(script);
          }
      };
  };
})();
