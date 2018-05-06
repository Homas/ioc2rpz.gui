<?php
  require 'io2auth.php';
?>
<div class="v-spacer"></div>
<div>
    <b-card-group deck>
      <b-card header="Import configuration" title="Import ioc2rpz">
          <p class="card-text">ioc2rpz server configuration import</p>
          <b-button v-b-tooltip.hover title="Import ioc2rpz configuration" variant="outline-secondary" size="sm" @click.stop="$emit('bv::show::modal', 'mImportConfig')"><i class="fa fa-upload"> Import</i></b-button>
      </b-card>
      <b-card header="ISC Bind configuration" title="Export ISC Bind">
          <p class="card-text">Export ISC Bind configuration</p>
          <b-button v-b-tooltip.hover title="Export ISC Bind configuration" variant="outline-secondary" size="sm"><i class="fa fa-download"> Export ISC Bind</i></b-button>
      </b-card>
      <b-card header="PowerDNS configuration" title="Export PowerDNS">
          <p class="card-text">Export PowerDNS configuration</p>
          <b-button v-b-tooltip.hover title="Export PowerDNS configuration" variant="outline-secondary" size="sm"><i class="fa fa-download"> Export PowerDNS</i></b-button>
      </b-card>
    </b-card-group>
</div>
