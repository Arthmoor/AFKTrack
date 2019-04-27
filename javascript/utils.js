/* General javascript utils loaded on all pages */

function select_all_boxes()
{
  formElements = document.getElementsByTagName('input');
  for(i=0; i<formElements.length; i++)
  {
    if (formElements[i].type == 'checkbox') {
      formElements[i].checked = true;
    }
  }
}

// Script to support the jump selector for projects on the main page

function ProjectSelect(list) {
  self.location.href = list.value;
}

function jumpselect_ready(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

jumpselect_ready(function () {
  selector = document.getElementById("projectselect");
  selector.addEventListener("change", function() { ProjectSelect(this); } );
})