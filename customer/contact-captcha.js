      (function () {
        const state = { enabled: false, siteKey: "", widgetId: null, token: "" };

        state.reset = function () {
          state.token = "";
          if (window.turnstile && state.widgetId !== null) {
            try { window.turnstile.reset(state.widgetId); } catch (e) { /* ignore */ }
          }
        };

        function mount() {
          const host = document.getElementById("contactTurnstile");
          if (!host || !window.turnstile || state.widgetId !== null) return;
          state.widgetId = window.turnstile.render(host, {
            sitekey: state.siteKey,
            action: "contact",
            callback: function (token) { state.token = token; },
            "expired-callback": function () { state.token = ""; },
            "error-callback": function () { state.token = ""; }
          });
        }

        function loadScript() {
          if (document.getElementById("pdrTurnstileScript")) return;
          const s = document.createElement("script");
          s.id = "pdrTurnstileScript";
          s.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
          s.async = true;
          s.defer = true;
          s.onload = mount;
          document.head.appendChild(s);
        }

        // The submit handler reads this object for the token and enabled flag.
        window.pdrContactCaptcha = state;

        fetch("../api/public_config.php", { cache: "no-store" })
          .then(function (r) { return r.json(); })
          .then(function (cfg) {
            if (cfg && cfg.ok && cfg.captchaEnabled && cfg.turnstileSiteKey) {
              state.enabled = true;
              state.siteKey = cfg.turnstileSiteKey;
              loadScript();
            }
          })
          .catch(function () { /* fail-open: never block the contact form */ });
      })();
