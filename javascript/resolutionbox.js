// Script to open the developer's resolution box when clicked.

var restogglestate = false;

function OpenResolutionBox() {
  if( !restogglestate ) {
    document.getElementById("resolution_box").style.display = 'inline';
    restogglestate = true;
  } else {
    document.getElementById("resolution_box").style.display = 'none';
    restogglestate = false;
  }
}

function resclosebox_ready(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

resclosebox_ready(function () {
  document.getElementById("resolution_box").style.display = 'none';
  document.getElementById("issue_closed").addEventListener("click", OpenResolutionBox);
})