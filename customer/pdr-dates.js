/**
 * T-53: one shared date formatter for the customer pages.
 *
 * Every customer page previously formatted dates in its own way. This helper
 * keeps the display consistent and prevents the classic off-by-one-day bug
 * caused by parsing a date-only string as UTC.
 *
 * Exposed as window.PDRDates.
 */
(function (global) {
  "use strict";

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  // Date-only values are treated as LOCAL dates; timestamps are parsed as-is.
  function toDate(value) {
    if (value instanceof Date) {
      return isNaN(value.getTime()) ? null : value;
    }
    if (typeof value === "number") {
      var numeric = new Date(value);
      return isNaN(numeric.getTime()) ? null : numeric;
    }

    var raw = String(value == null ? "" : value).trim();
    if (!raw) return null;

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

  function toISODate(value) {
    var date = toDate(value);
    if (!date) return "";
    return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
  }

  function format(value, options) {
    var date = toDate(value);
    if (!date) return "";
    return date.toLocaleDateString(
      "en-US",
      Object.assign({ month: "long", day: "numeric", year: "numeric" }, options || {})
    );
  }

  function formatShort(value) {
    return format(value, { month: "short" });
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

  function monthName(year, monthIndex, short) {
    return new Date(Number(year), Number(monthIndex), 1).toLocaleString("en-US", {
      month: short ? "short" : "long"
    });
  }

  function daysInMonth(year, monthIndex) {
    return new Date(Number(year), Number(monthIndex) + 1, 0).getDate();
  }

  function addDays(value, days) {
    var date = toDate(value) || new Date();
    var copy = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    copy.setDate(copy.getDate() + Number(days || 0));
    return copy;
  }

  global.PDRDates = {
    toDate: toDate,
    toISODate: toISODate,
    format: format,
    formatLong: format,
    formatShort: formatShort,
    formatDateTime: formatDateTime,
    monthName: monthName,
    daysInMonth: daysInMonth,
    addDays: addDays
  };
})(window);
