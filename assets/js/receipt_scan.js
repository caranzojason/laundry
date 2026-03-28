/**
 * OCR receipt scan: fills receipt number, customer name, contact after photo is chosen.
 * Requires Tesseract.js (v4) loaded before this script.
 */
(function () {
  'use strict';

  function resizeImageIfNeeded(file, maxWidth) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.width;
        var h = img.height;
        if (w <= maxWidth) {
          resolve(file);
          return;
        }
        h = Math.round((h * maxWidth) / w);
        w = maxWidth;
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);
        canvas.toBlob(
          function (blob) {
            if (blob) {
              resolve(blob);
            } else {
              reject(new Error('Could not resize image'));
            }
          },
          'image/jpeg',
          0.9
        );
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error('Invalid image'));
      };
      img.src = url;
    });
  }

  function detectTemplate(text, manual) {
    if (manual === 'main') {
      return 'main';
    }
    if (manual === 'backup') {
      return 'backup';
    }
    var u = text.toUpperCase();
    var scoreBackup = 0;
    var scoreMain = 0;
    if (u.indexOf('SERVICE RECEIPT') !== -1) {
      scoreBackup += 2;
    }
    if (/CONTACT\s*NO/i.test(text)) {
      scoreBackup += 1;
    }
    if (/NAME\s*:/i.test(text)) {
      scoreBackup += 1;
    }
    if (u.indexOf('SERVICE INVOICE') !== -1) {
      scoreMain += 2;
    }
    if (/RECEIVED\s+FROM/i.test(text)) {
      scoreMain += 1;
    }
    if (scoreBackup >= 2 && scoreBackup >= scoreMain) {
      return 'backup';
    }
    if (scoreMain >= 1) {
      return 'main';
    }
    if (scoreBackup >= 1) {
      return 'backup';
    }
    return 'main';
  }

  function parseBackup(text) {
    var receipt = '';
    var name = '';
    var contact = '';

    var noMatch = text.match(/No\.?\s*:?\s*(\d{3,8})\b/i);
    if (noMatch) {
      receipt = noMatch[1];
    }

    var nameMatch = text.match(/Name\s*:\s*([^\n\r]+)/i);
    if (nameMatch) {
      name = nameMatch[1].trim().replace(/\s+/g, ' ');
    }

    var contactMatch = text.match(/Contact\s*No\.?\s*:\s*([0-9\s\-]+)/i);
    if (contactMatch) {
      contact = contactMatch[1].replace(/\s+/g, ' ').trim();
    }

    return { receipt: receipt, name: name, contact: contact };
  }

  function parseMain(text) {
    var receipt = '';
    var name = '';
    var contact = '';

    var noRe = /No\.?\s*:?\s*(\d{3,8})\b/gi;
    var m;
    while ((m = noRe.exec(text)) !== null) {
      var n = m[1];
      if (n.length >= 3 && n.length <= 7) {
        receipt = n;
        break;
      }
    }

    var recv = text.match(/RECEIVED\s+from\s*[:\s]*([^\n\r]+)/i);
    if (recv) {
      var line = recv[1].trim();
      line = line.split(/\s{2,}|With\s|TIN/i)[0].trim();
      name = line.replace(/\s+/g, ' ');
    }

    var tin = text.match(/With\s*TIN\s*[:\s]*([0-9\s]+)/i);
    if (tin) {
      contact = tin[1].replace(/\s+/g, ' ').trim();
    } else {
      var digitRun = text.match(/(\d(?:[\s]\d){8,})/);
      if (digitRun) {
        contact = digitRun[1].replace(/\s+/g, ' ').trim();
      }
    }

    return { receipt: receipt, name: name, contact: contact };
  }

  function runScan() {
    var photoInput = document.getElementById('receipt_photo');
    var templateSelect = document.getElementById('receipt_template');
    var statusEl = document.getElementById('receipt_scan_status');
    var receiptNo = document.getElementById('receipt_no');
    var customerName = document.getElementById('customer_name');
    var customerContact = document.getElementById('customer_contact');
    var form = document.getElementById('order_form');
    var submitBtn = form ? form.querySelector('button[type="submit"]') : null;

    if (!photoInput || !statusEl || typeof Tesseract === 'undefined') {
      return;
    }

    var hideStatusTimer = null;
    var STATUS_HIDE_MS = 4000;

    function clearHideStatusTimer() {
      if (hideStatusTimer) {
        clearTimeout(hideStatusTimer);
        hideStatusTimer = null;
      }
    }

    function scheduleHideStatus() {
      clearHideStatusTimer();
      hideStatusTimer = setTimeout(function () {
        hideStatusTimer = null;
        statusEl.classList.add('d-none');
        statusEl.classList.remove('alert-success', 'alert-warning', 'alert-info', 'alert-secondary');
        statusEl.textContent = '';
        statusEl.innerHTML = '';
      }, STATUS_HIDE_MS);
    }

    function performScan(file) {
      if (!file || !file.type || file.type.indexOf('image/') !== 0) {
        return;
      }

      clearHideStatusTimer();

      statusEl.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger', 'alert-secondary');
      statusEl.classList.add('alert', 'alert-info', 'py-2', 'small', 'mb-0');
      statusEl.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Scanning receipt…';
      if (submitBtn) {
        submitBtn.disabled = true;
      }

      var manual = templateSelect ? templateSelect.value : 'auto';

      resizeImageIfNeeded(file, 1600)
        .then(function (blob) {
          return Tesseract.recognize(blob, 'eng', {
            logger: function (info) {
              if (info.status === 'recognizing text' && typeof info.progress === 'number') {
                statusEl.textContent =
                  'Reading text… ' + Math.round(info.progress * 100) + '%';
              }
            },
          });
        })
        .then(function (result) {
          var text = (result && result.data && result.data.text) ? result.data.text : '';
          var template = detectTemplate(text, manual);
          var parsed = template === 'backup' ? parseBackup(text) : parseMain(text);

          if (parsed.receipt && receiptNo) {
            receiptNo.value = parsed.receipt;
          }
          if (parsed.name && customerName) {
            customerName.value = parsed.name;
          }
          if (parsed.contact && customerContact) {
            customerContact.value = parsed.contact;
          }

          statusEl.classList.remove('alert-info');
          statusEl.classList.add('alert-success');
          statusEl.textContent =
            'Receipt scanned (' +
            template +
            ' layout). Please verify all fields before saving.';
          scheduleHideStatus();
        })
        .catch(function (err) {
          statusEl.classList.remove('alert-info');
          statusEl.classList.add('alert-warning');
          statusEl.textContent =
            'Could not read receipt automatically. Enter details manually. (' +
            (err && err.message ? err.message : 'error') +
            ')';
          scheduleHideStatus();
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
          }
        });
    }

    photoInput.addEventListener('change', function () {
      var file = this.files && this.files[0];
      performScan(file);
    });

    if (templateSelect) {
      templateSelect.addEventListener('change', function () {
        var file = photoInput.files && photoInput.files[0];
        if (file) {
          performScan(file);
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runScan);
  } else {
    runScan();
  }
})();
