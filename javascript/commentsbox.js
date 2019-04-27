// Script to open the comments box when clicked.

var commentboxtogglestate = false;

function OpenCommentBox() {
  if( !commentboxtogglestate ) {
    document.getElementById("comment_form").style.display = 'inline';
    commentboxtogglestate = true;
  } else {
    document.getElementById("comment_form").style.display = 'none';
    commentboxtogglestate = false;
  }
}

function comments_ready(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

comments_ready(function () {
  document.getElementById("comment_form").style.display = 'none';
  document.getElementById("newcomment").addEventListener("click", OpenCommentBox);
})