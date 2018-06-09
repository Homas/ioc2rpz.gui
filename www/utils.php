<?php
  require 'io2auth.php';
?>
<div class="v-spacer"></div>
<div>
    <b-card-group columns>
      <b-card header="Import configuration" title="Import ioc2rpz">
          <p class="card-text">Import ioc2rpz server configuration</p>
          <b-button v-b-tooltip.hover title="Import ioc2rpz configuration" variant="outline-secondary" size="sm" @click.stop="$emit('bv::show::modal', 'mImportConfig')"><i class="fa fa-upload"> Import</i></b-button>
      </b-card>
      <b-card header="ISC Bind configuration" title="Export ISC Bind">
          <p class="card-text">Export ISC Bind configuration</p>
          <b-button v-b-tooltip.hover title="Export ISC Bind configuration" variant="outline-secondary" size="sm" @click.stop="exportShowModal('bind')"><i class="fa fa-download"> Export ISC Bind</i></b-button>
      </b-card>
      <b-card header="PowerDNS configuration" title="Export PowerDNS">
          <p class="card-text">Export PowerDNS configuration</p>
          <b-button disabled v-b-tooltip.hover title="Export PowerDNS configuration" variant="outline-secondary" size="sm"><i class="fa fa-download"> Export PowerDNS</i></b-button>
      </b-card>
      <b-card header="Infoblox configuration" title="Export Infoblox">
          <p class="card-text">Export Infoblox CSV configuration file</p>
          <b-button disabled v-b-tooltip.hover title="Export Infoblox CSV import configuration file" variant="outline-secondary" size="sm"><i class="fa fa-download"> Export PowerDNS</i></b-button>
      </b-card>
      <b-card header="Backup ioc2rpz.gui" title="Backup ioc2rpz.gui">
          <p class="card-text">Backup ioc2rpz.gui database</p>
          <b-button disabled v-b-tooltip.hover title="Backup ioc2rpz.gui DB" variant="outline-secondary" size="sm"><i class="fa fa-download"> Backup ioc2rpz.gui DB</i></b-button>
      </b-card>
      <b-card header="Import ioc2rpz.gui backup" title="Import ioc2rpz.gui backup">
          <p class="card-text">Import ioc2rpz.gui database backup</p>
          <b-button disabled v-b-tooltip.hover title="Backup ioc2rpz.gui DB" variant="outline-secondary" size="sm"><i class="fa fa-download"> Import ioc2rpz.gui backup</i></b-button>
      </b-card>
    </b-card-group>
</div>
