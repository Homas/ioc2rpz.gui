/**
 * ioc2rpz.gui - Main Application JavaScript
 * 
 * This file contains:
 * - Helper functions for validation and utilities
 * - Vue-instance dependent functions
 * - Vue app configuration (appConfig) for use with main.js
 * 
 * ES Module format for Vite bundling
 */

// Import event bus functions for Vue 3 compatibility
// Replaces Vue 2's $root.$emit pattern
import { showModal, refreshTable } from '../src/eventBus.js'

// ============================================
// Helper Functions (standalone, no Vue dependency)
// ============================================

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function downloadAsPlainText(fileName, Data) {
  var dataStr = "data:text/plain;base64," + btoa(Data);
  var downloadAnchorNode = document.createElement('a');
  downloadAnchorNode.setAttribute("href", dataStr);
  downloadAnchorNode.setAttribute("download", fileName);
  document.body.appendChild(downloadAnchorNode);
  downloadAnchorNode.click();
  downloadAnchorNode.remove();
}

function copyToClipboardID(id) {
  document.getElementById(id).select();
  document.execCommand('copy');
}

function checkHostIPNet(V) {
  return checkIPv4(V) || checkIPv4Net(V) || checkIPv6(V) || checkHostName(V);
}

function checkHostIP(V) {
  return checkIPv4(V) || checkIPv6(V) || checkHostName(V);
}

function checkIP(IP) {
  return checkIPv4(IP) || checkIPv6(IP);
}

function checkIPv4(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(IP);
}

function checkIPv4Net(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/([0-9]|[1-2][0-9]|3[0-2])$/.test(IP);
}

function checkIPv6(IP) {
  return /^(([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))(\/[0-9]+)?$/.test(IP);
}

function checkHostName(HN) {
  return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z]{2,63}$)/.test(HN);
}

function checkHostNameNum(HN) {
  return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z0-9]{2,63}$)/.test(HN);
}

function checkHostNameOnly(HN) {
  return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.?)+$)/.test(HN);
}

function checkSourceURL(HN) {
  return /^(http:\/\/|https:\/\/|ftp:\/\/|file:|shell:)/.test(HN);
}

// Export helper functions for use in main.js and templates
export {
  sleep,
  downloadAsPlainText,
  copyToClipboardID,
  checkHostIPNet,
  checkHostIP,
  checkIP,
  checkIPv4,
  checkIPv4Net,
  checkIPv6,
  checkHostName,
  checkHostNameNum,
  checkHostNameOnly,
  checkSourceURL
};

// ============================================
// Vue-instance dependent functions
// These functions require access to the Vue app instance (passed as parameter)
// ============================================

/**
 * Updates window size related properties on the Vue instance
 * @param {Object} obj - Vue instance or component with $refs
 */
export function update_window_size(obj) {
  // Fallback to global app instance if $refs is undefined
  if (obj.$refs === undefined) { obj = window.io2gui_app; }
  obj.logs_pp = window.innerHeight > 500 && window.innerWidth > 1000 ? Math.floor((window.innerHeight - 350) / 28) : 5;
  obj.logs_height = window.innerHeight > 400 ? (window.innerHeight - 240) : 150;
  obj.windowInnerWidth = window.innerWidth;
  splitRpiDNSList(obj);
}

/**
 * Toggles the publish updates state
 * @param {*} srv - Server parameter (unused but kept for API compatibility)
 * @param {Object} obj - Vue instance
 * @param {boolean} state - New state for publishUpdates
 */
export function toggleUpdates(srv, obj, state) {
  window.localStorage.publishUpdates = state;
  obj.publishUpdates = state;
}

/**
 * Splits the RpiDNS list into chunks for dashboard display
 * @param {Object} obj - Vue instance with RpiDNSList and $refs.RpiDNSCards
 */
export function splitRpiDNSList(obj) {
  obj.RpiDNSListDash = [];
  var i, j, chunk = parseInt((obj.$refs.RpiDNSCards.offsetWidth == 0 ? (window.innerWidth - 165) : obj.$refs.RpiDNSCards.offsetWidth - 50) / 315);
  chunk = chunk > 0 ? chunk : 1;
  for (i = 0, j = obj.RpiDNSList.length; i < j; i += chunk) {
    obj.RpiDNSListDash.push(obj.RpiDNSList.slice(i, i + chunk));
  }
}

/**
 * Imports IOC2RPZ configuration from text
 * @param {Object} vm - Vue instance
 * @param {string} txt - Configuration text to import
 */
export async function ImportIOC2RPZ(vm, txt) {
  var SrvId;
  let ev = null;
  let p1 = axios.get('/io2data.php/servers');
  let p2 = axios.get('/io2data.php/tkeys');
  let p3 = axios.get('/io2data.php/sources');
  let p4 = axios.get('/io2data.php/whitelists');
  let p5 = axios.get('/io2data.php/rpzs');
  var [servers, tkeys, sources, whitelists, rpzs] = await Promise.all([p1, p2, p3, p4, p5]);
  var TKeysAll = [], SrvAll = [], WLAll = [], SrcAll = [], RpzAll = [];
  var TKeys = [], Srv = [], WL = [], Src = [], Rpz = [];
  if (servers.data) servers.data.forEach(function(el) { SrvAll[el['name']] = el['rowid'] });
  if (tkeys.data) tkeys.data.forEach(function(el) { TKeysAll[el['name']] = el['rowid'] });
  if (sources.data) sources.data.forEach(function(el) { SrcAll[el['name']] = el['rowid'] });
  if (whitelists.data) whitelists.data.forEach(function(el) { WLAll[el['name']] = el['rowid'] });
  if (rpzs.data) rpzs.data.forEach(function(el) { RpzAll[el['name']] = el['rowid'] });

  for (let line of txt.split(/\r|\n/)) {
    var l = line.trim();
    var m;
    if (m = l.match(/^{srv,{"([^"]+)","([^"]+)",\[([^\]]*)\],\[([^\]]*)\]}}\.(\t* *| *\t*%.*)$/)) {
      Srv['ns'] = m[1]; Srv['email'] = m[2]; Srv['tkeys'] = [];
      m[3].split(/,|\s|"/g).filter(String).forEach(function(el) { Srv['tkeys'].push(el); });
      Srv['mgmt'] = m[4].replace(/"/g, '');
    }
    if (m = l.match(/^{rpz,{"([^"]+)",([0-9]+),([0-9]+),([0-9]+),([0-9]+),"([^"]+)","([^"]+)","?([^"]+|\[[^\]]*\])"?,\[([^\]]*)\],"([^"]+)",([0-9]+),([0-9]+),\[([^\]]*)\],\[([^\]]*)\],\[([^\]]*)\]}}\.(\t* *| *\t*%.*)$/)) {
      Rpz[m[1]] = [];
      Rpz[m[1]]['tkeys'] = [];
      if (m[9]) m[9].split(/,|\s|"/g).filter(String).forEach(function(el) { Rpz[m[1]]['tkeys'].push(el); });
      Rpz[m[1]]['sources'] = [];
      m[13].split(/,|\s|"/g).filter(String).forEach(function(el) { Rpz[m[1]]['sources'].push(el); });
      Rpz[m[1]]['notify'] = m[14].replace(/"/g, '');
      Rpz[m[1]]['whitelists'] = [];
      if (m[15]) m[15].split(/,|\s|"/g).filter(String).forEach(function(el) { Rpz[m[1]]['whitelists'].push(el); });
      Rpz[m[1]]['name'] = m[1]; Rpz[m[1]]['soa_refresh'] = m[2]; Rpz[m[1]]['soa_update'] = m[3];
      Rpz[m[1]]['soa_exp'] = m[4]; Rpz[m[1]]['soa_nxttl'] = m[5];
      Rpz[m[1]]['cache'] = m[6] == "true" ? 1 : 0; Rpz[m[1]]['wildcards'] = m[7] == "true" ? 1 : 0;
      Rpz[m[1]]['action'] = m[8]; Rpz[m[1]]['ioc_type'] = m[10];
      Rpz[m[1]]['AXFR_time'] = m[11]; Rpz[m[1]]['IXFR_time'] = m[12];
    }
    if (m = l.match(/^{key,{"([^"]+)","([^"]+)","([^"]+)"}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!TKeysAll[m[1]] || (!TKeysAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!TKeysAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftKeyId = (TKeysAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? TKeysAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftKeyName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (TKeysAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftKeyAlg = m[2]; vm.ftKey = m[3]; vm.ftKeyMGMT = Srv['tkeys'].includes(m[1]) ? 1 : 0;
        TKeys[vm.ftKeyName] = vm.ftKeyName; TKeys[m[1]] = vm.ftKeyName;
        await vm.tblMgmtTKeyRecord(ev, 'tkeys');
      } else {
        TKeys[m[1]] = (TKeysAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (TKeysAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)")}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!WLAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? WLAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcREGEX = m[4] !== undefined ? m[4] : m[3]; vm.ftSrcURLIXFR = "";
        vm.ftSrcMaxIOC = '0'; vm.ftSrcHotCacheAXFR = '900'; vm.ftSrcHotCacheIXFR = '0';
        WL[vm.ftSrcName] = vm.ftSrcName; WL[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'whitelists'; await vm.tblMgmtSrcRecord(ev, 'whitelists');
      } else {
        WL[m[1]] = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)")}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!SrcAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? SrcAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcURLIXFR = m[3]; vm.ftSrcREGEX = m[5] !== undefined ? m[5] : m[4];
        vm.ftSrcMaxIOC = '0'; vm.ftSrcHotCacheAXFR = '900'; vm.ftSrcHotCacheIXFR = '0';
        Src[vm.ftSrcName] = vm.ftSrcName; Src[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'sources'; await vm.tblMgmtSrcRecord(ev, 'sources');
      } else {
        Src[m[1]] = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)"),"([^"]*)",([0-9]+),([0-9]+),([0-9]+)}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!WLAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? WLAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcREGEX = m[4] !== undefined ? m[4] : m[3]; vm.ftSrcURLIXFR = "";
        vm.ftSrcMaxIOC = m[6]; vm.ftSrcHotCacheAXFR = m[7]; vm.ftSrcHotCacheIXFR = m[8];
        WL[vm.ftSrcName] = vm.ftSrcName; WL[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'whitelists'; await vm.tblMgmtSrcRecord(ev, 'whitelists');
      } else {
        WL[m[1]] = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)"),"([^"]*)",([0-9]+),([0-9]+),([0-9]+)}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!SrcAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? SrcAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcURLIXFR = m[3]; vm.ftSrcREGEX = m[5] !== undefined ? m[5] : m[4];
        vm.ftSrcMaxIOC = m[7]; vm.ftSrcHotCacheAXFR = m[8]; vm.ftSrcHotCacheIXFR = m[9];
        Src[vm.ftSrcName] = vm.ftSrcName; Src[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'sources'; await vm.tblMgmtSrcRecord(ev, 'sources');
      } else {
        Src[m[1]] = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
  }

  await sleep(1000);
  p1 = axios.get('/io2data.php/tkeys');
  p2 = axios.get('/io2data.php/sources');
  p3 = axios.get('/io2data.php/whitelists');
  [tkeys, sources, whitelists] = await Promise.all([p1, p2, p3]);
  TKeysAll = []; WLAll = []; SrcAll = [];
  if (tkeys.data) tkeys.data.forEach(function(el) { TKeysAll[el['name']] = el['rowid'] });
  if (sources.data) sources.data.forEach(function(el) { SrcAll[el['name']] = el['rowid'] });
  if (whitelists.data) whitelists.data.forEach(function(el) { WLAll[el['name']] = el['rowid'] });

  if (Srv.length > 0) {
    vm.ftSrvId = -1; vm.ftSrvName = vm.ftImpServName;
    vm.ftSrvNS = Srv['ns']; vm.ftSrvEmail = Srv['email'].replace('.', '@');
    vm.ftSrvMGMTIP = Srv['mgmt'];
    if (Srv['tkeys']) Srv['tkeys'].forEach(function(el) {
      if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftSrvTKeys.push(TKeysAll[TKeys[el]]);
    });
    vm.ftSrvSType = 0; vm.ftSrvURL = vm.ftImpFiles[0].name;
    vm.ftCertFile = Srv['certfile']; vm.ftKeyFile = Srv['keyfile'];
    vm.ftCACertFile = Srv['cacertfile']; vm.ftCustomConfig = Srv['custom_config'];
    vm.ftSrvPubIP = vm.ftImpServPubIP; vm.ftSrvIP = vm.ftImpServMGMTIP;
    await vm.tblMgmtSrvRecord(ev, 'servers');
    do {
      await sleep(1000);
      p1 = axios.get('/io2data.php/servers');
      [servers] = await Promise.all([p1]);
      if (servers.data) servers.data.forEach(function(el) { if (vm.ftSrvName == el['name']) SrvId = el['rowid'] });
    } while (SrvId == undefined)
  }

  if (Rpz.length > 0) {
    vm.ftRPZId = -1; vm.ftRPZSrvs = []; vm.ftRPZSrvs.push(SrvId);
    for (var RpzName in Rpz) {
      vm.ftRPZName = RpzName;
      vm.ftRPZSOA_Refresh = Rpz[RpzName]['soa_refresh'];
      vm.ftRPZSOA_UpdRetry = Rpz[RpzName]['soa_update'];
      vm.ftRPZSOA_Exp = Rpz[RpzName]['soa_exp'];
      vm.ftRPZSOA_NXTTL = Rpz[RpzName]['soa_nxttl'];
      vm.ftRPZCache = Rpz[RpzName]['cache'];
      vm.ftRPZWildcard = Rpz[RpzName]['wildcards'];
      if (["nxdomain", "nodata", "passthru", "drop", "tcp-only"].includes(Rpz[RpzName]['action'])) {
        vm.ftRPZAction = Rpz[RpzName]['action']; vm.ftRPZActionCustom = "";
      } else {
        vm.ftRPZAction = "local"; vm.ftRPZActionCustom = Rpz[RpzName]['action'];
      }
      vm.ftRPZIOCType = Rpz[RpzName]['ioc_type'];
      vm.ftRPZAXFR = Rpz[RpzName]['AXFR_time'];
      vm.ftRPZIXFR = Rpz[RpzName]['IXFR_time'];
      vm.ftRPZTKeys = [];
      if (Rpz[RpzName]['tkeys']) Rpz[RpzName]['tkeys'].forEach(function(el) {
        if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftRPZTKeys.push(TKeysAll[TKeys[el]]);
      });
      vm.ftRPZSrc = [];
      Rpz[RpzName]['sources'].forEach(function(el) {
        if (Src[el] && SrcAll[Src[el]]) vm.ftRPZSrc.push(SrcAll[Src[el]]);
      });
      vm.ftRPZNotify = Rpz[RpzName]['notify'];
      vm.ftRPZWL = [];
      if (Rpz[RpzName]['whitelists']) Rpz[RpzName]['whitelists'].forEach(function(el) {
        if (WL[el] && WLAll[WL[el]]) vm.ftRPZWL.push(WLAll[WL[el]]);
      });
      vm.tblMgmtRPZRecord(ev, 'rpzs');
    }
  }
}


// ============================================
// Vue App Configuration Object
// This is exported for use in main.js to create the Vue instance
// ============================================

/**
 * Vue app configuration object
 * Contains el, data, mounted, computed, and methods properties
 * Used by main.js to create the Vue instance: new Vue(appConfig)
 */
export const appConfig = {
  el: "#app",
  data: {
    toggleMenu: 0,
    cfgTab: 0,
    windowInnerWidth: 800,
    logs_height: 150,
    logs_pp: 5,

    // Tab index to table name mapping for Vue 3 compatibility
    // (replaces $children access which is removed in Vue 3)
    tabTableMap: {
      0: 'servers',
      1: null,  // RpiDNS - no table
      2: 'tkeys_groups',
      3: 'tkeys',
      4: 'whitelists',
      5: 'sources',
      6: 'rpzs',
      7: null,  // Utils - no table
      8: 'users'
    },

    servers_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'ip', label: 'MGMT IP/FQDN', sortable: true },
      { key: 'ns', label: 'Name Server' },
      { key: 'email', label: 'Admin Email' },
      { key: 'mgmt', label: 'Manage', 'class': 'text-center' },
      { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width250' }
    ],

    tkeys_groups_fields: [
      { key: 'group_name', label: 'Group name', sortable: true },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    tkeys_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'alg', label: 'Algorithm', sortable: true },
      { key: 'tkey', label: 'TSIG Key', formatter: (value) => { return value.length > 45 ? value.substring(0, 44) + ' ...' : value; } },
      { key: 'mgmt', label: 'Management', 'class': 'text-center' },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    whitelists_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'url', label: 'URL', sortable: true },
      { key: 'regex', label: 'RegEx', sortable: true },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    sources_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'url', label: 'URL', sortable: true, formatter: (value) => { return value.length > 35 ? value.substring(0, 34) + ' ...' : value; } },
      { key: 'url_ixfr', label: 'URL update', sortable: true, formatter: (value) => { return value.length > 35 ? value.substring(0, 34) + ' ...' : value; } },
      { key: 'regex', label: 'RegEx', sortable: true, formatter: (value) => { return value.length > 25 ? value.substring(0, 24) + ' ...' : value; } },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    rpzs_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'servers_list', label: 'Servers', sortable: true },
      { key: 'ioc_type', label: 'IOC type', sortable: true },
      { key: 'cache', label: 'Cachable', sortable: true, 'class': 'text-center' },
      { key: 'wildcard', label: 'Wildcards', sortable: true, 'class': 'text-center' },
      { key: 'action', label: 'Responce action', sortable: true, formatter: (value) => { return value == "nxdomain" ? "NXDomain" : value == "nodata" ? "NoData" : value == "passthru" ? "Passthru" : value == "drop" ? "Drop" : value == "tcp-only" ? "TCP-Only" : "Local Records"; } },
      { key: 'sources_list', label: 'Sources', sortable: true },
      { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    users_fields: [
      { key: 'name', label: 'Login', sortable: true },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    modalMSG: 'Modal',
    errorMSG: 'Error',
    deleteRec: 0,
    deleteTbl: '',

    // TSIG Keys
    ftKeyId: 0, ftKeyName: '', ftKey: '', ftKeyMGMT: 0, ftKeyAlg: "md5",
    tkeys_Alg: ["md5", "sha256", "sha512"],
    ftTKeysGroups: [], ftTKeysAllGroups: [],

    // Key Groups
    ftKeyGId: -1, ftKeyGName: '',

    // Sources/Whitelists
    ftSrcId: 0, ftSrcName: '', ftSrcURL: '', ftSrcURLIXFR: '', ftSrcREGEX: '',
    ftSrcType: "sources", ftSrcTitle: "Source",
    ftSrcMaxIOC: '0', ftSrcHotCacheAXFR: '900', ftSrcHotCacheIXFR: '0',
    ftSrcIoCType: 'mixed', ftSrcKeepInCache: 0,

    // Servers
    ftSrvId: 0, ftSrvName: '', ftSrvPubIP: '', ftSrvIP: '', ftSrvNS: '', ftSrvEmail: '',
    ftSrvMGMT: 0, ftSrvMGMTIP: '', ftSrvTKeys: [], ftSrvTKeysAll: [], ftSrvDisabled: 0,
    ftSrvSType: 0, ftSrvURL: "", ftCertFile: "", ftKeyFile: "", ftCACertFile: "", ftCustomConfig: "",
    servers_filter: "",

    // RPZs
    ftRPZId: 0, ftRPZName: '', ftRPZSrvs: [], ftRPZSrvsAll: [],
    ftRPZTKeys: [], ftRPZTKeysAll: [], ftRPZWL: [], ftRPZWLAll: [],
    ftRPZSrc: [], ftRPZSrcAll: [], ftRPZNotify: "",
    ftRPZSOA_Refresh: '', ftRPZSOA_UpdRetry: '', ftRPZSOA_Exp: '', ftRPZSOA_NXTTL: '',
    ftRPZCache: 0, ftRPZWildcard: 0,
    ftRPZAction: "nxdomain", ftRPZActionCustom: "",
    ftRPZIOCType: "mixed", ftRPZAXFR: '', ftRPZIXFR: '', ftRPZDisabled: 0,

    RPZ_Act_Options: [
      { value: 'nxdomain', text: 'NXDomain' },
      { value: 'nodata', text: 'NoData' },
      { value: 'passthru', text: 'Passthru' },
      { value: 'drop', text: 'Drop' },
      { value: 'tcp-only', text: 'TCP-only' },
      { value: 'local', text: 'Local records' }
    ],

    RPZ_IType_Options: [
      { value: 'mixed', text: 'mixed' },
      { value: 'fqdn', text: 'fqdn' },
      { value: 'ip', text: 'ip' }
    ],

    ftRPZProWindow: "", ftRPZProWindowInfo: "", RPZtabI: 0,
    ftRPZInfoServerName: '', ftRPZInfoServerIP: '',
    ftRPZInfoTKeyName: '', ftRPZInfoTKeyAlg: '', ftRPZInfoTKey: '', ftRPZInfoDig: '',

    infoWindow: true, publishUpdates: false, editRow: {},
    mInfoMSGvis: false, msgInfoMSG: '',

    // Import
    ftImpServName: '', ftImpServPubIP: '', ftImpServMGMTIP: '',
    ftImpFiles: [], ftImpFileDesc: '', ftImpPrefix: '', ftImpAction: 0,
    ftImportRec: '',

    // Users
    ftUId: 0, ftUName: '', ftUNameProf: '', ftUCPwd: '', ftUPwd: '', ftUpwdConf: '', ftUPerm: 0,
    UPerm_Options: [
      { value: 1, text: 'Super Admin' },
      { value: 100, text: 'RPZ Admin' },
      { value: 1000, text: 'Read Only', disabled: true }
    ],

    // Export
    ftExRPZ: [], ftExRPZAll: [], ftExFormat: '',
    rpzExportSAll: false, rpzExportIBView: 'default', rpzExportIBMember: 'infoblox.localdomain',

    // RpiDNS
    RpiDNSList: [], RpiDNSListDash: [],
    addRpiDNSName: "", addRpiDNSComment: "", addRpiDNSModel: null, addRpiDNSServer: null,
    addRpiDNSOptions: [
      { id: null, value: null, text: 'Select your hardware/VM', disabled: true },
      { id: 'pizero', value: { type: 'pizero', max: 500000 }, text: 'Raspbian on Pi Zero/Zero W' },
      { id: 'pi123', value: { type: 'pi123', max: 500000 }, text: 'Raspbian on Pi 1/2/3' },
      { id: 'pi4-1g', value: { type: 'pi4-1g', max: 2500000 }, text: 'Raspbian on Pi 4 with 1Gb/2Gb' },
      { id: 'pi4-4g', value: { type: 'pi4-4g', max: 5000000 }, text: 'Raspbian on Pi 4 with 4Gb' },
      { id: 'ubuntu18', value: { type: 'ubuntu18', max: 100000000 }, text: 'Ubuntu 18.x', disabled: true }
    ],
    addRpiDNSServerOptions: [
      { id: null, value: null, text: 'Select DNS server', disabled: true },
      { id: 'bind', value: { type: 'bind', max: 0 }, text: 'ISC Bind' },
      { id: 'powerdns', value: { type: 'powerdns', max: 500000 }, text: 'PowerDNS', disabled: true },
      { id: 'pidns', value: { type: 'pidns', max: 500000 }, text: 'piDNS', disabled: true }
    ],
    addRpiDNSFeedAction: [
      { id: 'passthrunolog', value: 'passthru log no', text: 'Passthru - No log', type: "allow" },
      { id: 'passthru', value: 'passthru', text: 'Passthru', type: "allow" },
      { id: 'cname', value: 'cname', text: 'Block - Redirect', type: "deny" },
      { id: 'nxdomain', value: 'nxdomain', text: 'Block - NXDomain', type: "deny" },
      { id: 'nodata', value: 'nodata', text: 'Block - NoData', type: "deny" },
      { id: 'drop', value: 'drop', text: 'Block - Drop', type: "deny" },
      { id: 'disabled', value: 'disabled', text: 'Log only', type: "any" }
    ],

    tRPZRpiDNS_fields: [
      { key: 'rowid', label: '', sortable: false, 'tdClass': 'width005' },
      { key: 'name', label: 'Name', sortable: false },
      { key: 'action', label: 'Action', sortable: false, 'tdClass': 'width200' }
    ],
    addRpiDNSRulesCount: 0, ftRpiDNSRPZ: [], ftRpiDNSRPZrecom: [], ftRpiDNSRPZAction: {},
    addRpiDNSRedirect: "", addRpiDNSRedirectURL: "",
    addRpiDNSType: "", addRpiDNSTypeIPNet: "",
    addRpiDNSLogs: "", addRpiDNSLogsURL: "",
    addRpiDNSCheckConf: true, RpiDNSLabel: "Add RpiDNS", RpiDNSBttn: "Add", addRpiDNSid: 0
  },

  mounted: function() {
    if (window.location.hash) {
      var a = window.location.hash.split(/#|\//).filter(String);
      switch (a[0]) {
        case "tabs_menu":
          this.cfgTab = parseInt(a[1]);
      }
    }
    this.ftUName = window.jsUser || '';
    this.ftUNameProf = window.jsUser || '';

    update_window_size(this);
    this.$nextTick(() => {
      window.addEventListener('resize', () => { update_window_size(this); });
    });

    if (window.localStorage.getItem('publishUpdates')) {
      this.publishUpdates = (window.localStorage.getItem('publishUpdates') == "true");
    }

    this.refreshRpiDNS();
  },

  computed: {
    // Empty computed section - can be extended as needed
  },

  methods: {
    refreshRpiDNS: function() {
      let obj = this;
      axios.get('/io2data.php/rpidns').then(function(response) {
        if (/DOCTYPE html/.test(response.data)) {
          window.location.reload(true);
        } else if (response.data.status == "success") {
          obj.$root.RpiDNSList = [];
          response.data.data.forEach(function(El) {
            El.dns_name = obj.addRpiDNSServerOptions.find(item => { return item.id === El.dns }).text;
            El.model_name = obj.addRpiDNSOptions.find(item => { return item.id === El.model }).text;
            obj.$root.RpiDNSList.push(El);
          });
          splitRpiDNSList(obj);
        } else {
          obj.showInfo(response.data.description, 3);
        }
      }).catch(function(error) {
        obj.showInfo('Unknown error!!!', 3);
      });
    },

    rpidns_add: function(id) {
      this.clear_rpidns_modal();
      this.RpiDNSLabel = "Add RpiDNS"; this.RpiDNSBttn = "Add"; this.addRpiDNSid = 0;
      showModal('mAddRpiDNS');
    },

    rpidns_edit: function(id) {
      this.clear_rpidns_modal();
      this.addRpiDNSid = id; this.RpiDNSLabel = "Edit RpiDNS"; this.RpiDNSBttn = "Save";
      this.addRpiDNSRulesCount = 0;
      var obj = this;
      let El = this.RpiDNSList.find(item => { return item.id === id });
      this.addRpiDNSName = El.name;
      this.addRpiDNSModel = this.addRpiDNSOptions.find(item => { return item.id === El.model }).value;
      this.addRpiDNSServer = this.addRpiDNSServerOptions.find(item => { return item.id === El.dns }).value;
      this.addRpiDNSCheckConf = El.updconf;
      this.addRpiDNSRedirect = El.redirect === undefined ? "default" : El.redirect;
      this.addRpiDNSRedirectURL = El.redirect_cname === undefined ? "" : El.redirect_cname;
      this.addRpiDNSLogs = El.logging === undefined ? "local" : El.logging;
      this.addRpiDNSLogsURL = El.logging_host === undefined ? "" : El.logging_host;
      this.addRpiDNSType = El.dns_type === undefined ? "primary" : El.dns_type;
      this.addRpiDNSTypeIPNet = El.dns_ipnet === undefined ? "" : El.dns_ipnet;
      El.rpz.forEach(function(item) { if (obj.$refs.io2tbl_rpzs.localItems.filter(e => e.name === item.feed).length > 0) { obj.ftRpiDNSRPZAction[item.feed] = item.action; obj.ftRpiDNSRPZ.push(item.feed); } });
      this.addRpiDNSComment = El.comment;
      showModal('mAddRpiDNS');
    },

    validateHostnameIP: function(vrbl) {
      return this.$data[vrbl].length == 0 ? null : checkHostIP(this.$data[vrbl]);
    },

    validateHostnameIPNet: function(vrbl) {
      return this.$data[vrbl].length == 0 ? null : checkHostIPNet(this.$data[vrbl]);
    },

    formatHostnameIPNet: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/\/\,]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatHostnameIP: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    clear_rpidns_modal: function() {
      this.addRpiDNSName = ""; this.addRpiDNSModel = null;
      this.addRpiDNSServer = { type: 'bind', max: 0 };
      this.addRpiDNSCheckConf = true; this.ftRpiDNSRPZ = []; this.ftRpiDNSRPZAction = {};
      var obj = this;
      if (obj.$refs.io2tbl_rpzs && obj.$refs.io2tbl_rpzs.localItems) {
        obj.$refs.io2tbl_rpzs.localItems.forEach(function(item) { obj.ftRpiDNSRPZAction[item.name] = ((item.type == "v" || item.type == "w") ? "passthru log no" : "cname"); });
      }
      this.addRpiDNSComment = ""; this.addRpiDNSRulesCount = 0;
      this.addRpiDNSRedirect = "default"; this.addRpiDNSRedirectURL = "";
      this.addRpiDNSLogs = "local"; this.addRpiDNSLogsURL = "";
      this.addRpiDNSType = "primary"; this.addRpiDNSTypeIPNet = "";
    },

    add_rpidns: function(event) {
      if (this.validateHostnameOnly('addRpiDNSName') && this.ftRpiDNSRPZ.length > 0 && this.addRpiDNSModel !== null && this.addRpiDNSServer !== null && ((this.addRpiDNSType == 'secondary' && checkIP(this.addRpiDNSTypeIPNet)) || this.addRpiDNSType == 'primary')) {
        let doc = this;
        var data, promise;
        let rpzfeeds = [];
        this.ftRpiDNSRPZ.forEach(function(item) { rpzfeeds.push({ "feed": item, "action": doc.ftRpiDNSRPZAction[item] }); });
        data = { id: this.addRpiDNSid, name: this.addRpiDNSName, comment: this.addRpiDNSComment, model: this.addRpiDNSModel.type, dns: this.addRpiDNSServer.type, updconf: this.addRpiDNSCheckConf, rpz: JSON.stringify(rpzfeeds), redirect: this.addRpiDNSRedirect, redirect_cname: this.addRpiDNSRedirectURL, logging: this.addRpiDNSLogs, logging_host: this.addRpiDNSLogsURL, dns_type: this.addRpiDNSType, dns_ipnet: this.addRpiDNSTypeIPNet };
        if (this.RpiDNSBttn == "Add") promise = axios.post('/io2data.php/rpidns', data); else promise = axios.put('/io2data.php/rpidns', data);
        promise.then((data) => {
          if (data.data[0].status == "success") { doc.clear_rpidns_modal(); doc.refreshRpiDNS(); }
          else { doc.showInfo(data.data[0].description, 3); }
        }).catch(error => { doc.showInfo('Unknown error!!!', 3); });
      } else {
        event.preventDefault();
        if (!this.validateHostnameOnly('addRpiDNSName') || this.addRpiDNSName.length == 0) this.showInfo('Please set correct RpiDNS name', 3);
        else if (this.addRpiDNSType == 'secondary' && !checkIP(this.addRpiDNSTypeIPNet)) this.showInfo('Please set a primary DNS server IP', 3);
        else if (this.addRpiDNSModel == null) this.showInfo('Please select RpiDNS model', 3);
        else if (this.addRpiDNSServer == null) this.showInfo('Please select DNS server software', 3);
        else if (this.ftRpiDNSRPZ.length == 0) this.showInfo('Please select RPZ feeds', 3);
        else this.showInfo('Please define all fields', 3);
      }
    },

    rpidns_delete: function(rpidns_id) {
      this.$bvModal.msgBoxConfirm('You are about to delete selected RpiDNS. This action is irreversible!', {
        title: 'Please confirm the action', size: 'md', buttonSize: 'md', okVariant: 'danger',
        okTitle: 'YES', cancelTitle: 'NO', footerClass: 'p-2', bodyClass: 'text-center',
        hideHeaderClose: false, centered: true
      }).then(value => {
        if (value) {
          let doc = this;
          var data = { id: rpidns_id };
          let promise = axios.delete('/io2data.php/rpidns', { data });
          promise.then((data) => {
            if (data.data[0].status == "success") { doc.refreshRpiDNS(); }
            else { doc.showInfo(data.data[0].description, 3); }
          }).catch(error => { doc.showInfo('Unknown error!!!', 3); });
        }
      });
    },

    addRpiDNSFeedActionComp: function(type) {
      return this.addRpiDNSFeedAction;
    },

    validateCustomAction: function(CustomActions) {
      let good = CustomActions == '' ? null : true;
      let gotcname = 0;
      CustomActions.split(/\r\n|\n|\r/).forEach(function(action) {
        let rule = action.trim().split("=", 2);
        switch (rule[0]) {
          case "local_aaaa": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIPv6(rule[1]); break;
          case "local_a": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIPv4(rule[1]); break;
          case "redirect_ip": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIP(rule[1]); break;
          case "local_cname": case "redirect_domain": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkHostName(rule[1]); gotcname++; break;
          case "local_txt": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && true; break;
          default: good = good && (action.startsWith("#") || action.startsWith("//") || action == "");
        }
      });
      return good && (gotcname <= 1);
    },

    /**
     * Items provider function for bootstrap-vue-next BTable
     * In bootstrap-vue-next, the provider function receives a context object
     * and should return items directly or a Promise that resolves to items
     * 
     * @param {Object} ctx - Provider context with currentPage, perPage, filter, sortBy, signal
     * @param {string} apiUrl - The API URL to fetch data from (passed via closure or table ref)
     * @returns {Promise<Array>} Promise resolving to array of items
     */
    async tableProvider(ctx, apiUrl) {
      try {
        const response = await axios.get(apiUrl, { signal: ctx.signal });
        if (/DOCTYPE html/.test(response.data)) {
          window.location.reload(true);
          return [];
        }
        const items = response.data;
        this.totalRows = items.length;
        return items;
      } catch (error) {
        if (error.name === 'CanceledError' || error.name === 'AbortError') {
          // Request was cancelled, this is expected behavior
          return [];
        }
        console.error('Table provider error:', error);
        return [];
      }
    },

    /**
     * Legacy get_tables function for backward compatibility
     * This wraps the new tableProvider for tables that still use the old pattern
     * @deprecated Use tableProvider with provider prop instead
     */
    get_tables(obj) {
      // For backward compatibility, extract apiUrl from the obj parameter
      // In bootstrap-vue-next, we should use the provider prop instead
      const apiUrl = obj.apiUrl || obj;
      return this.tableProvider({ signal: new AbortController().signal }, apiUrl);
    },

    /**
     * Creates a provider function for a specific API URL
     * Use this to create provider functions for each table
     * @param {string} apiUrl - The API URL for the table
     * @returns {Function} Provider function for BTable
     */
    createTableProvider(apiUrl) {
      return (ctx) => this.tableProvider(ctx, apiUrl);
    },

    onFiltered(filteredItems) {
      this.checkedItems = []; this.checkAll = false;
      this.totalRows = filteredItems.length; this.currentPage = 1;
    },

    refreshTbl(table) {
      refreshTable(table);
    },

    importRec: function(action, table, row, target) {
      this.$root.ftImportRec = '';
      showModal('mImportRec');
    },

    mgmtRec: function(action, table, row, target) {
      this.$root.infoWindow = action == 'info' ? true : false;
      switch (action + ' ' + table) {
        case "add users":
          this.$root.ftUId = 0; this.$root.ftUNameProf = ""; this.$root.ftUPerm = 1;
          this.$root.ftUPwd = ""; this.$root.ftUpwdConf = "";
          showModal('mUAdd'); break;
        case "edit users":
          this.$root.ftUId = row.item.rowid; this.$root.ftUNameProf = row.item.name;
          this.$root.ftUPerm = row.item.perm; this.$root.ftUPwd = ""; this.$root.ftUpwdConf = "";
          showModal('mUAdd'); break;
        case "add tkeys_groups":
          this.$root.ftKeyGId = -1; this.$root.ftKeyGName = "";
          showModal('mTGroups'); break;
        case "edit tkeys_groups":
          this.$root.ftKeyGId = row.item.rowid; this.$root.ftKeyGName = row.item.group_name;
          showModal('mTGroups'); break;
        case "add tkeys":
          this.$root.ftKeyId = -1; this.$root.genRandom('tkeyName'); this.$root.genRandom('tkey');
          this.$root.ftKeyAlg = 'md5'; this.$root.ftKeyMGMT = 0; this.$root.editRow = {};
          this.$root.get_lists('tkeys_groups_list', 'ftTKeysAllGroups'); this.$root.ftTKeysGroups = [];
          showModal('mConfEditTSIG'); break;
        case "info tkeys": case "edit tkeys": case "clone tkeys":
          this.$root.ftKeyId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftKeyName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftKey = row.item.tkey; this.$root.ftKeyAlg = row.item.alg;
          this.$root.ftKeyMGMT = row.item.mgmt; this.$root.editRow = row.item;
          this.$root.get_lists('tkeys_groups_list', 'ftTKeysAllGroups');
          var tkey_groups = [];
          row.item.tkey_groups.forEach(function(el) { tkey_groups.push(el.rowid); });
          this.$root.ftTKeysGroups = tkey_groups;
          showModal('mConfEditTSIG'); break;
        case "add whitelists": case "add sources":
          this.$root.ftSrcId = -1; this.$root.ftSrcName = ''; this.$root.ftSrcURL = '';
          this.$root.ftSrcREGEX = ''; this.$root.ftSrcType = table; this.$root.ftSrcURLIXFR = '';
          this.$root.ftSrcMaxIOC = '0'; this.$root.ftSrcHotCacheAXFR = '900'; this.$root.ftSrcHotCacheIXFR = '0';
          this.$root.ftSrcTitle = (table == "sources") ? "Source" : "Whitelist";
          this.$root.editRow = {}; this.$root.ftSrcIoCType = 'mixed'; this.$root.ftSrcKeepInCache = 0;
          showModal('mConfEditSources'); break;
        case "info whitelists": case "edit whitelists": case "clone whitelists":
        case "info sources": case "edit sources": case "clone sources":
          this.$root.ftSrcId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftSrcName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftSrcURL = row.item.url; this.$root.ftSrcREGEX = row.item.regex;
          this.$root.ftSrcIoCType = row.item.ioc_type; this.$root.ftSrcKeepInCache = row.item.keep_in_cache;
          this.$root.ftSrcType = table;
          this.$root.ftSrcURLIXFR = (table == "sources") ? row.item.url_ixfr : '';
          this.$root.ftSrcMaxIOC = `${row.item.max_ioc}`;
          this.$root.ftSrcHotCacheAXFR = `${row.item.hotcache_time}`;
          this.$root.ftSrcHotCacheIXFR = `${row.item.hotcacheixfr_time}`;
          this.$root.ftSrcTitle = (table == "sources") ? "Source" : "Whitelist";
          this.$root.editRow = row.item;
          showModal('mConfEditSources'); break;
        case "add servers":
          this.$root.ftSrvId = -1; this.$root.ftSrvName = ''; this.$root.ftSrvIP = '';
          this.$root.ftSrvPubIP = ''; this.$root.ftSrvNS = ''; this.$root.ftSrvEmail = '';
          this.$root.ftSrvMGMT = 0; this.$root.ftSrvTKeys = []; this.$root.ftSrvMGMTIP = '';
          this.$root.ftSrvSType = 0; this.$root.ftSrvURL = "";
          this.$root.ftCertFile = ""; this.$root.ftKeyFile = "";
          this.$root.ftCACertFile = ""; this.$root.ftCustomConfig = "";
          this.$root.ftSrvDisabled = 0;
          this.$root.get_lists('tkeys_mgmt', 'ftSrvTKeysAll'); this.$root.editRow = {};
          showModal('mConfEditSrv'); break;
        case "info servers": case "edit servers": case "clone servers":
          this.$root.ftSrvId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftSrvName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftSrvIP = row.item.ip; this.$root.ftSrvPubIP = row.item.pub_ip;
          this.$root.ftSrvNS = row.item.ns; this.$root.ftSrvEmail = row.item.email;
          this.$root.ftSrvMGMT = row.item.mgmt; this.$root.ftSrvSType = row.item.stype;
          this.$root.ftSrvURL = row.item.URL; this.$root.ftCertFile = row.item.certfile;
          this.$root.ftKeyFile = row.item.keyfile; this.$root.ftCACertFile = row.item.cacertfile;
          this.$root.ftCustomConfig = row.item.custom_config; this.$root.ftSrvDisabled = row.item.disabled;
          var IPs = '';
          row.item.mgmt_ips.forEach(function(el) { IPs += el.mgmt_ip + ' '; });
          this.$root.ftSrvMGMTIP = IPs.trim();
          this.$root.get_lists('tkeys_mgmt', 'ftSrvTKeysAll');
          var tkeys = [];
          row.item.tkeys.forEach(function(el) { tkeys.push(el.rowid); });
          this.$root.ftSrvTKeys = tkeys;
          this.$root.editRow = row.item;
          this.$root.editRow.mgmt_ips_str = this.$root.ftSrvMGMTIP;
          this.$root.editRow.tkeys_arr = this.$root.ftSrvTKeys;
          showModal('mConfEditSrv'); break;
        case "publish servers":
          this.$root.pushUpdatestoSRV(row.item.rowid); break;
        case "export servers":
          axios.get('/io2data.php/servercfg?rowid=' + row.item.rowid, { responseType: 'blob' }).then(function(response) {
            if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); }
            else {
              let blob = new Blob([response.data], { type: 'text/plain' });
              let link = document.createElement('a');
              link.href = window.URL.createObjectURL(blob);
              var sFN = response.headers['content-disposition'].match(/filename="([^"]+)"/)[1];
              link.download = sFN ? sFN : row.item.name + '.conf';
              link.click();
            }
          }).catch(function(error) { alert("export failed"); }); break;
        case "add rpzs":
          this.$root.RPZtabI = 0; this.$root.ftRPZProWindow = "hidden"; this.$root.ftRPZProWindowInfo = "";
          this.$root.ftRPZId = -1; this.$root.ftRPZName = '';
          this.$root.ftRPZSOA_Refresh = '86400'; this.$root.ftRPZSOA_UpdRetry = '3600';
          this.$root.ftRPZSOA_Exp = '2592000'; this.$root.ftRPZSOA_NXTTL = '7200';
          this.$root.ftRPZAXFR = '604800'; this.$root.ftRPZIXFR = '86400';
          this.$root.ftRPZCache = 1; this.$root.ftRPZWildcard = 1;
          this.$root.get_lists('rpz_servers', 'ftRPZSrvsAll'); this.$root.ftRPZSrvs = [];
          this.$root.get_lists('rpz_tkeys', 'ftRPZTKeysAll'); this.$root.ftRPZTKeys = [];
          this.$root.get_lists('rpz_sources', 'ftRPZSrcAll'); this.$root.ftRPZSrc = [];
          this.$root.get_lists('rpz_whitelists', 'ftRPZWLAll'); this.$root.ftRPZWL = [];
          this.$root.ftRPZAction = "nxdomain"; this.$root.ftRPZActionCustom = "";
          this.$root.ftRPZIOCType = "mixed"; this.$root.ftRPZNotify = "";
          this.$root.ftRPZDisabled = 0; this.$root.editRow = {};
          showModal('mConfEditRPZ'); break;
        case "info rpzs": case "edit rpzs": case "clone rpzs":
          this.$root.RPZtabI = 0;
          this.$root.ftRPZProWindow = action == "info" ? "" : "hidden";
          this.$root.ftRPZId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftRPZName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftRPZSOA_Refresh = `${row.item.soa_refresh}`;
          this.$root.ftRPZSOA_UpdRetry = `${row.item.soa_update_retry}`;
          this.$root.ftRPZSOA_Exp = `${row.item.soa_expiration}`;
          this.$root.ftRPZSOA_NXTTL = `${row.item.soa_nx_ttl}`;
          this.$root.ftRPZAXFR = `${row.item.axfr_update}`;
          this.$root.ftRPZIXFR = `${row.item.ixfr_update}`;
          this.$root.ftRPZCache = row.item.cache; this.$root.ftRPZWildcard = row.item.wildcard;
          this.$root.ftRPZAction = row.item.action;
          this.$root.ftRPZActionCustom = row.item.actioncustom ? JSON.parse(row.item.actioncustom) : "";
          this.$root.ftRPZIOCType = row.item.ioc_type; this.$root.ftRPZDisabled = row.item.disabled;
          let vm = this;
          var RPZNotify = '';
          row.item.notify.forEach(function(el) { RPZNotify += el.notify + ' '; });
          this.$root.ftRPZNotify = RPZNotify.trim();
          let dig_srv = ""; let dig_tkey = "";
          this.$root.get_lists('rpz_servers', 'ftRPZSrvsAll');
          let list = [];
          row.item.servers.forEach(function(el) { list.push(el.rowid); dig_srv = dig_srv == "" ? el.pub_ip : dig_srv; });
          this.$root.ftRPZSrvs = list;
          this.$root.ftRPZInfoServerName = Array.isArray(row.item.servers) && row.item.servers.length ? row.item.servers[0].name : '';
          this.$root.ftRPZInfoServerIP = Array.isArray(row.item.servers) && row.item.servers.length ? row.item.servers[0].pub_ip : '';
          this.$root.ftRPZInfoTKeyName = Array.isArray(row.item.tkeys) && row.item.tkeys.length ? row.item.tkeys[0].name : '';
          this.$root.ftRPZInfoTKeyAlg = Array.isArray(row.item.tkeys) && row.item.tkeys.length ? 'hmac-' + row.item.tkeys[0].alg : '';
          this.$root.ftRPZInfoTKey = Array.isArray(row.item.tkeys) && row.item.tkeys.length ? row.item.tkeys[0].tkey : '';
          this.$root.get_lists('rpz_tkeys', 'ftRPZTKeysAll');
          list = [];
          row.item.tkeys.forEach(function(el) { list.push(el.rowid); dig_tkey = dig_tkey == "" ? "hmac-" + el.alg + ":" + el.name + ":" + el.tkey : dig_tkey; });
          this.$root.ftRPZTKeys = list;
          this.$root.ftRPZInfoDig = this.$root.ftRPZInfoServerIP && this.$root.ftRPZInfoTKeyName ? "dig +tcp @" + dig_srv + " -y " + dig_tkey + " " + row.item.name + " SOA" : '';
          this.$root.get_lists('rpz_sources', 'ftRPZSrcAll');
          list = [];
          row.item.sources.forEach(function(el) { list.push(el.rowid); });
          this.$root.ftRPZSrc = list;
          this.$root.get_lists('rpz_whitelists', 'ftRPZWLAll');
          list = [];
          row.item.whitelists.forEach(function(el) { list.push(el.rowid); });
          this.$root.ftRPZWL = list;
          this.$root.editRow = row.item;
          this.$root.editRow.notify_str = this.$root.ftRPZNotify;
          this.$root.editRow.servers_arr = this.$root.ftRPZSrvs;
          this.$root.editRow.tkeys_arr = this.$root.ftRPZTKeys;
          this.$root.editRow.sources_arr = this.$root.ftRPZSrc;
          this.$root.editRow.whitelists_arr = this.$root.ftRPZWL;
          showModal('mConfEditRPZ'); break;
        default:
          alert(action + ' ' + table);
      }
    },

    requestDelete: function(table, row) {
      this.$root.deleteRec = row.item.rowid; this.$root.deleteTbl = table;
      this.$root.modalMSG = '<b>Do you want to delete ' + row.item.name + '?</b>';
      showModal('mConfDel');
    },

    validateName: function(vrbl) {
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateNameAT: function(vrbl) {
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9@\/\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateUName: function(vrbl) {
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatName: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateB64: function(vrbl) {
      return (this.$data[vrbl].length > 16 && /^(?:[A-Za-z0-9\+\/]{4})*(?:[A-Za-z0-9\+\/]{2}==|[A-Za-z0-9\+\/]{3}=)?$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatB64: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9/=\+\/]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateInt: function(vrbl) {
      return (this.$data[vrbl].length > 0 && /^[0-9]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatInt: function(val, e) {
      let a = val.replace(/[^0-9]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateURL: function(vrbl) {
      return (this.$data[vrbl].length > 0 && checkSourceURL(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatURL: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9/=:\?#.\-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatURLAT: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9@/=:\?#.\-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatSourceURL: function(val, e) {
      let a;
      if (/^shell:/.test(val) || /^file:/.test(val) || /^[:AXFR:]/.test(val)) a = val; else a = val.replace(/[^A-Za-z0-9/=:\?#.\-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateLocFile: function(vrbl) {
      return (this.$data[vrbl].length > 0) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatLocFile: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9/=:\?#.-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateIXFRURL: function(vrbl) {
      return this.$data[vrbl].length == 0 ? null : (this.validateURL(vrbl) || this.$data[vrbl] == '[:AXFR:]' || (/^\[:AXFR:\]((\?|\&)[;&a-zA-Z0-9\d%_.~+=-]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?(\#[-a-zA-Z0-9\d_]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?$/.test(this.$data[vrbl])));
    },

    formatIXFRURL: function(val, e) {
      let a;
      if (/^shell:/.test(val) || /^file:/.test(val) || /^[:AXFR:]/.test(val)) a = val; else a = val.replace(/[^A-Za-z0-9/=:\?#.-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateREGEX: function(vrbl) {
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateIP: function(vrbl) {
      return (this.$data[vrbl].length > 0 && checkIP(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatIP: function(val, e) {
      let a = val.replace(/[^0-9\.:\-]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateIPList: function(vrbl) {
      return (this.$data[vrbl].length > 0 && this.$data[vrbl].trim().split(/,|\s|\;/g).every(checkIP)) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatIPList: function(val, e) {
      let a = val.replace(/[^0-9\.:\-,; ]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateHostname: function(vrbl) {
      return (this.$data[vrbl].length > 5 && checkHostName(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateHostnameNum: function(vrbl) {
      return (this.$data[vrbl].length > 5 && checkHostNameNum(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateHostnameOnly: function(vrbl) {
      return (this.$data[vrbl].length > 5 && checkHostNameOnly(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatHostname: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateEmail: function(vrbl) {
      return (this.$data[vrbl].length > 0 && /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(this.$data[vrbl].toLowerCase())) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatEmail: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_@]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validatePass: function(pass1) {
      return ((this.$data[pass1].length > 7 && /([0-9])/.test(this.$data[pass1]) && /([a-z])/.test(this.$data[pass1]) && /([A-Z])/.test(this.$data[pass1]) && /([!,%,&,@,#,$,^,*,?,_,~,\,,\.])/.test(this.$data[pass1])) || this.$data[pass1].length > 15) ? true : this.$data[pass1].length == 0 ? null : false;
    },

    validatePassMatch: function(pass1, pass2) {
      return this.$data[pass1] == this.$data[pass2] ? true : false;
    },

    get_lists: function(table, variable) {
      let promise = axios.get('/io2data.php/' + table);
      promise.then((data) => {
        if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); }
        else { this.$root.$data[variable] = data.data; }
      }).catch(error => { this.$root.$data[variable] = []; });
    },

    mgmtTableOk: function(response, obj, table) {
      if (response.data.status == "ok") {
        refreshTable('io2tbl_' + table);
      } else {
        alert('sql error while adding ' + table);
      }
    },

    mgmtTableError: function(error, obj, table) {
      alert('error while adding ' + table + ' ');
    },

    tblMgmtTKeyRecord: function(ev, table) {
      if (this.validateName('ftKeyName') && this.validateB64('ftKey')) {
        var obj = this;
        if ((this.ftKeyId != -1 && (this.$root.ftKeyName != this.editRow.name || this.$root.ftKey != this.editRow.tkey || this.$root.ftKeyAlg != this.editRow.alg || this.$root.ftKeyMGMT != this.editRow.mgmt || this.$root.ftTKeysGroups != this.editRow.tkey_groups))) toggleUpdates(0, this, true);
        let data = { tKeyId: this.ftKeyId, tKeyName: this.ftKeyName, tKey: this.ftKey, tKeyAlg: this.ftKeyAlg, tKeyMGMT: this.ftKeyMGMT, tTKeysGroups: JSON.stringify(this.ftTKeysGroups) };
        if (this.ftKeyId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftKeyName')) this.$refs.formKeyName.$el.focus();
        else this.$refs.formKey.$el.focus();
      }
    },

    tblMgmtTKeyGRecord: function(ev, table) {
      if (this.validateName('ftKeyGName')) {
        var obj = this;
        let data = { tKeyGId: this.ftKeyGId, tKeyGName: this.ftKeyGName };
        if (this.ftKeyGId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftKeyGName')) this.$refs.formKeyGName.$el.focus();
        else this.$refs.formKey.$el.focus();
      }
    },

    tblMgmtSrcRecord: function(ev, table) {
      if (this.validateName('ftSrcName') && this.validateURL('ftSrcURL') && (this.validateREGEX('ftSrcREGEX') == null || this.validateREGEX('ftSrcREGEX')) && (((this.validateIXFRURL('ftSrcURLIXFR') || this.validateIXFRURL('ftSrcURLIXFR') == null) && this.ftSrcType == 'sources') || this.ftSrcType != 'sources') && this.validateInt('ftSrcMaxIOC') && this.validateInt('ftSrcHotCacheAXFR') && this.validateInt('ftSrcHotCacheIXFR')) {
        var obj = this;
        if (this.ftSrcId != -1 && (this.ftSrcName != this.editRow.name || this.ftSrcURL != this.editRow.url || this.ftSrcREGEX != this.editRow.regex || this.ftSrcMaxIOC != this.editRow.max_ioc || this.ftSrcHotCacheAXFR != this.editRow.hotcache_time || this.ftSrcHotCacheIXFR != this.editRow.hotcacheixfr_time || (this.ftSrcURLIXFR != this.editRow.url_ixfr && this.ftSrcType == 'sources') || this.ftSrcIoCType != this.editRow.ioc_type || this.ftSrcKeepInCache != this.editRow.keep_in_cache)) toggleUpdates(0, this, true);
        let data = { tSrcId: this.ftSrcId, tSrcName: this.ftSrcName, tSrcURL: this.ftSrcURL, tSrcREGEX: this.ftSrcREGEX, tSrcURLIXFR: this.ftSrcURLIXFR, tSrcMaxIOC: this.ftSrcMaxIOC, tSrcHotCacheAXFR: this.ftSrcHotCacheAXFR, tSrcHotCacheIXFR: this.ftSrcHotCacheIXFR, tSrcIoCType: this.ftSrcIoCType, tSrcKeepInCache: this.ftSrcKeepInCache };
        if (this.ftSrcId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftSrcName')) this.$refs.formSrcName.$el.focus();
        else if (!this.validateURL('ftSrcURL') && this.validateREGEX('ftSrcURL') != null) this.$refs.formSrcURL.$el.focus();
        else if (!this.validateREGEX('ftSrcREGEX') && this.validateREGEX('ftSrcREGEX') != null) this.$refs.formREGEX.$el.focus();
        else this.$refs.formSrcURLIXFR.$el.focus();
      }
    },

    manageUsers: function(ev) {
      if (this.validateUName('ftUNameProf') && this.validatePass('ftUPwd') && this.validatePassMatch('ftUPwd', 'ftUpwdConf')) {
        let obj = this;
        let data = { rowid: this.ftUId, name: this.ftUNameProf, pwd: this.ftUPwd, perm: this.ftUPerm };
        if (this.ftUId == 0) {
          axios.post('/io2data.php/users', data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, 'users'); }).catch(function(error) { obj.mgmtTableError(error, obj, 'users'); });
        } else {
          axios.put('/io2data.php/users', data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, 'users'); }).catch(function(error) { obj.mgmtTableError(error, obj, 'users'); });
        }
      } else if (ev != null) {
        ev.preventDefault();
      }
    },

    tblMgmtSrvRecord: function(ev, table) {
      if (this.validateName('ftSrvName') && (this.validateIP('ftSrvPubIP') || this.validateIP('ftSrvPubIP') == null) && (this.validateIP('ftSrvIP') || this.validateIP('ftSrvIP') == null) && this.validateHostname('ftSrvNS') && this.validateEmail('ftSrvEmail') && (this.validateIPList('ftSrvMGMTIP') || this.validateIP('ftSrvMGMTIP') == null)) {
        var obj = this;
        if (this.ftSrvName != this.editRow.name || this.ftSrvIP != this.editRow.ip || this.ftSrvPubIP != this.editRow.pub_ip || this.ftSrvNS != this.editRow.ns || this.ftSrvEmail != this.editRow.email || this.ftSrvMGMT != this.editRow.mgmt || this.ftSrvSType != this.editRow.stype || this.ftSrvURL != this.editRow.URL || this.ftSrvMGMTIP != this.editRow.mgmt_ips_str || this.ftSrvTKeys != this.editRow.tkeys_arr || this.ftCertFile != this.editRow.certfile || this.ftKeyFile != this.editRow.keyfile || this.ftCACertFile != this.editRow.cacertfile || this.ftCustomConfig != this.editRow.custom_config) toggleUpdates(0, this, true);
        let data = { tSrvId: this.ftSrvId, tSrvName: this.ftSrvName, tSrvIP: this.ftSrvIP, tSrvPubIP: this.ftSrvPubIP, tSrvNS: this.ftSrvNS, tSrvEmail: this.ftSrvEmail, tSrvMGMT: this.ftSrvMGMT, tSrvMGMTIP: JSON.stringify(this.ftSrvMGMTIP.split(/,|\s/g).filter(String)), tSrvTKeys: JSON.stringify(this.ftSrvTKeys), tSrvDisabled: this.ftSrvDisabled, tSrvSType: this.ftSrvSType, tSrvURL: this.ftSrvURL, tCertFile: this.ftCertFile, tKeyFile: this.ftKeyFile, tCACertFile: this.ftCACertFile, tCustomConfig: this.ftCustomConfig };
        if (this.ftSrvId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftSrvName')) this.$refs.formSrvName.$el.focus();
        else if (!(this.validateIP('ftSrvPubIP') || this.validateIP('ftSrvPubIP') == null)) this.$refs.formSrvPubIP.$el.focus();
        else if (!(this.validateIP('ftSrvIP') || this.validateIP('ftSrvIP') == null)) this.$refs.formSrvIP.$el.focus();
        else if (!this.validateHostname('ftSrvNS')) this.$refs.formSrvNS.$el.focus();
        else if (!this.validateEmail('ftSrvEmail')) this.$refs.formSrvEmail.$el.focus();
        else if (!this.validateLocFile('ftCertFile')) this.$refs.formCertFile.$el.focus();
        else if (!this.validateLocFile('ftKeyFile')) this.$refs.formKeyFile.$el.focus();
        else if (!this.validateLocFile('ftCACertFile')) this.$refs.formCACertFile.$el.focus();
        else this.$refs.formSrcNotify.$el.focus();
      }
    },

    tblMgmtRPZRecord: function(ev, table) {
      if (this.validateHostnameNum('ftRPZName') && (this.validateIPList('ftRPZNotify') || this.validateIPList('ftRPZNotify') == null) && ((this.validateCustomAction(this.ftRPZActionCustom) && this.ftRPZAction === 'local') || this.ftRPZAction != 'local') && this.validateInt('ftRPZSOA_Refresh') && this.validateInt('ftRPZSOA_UpdRetry') && this.validateInt('ftRPZSOA_Exp') && this.validateInt('ftRPZSOA_NXTTL') && this.validateInt('ftRPZAXFR') && this.validateInt('ftRPZIXFR')) {
        var obj = this;
        if (this.ftRPZName != this.editRow.name || this.ftRPZSOA_Refresh != this.editRow.soa_refresh || this.ftRPZSOA_UpdRetry != this.editRow.soa_update_retry || this.ftRPZSOA_Exp != this.editRow.soa_expiration || this.ftRPZSOA_NXTTL != this.editRow.soa_nx_ttl || this.ftRPZAXFR != this.editRow.axfr_update || this.ftRPZIXFR != this.editRow.ixfr_update || this.ftRPZCache != this.editRow.cache || this.ftRPZWildcard != this.editRow.wildcard || this.ftRPZAction != this.editRow.action || this.ftRPZIOCType != this.editRow.ioc_type || this.editRow.notify_str != this.ftRPZNotify || this.editRow.servers_arr != this.ftRPZSrvs || this.editRow.tkeys_arr != this.ftRPZTKeys || this.editRow.sources_arr != this.ftRPZSrc || this.editRow.whitelists_arr != this.ftRPZWL || this.ftRPZActionCustom != this.editRow.actioncustom || this.ftRPZDisabled != this.editRow.disabled) toggleUpdates(0, this, true);
        let data = { tRPZId: this.ftRPZId, tRPZName: this.ftRPZName, tRPZSOA_Refresh: this.ftRPZSOA_Refresh, tRPZSOA_UpdRetry: this.ftRPZSOA_UpdRetry, tRPZSOA_Exp: this.ftRPZSOA_Exp, tRPZSOA_NXTTL: this.ftRPZSOA_NXTTL, tRPZCache: this.ftRPZCache, tRPZWildcard: this.ftRPZWildcard, tRPZNotify: JSON.stringify(this.ftRPZNotify.split(/,|\s/g).filter(String)), tRPZSrvs: JSON.stringify(this.ftRPZSrvs), tRPZIOCType: this.ftRPZIOCType, tRPZAXFR: this.ftRPZAXFR, tRPZIXFR: this.ftRPZIXFR, tRPZDisabled: this.ftRPZDisabled, tRPZTKeys: JSON.stringify(this.ftRPZTKeys), tRPZWL: JSON.stringify(this.ftRPZWL), tRPZSrc: JSON.stringify(this.ftRPZSrc), tRPZAction: this.ftRPZAction, tRPZActionCustom: JSON.stringify(this.ftRPZActionCustom) };
        if (this.ftRPZId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateHostnameNum('ftRPZName')) this.$refs.formRPZName.$el.focus();
        else if (!((this.validateIPList('ftRPZNotify') || this.validateIPList('ftRPZNotify') == null))) this.$refs.formRPZNotify.$el.focus();
        else if (!this.validateCustomAction(this.ftRPZActionCustom) && this.ftRPZAction === 'local') this.$refs.formRPZActionCustom.$el.focus();
        else if (!this.validateInt('ftRPZSOA_Refresh')) this.$refs.formRPZSOA_Refresh.$el.focus();
        else if (!this.validateInt('ftRPZSOA_UpdRetry')) this.$refs.formRPZSOA_UpdRetry.$el.focus();
        else if (!this.validateInt('ftRPZSOA_Exp')) this.$refs.formRPZSOA_Exp.$el.focus();
        else if (!this.validateInt('ftRPZSOA_NXTTL')) this.$refs.formRPZSOA_NXTTL.$el.focus();
        else if (!this.validateInt('ftRPZAXFR')) this.$refs.formRPZAXFR.$el.focus();
        else this.$refs.formRPZIXFR.$el.focus();
      }
    },

    tblDeleteRecord: function(table, rowid) {
      var el = this;
      if (table != 'users') toggleUpdates(0, this, true);
      axios.delete('/io2data.php/' + table + '?rowid=' + JSON.stringify(rowid)).then(function(response) {
        if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); }
        else if (response.data.status == "ok") { refreshTable('io2tbl_' + table); }
        else { alert('sql error while deleting ' + table + ' ' + rowid); }
      }).catch(function(error) { alert('error while deleting ' + table + ' ' + rowid); });
    },

    pushUpdatestoSRV: function(SrvId) {
      var obj = this;
      toggleUpdates(0, obj, false);
      axios.post(`/io2data.php/publish_upd?SrvId=${SrvId}`).then(function(response) {
        if (response.data.status == "ok") {
          obj.showInfo('Configuration will be updated in a few seconds', 3);
          toggleUpdates(0, obj, false); refreshTable('servers');
        } else {
          if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); } else alert('Publishing error');
        }
      }).catch(function(error) { alert('Publishing error'); });
    },

    showInfo: function(msg, time) {
      var self = this;
      this.msgInfoMSG = msg; this.mInfoMSGvis = true;
      setTimeout(function() { self.mInfoMSGvis = false; }, time * 1000);
    },

    ImportConfig: function(ev) {
      var file = new FileReader();
      var vm = this;
      file.onload = function(e) { ImportIOC2RPZ(vm, e.target.result); };
      file.readAsText(vm.ftImpFiles[0]);
    },

    ImportConfigLine: function(ev) {
      ImportIOC2RPZ(this, this.ftImportRec);
    },

    checkImpFile: function(e) {
      this.ftImpFiles = e.dataTransfer.files;
      this.ftImpFileDesc = 'File name: ' + encodeURI(this.ftImpFiles[0].name) + ", size: " + this.ftImpFiles[0].size + ' bytes';
    },

    alert: function(txt) { alert(txt); },

    copyToClipboard(ref) {
      this.$refs[ref].$el.select();
      document.execCommand('copy');
    },

    genRandom(type) {
      switch (type) {
        case "tkeyName":
          this.$root.ftKeyName = 'tkey-' + Math.random().toString(36).substr(2, 10) + '-' + Math.random().toString(36).substr(2, 10);
          break;
        case "tkey":
          let key = new Uint8Array(this.$root.ftKeyAlg == 'md5' ? 16 : this.$root.ftKeyAlg == 'sha256' ? 32 : 64);
          do {
            window.crypto.getRandomValues(key);
            this.$root.ftKey = btoa(String.fromCharCode.apply(null, key));
          } while (!this.validateB64('ftKey'));
          break;
      }
    },

    changeTab: function(tab) {
      history.pushState(null, null, '#tabs_menu/' + tab);
      // Use tabTableMap instead of $children (removed in Vue 3)
      const tableName = this.tabTableMap[tab];
      if (tableName) {
        refreshTable('io2tbl_' + tableName);
      }
    },

    signOut: function() {
      axios.post('/io2auth.php/logout').then(function(response) { window.location.reload(true); });
    },

    exportShowModal: function(format) {
      this.$root.ftExFormat = format;
      this.$root.get_lists('rpz_lists', 'ftExRPZAll');
      this.$root.ftExRPZ = [];
      this.$root.rpzExportSAll = false;
      showModal('mExpRPZ');
    },

    rpzExportToggleAll: function(checked) {
      this.ftExRPZ = checked ? this.ftExRPZAll.map(function(el) { return el.value; }) : [];
    },

    exportDNSConfig: async function() {
      let p = axios.get('/io2data.php/rpzs?rowid=' + JSON.stringify(this.$root.ftExRPZ));
      var [rpzs] = await Promise.all([p]);
      var keys = []; var options = "";
      var zone_opt = []; zone_opt['fqdn'] = ""; zone_opt['mixed'] = ""; zone_opt['ip'] = "";
      var keys_txt = ""; var zones = ""; let tkey_str = "";

      switch (this.$root.ftExFormat) {
        case 'bind':
          rpzs.data.forEach(function(el) {
            let servers = "";
            el['servers'].forEach(function(srv) {
              if (el['tkeys'].length == 0) { tkey_str = ""; } else { tkey_str = ` key "${el['tkeys'][0]['name']}"`; }
              servers += `${srv['pub_ip']} ${tkey_str};`;
            });
            zones += `\nzone "${el['name']}" {\n  type slave;\n  file "/var/cache/bind/${el['name']}";\n  masters {${servers}};\n};\n`;
            zone_opt[el['ioc_type']] += `\n    zone "${el['name']}" policy ` + (el['action'] == 'local' ? 'given' : el['action']) + ";";
            if (el['tkeys'].length > 0) {
              keys[el['tkeys'][0]['name']] = [];
              keys[el['tkeys'][0]['name']]['name'] = el['tkeys'][0]['name'];
              keys[el['tkeys'][0]['name']]['alg'] = el['tkeys'][0]['alg'];
              keys[el['tkeys'][0]['name']]['tkey'] = el['tkeys'][0]['tkey'];
            }
          });
          options = `\noptions {\n  #This is just options for RPZs. Add other options as required\n  recursion yes;\n  response-policy {\n    ####FQDN only zones ${zone_opt['fqdn']}\n    ####Mixed zones ${zone_opt['mixed']}\n    ####IP only zones ${zone_opt['ip']}\n  } qname-wait-recurse no break-dnssec yes;\n};\n`;
          for (var i in keys) {
            keys_txt += `\nkey "${keys[i]['name']}"{\n  algorithm hmac-${keys[i]['alg']}; secret "${keys[i]['tkey']}";\n};\n`;
          }
          break;
        case 'PowerDNS':
          let RPZ_PowerDNS_Options = { 'nxdomain': 'defpol=Policy.NXDOMAIN', 'nodata': 'defpol=Policy.NODATA', 'passthru': 'defpol=Policy.NoAction', 'drop': 'defpol=Policy.Drop', 'tcp-only': 'defpol=Policy.Truncate', 'local': '' };
          let pdns_opt = ""; let cmm = "";
          rpzs.data.forEach(function(el) {
            if (el['tkeys'].length == 0) { tkey_str = ""; }
            else { tkey_str = `tsigname="${el['tkeys'][0]['name']}", tsigalgo="hmac-${el['tkeys'][0]['alg']}", tsigsecret="${el['tkeys'][0]['tkey']}"`; }
            if (RPZ_PowerDNS_Options[el['action']] != "" && tkey_str != "") cmm = ",";
            if (RPZ_PowerDNS_Options[el['action']] != "" || tkey_str != "") pdns_opt = `, {${RPZ_PowerDNS_Options[el['action']]}${cmm} ${tkey_str}}`; else pdns_opt = "";
            zones += `\nrpzMaster("${el['servers'][0]['pub_ip']}", "${el['name']}"${pdns_opt})\n`;
          });
          break;
        case 'Infoblox':
          let zone_pri = []; zone_pri['fqdn'] = []; zone_pri['mixed'] = []; zone_pri['ip'] = [];
          let zp = 0;
          options = "header-responsepolicyzone,fqdn*,zone_format*,rpz_policy,substitute_name,view,zone_type,external_primaries,grid_secondaries,priority";
          let RPZ_IB_Options = { 'nxdomain': 'Nxdomain', 'nodata': 'Nodata', 'passthru': 'Passthru', 'drop': 'Nxdomain', 'tcp-only': 'Passthru', 'local': 'Given' };
          let TKEY_Alg = { 'md5': 'HMAC-MD5', 'sha256': 'HMAC-SHA256', 'sha512': 'HMAC-SHA512' };
          let IBMember = this.$root.rpzExportIBMember;
          let IBNView = this.$root.rpzExportIBView;
          rpzs.data.forEach(function(el) {
            let tkey = -1;
            el['tkeys'].some(function(el) {
              tkey++;
              return ((el['alg'] != 'sha512') && (el['tkey'].indexOf('/') == -1));
            });
            if (el['tkeys'].length == 0) {
              tkey_str = `${el['servers'][0]['name']}/${el['servers'][0]['pub_ip']}/FALSE/FALSE/FALSE`;
            } else {
              tkey_str = `${el['servers'][0]['name']}/${el['servers'][0]['pub_ip']}/FALSE/FALSE/TRUE/${el['tkeys'][tkey]['name']}/${el['tkeys'][tkey]['tkey']}/${TKEY_Alg[el['tkeys'][tkey]['alg']]}`;
            }
            zone_pri[el['ioc_type']].push(`\n  responsepolicyzone,${el['name']},FORWARD,${RPZ_IB_Options[el['action']]},,${IBNView},responsepolicy,${tkey_str},${IBMember}/False/False/False,`);
          });
          zone_pri['fqdn'].forEach(function(el) { zones += el + zp; zp++; });
          zone_pri['mixed'].forEach(function(el) { zones += el + zp; zp++; });
          zone_pri['ip'].forEach(function(el) { zones += el + zp; zp++; });
          break;
      }
      downloadAsPlainText(this.$root.ftExFormat + "_sample_config.txt", options + keys_txt + zones);
    }
  }
};

// Note: No global Vue instance creation here.
// The Vue instance is created in main.js using: new Vue(appConfig)
