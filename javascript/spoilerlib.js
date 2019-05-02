/* eslint-disable no-console */
/*eslint-disable no-unused-vars */
/*jslint browser:true*/
'use strict';

// makes sure all of the DOM elements exist first
function pageReady(triggerEvents) {
    if (document.readyState != "loading") return triggerEvents();
    document.addEventListener("DOMContentLoaded", triggerEvents);
}

// runs once it's given the "loaded" signal
pageReady(function () {
	var spoilerClass = document.getElementsByClassName("spoilerbox");				// details tag     // finding our parents
    Object.keys(spoilerClass).forEach(function (i) {
/* doing some research */
		var spoilerHead = spoilerClass[i].getElementsByTagName("strong")[0];		// details tag     // finding our toggler
		var spoilerChild = spoilerClass[i].getElementsByClassName("spoiler")[0];	// summary tag    // finding our inner child
/* doing some wiretapping */
		spoilerClass[i].setAttribute('spoiled', 'false');							// making some secrets
		spoilerHead.addEventListener("click", spoilToggle);							// listen for the call
/* doing some css art */
        spoilerClass[i].style.height = "calc(1em + 3px)";							// minimum size with text + parent padding
        spoilerClass[i].style.overflow = "hidden";									// lock text inside spoiler
		spoilerClass[i].style.transition = "height 0.5s ease"						// make the transition look nice
		spoilerHead.style.width = "100%";											// make toggle object full parent width
		spoilerChild.style.color = "#c0c0c0";										// standardize spoiler text color
		spoilerChild.style.padding = "0.5em 0.5em calc(0.5em - 3px) 0.5em";			// spoiler padding - parent padding
/*
		.spoilerbox {
			height = calc(1em + 3px);
			overflow = hidden;
			transition = height 0.5s ease;
		}
		.spoilerbox strong {
			width = 100%;
		}
		.spoilerbox .spoiler {
			color = #c0c0c0;
			padding = 0.5em;
			padding-bottom = calc(0.5em - 3px);
		}
*/
	});
});

// the keymaster
var spoilerToggle;
// the gatekeeper
function spoilToggle() {
	var spoiler = this.parentNode;								// finding our parent
	var spoilerOpen = spoiler.getAttribute('spoiled');			// we should be closed
	spoilerToggle = spoiler.getElementsByTagName("strong")[0];	// finding our toggler
/* we're closed */
	if (spoilerOpen === "false") {								// someone's poked us
		return expandSpoiler(spoiler);							// reveal the secrets!
	}
/* we're open */
	return collapseSpoiler(spoiler);							// hide the secrets!
}

// ruining the secret
function expandSpoiler(spoiler) {
	var spoilerHeight = spoiler.scrollHeight;						// get full inner content height
	spoiler.style.height = spoilerHeight + "px";					// transform to content height
	spoiler.addEventListener("transitionend", function check() {	// wait for animation to finish
		spoiler.removeEventListener("transitionend", check);		// clean up
	});
	spoiler.setAttribute('spoiled', 'true');						// it's a secret to nobody
}

// burying the secrets again
function collapseSpoiler(spoiler) {
/* frame one */
	var spoilerHeight = spoiler.scrollHeight;			// get full inner content height
	var spoilerTransition = spoiler.style.transition;	// save current transitions
	spoiler.style.transition = "";						// temporarily remove transitions to prevent process load
/* frame two */
	requestAnimationFrame(function () {
		spoiler.style.height = spoilerHeight + "px";	// converting auto to explicit value
		spoiler.style.transition = spoilerTransition;	// reassign transitions post explicit height
/* frame three */
		requestAnimationFrame(function () {
			spoiler.style.height = "calc(1em + 3px)";						// transform to minimum height
			spoiler.addEventListener("transitionend", function check() {	// wait for animation to finish
				spoiler.removeEventListener("transitionend", check);		// clean up
				spoilerToggle.innerText = "Spoiler:";						// change toggle object text
			});
		});
	});
	spoiler.setAttribute('spoiled', 'false');			// it's a secret to everybody
}
