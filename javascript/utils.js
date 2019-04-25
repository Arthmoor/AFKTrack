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
