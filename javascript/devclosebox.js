// Script to open the developer's resolution box when clicked.

var devtogglestate = false;

function OpenDevCloseBox() {
  if( !devtogglestate ) {
    document.getElementById("devclosebox").style.display = 'inline';
    devtogglestate = true;
  } else {
    document.getElementById("devclosebox").style.display = 'none';
    devtogglestate = false;
  }
}

function devclosebox_ready(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

devclosebox_ready(function () {
  document.getElementById("devclosebox").style.display = 'none';
  document.getElementById("devcloselink").addEventListener("click", OpenDevCloseBox);
})