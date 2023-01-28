	 <form action='<?php echo $afktrack->self; ?>' method='post'>
	  <div class='article'>
	   <div class='title'>Choose Installation Type:</div>

           <span class='field'><input id='install' type='radio' name='mode' value='new_install' checked='checked'><label for='install'>New Installation</label></span>
           <span class='form'>            
            This can be used to install a fresh copy of AFKTrack, either brand new or while wiping out a previous installation.
           </span>
           <p class='line'></p>

           <span class='field'><input id='upgrade' type='radio' name='mode' value='upgrade'><label for='upgrade'>Upgrade Existing Site</label></span>
           <span class='form'>
            Used to upgrade an existing installation to the latest version of AFKTrack.
           </span>
           <p class='line'></p>

           <div style='text-align:center'><input type='submit' name='submit' value='Continue'></div>
	  </div>
         </form>