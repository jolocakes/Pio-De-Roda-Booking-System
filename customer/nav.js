      (function () {
        const toggle = document.getElementById("navToggle"),
          links = document.getElementById("navLinks");
        if (toggle && links) {
          toggle.addEventListener("click", () => {
            const open = links.classList.toggle("open");
            toggle.classList.toggle("open", open);
            toggle.setAttribute("aria-expanded", open);
          });
          // Homepage menu links jump to sections — close the drawer after a choice.
          links.addEventListener("click", (e) => {
            if (e.target.closest("a")) {
              links.classList.remove("open");
              toggle.classList.remove("open");
              toggle.setAttribute("aria-expanded", false);
            }
          });
        }
        const header = document.getElementById("site-header");
        if (header) {
          const onScroll = () =>
            header.classList.toggle("scrolled", window.scrollY > 10);
          window.addEventListener("scroll", onScroll);
          onScroll();
        }

        // Scroll-spy: highlight the nav link of whichever section is in view,
        // so the active item follows the user as they scroll the page.
        const navAnchors = Array.from(
          document.querySelectorAll(".nav-link[data-nav]"),
        );
        const spySections = navAnchors
          .map((a) => document.querySelector(a.getAttribute("href")))
          .filter(Boolean)
          .sort((a, b) => a.offsetTop - b.offsetTop);

        const runSpy = () => {
          // Probe line: just past the fixed header, a third into the viewport.
          const line =
            window.scrollY +
            (header ? header.offsetHeight : 0) +
            window.innerHeight * 0.3;
          let current = spySections[0];
          for (const s of spySections) {
            if (s.offsetTop <= line) current = s;
          }
          navAnchors.forEach((a) => {
            const on = a.getAttribute("href") === "#" + current.id;
            a.classList.toggle("active-link", on);
            if (on) a.setAttribute("aria-current", "location");
            else a.removeAttribute("aria-current");
          });
        };

        window.addEventListener("scroll", runSpy, { passive: true });
        window.addEventListener("resize", runSpy);
        window.addEventListener("hashchange", runSpy);
        runSpy();
      })();

      // Contact form: send message via PHP backend (no redirect to email client)
      function handleContactFormSubmit(event) {
        event.preventDefault();
        
        // Gather form data
        const name = document.getElementById("cf-name").value.trim();
        const email = document.getElementById("cf-email").value.trim();
        const message = document.getElementById("cf-message").value.trim();
        
        // Validate
        if (!name || !email || !message) {
          alert("Please fill in all required fields.");
          return;
        }
        
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
          alert("Please enter a valid email address.");
          return;
        }
        
        // T-45: when Turnstile is configured, a submission must carry a token.
        const captcha = window.pdrContactCaptcha;
        if (captcha && captcha.enabled && !captcha.token) {
          showMessage("Please complete the human verification before sending.", "error");
          return;
        }

        // Show loading state on the button
        const button = event.submitter;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = "Sending...";
        
        // Send data to PHP processor via fetch (AJAX - no redirect)
        // Go up from customer/ to pdrsystem/, then into api/
        fetch("../api/process_contact.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            name: name,
            email: email,
            message: message,
            turnstileToken: (window.pdrContactCaptcha && window.pdrContactCaptcha.token) || "",
            company: document.getElementById("cf-company").value
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.ok) {
            // Show success message (includes Reply-To info from Option C)
            showMessage(data.message, "success");
            document.getElementById("contactForm").reset();
            // Turnstile tokens are single-use; refresh the widget for the next send.
            if (window.pdrContactCaptcha) window.pdrContactCaptcha.reset();
          } else {
            showMessage("❌ Failed to send: " + (data.error || "Unknown error"), "error");
          }
        })
        .catch(error => {
          console.error("Fetch error:", error);
          // Show specific error if it's a JSON response from PHP
          if (error instanceof TypeError) {
            showMessage("❌ Could not connect to the server. Is Apache running?", "error");
          } else {
            showMessage("❌ Error sending message. Please try again.", "error");
          }
        })
        .finally(() => {
          // Restore button
          button.disabled = false;
          button.textContent = originalText;
        });
      }
      
      // Helper: show success/error message on page
      function showMessage(text, type) {
        // Remove any existing message
        const existing = document.querySelector('.contact-message-status');
        if (existing) existing.remove();
        
        const statusDiv = document.createElement('div');
        statusDiv.className = `contact-message-status ${type}`;
        statusDiv.textContent = text;
        
        // Insert inside the form, after the submit button
        // This ensures the message stays visually part of the form
        // and won't appear confusingly outside or under the form.
        const form = document.getElementById("contactForm");
        if (form) {
          // Insert after the button (button is the last child, so this appends to form)
          form.insertBefore(statusDiv, form.lastElementChild.nextSibling);
        } else {
          // Fallback: insert at the end of contact-grid
          const contactGrid = document.querySelector('#contact .contact-grid');
          contactGrid.appendChild(statusDiv);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
          if (statusDiv.parentNode) {
            statusDiv.parentNode.removeChild(statusDiv);
          }
        }, 5000);
      }
      
      // Initialize mailto link on the email list item (still works for direct clicks)
      document.addEventListener("DOMContentLoaded", () => {
        const emailLink = document.querySelector(
          '.contact-list a[href^="mailto"]'
        );
        if (emailLink) {
          emailLink.addEventListener("click", function () {
            // Ensure the mailto link opens the email client
            // (already handled by the href, but we add tracking if needed)
          });
        }
      });

/* T-61: bind the contact form here instead of onsubmit="..." so the page can
   run under a CSP that does not allow inline handlers. */
(function () {
  const form = document.getElementById("contactForm");
  if (form) form.addEventListener("submit", handleContactFormSubmit);
})();