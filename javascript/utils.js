/* General javascript utils loaded on all pages */

/* A centered pop-up window for any cases that might need one */
function CenterPopUp( URL, name, width, height ) {
	var left = ( screen.width - width ) / 2;
	var top  = ( screen.height - height ) / 2;
	var settings;

	if( left < 0 ) left = 0;
	if( top < 0 ) top = 0;

	settings = 'width=' + width + ',';
	settings += 'height=' + height + ',';
	settings += 'top=' + top + ',';
	settings += 'left=' + left;

	window.open( URL, name, settings );
}

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
