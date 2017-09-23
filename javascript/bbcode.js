/* Javascript for bbcode controls. */

function insertCode( code, textarea ) {
  /* IE Support. (Damn you Microsoft) */
  if( document.selection ) {
    textarea.focus();
    range = document.selection.createRange();

    if(range.parentElement() != textarea) { 
      return false; 
    }

    range.text = code;
    range.select();
  }
  /* Firefox and others who also handle this correctly */
  else if( textarea.selectionStart || textarea.selectionStart == '0' ) {
    var start = textarea.selectionStart;
    var end   = textarea.selectionEnd;

    textarea.value = textarea.value.substr(0, start) + code + textarea.value.substr(end, textarea.value.length);

    textarea.focus();
    textarea.setSelectionRange( start + code.length, start + code.length );
  }
  /* Fallback case. You never know. */
  else {
    textarea += code;
  }
}

function getText(textarea) {
  if(document.selection) {
    sel = document.selection.createRange();
    return sel.text;
  } else if (window.getSelection) {
    return textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
  } else {
    return "";
  }
}

function bbCode(e, textarea) {
  var tag = e.name;
  var text = getText(textarea);

  if (text) {
    var code = "[" + tag + "]" + text + "[/" + tag + "]";
  } else {
    if (e.value.indexOf("*") != -1) {
      var code = "[/" + tag + "]";
      e.value = e.value.substring(0,(e.value.length-1));
    } else {
      var code = "[" + tag + "]";
      e.value += "*";
    }
  }

  insertCode(code, textarea);
}

function bbcURL(e, textarea) {
  var type = e.name;
  var text = getText(textarea);
  var isURL = (text.substring(0,7) == "http://");

  if ( type == 'img' ) {
    if (isURL) {
      var code = "[" + type + "]" + text + "[/" + type + "]";
    } else {
      var code = text + "[" + type + "]" + prompt("URL:","") + "[/" + type + "]";
    }
  } else if( type == 'youtube' ) {
    var code = "[" + type + "]" + prompt("Enter the YouTube video URL:","") + text + "[/" + type + "]";
  } else {
    var code = "[" + type + "=" + (isURL ? text : prompt("Enter an address:","")) + "]" + ((text && !isURL) ? text : prompt("Enter a description:","")) + "[/" + type + "]";
  }
  insertCode(code, textarea);
}

function bbcFont(list, textarea) {
  var attrib = list.name.substring(1,list.name.length);
  var value = list.options[list.selectedIndex].value;
  if (value && attrib) {
    insertCode("[" + attrib + "=" + value + "]" + getText(textarea) + "[/" + attrib + "]", textarea);
  }
  setTimeout(function() {
	  list.options[0].selected = true;
  	},10);
}

function insertSmiley(smiley, ta) {
  insertCode(getText(ta) + ' ' + smiley + ' ', ta);
  return false;
}

function bbcodeInit(textbox) {
   var textarea = document.getElementById(textbox);

   return textarea;
}