/**
 * T-53: one shared date formatter for the admin pages.
 *
 * Admin tables receive MySQL date/datetime strings. Formatting them in the
 * browser with this helper keeps every page consistent and avoids the
 * timezone shift that happens when a date-only string is parsed as UTC.
 *
 * Exposed as window.PDRFormat.
 */
(function (global) {
  "use strict";

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function toDate(value) {
    if (value instanceof Date) {
      return isNaN(value.getTime()) ? null : value;
    }
    var raw = String(value == null ? "" : value).trim();
    if (!raw || raw === "0000-00-00" || raw === "0000-00-00 00:00:00") return null;

    var dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);
    if (dateOnly) {
      return new Date(+dateOnly[1], +dateOnly[2] - 1, +dateOnly[3]);
    }

    var stamp = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(raw);
    if (stamp) {
      return new Date(
        +stamp[1],
        +stamp[2] - 1,
        +stamp[3],
        +stamp[4],
        +stamp[5]
      );
    }

    var parsed = new Date(raw);
    return isNaN(parsed.getTime()) ? null : parsed;
  }

  function format(value, options) {
    var date = toDate(value);
    if (!date) return "";
    return date.toLocaleDateString(
      "en-US",
      Object.assign({ month: "short", day: "numeric", year: "numeric" }, options || {})
    );
  }

  function formatLong(value) {
    return format(value, { month: "long" });
  }

  function formatDateTime(value) {
    var date = toDate(value);
    if (!date) return "";
    return date.toLocaleString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit"
    });
  }

  function toISODate(value) {
    var date = toDate(value) || new Date();
    return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
  }

  global.PDRFormat = {
    toDate: toDate,
    toISODate: toISODate,
    format: format,
    formatLong: formatLong,
    formatShort: function (value) { return format(value, { month: "short" }); },
    formatDateTime: formatDateTime
  };
})(window);
