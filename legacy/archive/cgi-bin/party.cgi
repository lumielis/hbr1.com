#!/usr/bin/perl

require 'include/settings.inc';

print "Status: 302 Moved\n";
print "Location: $url{'system'}\/main.xp\?action\=party\n\n";
