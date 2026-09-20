(function(document, navigator) {
  "use strict";

  function setStatus(status, message) {
    if (!status) {
      return;
    }

    if (typeof status.textContent !== "undefined") {
      status.textContent = message;
    }
    else {
      status.innerText = message;
    }
  }

  function selectAndCopy(target, status, copiedLabel, fallbackLabel) {
    var copied = false;

    target.focus();
    target.select();

    try {
      copied = typeof document.execCommand === "function" && document.execCommand("copy");
    }
    catch (error) {
      copied = false;
    }

    setStatus(status, copied ? copiedLabel : fallbackLabel);
  }

  function initialize(button) {
    var targetId;
    var target;
    var status;
    var copiedLabel;
    var fallbackLabel;

    if (button.getAttribute("data-basicrum-copy-ready") === "true") {
      return;
    }

    targetId = button.getAttribute("data-basicrum-copy-target");
    target = targetId ? document.getElementById(targetId) : null;
    status = button.parentNode.querySelector(".basicrum-copy-status");
    copiedLabel = button.getAttribute("data-copied-label") || "Copied";
    fallbackLabel = button.getAttribute("data-copy-fallback-label") || "Select the snippet and copy it manually.";

    if (!target) {
      return;
    }

    function copySnippet() {
      function reportCopied() {
        setStatus(status, copiedLabel);
      }

      function copyWithSelection() {
        selectAndCopy(target, status, copiedLabel, fallbackLabel);
      }

      if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
        try {
          navigator.clipboard.writeText(target.value).then(reportCopied, copyWithSelection);
          return;
        }
        catch (error) {
          copyWithSelection();
          return;
        }
      }

      copyWithSelection();
    }

    button.setAttribute("data-basicrum-copy-ready", "true");
    if (button.addEventListener) {
      button.addEventListener("click", copySnippet, false);
    }
    else if (button.attachEvent) {
      button.attachEvent("onclick", copySnippet);
    }
  }

  var buttons = document.querySelectorAll(".basicrum-copy-consent-snippet");
  var index;

  for (index = 0; index < buttons.length; index++) {
    initialize(buttons[index]);
  }
})(document, window.navigator);
