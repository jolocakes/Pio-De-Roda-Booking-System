/* T-61: receipt actions are bound here instead of inline onclick attributes,
   so customer/receipt.php can run under a CSP without 'unsafe-inline'. */
(function () {
  var printBtn = document.getElementById("receiptPrint");
  var closeBtn = document.getElementById("receiptClose");
  if (printBtn) {
    printBtn.addEventListener("click", function () {
      window.print();
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener("click", function () {
      window.close();
    });
  }
})();
