// Script to open the login box for guests when clicked.

var logintogglestate = false;

function OpenLoginBox() {
  if( !logintogglestate ) {
    document.getElementById("logintogglebox").style.display = 'inline';
    logintogglestate = true;
  } else {
    document.getElementById("logintogglebox").style.display = 'none';
    logintogglestate = false;
  }
}

function login_ready(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

login_ready(function () {
  document.getElementById("loginlink").addEventListener("click", OpenLoginBox);
})