/* Javascript for bbcode controls. */

var textarea = 'none';

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
  var isURL = (text.substring(0,8) == "https://");

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

function InsertEmoji(emoji, ta) {
  insertCode(getText(ta) + ' ' + emoji.name + ' ', ta);
}

function bbcode_ready(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

function set_emoji_events() {
  emojis = document.getElementsByClassName("clickable_emoji");

  for( i=0; i<emojis.length; i++) {
    emojis[i].addEventListener("click", function() { InsertEmoji(this,textarea); });
  }
}

function set_select_events() {
  selectors = document.getElementsByClassName("bbcode_select");

  for( i=0; i<selectors.length; i++) {
    selectors[i].addEventListener("change", function() { bbcFont(this,textarea); });
  }
}

function set_button_events() {
  buttons = document.getElementsByClassName("bbcode_button");

  for( i=0; i<buttons.length; i++) {
    buttons[i].addEventListener("click", function() { bbCode(this,textarea); });
  }

  url_buttons = document.getElementsByClassName("bbcode_url_button");

  for( i=0; i<url_buttons.length; i++) {
    url_buttons[i].addEventListener("click", function() { bbcURL(this,textarea); });
  }
}

bbcode_ready(function () {
  textarea = document.getElementById("bbcode_textbox");

  set_button_events();
  set_select_events();
  set_emoji_events();
})