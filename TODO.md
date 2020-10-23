# 2020-05-28
- [ ] Security
  - [ ] Enforce HSTS 
- [ ] Server's current status and history
- [ ] Update RPZ management window
- [ ] IPv4 and IPv6 DNS IPs
- [ ] Force RPZ, Source refresh
- [ ] Users management
  - [ ] Change password hashing
  - [ ] User's profile + require for the current user to change the password via the profile
  - [ ] Add indexes on names (users, rpz, sources, whitelists) to enforce uniqueness
  - [ ] Check if a user name is unique
  - [ ] If password was not set - just change permissions
- [ ] Publish
  - [ ] do it per server
  - [ ] Change server management keys - safe config and immediately publish update - store old key data
  - [ ] Updating a management TSIG (assigned tot a server) - publish config immediately - store old key data.
  - [ ] Whitelist/source create doesn't push the config update but edit/delete does even is the WL is not used in RPZ. Should be validated if used in servers configs
- [0] On tables refresh if a session was expired - redirect to the login page <--- Mostly done. Need to check how to handle "await"

# Bugs

# TODO
- [ ] Container. Session expiration
- [ ] Config import. Pub_IP & local management IP & Email & Management.


----- old cut before Def Con 2018 -----
- [ ] Constraints enforcements on SQLite (requires redo the DB, keys etc) (if there is a named index, php doesn't see rowid.....)
- [ ] Source/whitelist check availability/rechability
- [ ] Server side. Intelligent publishing an updated server configuration
- [ ] Monitoring/dashboards
- [ ] MySQL or PostgreSQL support
- [ ] S3 support
- [ ] Utils
    - [ ] Import configuration. Srv and RPZs uniqueness + add SRV params
    - [ ] Import/Backup ioc2rpz.gui config