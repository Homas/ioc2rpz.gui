<?php
#(c) Vadim Pavlov 2018-2020
#ioc2rpz.gui RpiDNS

require_once 'io2auth.php';
require_once 'io2fun.php';

?>
<div class="v-spacer"></div>
<div>
	<b-card header-class="bold" style="max-height:calc(100vh - 100px)">
		<div slot="header" class="py-0 d-flex">
			<span class="bold"><i class="fas fa-atom"></i>&nbsp;&nbsp;RpiDNS</span>
		</div> 
		<b-alert show class="d-none d-md-block">
			Provision and manage your DNS servers.
		</b-alert>
  </b-card>
</div>

<?php
?>