<?php
/**
 * ioc2rpz.gui - Utilities Page
 * 
 * This file provides the utilities interface for:
 * - Importing ioc2rpz server configurations
 * - Exporting configurations to ISC Bind format
 * - Exporting configurations to PowerDNS format
 * - Exporting configurations to Infoblox CSV format
 * - Database backup and restore (planned features)
 * 
 * Requires authentication via io2auth.php.
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2018-2026
 * @license MIT
 */

require 'io2auth.php';
?>

<div>
    <b-card-group columns>
      <b-card header="Import configuration" title="Import ioc2rpz">
          <p class="card-text">Import ioc2rpz server configuration</p>
          <b-button v-b-tooltip.hover title="Import ioc2rpz configuration" variant="outline-secondary" size="sm" @click.stop="showModalById('mImportConfig')"><i class="fa fa-download"> Import</i></b-button>
      </b-card>
      <b-card header="ISC Bind configuration" title="Export ISC Bind">
          <p class="card-text">Export ISC Bind configuration</p>
          <b-button v-b-tooltip.hover title="Export ISC Bind configuration" variant="outline-secondary" size="sm" @click.stop="exportShowModal('bind')"><i class="fa fa-upload"> Export ISC Bind</i></b-button>
      </b-card>
      <b-card header="PowerDNS configuration" title="Export PowerDNS">
          <p class="card-text">Export PowerDNS configuration</p>
          <b-button v-b-tooltip.hover title="Export PowerDNS configuration" variant="outline-secondary" size="sm" @click.stop="exportShowModal('PowerDNS')"><i class="fa fa-upload"> Export PowerDNS</i></b-button>
      </b-card>
      <b-card header="Infoblox configuration" title="Export Infoblox">
          <p class="card-text">Export Infoblox CSV configuration file</p>
          <b-button v-b-tooltip.hover title="Export Infoblox CSV import format" variant="outline-secondary" size="sm" @click.stop="exportShowModal('Infoblox')"><i class="fa fa-upload"> Export Infoblox</i></b-button>
      </b-card>
      <b-card header="Backup ioc2rpz.gui" title="Backup ioc2rpz.gui">
          <p class="card-text">Backup ioc2rpz.gui database</p>
          <b-button disabled v-b-tooltip.hover title="Backup ioc2rpz.gui DB" variant="outline-secondary" size="sm"><i class="fa fa-download"> Backup ioc2rpz.gui DB</i></b-button>
      </b-card>
      <b-card header="Import ioc2rpz.gui backup" title="Import ioc2rpz.gui backup">
          <p class="card-text">Import ioc2rpz.gui database backup</p>
          <b-button disabled v-b-tooltip.hover title="Backup ioc2rpz.gui DB" variant="outline-secondary" size="sm"><i class="fa fa-upload"> Import ioc2rpz.gui backup</i></b-button>
      </b-card>
    </b-card-group>
</div>
